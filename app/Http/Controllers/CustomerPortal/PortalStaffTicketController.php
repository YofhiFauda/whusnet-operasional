<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\FopTaskPriority;
use App\Enums\TaskType;
use App\Exceptions\DuplicateTicketException;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\StaffPortalToken;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\TicketService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `POST /customer-portal/tickets` (docs/plan/qr-code/
 * analisa-unifikasi-qr-staff-portal.md §2) — create tiket dari staf yang
 * masuk lewat scan QR di dalam app Operasional (`QrInAppScanController`),
 * di-redirect ke Portal bawa `StaffPortalToken` one-shot. Delegasi PENUH ke
 * `TicketService::create()` yang sama persis dipakai dashboard
 * `/tickets/create` — endpoint ini TIDAK menduplikasi business logic apa
 * pun, murni menerjemahkan identitas token → panggilan service yang sudah
 * ada (CLAUDE.md § Service layer).
 *
 * Middleware `portal_staff_token:tickets` (bukan `portal_token`, yang buat
 * pelanggan) sudah menaruh `staff_portal_user_id`/`staff_portal_customer_id`/
 * `staff_portal_token` di `$request->attributes` SEBELUM controller ini
 * jalan — `customer_id` di body TIDAK PERNAH dipercaya dari client, sama
 * prinsipnya dengan `EnsurePortalCustomerToken` (IDOR lintas pelanggan).
 */
#[Group('Portal Pelanggan — Staf', 'Endpoint yang dipanggil Portal atas nama STAF/kolektor (bukan pelanggan), setelah scan QR di dalam app Operasional. Butuh header `X-Portal-Client` + `Authorization: Bearer <staff_portal_token>` (one-shot, TTL 15 menit, purpose-scoped).')]
class PortalStaffTicketController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly EffectiveAccessService $access,
    ) {}

    /**
     * Opsi form (tipe ticket, prioritas, kategori issue) — sama persis
     * sumbernya dengan halaman `/tickets/create` Helpdesk (`TicketController::
     * create()`), cuma diterjemahkan ke JSON buat form Portal. TIDAK
     * mengonsumsi token (baca doang), sama pola `PortalStaffKolektorController::
     * worklist()` — staf boleh buka form berkali-kali sebelum submit tanpa
     * kehabisan jatah one-shot-nya.
     */
    #[ScrambleResponse(200, description: 'Opsi form ticket.', examples: [[
        'data' => [
            'types' => [['value' => 'MTN', 'label' => 'Maintenance'], ['value' => 'C-REQ', 'label' => 'Customer Request']],
            'priorities' => ['low', 'Medium', 'High', 'Urgent'],
            'issue_categories' => [['id' => 1, 'name' => 'Internet Lambat', 'default_priority' => 'Medium', 'sla_source' => 'paket']],
        ],
    ]])]
    public function options(Request $request): JsonResponse
    {
        $issueCategories = TicketIssueCategory::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'default_priority', 'sla_source'])
            ->map(fn (TicketIssueCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'default_priority' => $c->default_priority->value,
                'sla_source' => $c->sla_source,
            ])
            ->values();

        return response()->json([
            'data' => [
                'types' => TaskType::ticketOptions(),
                'priorities' => array_map(fn (FopTaskPriority $p) => $p->value, FopTaskPriority::cases()),
                'issue_categories' => $issueCategories,
            ],
        ]);
    }

    /**
     * Create tiket via QR
     *
     * `type` cuma boleh `MTN`/`C-REQ` (sama pembatasan dashboard,
     * `TaskType::ticketValues()`). `priority`: `low`/`Medium`/`High`/`Urgent`
     * (casing persis, ikut `FopTaskPriority`). `confirmed_duplicate` cuma
     * perlu dikirim `true` setelah staf lihat respons 409 & tetap mau bikin
     * tiket baru.
     */
    #[ScrambleResponse(201, description: 'Tiket berhasil dibuat.', examples: [[
        'data' => ['ticket_number' => 'TKT-2026-0123', 'status' => 'open'],
    ]])]
    #[ScrambleResponse(401, description: 'Token staf tidak ada/tidak valid/kedaluwarsa/sudah dipakai, atau purpose token salah.')]
    #[ScrambleResponse(403, description: 'Staf tidak punya permission tickets.qr.create, atau pelanggan di luar POP scope-nya.')]
    #[ScrambleResponse(409, description: 'Pelanggan masih punya tiket terbuka — kirim ulang dengan confirmed_duplicate=true kalau tetap mau bikin baru.', examples: [[
        'message' => 'Pelanggan ini masih punya tiket terbuka: TKT-2026-0099.',
        'existing_ticket_number' => 'TKT-2026-0099',
    ]])]
    public function store(Request $request): JsonResponse
    {
        /** @var StaffPortalToken $token */
        $token = $request->attributes->get('staff_portal_token');

        /** @var User $staff */
        $staff = User::findOrFail($token->user_id);

        if (! $staff->hasPermission('tickets.qr.create')) {
            abort(403, 'Anda tidak punya izin membuat tiket lewat kanal ini.');
        }

        $customer = Customer::findOrFail($token->customer_id);

        // POP scope dicek ULANG di sini — keputusan QrScanController cuma
        // menentukan arah redirect, BUKAN otorisasi final (§1.3/§2 dokumen
        // di atas). Token bisa saja ditukar belakangan setelah scope staf
        // berubah di antara scan & submit.
        if (! $this->access->hasAllPopAccess($staff)
            && ! in_array((int) $customer->pop_id, $this->access->getAllowedPopIds($staff), true)) {
            abort(403, 'Anda tidak memiliki akses ke pelanggan di POP ini.');
        }

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(TaskType::ticketValues())],
            'issue_category_id' => ['nullable', 'integer', 'exists:ticket_issue_categories,id'],
            'detail_keluhan' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'string', Rule::enum(FopTaskPriority::class)],
            'confirmed_duplicate' => ['sometimes', 'boolean'],
        ]);

        $data['customer_id'] = $customer->id;

        try {
            $result = $this->tickets->create(
                $data,
                $staff,
                enforceDuplicateGuard: true,
                confirmedDuplicate: (bool) ($data['confirmed_duplicate'] ?? false),
            );
        } catch (DuplicateTicketException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'existing_ticket_number' => $e->existingTicket->ticket_number,
            ], 409);
        }

        // Konsumsi SETELAH sukses tersimpan — token yang gagal kena dedup
        // guard (409) di atas TIDAK ikut hangus, staf masih bisa submit
        // ulang dengan confirmed_duplicate=true pakai token yang sama.
        $token->consume();

        return response()->json([
            'data' => [
                'ticket_number' => $result['ticket']->ticket_number,
                'status' => $result['ticket']->status?->value,
            ],
        ], 201);
    }
}
