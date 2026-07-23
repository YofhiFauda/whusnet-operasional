<?php

namespace App\Http\Controllers;

use App\Enums\FopTaskPriority;
use App\Enums\TaskType;
use App\Enums\TicketBucket;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ticketing — tiket internal PERUSAHAAN (helpdesk/NOC/sales/admin/dll),
 * berbeda dari FopTask yang internal FOP. Tiap tiket yang masuk otomatis
 * memunculkan FopTask baru (status Draft) di halaman Task FOP —
 * lihat TicketService::create().
 */
class TicketController extends Controller
{
    public function __construct(private readonly TicketService $ticketService) {}

    /**
     * Daftar tiket ala Gmail, per bucket submenu (Masuk / Diproses / Selesai /
     * Dibatalkan). Bucket-nya nentuin status FopTask mana yang ditarik —
     * lihat TicketBucket.
     */
    public function index(Request $request, string $bucket = TicketBucket::MASUK->value)
    {
        abort_unless(auth()->user()->hasPermission('tickets.view'), 403);

        $activeBucket = TicketBucket::tryFrom($bucket) ?? TicketBucket::MASUK;

        $query = Ticket::query()
            ->applyUserScope()
            ->inBucket($activeBucket)
            ->with([
                // pop_id/status/distribution_id WAJIB ikut ke-select — dipakai
                // Customer::getDisplayIdAttribute() buat nebak format CID lengkap
                // (Pop::resolveDisplayId()). Tanpa kolom-kolom ini, $customer->pop
                // selalu null (FK-nya gak ke-load) dan display_id diam-diam jatuh
                // ke customer_code mentah (mis. "RQ000007" tanpa prefix POP/
                // distribusi), padahal customers.cid udah nyimpen CID lengkap
                // yang benar (mis. "C1X4CRQ000007").
                'customer:id,full_name,cid,customer_code,pop_id,status,distribution_id',
                'customer.pop:id,name,cid_prefix',
                'creator:id,name',
                'pop:id,name',
                'fopTask:id,task_number,status',
            ])
            ->withCount('attachments');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('detail_keluhan', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('full_name', 'like', "%{$search}%")
                            ->orWhere('cid', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%");
                    });
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // "Ticket Saya" — riwayat tiket yang dikirim user login sendiri.
        if ($request->boolean('mine')) {
            $query->where('created_by', auth()->id());
        }

        $tickets = $query->latest('created_at')->paginate(20)->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'activeBucket' => $activeBucket,
            'bucketCounts' => $this->bucketCounts(),
            'typeOptions' => TaskType::ticketOptions(),
        ]);
    }

    /**
     * Halaman form ticket baru.
     */
    public function create()
    {
        abort_unless(auth()->user()->hasPermission('tickets.create'), 403);

        return view('tickets.create', [
            'typeOptions' => TaskType::ticketOptions(),
            'priorityOptions' => FopTaskPriority::cases(),
        ]);
    }

    /**
     * Jumlah tiket per bucket buat badge di header — dihitung sekali, sekali
     * query per bucket, dalam scope POP user yang sama kayak list-nya.
     *
     * @return array<string, int>
     */
    private function bucketCounts(): array
    {
        $counts = [];

        foreach (TicketBucket::cases() as $bucket) {
            $counts[$bucket->value] = Ticket::query()
                ->applyUserScope()
                ->inBucket($bucket)
                ->count();
        }

        return $counts;
    }

    public function show(Ticket $ticket)
    {
        abort_unless(auth()->user()->hasPermission('tickets.view'), 403);
        $this->authorizeTicketScope($ticket);

        $ticket->load([
            'customer.pop',
            'customer.village',
            'customer.internetPackage',
            'customer.customerDevice',
            'creator',
            'fopTask.technicians',
            'fopTask.statusHistories.changedByUser',
            'attachments.uploader',
            'histories.actor',
        ]);

        return view('tickets.show', ['ticket' => $ticket]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('tickets.create'), 403);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(TaskType::ticketValues())],
            'customer_id' => ['required', 'exists:customers,id'],
            'detail_keluhan' => ['required', 'string', 'max:2000'],
            'catatan_teknis' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', 'string', Rule::enum(FopTaskPriority::class)],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            // Cuma dipakai jalur FOP submit langsung dari halaman Task FOP —
            // dicek permission-nya di bawah, BUKAN cuma divalidasi di sini.
            'task_date' => ['nullable', 'date'],
            'technicians' => ['nullable', 'array'],
            'technicians.*' => ['exists:users,id'],
            'origin' => ['nullable', 'string', 'in:fop_tasks'],
        ], [
            'type.in' => 'Tipe ticket hanya boleh MTN atau C-REQ.',
            'attachments.max' => 'Maksimal 5 lampiran per ticket.',
            'attachments.*.max' => 'Ukuran tiap lampiran maksimal 5 MB.',
            'attachments.*.mimes' => 'Lampiran harus berupa gambar (jpg/png/webp) atau PDF.',
        ]);

        // Penugasan teknisi langsung cuma dihonor buat aktor yang emang
        // berwenang bikin/assign Task FOP (fop_tasks.create). Kalau bukan —
        // helpdesk/sales/dll yang submit dari /tickets/new biasa, atau
        // request yang di-craft manual — dua field ini DIABAIKAN diam-diam,
        // bukan ditolak 422/403, biar submit ticket normal tetap jalan.
        $assignment = [];
        if (! empty($validated['technicians']) && auth()->user()->hasPermission('fop_tasks.create')) {
            $assignment = [
                'technicians' => $validated['technicians'],
                'task_date' => $validated['task_date'] ?? null,
            ];
        }

        $result = $this->ticketService->create(
            $validated,
            auth()->user(),
            $request->file('attachments', []),
            $assignment
        );

        $ticket = $result['ticket'];

        // Submit dari halaman Task FOP — balikin ke situ juga (bukan
        // tickets.show), gak peduli teknisi diisi atau dikosongin (biar masuk
        // Ticket Masuk dulu). Origin cuma dihonor buat aktor yang emang
        // berwenang bikin Task FOP, sama kayak gate assignment di atas —
        // gak ada insentif nyata buat spoofing (cuma ngubah tujuan redirect,
        // bukan buka akses baru), tapi tetap dikunci permission yang sama
        // biar konsisten satu aturan.
        $fromFopTasksPage = $request->input('origin') === 'fop_tasks'
            && auth()->user()->hasPermission('fop_tasks.create');

        if ($fromFopTasksPage) {
            $message = $ticket->fopTask->task_id
                ? "Ticket {$ticket->ticket_number} dibuat, Task FOP {$ticket->fopTask->task_number} langsung dijadwalkan."
                : "Ticket {$ticket->ticket_number} dibuat, masuk ke Ticket Masuk — assign teknisi belakangan.";

            $redirect = redirect()->route('fop-tasks.index')->with('success', $message);

            if (! empty($result['conflicts'])) {
                $redirect = $redirect->with('fop_team_conflicts', $result['conflicts']);
            }

            return $redirect;
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} terkirim. Task FOP {$ticket->fopTask?->task_number} otomatis dibuat.");
    }

    public function download(TicketAttachment $attachment): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('tickets.view'), 403);
        $this->authorizeTicketScope($attachment->ticket);

        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->response($attachment->file_path, $attachment->original_name);
    }

    /**
     * Lookup CID — memuat data pelanggan buat panel read-only di form ticket.
     */
    public function lookupCustomer(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasPermission('tickets.create'), 403);

        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json([]);
        }

        $customers = Customer::query()
            ->applyUserScope()
            ->with(['pop:id,name', 'village:id,name', 'internetPackage', 'customerDevice'])
            ->where(function ($query) use ($q) {
                $query->where('full_name', 'like', "%{$q}%")
                    ->orWhere('cid', 'like', "%{$q}%")
                    ->orWhere('customer_code', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get();

        return response()->json($customers->map(fn (Customer $c) => $this->customerPayload($c)));
    }

    /**
     * Bentuk payload pelanggan sesuai panel di form Ticketing.
     */
    private function customerPayload(Customer $c): array
    {
        $device = $c->customerDevice;

        return [
            'id' => $c->id,
            'cid' => $c->display_id ?: ($c->cid ?: $c->customer_code),
            'label' => sprintf(
                '%s — %s',
                $c->display_id ?: ($c->cid ?: $c->customer_code),
                strtoupper((string) $c->full_name)
            ),
            'nama' => $c->full_name,
            'alamat' => $c->address,
            'no_hp' => $c->primary_phone ?: $c->phone,
            'pop' => $c->pop?->name,
            'odp' => $c->odp_code ?: $device?->odp,
            'paket' => $c->internetPackage?->name,
            'perangkat' => $this->deviceSummary($device),
            'koordinat' => ($c->latitude && $c->longitude)
                ? "{$c->latitude}, {$c->longitude}"
                : null,
            'maps_url' => ($c->latitude && $c->longitude)
                ? "https://www.google.com/maps/search/?api=1&query={$c->latitude},{$c->longitude}"
                : null,
        ];
    }

    private function deviceSummary(?CustomerDevice $device): ?string
    {
        if (! $device) {
            return null;
        }

        // Cuma field non-sensitif — SN/MAC/PPPoE sengaja gak ikut, itu dikunci
        // permission customers.detail.devices.view_sensitive di modul Pelanggan.
        $parts = array_filter([
            $device->brand,
            $device->model,
            $device->device_type,
        ]);

        return $parts ? implode(' ', $parts) : null;
    }

    private function authorizeTicketScope(Ticket $ticket): void
    {
        abort_unless(
            Ticket::query()->applyUserScope()->whereKey($ticket->id)->exists(),
            403
        );
    }
}
