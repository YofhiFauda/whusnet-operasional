<?php

namespace App\Services;

use App\Enums\FopTaskPriority;
use App\Enums\NotificationType;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Enums\TicketHistoryAction;
use App\Events\TicketQueueUpdated;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\FopTask;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TicketService — pembuatan tiket internal perusahaan + mekanisme
 * Close/Escalate (docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD).
 *
 * FopTask TIDAK LAGI auto-dibuat begitu tiket disubmit (beda dari versi lama
 * Sprint 8.3). Tiket lahir di tangan Helpdesk (`handler` = HELPDESK), dan
 * FopTask baru kebentuk kalau:
 *  1. Helpdesk/NOC eksplisit escalateToFop() — FopTask Draft, BELUM ada
 *     teknisi/Task eksekusi. FOP assign teknisi belakangan lewat /fop-tasks.
 *  2. FOP sendiri submit MTN/C-REQ langsung dari halaman Task FOP ($fopOrigin
 *     true) — FopTask langsung dibuat di titik create() ini juga, sekaligus
 *     pilih teknisi & jadwal di form yang sama ($assignment terisi) kalau
 *     ada → FopTask langsung Terjadwal + Task eksekusi langsung dibuat, gak
 *     perlu mampir Draft dulu. Otorisasi $fopOrigin/$assignment WAJIB dicek
 *     caller (TicketController) SEBELUM manggil create(), bukan di sini.
 */
class TicketService
{
    /**
     * @param  array  $data  Data tervalidasi dari TicketController::store()
     * @param  UploadedFile[]  $attachments
     * @param  array{technicians?: int[], task_date?: string|null}  $assignment  Cuma dihonor kalau $fopOrigin true
     * @param  bool  $fopOrigin  Submit langsung dari halaman Task FOP — lihat docblock kelas
     * @return array{ticket: Ticket, conflicts: array}
     */
    public function create(array $data, User $actor, array $attachments = [], array $assignment = [], bool $fopOrigin = false): array
    {
        $result = DB::transaction(function () use ($data, $actor, $attachments, $assignment, $fopOrigin) {
            /** @var Customer $customer */
            $customer = Customer::query()
                ->applyUserScope($actor)
                // 'pop' WAJIB ikut — Customer::getDisplayIdAttribute() butuh
                // relasi ini buat resolve CID (lihat syncToFopTask() & bug CID
                // yang sama di docs/ticketing/business-logic.md §7).
                // 'village' dipakai snapshotCustomer() buat kolom
                // customer_village — tanpa eager load ini nilainya diam-diam
                // null (preventLazyLoading aktif di test).
                ->with(['internetPackage', 'customerDevice', 'pop', 'village'])
                ->whereKey($data['customer_id'])
                ->firstOrFail();

            // tickets.pop_id & fop_tasks butuh POP buat scoping — pelanggan tanpa
            // POP bakal bikin insert gagal dengan error FK yang gak kebaca user.
            if (! $customer->pop_id) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Pelanggan ini belum punya POP/Cabang — lengkapi dulu data pelanggannya sebelum buat tiket.',
                ]);
            }

            $type = TaskType::from($data['type']);

            $ticket = new Ticket;
            $ticket->ticket_number = $this->generateTicketNumber();
            $ticket->type = $type;
            $ticket->customer_id = $customer->id;
            $ticket->pop_id = $customer->pop_id;
            $this->snapshotCustomer($ticket, $customer);
            $issueCategory = ! empty($data['issue_category_id'])
                ? TicketIssueCategory::find($data['issue_category_id'])
                : null;

            $ticket->issue_category_id = $issueCategory?->id;
            $ticket->detail_keluhan = $data['detail_keluhan'];
            $ticket->catatan_teknis = $data['catatan_teknis'] ?? null;
            $ticket->priority = $data['priority'];
            $ticket->created_by = $actor->id;
            $ticket->handler = TicketHandler::HELPDESK;
            $ticket->status = TicketHandlingStatus::OPEN;

            // Handling SLA — snapshot SEKALI di titik tiket lahir, anchor
            // created_at (lihat Ticket::slaDeadline()). Dihitung di sini
            // (bukan nunggu FopTask kebentuk) supaya tiket yang gak pernah
            // dieskalasi ke FOP (ditutup langsung Helpdesk/NOC) tetap punya
            // target SLA — lihat docs/plan/analisa-target-sla-ticketing.md.
            $ticket->sla_hours = $this->resolveSlaHours($type, $ticket->priority, $customer, $issueCategory);
            $ticket->sla_deadline_at = now()->addHours($ticket->sla_hours);

            $ticket->save();

            $conflicts = [];

            if ($fopOrigin) {
                $fopTask = $this->syncToFopTask($ticket, $customer, $actor, $assignment['task_date'] ?? null);

                $ticket->fop_task_id = $fopTask->id;
                $ticket->handler = TicketHandler::FOP;
                // Submit langsung dari halaman Task FOP — tiket lahir DAN
                // langsung diserahkan, jadi waktu lepas dari meja Ticketing =
                // waktu dibuat (lihat catatan `resolved_at` di escalateToFop()).
                $ticket->resolved_at = now();
                $ticket->save();

                if (! empty($assignment['technicians'])) {
                    $conflicts = $this->assignTechnicians($fopTask, $ticket, $assignment['technicians'], $actor);
                }
            }

            foreach ($attachments as $file) {
                $this->storeAttachment($ticket, $file, $actor);
            }

            $ticket->histories()->create([
                'action' => TicketHistoryAction::DIBUAT,
                'to_status' => $ticket->handler->value,
                'actor_id' => $actor->id,
                'happened_at' => now(),
            ]);

            if (class_exists(AuditLog::class)) {
                AuditLog::log($ticket, 'create', null, $ticket->fresh()->toArray());
            }

            return [
                'ticket' => $ticket->load(['customer', 'creator', 'fopTask.technicians', 'attachments', 'issueCategory']),
                'conflicts' => $conflicts,
            ];
        });

        // Dispatch SETELAH transaksi commit (bukan di dalam closure) — broadcast
        // gak boleh nembak kalau transaksinya rollback. toOthers() biar tab
        // sendiri yang barusan aksi gak refetch dobel (udah di-patch lokal).
        broadcast(new TicketQueueUpdated($result['ticket']->pop_id))->toOthers();

        return $result;
    }

    /**
     * Helpdesk/NOC selesaikan tiket sendiri, gak pernah nyampe FOP sama
     * sekali (Skenario A worksheet: Helpdesk bisa selesaikan konfigurasi
     * sendiri; juga dipakai NOC pas berhasil perbaiki tanpa lapangan).
     */
    public function close(Ticket $ticket, User $actor, ?string $reason = null): Ticket
    {
        $ticket = DB::transaction(function () use ($ticket, $actor, $reason) {
            // lockForUpdate() — dua request nyaris bareng (double-klik, atau
            // Helpdesk Close pas NOC Escalate di detik yang sama) WAJIB
            // antre di sini, bukan dua-duanya lolos guard di bawah sebelum
            // salah satu commit (TOCTOU). Baris kedua yang nunggu bakal baca
            // state TERBARU begitu lock lepas, jadi ketahan assertTicketStillOpen().
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $this->assertActorOwnsTicket($ticket, $actor);
            $this->assertTicketStillOpen($ticket);

            $fromHandler = $ticket->handler->value;
            $ticket->status = TicketHandlingStatus::CLOSED;
            // `resolved_at` = kapan tiket LEPAS dari meja Ticketing. Di sini
            // artinya beneran selesai (Helpdesk/NOC nutup sendiri); jalur FOP
            // nulis kolom yang sama di escalateToFop() dengan arti "diserahkan".
            // Lihat docs/plan/analisa-halaman-history-ticketing.md §4.1.
            $ticket->resolved_at = now();
            $ticket->save();

            $ticket->histories()->create([
                'action' => TicketHistoryAction::DISELESAIKAN,
                'from_status' => $fromHandler,
                'to_status' => $fromHandler,
                'reason' => $reason,
                'actor_id' => $actor->id,
                'happened_at' => now(),
            ]);

            return $ticket->fresh();
        });

        broadcast(new TicketQueueUpdated($ticket->pop_id))->toOthers();

        // Notif ke pembuat tiket kalau yang menutup BUKAN dia sendiri (mis.
        // NOC yang nutup tiket yang tadinya dikirim Helpdesk). Helpdesk yang
        // nutup tiket bikinan sendiri gak perlu dikasih tau — dia yang aksi.
        $this->notifyCreatorIfDifferentActor($ticket, $actor, 'Tiket Diselesaikan: '.$ticket->ticket_number,
            "Tiket {$ticket->ticket_number} telah diselesaikan oleh {$actor->name}.".($reason ? " Catatan: {$reason}" : ''),
            NotificationType::SUCCESS
        );

        return $ticket;
    }

    /**
     * Helpdesk/NOC batalkan tiket sendiri, gak pernah nyampe FOP sama sekali —
     * kembaran close() tapi berakhir di bucket Dibatalkan, bukan Selesai.
     * Reason WAJIB (dicek di controller) — beda dari close() yang opsional,
     * karena membatalkan keluhan pelanggan tanpa alasan riskan buat audit.
     * Pembatalan tiket yang udah `handler=FOP` TETAP lewat modul FOP
     * (/fop-tasks, lihat FopTaskObserver) — bukan di sini, ditolak lewat
     * assertTicketStillOpen() sama seperti close/escalate/return.
     */
    public function cancel(Ticket $ticket, User $actor, ?string $reason): Ticket
    {
        $ticket = DB::transaction(function () use ($ticket, $actor, $reason) {
            // lockForUpdate() — lihat penjelasan di close().
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $this->assertActorOwnsTicket($ticket, $actor);
            $this->assertTicketStillOpen($ticket);

            $fromHandler = $ticket->handler->value;
            $ticket->status = TicketHandlingStatus::CANCELLED;
            $ticket->save();

            $ticket->histories()->create([
                'action' => TicketHistoryAction::DIBATALKAN,
                'from_status' => $fromHandler,
                'to_status' => $fromHandler,
                'reason' => $reason,
                'actor_id' => $actor->id,
                'happened_at' => now(),
            ]);

            return $ticket->fresh();
        });

        broadcast(new TicketQueueUpdated($ticket->pop_id))->toOthers();

        $this->notifyCreatorIfDifferentActor($ticket, $actor, 'Tiket Dibatalkan: '.$ticket->ticket_number,
            "Tiket {$ticket->ticket_number} dibatalkan oleh {$actor->name}.".($reason ? " Alasan: {$reason}" : ''),
            NotificationType::ERROR
        );

        return $ticket;
    }

    /**
     * Helpdesk kirim tiket ke NOC (Skenario B worksheet) — belum ke FOP,
     * cuma pindah tangan penanganan internal.
     */
    public function escalateToNoc(Ticket $ticket, User $actor, ?string $reason = null): Ticket
    {
        $ticket = DB::transaction(function () use ($ticket, $actor, $reason) {
            // lockForUpdate() — lihat penjelasan di close().
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $this->assertActorOwnsTicket($ticket, $actor);
            $this->assertTicketStillOpen($ticket);

            if ($ticket->handler !== TicketHandler::HELPDESK) {
                throw ValidationException::withMessages([
                    'target' => 'Cuma tiket yang masih di tangan Helpdesk yang bisa dikirim ke NOC.',
                ]);
            }

            $fromHandler = $ticket->handler->value;
            // Begitu dikirim ke NOC, tiket LANGSUNG berstatus diproses NOC.
            // Gak ada lagi window "Pending NOC" + aksi Oncheck (dihapus
            // 2026-07-29, ADHOC-06) — dulu tiket nyangkut di limbo sampai NOC
            // menekan Oncheck, padahal pada praktiknya assign = mulai kerja.
            // Helpdesk TETAP boleh act atas tiket ini (lihat holderRoles()).
            $ticket->handler = TicketHandler::NOC;
            $ticket->save();

            $ticket->histories()->create([
                'action' => TicketHistoryAction::DIESKALASI,
                'from_status' => $fromHandler,
                'to_status' => TicketHandler::NOC->value,
                'reason' => $reason,
                'actor_id' => $actor->id,
                'happened_at' => now(),
            ]);

            return $ticket->fresh();
        });

        broadcast(new TicketQueueUpdated($ticket->pop_id))->toOthers();

        // Tiket baru masuk antrean NOC — gak ada langkah "terima" (ADHOC-06),
        // jadi lonceng ini SATU-SATUNYA sinyal personal yang NOC dapat di luar
        // buka Worksheet manual. Broadcast TicketQueueUpdated di atas cuma
        // nyala kalau NOC pas lagi buka halaman Worksheet-nya juga.
        $this->notifyRoleUsersInPop('noc', $ticket->pop_id,
            'Tiket Masuk NOC: '.$ticket->ticket_number,
            "Tiket {$ticket->ticket_number} ({$ticket->customer_name}) dikirim {$actor->name} ke NOC.".($reason ? " Catatan: {$reason}" : ''),
            route('tickets.show', $ticket->id)
        );

        return $ticket;
    }

    /**
     * Helpdesk (Skenario C) atau NOC kirim tiket ke FOP — titik SATU-SATUNYA
     * di mana FopTask kebentuk buat tiket yang gak lewat halaman Task FOP.
     * Selalu Draft-unassigned, sama kayak perilaku auto-sync lama — FOP yang
     * nentuin penjadwalan teknisi belakangan lewat /fop-tasks.
     */
    public function escalateToFop(Ticket $ticket, User $actor, ?string $reason = null): FopTask
    {
        $fopTask = DB::transaction(function () use ($ticket, $actor, $reason) {
            // lockForUpdate() — lihat penjelasan di close().
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $this->assertActorOwnsTicket($ticket, $actor);
            $this->assertTicketStillOpen($ticket);

            /** @var Customer $customer */
            $customer = Customer::query()->with('pop')->findOrFail($ticket->customer_id);

            $fromHandler = $ticket->handler->value;
            $fopTask = $this->syncToFopTask($ticket, $customer, $actor);

            $ticket->fop_task_id = $fopTask->id;
            $ticket->handler = TicketHandler::FOP;
            // Titik ini = akhir keterlibatan Ticketing atas tiket ini, jadi
            // `resolved_at` diisi SEKALI di sini dan gak berubah lagi walau
            // task lapangannya nanti selesai/dibatalkan. History Ticketing
            // memang berhenti di "Assign FOP" — progres lapangan diukur di
            // modul FOP (SLA pengerjaan), bukan di sini.
            $ticket->resolved_at = now();
            $ticket->save();

            $ticket->histories()->create([
                'action' => TicketHistoryAction::DIESKALASI,
                'from_status' => $fromHandler,
                'to_status' => TicketHandler::FOP->value,
                'reason' => $reason,
                'actor_id' => $actor->id,
                'happened_at' => now(),
            ]);

            return $fopTask;
        });

        broadcast(new TicketQueueUpdated($fopTask->pop_id))->toOthers();

        // Titik ini terminal buat sisi Ticketing (handler=FOP) — FOP baru tau
        // ada kerjaan lewat lonceng ini atau buka /fop-tasks manual, gak ada
        // jalur lain lagi dari Ticketing.
        $this->notifyRoleUsersInPop('fop', $fopTask->pop_id,
            'FopTask Baru dari Tiket: '.$fopTask->task_number,
            "Tiket {$ticket->ticket_number} dikirim {$actor->name} ke FOP, jadi {$fopTask->task_number}. Perlu ditugaskan teknisi.",
            route('fop-tasks.index')
        );

        return $fopTask;
    }

    /**
     * NOC kembaliin tiket ke Helpdesk — jalur pemulihan salah kirim (Gap #7,
     * docs/plan/analisa-efektivitas-worksheet-ticketing.md). Cuma NOC yang
     * bisa "turun" — Helpdesk gak punya ke mana pun buat dikembaliin (dia
     * asal-usulnya), dan FOP terminal buat sisi Ticketing (lihat
     * assertTicketStillOpen()).
     */
    public function returnToHelpdesk(Ticket $ticket, User $actor, ?string $reason = null): Ticket
    {
        $ticket = DB::transaction(function () use ($ticket, $actor, $reason) {
            // lockForUpdate() — lihat penjelasan di close().
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $this->assertActorOwnsTicket($ticket, $actor);
            $this->assertTicketStillOpen($ticket);

            if ($ticket->handler !== TicketHandler::NOC) {
                throw ValidationException::withMessages([
                    'target' => 'Cuma tiket yang lagi di tangan NOC yang bisa dikembalikan ke Helpdesk.',
                ]);
            }

            $fromHandler = $ticket->handler->value;
            $ticket->handler = TicketHandler::HELPDESK;
            $ticket->save();

            $ticket->histories()->create([
                'action' => TicketHistoryAction::DIKEMBALIKAN,
                'from_status' => $fromHandler,
                'to_status' => TicketHandler::HELPDESK->value,
                'reason' => $reason,
                'actor_id' => $actor->id,
                'happened_at' => now(),
            ]);

            return $ticket->fresh();
        });

        broadcast(new TicketQueueUpdated($ticket->pop_id))->toOthers();

        // Balik ke Helpdesk = salah kirim yang perlu ditindaklanjuti pengirim
        // aslinya secepatnya — notif personal ke created_by, bukan broadcast
        // role-wide (beda dari escalateToNoc/escalateToFop yang emang antrean
        // baru buat semua orang di role itu).
        $this->notifyCreatorIfDifferentActor($ticket, $actor, 'Tiket Dikembalikan ke Anda: '.$ticket->ticket_number,
            "Tiket {$ticket->ticket_number} dikembalikan NOC ke Helpdesk oleh {$actor->name}.".($reason ? " Alasan: {$reason}" : ''),
            NotificationType::WARNING
        );

        return $ticket;
    }

    /**
     * Cuma pihak yang lagi pegang tiket yang boleh close/escalate. Daftar role
     * yang sah datang dari Ticket::holderRoles() — SATU-SATUNYA sumbernya,
     * jangan diduplikasi di sini. Full-access (owner/admin) dibebaskan.
     *
     * Tiket di tangan NOC dipegang BERSAMA `helpdesk` + `noc` (keputusan
     * ADHOC-06, 2026-07-29): Helpdesk yang mengirim tetap boleh
     * menyelesaikan/membatalkan tiketnya sendiri walau sudah diproses NOC.
     */
    private function assertActorOwnsTicket(Ticket $ticket, User $actor): void
    {
        if ($actor->hasFullAccess()) {
            return;
        }

        $expectedRoles = $ticket->holderRoles();

        if (empty($expectedRoles) || ! collect($expectedRoles)->contains(fn ($role) => $actor->hasRole($role))) {
            throw ValidationException::withMessages([
                'target' => 'Tiket ini bukan di tangan Anda — cuma pihak yang lagi menangani yang bisa selesaikan/kirim tiket ini.',
            ]);
        }
    }

    private function assertTicketStillOpen(Ticket $ticket): void
    {
        if ($ticket->handler === TicketHandler::FOP) {
            throw ValidationException::withMessages([
                'target' => 'Tiket ini udah di FOP — Close/Escalate Ticketing gak berlaku lagi, lihat Task FOP.',
            ]);
        }

        if ($ticket->status === TicketHandlingStatus::CLOSED) {
            throw ValidationException::withMessages([
                'target' => 'Tiket ini udah selesai.',
            ]);
        }

        if ($ticket->status === TicketHandlingStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'target' => 'Tiket ini udah dibatalkan.',
            ]);
        }
    }

    /**
     * Bikin FopTask category DEAC (Ambil Modem) dari tombol "Ambil Alat" di
     * List Putus Langganan (CustomerController::retrieveDevice()). Gak lewat
     * Ticket sama sekali (DEAC bukan tipe yang boleh diajukan lewat Ticketing —
     * lihat TaskType::ticketValues()), tapi tetap dibikin Draft-unassigned
     * kayak syncToFopTask() supaya alur eksekusinya (assign teknisi → Task →
     * evidence/report → review) SAMA PERSIS dengan MTN/C-REQ, bukan lagi
     * langsung tandai device_retrieved_at sekali klik. Nomor TFOP- tetap
     * lewat generateFopTaskNumber() yang sama biar gak nyimpang dari deret
     * yang dipakai FopTaskController::generateTaskNumber() (lihat CLAUDE.md
     * § Sinkronisasi Ticket ↔ FopTask ↔ Task). device_retrieved_at sendiri
     * baru keisi otomatis saat Task-nya selesai (lihat TaskService::complete()).
     */
    public function createDeviceRetrievalTask(Customer $customer, User $actor): FopTask
    {
        return DB::transaction(function () use ($customer, $actor) {
            $fopTask = new FopTask;
            $fopTask->task_number = $this->generateFopTaskNumber();
            $fopTask->task_date = now();
            $fopTask->category = TaskType::AMBIL_MODEM;
            $fopTask->tugas = $customer->display_id.'_'.$customer->full_name;
            $fopTask->village_id = $customer->village_id;
            $fopTask->pop_id = $customer->pop_id;
            $fopTask->customer_id = $customer->id;
            $fopTask->issue = 'Pengambilan alat pelanggan putus langganan.';
            $fopTask->notes = "Ambil Alat — diajukan oleh {$actor->name} dari List Putus Langganan.";
            $fopTask->status = TaskStatus::DRAFT;
            $fopTask->priority = FopTaskPriority::MEDIUM;
            $fopTask->save();

            if (class_exists(AuditLog::class)) {
                AuditLog::log($fopTask, 'create', null, $fopTask->fresh()->toArray());
            }

            return $fopTask;
        });
    }

    /**
     * Penugasan teknisi langsung saat submit — SATU-SATUNYA jalur di mana
     * FopTask hasil Ticketing bisa lompat dari Draft langsung ke Terjadwal
     * tanpa mampir tabel /fop-tasks dulu. Logic-nya sengaja disalin dari
     * FopTaskController::store() (bukan dipanggil silang), karena titik
     * masuknya beda: di sana FopTask baru dibuat lewat form generik, di sini
     * lewat Ticket yang udah beres duluan (customer_id/pop_id/village_id
     * udah kefrozen dari snapshot, gak perlu divalidasi ulang).
     *
     * @param  int[]  $technicianIds
     * @return array Konflik team (kalau >1 teknisi) — dipakai controller buat flash session yang sama dengan /fop-tasks.
     */
    private function assignTechnicians(FopTask $fopTask, Ticket $ticket, array $technicianIds, User $actor): array
    {
        $fopTask->technicians()->sync($technicianIds);
        $fopTask->status = TaskStatus::TERJADWAL;
        $fopTask->save();

        $taskData = [
            'customer_id' => $fopTask->customer_id,
            'pop_id' => $fopTask->pop_id,
            'task_type' => $fopTask->category->value,
            'title' => 'FOP: '.$fopTask->tugas,
            'description' => trim($ticket->detail_keluhan."\n".($ticket->catatan_teknis ?? '')),
            'team_member_ids' => $technicianIds,
            'scheduled_at' => $fopTask->task_date,
            'conflict_override' => true,
        ];

        $task = app(TaskService::class)->create($taskData, $actor);
        $fopTask->task_id = $task->id;
        $fopTask->save();

        $teamResult = app(FopTaskTeamService::class)->rebuildTeamsForDate(Carbon::parse($fopTask->task_date));

        return count($technicianIds) > 1 ? ($teamResult['conflicts'] ?? []) : [];
    }

    /**
     * Resolusi Handling SLA (jam) buat tiket — dua jalur, dipilih lewat
     * `TicketIssueCategory::sla_source` (mengaktifkan opsi "Prioritas" yang
     * sebelumnya cuma teks info di form, gak pernah dibaca backend, lihat
     * docs/plan/analisa-target-sla-ticketing.md §3):
     *   - 'prioritas' → FopTaskPriority::slaHours(), matrix tetap per level.
     *   - 'paket' (atau kategori kosong/legacy) → InternetPackage::getHandlingSla(),
     *     fallback TaskType::defaultHandlingSlaHours() kalau pelanggan gak
     *     punya paket / paket belum diatur di Master Timeline SLA.
     * Sengaja mirror persis logic `FopTask::booted()` buat jalur paket, biar
     * dua tempat itu gak diam-diam menyimpang.
     */
    private function resolveSlaHours(TaskType $type, FopTaskPriority $priority, Customer $customer, ?TicketIssueCategory $issueCategory): int
    {
        if ($issueCategory?->sla_source === 'prioritas') {
            return $priority->slaHours();
        }

        return $customer->internetPackage?->getHandlingSla($type) ?? $type->defaultHandlingSlaHours();
    }

    /**
     * Bekukan data pelanggan ke kolom ticket saat itu juga (lihat rationale
     * di migration 2026_07_24_000001). ODP: prioritaskan denormalisasi di
     * customers.odp_code, fallback ke customer_devices.odp kalau kosong —
     * sama persis urutan yang dipakai TicketController::customerPayload()
     * buat panel live-preview form (sengaja diduplikasi, bukan dipanggil
     * silang controller↔service).
     */
    private function snapshotCustomer(Ticket $ticket, Customer $customer): void
    {
        $device = $customer->customerDevice;

        $ticket->customer_name = $customer->full_name;
        $ticket->customer_address = $customer->address;
        // Desa ikut di-snapshot, BUKAN dibaca lewat relasi waktu ditampilkan —
        // pelanggan bisa pindah desa dan rekap bulan lalu gak boleh ikut berubah
        // (docs/plan/analisa-halaman-history-ticketing.md §4.2).
        $ticket->customer_village = $customer->village?->name;
        $ticket->customer_phone = $customer->primary_phone;
        $ticket->customer_odp = $customer->odp_code ?: $device?->odp;
        $ticket->customer_package = $customer->internetPackage?->name;
        $ticket->customer_device = $this->deviceSummary($device);
        $ticket->customer_latitude = $customer->latitude;
        $ticket->customer_longitude = $customer->longitude;
    }

    /**
     * Cuma field non-sensitif — SN/MAC/PPPoE sengaja gak ikut, itu dikunci
     * permission customers.detail.devices.view_sensitive di modul Pelanggan.
     */
    private function deviceSummary(?CustomerDevice $device): ?string
    {
        if (! $device) {
            return null;
        }

        $parts = array_filter([$device->brand, $device->model, $device->device_type]);

        return $parts ? implode(' ', $parts) : null;
    }

    /**
     * Bikin FopTask kembar dari tiket. Sengaja gak lewat FopTaskController::store()
     * — controller itu mewajibkan minimal 1 teknisi dan langsung bikin Task
     * eksekusi, sedangkan tiket dari perusahaan masuk sebagai antrean mentah
     * yang penugasannya jadi keputusan FOP (kecuali FOP sendiri yang submit
     * sambil langsung assign — lihat assignTechnicians()).
     */
    private function syncToFopTask(Ticket $ticket, Customer $customer, User $actor, ?string $taskDate = null): FopTask
    {
        $fopTask = new FopTask;
        $fopTask->task_number = $this->generateFopTaskNumber();
        $fopTask->task_date = $taskDate ?? now();
        $fopTask->category = $ticket->type;
        // Format "{CID}_{Nama}" (mis. "C1X4ARQ000631_Masudah Yuni Fitri") —
        // konsisten sama identitas pelanggan yang dipakai di seluruh sistem
        // (CID/REQ ID, lihat docs/master/pop/business-logic.md), bukan label
        // tipe tiket generik kayak "Maintenance: ...".
        $fopTask->tugas = $customer->display_id.'_'.$customer->full_name;
        $fopTask->village_id = $customer->village_id;
        $fopTask->pop_id = $customer->pop_id;
        $fopTask->customer_id = $customer->id;
        $fopTask->issue = mb_substr($ticket->detail_keluhan, 0, 255);
        $fopTask->notes = $this->composeFopNotes($ticket, $actor);
        $fopTask->status = TaskStatus::DRAFT;
        $fopTask->priority = $ticket->priority;
        // Warisi SLA yang udah disnapshot di titik tiket lahir (TicketService::
        // create()) — BUKAN biarin FopTask::booted() hitung ulang dari paket.
        // Clock-nya satu, gak reset di titik handoff Ticketing → FOP. Fallback
        // ke logic lama cuma buat tiket peninggalan sebelum kolom ini ada
        // (sla_hours null).
        $fopTask->handling_sla_hours = $ticket->sla_hours
            ?? ($customer->internetPackage?->getHandlingSla($ticket->type) ?? $ticket->type->defaultHandlingSlaHours());
        $fopTask->save();

        if (class_exists(AuditLog::class)) {
            AuditLog::log($fopTask, 'create', null, $fopTask->fresh()->toArray());
        }

        return $fopTask;
    }

    /**
     * Jejak asal-usul di FopTask — biar FOP yang cuma lihat /fop-tasks tetap
     * tahu tiket ini datang dari siapa tanpa harus buka modul Ticketing.
     *
     * SENGAJA cuma pointer pendek, BUKAN nyalin ulang catatan_teknis ke sini —
     * catatan teknis udah punya rumah sendiri yang bersih di `ticket->catatan_teknis`
     * (ditampilkan proper di halaman Detail Task, lihat history_detail.blade.php).
     * Nyalin ke sini bikin dua sumber kebenaran yang gampang menyimpang begitu
     * salah satunya diedit belakangan, dan bikin kolom `notes` jadi blob teks
     * campur aduk (metadata + isi), bukan catatan yang proper.
     */
    private function composeFopNotes(Ticket $ticket, User $actor): string
    {
        return "Ticket {$ticket->ticket_number} — dikirim oleh {$actor->name}.";
    }

    private function storeAttachment(Ticket $ticket, UploadedFile $file, User $actor): void
    {
        // Disk 'local' (privat), bukan 'public' — lampiran bisa memuat data
        // pelanggan, jadi aksesnya harus lewat TicketController::download()
        // yang ngecek permission + scope POP, bukan URL tebakan.
        $path = $file->store("tickets/{$ticket->id}", 'local');

        $ticket->attachments()->create([
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $actor->id,
        ]);
    }

    private function generateTicketNumber(): string
    {
        $year = date('Y');
        $lastNum = Ticket::where('ticket_number', 'like', "TKT-{$year}-%")
            ->pluck('ticket_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('TKT-%s-%04d', $year, $lastNum + 1);
    }

    /**
     * Format nomor harus identik sama FopTaskController::generateTaskNumber()
     * — dua-duanya nulis ke deret yang sama (TFOP-{tahun}-{urut}).
     */
    private function generateFopTaskNumber(): string
    {
        $year = date('Y');
        $lastNum = FopTask::where('task_number', 'like', "TFOP-{$year}-%")
            ->pluck('task_number')
            ->map(fn ($taskNumber) => (int) substr($taskNumber, strrpos($taskNumber, '-') + 1))
            ->max() ?? 0;

        return sprintf('TFOP-%s-%04d', $year, $lastNum + 1);
    }

    /**
     * Notif ke pembuat tiket asli (created_by), tapi cuma kalau BUKAN dia
     * sendiri yang barusan aksi — orang gak perlu dikasih tau soal aksinya
     * sendiri. Dipakai buat close/cancel/returnToHelpdesk, yang sifatnya
     * "kabar balik ke satu orang", beda dari escalateToNoc/escalateToFop
     * yang "antrean baru buat satu role" (lihat notifyRoleUsersInPop()).
     */
    private function notifyCreatorIfDifferentActor(Ticket $ticket, User $actor, string $title, string $message, NotificationType $type): void
    {
        $creator = $ticket->creator ?? User::find($ticket->created_by);

        if (! $creator || $creator->id === $actor->id) {
            return;
        }

        $creator->notify(new AppNotification(
            title: $title,
            message: $message,
            actionUrl: route('tickets.show', $ticket->id),
            type: $type
        ));
    }

    /**
     * Semua user berrole $roleCode yang punya akses ke POP $popId (lewat
     * EffectiveAccessService scope: all_pop, atau selected_pop/pop_tree yang
     * nyakup POP ini) — pola sama persis query "notify FOP users" di
     * TaskService::complete(), disalin bukan diekstrak jadi shared service
     * karena cuma dipakai internal 2 titik di kelas ini (lihat CLAUDE.md:
     * hindari abstraksi sebelum dibutuhkan).
     *
     * @return Collection<int, User>
     */
    private function usersWithRoleInPop(string $roleCode, int $popId): Collection
    {
        return User::whereHas('role', fn ($q) => $q->where('code', $roleCode))
            ->where(function ($query) use ($popId) {
                $query->whereHas('roleScopes', fn ($q) => $q->where('scope_type', ScopeType::ALL_POP->value))
                    ->orWhereHas('roleScopes', fn ($q) => $q->whereIn('scope_type', [ScopeType::SELECTED_POP->value, ScopeType::POP_TREE->value])
                        ->whereHas('targets', fn ($t) => $t->where('pop_id', $popId))
                    );
            })
            ->get();
    }

    private function notifyRoleUsersInPop(string $roleCode, int $popId, string $title, string $message, string $actionUrl): void
    {
        foreach ($this->usersWithRoleInPop($roleCode, $popId) as $user) {
            $user->notify(new AppNotification(
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                type: NotificationType::INFO
            ));
        }
    }
}
