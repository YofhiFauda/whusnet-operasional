<?php

namespace App\Http\Controllers;

use App\Enums\FopTaskPriority;
use App\Enums\TaskType;
use App\Enums\TicketHandler;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketIssueCategory;
use App\Services\TicketService;
use App\Support\IndonesianDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ticketing — tiket internal PERUSAHAAN (helpdesk/NOC/sales/admin/dll),
 * berbeda dari FopTask yang internal FOP. Tiket lahir di tangan Helpdesk —
 * FopTask BARU kebentuk kalau eksplisit dieskalasi ke FOP (lihat
 * TicketService::escalateToFop()), atau kalau FOP sendiri yang submit
 * langsung dari halaman Task FOP (lihat TicketService::create()).
 */
class TicketController extends Controller
{
    public function __construct(private readonly TicketService $ticketService) {}

    /**
     * Cap tampilan panel worksheet — bukan cap TOTAL tiket aktif. Kalau lebih
     * dari ini, worksheetTotalActiveCount() bakal beda dari count(initialTasks)
     * dan Blade nampilin indikator "+N lainnya" (Gap #4).
     */
    private const WORKSHEET_DISPLAY_LIMIT = 30;

    /**
     * Halaman form ticket baru — "Worksheet Helpdesk", pengganti pencatatan
     * manual Excel (docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD). Dropdown
     * kategori dari Master Issue + panel kanan "List Task Ticketing" diisi
     * data awal server-side, auto-refresh lewat broadcast Reverb
     * (App\Events\TicketQueueUpdated, lihat worksheetJson()).
     */
    public function create()
    {
        abort_unless(auth()->user()->hasPermission('tickets.create'), 403);

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
            ->values()
            ->all();

        return view('tickets.create', [
            'typeOptions' => TaskType::ticketOptions(),
            'priorityOptions' => FopTaskPriority::cases(),
            'issueCategories' => $issueCategories,
            'initialTasks' => $this->worksheetTasks(),
            'worksheetTotalCount' => $this->worksheetTotalActiveCount(),
            'allowedPopIds' => Pop::forUser(auth()->user())->pluck('id'),
        ]);
    }

    /**
     * Endpoint refresh panel worksheet — dipanggil Alpine pas nerima broadcast
     * TicketQueueUpdated (Gap #3) atau klik tombol Refresh manual. Bentuk
     * respons SAMA PERSIS field-nya sama initialTasks (server-side) biar
     * Alpine tinggal replace array `tasks`, gak ada "lompat" bentuk.
     */
    public function worksheetJson(): JsonResponse
    {
        abort_unless(auth()->user()->hasPermission('tickets.create'), 403);

        return response()->json([
            'tasks' => $this->worksheetTasks(),
            'total' => $this->worksheetTotalActiveCount(),
        ]);
    }

    /**
     * Snapshot tiket terbaru (scope POP user) buat initial load panel kanan
     * worksheet. Bentuknya sengaja sama persis field-nya sama card yang
     * di-prepend submitForm() di Alpine — biar item hasil submit gak "lompat"
     * bentuk begitu nanti disusul broadcast asli.
     *
     * @return array<int, array<string, mixed>>
     */
    private function worksheetTasks(): array
    {
        return Ticket::query()
            ->applyUserScope()
            ->activeForWorksheet()
            ->with([
                // primary_phone/address/odp_code ikut di-select karena kartu &
                // tabel worksheet nampilin kontak + lokasi; tanpa itu nilainya
                // null dan diam-diam jatuh ke snapshot tiket.
                'customer:id,full_name,cid,customer_code,pop_id,status,distribution_id,primary_phone,address,odp_code',
                'customer.pop:id,name,cid_prefix',
                'issueCategory:id,name',
                'fopTask:id,task_number,status',
                'creator:id,name',
                'histories.actor:id,name',
            ])
            ->latest('created_at')
            ->limit(self::WORKSHEET_DISPLAY_LIMIT)
            ->get()
            ->map(fn (Ticket $ticket) => $this->worksheetCardPayload($ticket))
            ->values()
            ->all();
    }

    /**
     * Total tiket aktif (scope POP user) — dibandingkan sama count(worksheetTasks())
     * buat nentuin apakah perlu nampilin indikator "+N lainnya, lihat semua"
     * (Gap #4: cap 30 sebelumnya diem-diem nyembunyiin tiket tanpa indikator).
     */
    private function worksheetTotalActiveCount(): int
    {
        return Ticket::query()
            ->applyUserScope()
            ->activeForWorksheet()
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function worksheetCardPayload(Ticket $ticket): array
    {
        $customer = $ticket->customer;

        return [
            'id' => $ticket->id,
            'code' => $ticket->ticket_number,
            'priority' => $ticket->priority->value,
            'title' => $ticket->issueCategory?->name ?? Str::limit($ticket->detail_keluhan, 60),
            'desc' => $ticket->detail_keluhan,
            'time' => $ticket->created_at->diffForHumans(),
            // `time` = relatif ("2 menit lalu"), `time_at` = jam absolut. Tabel
            // worksheet nampilin dua-duanya sebaris — relatif buat rasa urgensi,
            // absolut buat dicocokin sama log/chat pelanggan.
            'time_at' => $ticket->created_at->format('H:i:s'),
            'cid' => $customer?->display_id ?: ($customer?->cid ?: $customer?->customer_code) ?: '—',
            'customer_name' => $customer?->full_name ?? $ticket->customer_name ?? '—',
            'customer_phone' => $customer?->primary_phone ?? $ticket->customer_phone ?? '—',
            // Kolom "Lokasi / POP / ODP" di tabel worksheet. Snapshot tiket
            // didahulukan (customer_odp/customer_address) — itu kondisi saat
            // tiket dibuat; data pelanggan bisa udah berubah sejak itu.
            'pop' => $customer?->pop?->name ?: '—',
            'odp' => $ticket->customer_odp ?: ($customer?->odp_code ?: '—'),
            'address' => $ticket->customer_address ?: ($customer?->address ?: '—'),
            'issue_category' => $ticket->issueCategory?->name,
            'status_label' => $ticket->statusLabel(),
            // Target SLA — worksheet cuma butuh label statis (precomputed,
            // ngikut refresh halaman/broadcast), bukan countdown live per
            // detik kayak <x-countdown-timer> di halaman Detail Tiket. Lihat
            // docs/plan/analisa-target-sla-ticketing.md.
            'sla_label' => $ticket->slaBadgeLabel(),
            'sla_badge_class' => $ticket->slaBadgeLabel() ? $ticket->slaBadgeClasses() : null,
            'bucket' => $ticket->bucket()->value,
            'handler' => $ticket->handler->value,
            'actions' => $ticket->actionFlagsFor(auth()->user()),
            // Atribusi "siapa ngapain" — Ticket::closedBy()/escalatedToNocBy()/
            // escalatedToFopBy() (dari ticket_histories, tanpa query baru,
            // relasi 'histories.actor' udah eager-loaded pemanggil).
            'created_by' => $ticket->creator?->name,
            'closed_by' => $ticket->closedBy()?->name,
            'escalated_noc_by' => $ticket->escalatedToNocBy()?->name,
            'escalated_fop_by' => $ticket->escalatedToFopBy()?->name,
            'returned_to_helpdesk_by' => $ticket->returnedToHelpdeskBy()?->name,
        ];
    }

    /**
     * Detail tiket buat **drawer kanan** di Worksheet Helpdesk & Worksheet NOC —
     * dua halaman kerja itu gak boleh melempar user ke halaman baru cuma buat
     * baca detail (navigasi penuh disisakan buat halaman arsip: Ticket Selesai,
     * Ticket Dibatalkan, History Ticketing).
     *
     * SENGAJA endpoint sendiri, BUKAN memperbesar worksheetCardPayload(): payload
     * kartu dimuat 30 baris sekaligus tiap load halaman, sedangkan riwayat +
     * lampiran cuma dibutuhin buat SATU tiket yang lagi dibuka.
     *
     * Gerbangnya sama dengan show(): `tickets.view` + POP scope.
     */
    public function detailJson(Ticket $ticket): JsonResponse
    {
        abort_unless(auth()->user()->hasPermission('tickets.view'), 403);
        $this->authorizeTicketScope($ticket);

        $ticket->load([
            'customer.pop',
            'customer.customerDevice',
            'creator',
            'pop',
            'issueCategory',
            'fopTask.technicians',
            'fopTask.statusHistories.changedByUser',
            'attachments.uploader',
            'histories.actor',
        ]);

        $customer = $ticket->customer;

        return response()->json([
            'id' => $ticket->id,
            'code' => $ticket->ticket_number,
            'type' => $ticket->type->value,
            'type_label' => $ticket->type->value.' — '.$ticket->type->label(),
            'priority' => $ticket->priority?->value,
            'status_label' => $ticket->statusLabel(),
            'status_badge' => $ticket->statusBadgeClasses(),
            'handler_label' => $ticket->handler->label(),
            'created_by' => $ticket->creator?->name,
            'created_at' => IndonesianDate::dateTime($ticket->created_at),
            'resolved_at' => $ticket->resolved_at ? IndonesianDate::dateTime($ticket->resolved_at) : null,
            'solving_time' => $ticket->solvingTimeLabel(),
            // Target SLA (docs/plan/analisa-target-sla-ticketing.md) — drawer
            // detail worksheet cuma butuh angka mentah, live-ticking-nya
            // ditangani JS drawer sendiri (bukan <x-countdown-timer>, itu
            // Blade component gak bisa ditembak dari JSON fetch).
            'sla_deadline_at' => $ticket->slaDeadline()?->toIso8601String(),
            'sla_total_seconds' => $ticket->slaTotalSeconds(),
            'sla_label' => $ticket->slaBadgeLabel(),
            'sla_badge_class' => $ticket->slaBadgeLabel() ? $ticket->slaBadgeClasses() : null,
            'sla_is_live' => ! $ticket->resolved_at && $ticket->handler !== TicketHandler::FOP,
            'fop_task_number' => $ticket->fopTask?->task_number,
            // Tiket "Terputus" — pernah dieskalasi ke FOP tapi FopTask-nya udah
            // dihapus (fop_task_id nullOnDelete). WAJIB flag sendiri: begitu
            // relasinya hilang, `fop_task` DAN `fop_task_number` dua-duanya jadi
            // null, jadi drawer gak punya cara lain buat bedain "belum pernah ke
            // FOP" dari "task-nya udah gak ada".
            'fop_task_orphan' => $ticket->isOrphan(),
            'fop_task' => $ticket->fopTask ? [
                'id' => $ticket->fopTask->id,
                'number' => $ticket->fopTask->task_number,
                'can_view' => auth()->user()->hasPermission('fop_tasks.view'),
                'url' => route('fop-tasks.index'),
                'technicians' => $ticket->fopTask->technicians->pluck('name')->join(', '),
                'histories' => $ticket->fopTask->statusHistories->map(fn ($h) => [
                    'label' => $h->label(),
                    'changed_by' => $h->changedByUser?->name ?? 'Sistem',
                    'happened_at' => IndonesianDate::dateTime($h->changed_at),
                ])->all(),
            ] : null,
            'issue_category' => $ticket->issueCategory?->name,
            'customer' => [
                'name' => $customer?->full_name ?? $ticket->customer_name,
                'cid' => $customer?->display_id ?: ($customer?->cid ?: $customer?->customer_code),
                'phone' => $ticket->customer_phone ?: $customer?->primary_phone,
                'address' => $ticket->customer_address ?: $customer?->address,
                'village' => $ticket->customer_village,
                'package' => $ticket->customer_package,
                'device' => $ticket->customer_device,
                'odp' => $ticket->customer_odp,
                'pop' => $ticket->pop?->name ?: $customer?->pop?->name,
                'maps_url' => $ticket->customerMapsUrl(),
            ],
            'detail_keluhan' => $ticket->detail_keluhan,
            'catatan_teknis' => $ticket->catatan_teknis,
            'actions' => $ticket->actionFlagsFor(auth()->user()),
            'attachments' => $ticket->attachments->map(fn (TicketAttachment $attachment) => [
                'name' => $attachment->original_name,
                'size' => $attachment->humanSize(),
                'uploader' => $attachment->uploader?->name ?? 'Sistem',
                'is_image' => $attachment->isImage(),
                'url' => route('tickets.attachments.download', $attachment),
            ])->all(),
            'histories' => $ticket->histories->map(fn ($history) => [
                'label' => $history->action->label(),
                'badge' => $history->action->badgeClasses(),
                'actor' => $history->actor?->name ?? 'Sistem',
                'reason' => $history->reason,
                'happened_at' => IndonesianDate::dateTime($history->happened_at),
            ])->all(),
        ]);
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
            // Kategori Issue (Master Issue) — sama kayak fop_tasks.history_detail,
            // belum pernah dieager-load/ditampilkan di halaman Detail Ticket ini
            // sama sekali sebelumnya.
            'issueCategory:id,name',
        ]);

        return view('tickets.show', ['ticket' => $ticket]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('tickets.create'), 403);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(TaskType::ticketValues())],
            'customer_id' => ['required', 'exists:customers,id'],
            // Nullable — dropdown boleh "Lainnya (isi manual)", detail_keluhan
            // tetap satu-satunya sumber klasifikasi buat kasus itu (rancangan
            // bagian C RANCANGAN_MASTER_ISSUE_TICKETING.md).
            'issue_category_id' => ['nullable', 'integer', 'exists:ticket_issue_categories,id'],
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

        // Submit langsung dari halaman Task FOP — satu-satunya jalur non-FOP-
        // origin di mana FopTask masih boleh kebentuk di titik create() ini
        // juga (lihat TicketService docblock). Origin cuma dihonor buat aktor
        // yang emang berwenang bikin Task FOP — gak ada insentif nyata buat
        // spoofing (cuma ngubah tujuan redirect, bukan buka akses baru), tapi
        // tetap dikunci permission yang sama biar konsisten satu aturan.
        $fromFopTasksPage = $request->input('origin') === 'fop_tasks'
            && auth()->user()->hasPermission('fop_tasks.create');

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
            $assignment,
            $fromFopTasksPage
        );

        $ticket = $result['ticket'];

        // Worksheet Helpdesk (rancangan bagian D) submit via fetch() JSON —
        // stay-on-page, BUKAN PRG redirect. Fallback non-JS (mis. form FOP di
        // /fop-tasks yang submit native POST) tetap lewat jalur di bawah,
        // gak kena cabang ini sama sekali karena gak kirim Accept: application/json.
        if ($request->wantsJson()) {
            return response()->json([
                'ticket' => $this->worksheetCardPayload($ticket->fresh([
                    'customer.pop', 'issueCategory', 'fopTask', 'creator', 'histories.actor',
                ])) + ['fop_task_number' => $ticket->fopTask?->task_number],
            ], 201);
        }

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
            ->with('success', "Ticket {$ticket->ticket_number} terkirim ke Helpdesk. Selesaikan sendiri atau kirim ke NOC/FOP dari halaman detail.");
    }

    /**
     * Helpdesk/NOC selesaikan tiket sendiri — Skenario A worksheet (Helpdesk),
     * juga dipakai NOC pas berhasil perbaiki tanpa lapangan.
     */
    public function close(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()->hasPermission('tickets.update'), 403);
        $this->authorizeTicketScope($ticket);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->ticketService->close($ticket, auth()->user(), $validated['reason'] ?? null);
        } catch (ValidationException $e) {
            return $this->respondToTicketActionError($request, $e);
        }

        $message = "Ticket {$ticket->ticket_number} berhasil diselesaikan.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'ticket' => $this->worksheetCardPayload($ticket->fresh(['customer.pop', 'issueCategory', 'fopTask', 'creator', 'histories.actor'])),
            ]);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', $message);
    }

    /**
     * Helpdesk kirim ke NOC (Skenario B), atau Helpdesk/NOC kirim ke FOP
     * (Skenario C worksheet + skenario NOC gagal perbaiki).
     */
    public function escalate(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()->hasPermission('tickets.update'), 403);
        $this->authorizeTicketScope($ticket);

        $validated = $request->validate([
            'target' => ['required', 'string', Rule::in(['noc', 'fop'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            if ($validated['target'] === TicketHandler::FOP->value) {
                $fopTask = $this->ticketService->escalateToFop($ticket, auth()->user(), $validated['reason'] ?? null);
                $message = "Ticket {$ticket->ticket_number} dikirim ke FOP, Task FOP {$fopTask->task_number} dibuat.";
            } else {
                $this->ticketService->escalateToNoc($ticket, auth()->user(), $validated['reason'] ?? null);
                $message = "Ticket {$ticket->ticket_number} dikirim ke NOC.";
            }
        } catch (ValidationException $e) {
            return $this->respondToTicketActionError($request, $e);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'ticket' => $this->worksheetCardPayload($ticket->fresh(['customer.pop', 'issueCategory', 'fopTask', 'creator', 'histories.actor'])),
            ]);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', $message);
    }

    /**
     * NOC kembaliin tiket ke Helpdesk — Gap #7 (docs/plan/analisa-efektivitas-worksheet-ticketing.md),
     * jalur pemulihan kalau NOC salah pencet/salah terima tiket sebelumnya.
     */
    public function returnToHelpdesk(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()->hasPermission('tickets.update'), 403);
        $this->authorizeTicketScope($ticket);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->ticketService->returnToHelpdesk($ticket, auth()->user(), $validated['reason'] ?? null);
        } catch (ValidationException $e) {
            return $this->respondToTicketActionError($request, $e);
        }

        $message = "Ticket {$ticket->ticket_number} dikembalikan ke Helpdesk.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'ticket' => $this->worksheetCardPayload($ticket->fresh(['customer.pop', 'issueCategory', 'fopTask', 'creator', 'histories.actor'])),
            ]);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', $message);
    }

    /**
     * Helpdesk/NOC batalkan tiket yang masih ditangani sendiri (belum pernah
     * ke FOP). Reason wajib diisi — beda dari close() yang opsional, karena
     * membatalkan keluhan pelanggan tanpa alasan riskan buat audit. Tiket
     * yang udah `handler=FOP` TETAP dibatalkan lewat modul FOP (/fop-tasks),
     * ditolak di sini oleh TicketService::assertTicketStillOpen().
     */
    public function cancel(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()->hasPermission('tickets.cancel'), 403);
        $this->authorizeTicketScope($ticket);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->ticketService->cancel($ticket, auth()->user(), $validated['reason']);
        } catch (ValidationException $e) {
            return $this->respondToTicketActionError($request, $e);
        }

        $message = "Ticket {$ticket->ticket_number} dibatalkan.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'ticket' => $this->worksheetCardPayload($ticket->fresh(['customer.pop', 'issueCategory', 'fopTask', 'creator', 'histories.actor'])),
            ]);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', $message);
    }

    private function respondToTicketActionError(Request $request, ValidationException $e): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return back()->withErrors($e->errors());
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
                    ->orWhere('customer_code', 'like', "%{$q}%")
                    ->orWhere('primary_phone', 'like', "%{$q}%")
                    ->orWhere('alternative_phone', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get();

        return response()->json($customers->map(fn (Customer $c) => $this->customerPayload($c)));
    }

    /**
     * Tiket open (bucket Masuk/Diproses) milik satu customer — server-side,
     * TANPA kena cap panel worksheet. Gap #5 (docs/plan/analisa-efektivitas-worksheet-ticketing.md)
     * — sebelumnya deteksi duplikat cuma nyisir array `tasks` Alpine yang
     * kena cap 30, jadi tiket lama yang udah kegeser dari cap gak kedeteksi.
     * activeForWorksheet() PERSIS sama cakupannya sama bucket Masuk+Diproses
     * gabungan (lihat Ticket::scopeActiveForWorksheet() docblock).
     */
    public function duplicates(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->hasPermission('tickets.create'), 403);

        $customerId = $request->query('customer_id');

        if (! $customerId) {
            return response()->json([]);
        }

        $tickets = Ticket::query()
            ->applyUserScope()
            ->where('customer_id', $customerId)
            ->activeForWorksheet()
            ->with('fopTask:id,status')
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'code' => $ticket->ticket_number,
                'bucket' => $ticket->bucket()->value,
            ])
            ->values();

        return response()->json($tickets);
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
            'no_hp' => $c->primary_phone,
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

    /**
     * Panel pratinjau form tiket menampilkan perangkat sebagai SERIAL NUMBER —
     * harus sama persis dengan yang dibekukan TicketService::deviceSummary()
     * ke `tickets.customer_device`, kalau tidak preview dan tiket jadinya beda.
     * Rationale & konsekuensi permission-nya ditulis di service itu.
     */
    private function deviceSummary(?CustomerDevice $device): ?string
    {
        return $device?->serial_number ?: null;
    }

    private function authorizeTicketScope(Ticket $ticket): void
    {
        abort_unless(
            Ticket::query()->applyUserScope()->whereKey($ticket->id)->exists(),
            403
        );
    }
}
