<?php

namespace App\Services;

use App\Enums\FopTaskPriority;
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
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
                ->with(['internetPackage', 'customerDevice', 'pop'])
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
            $ticket->issue_category_id = $data['issue_category_id'] ?? null;
            $ticket->detail_keluhan = $data['detail_keluhan'];
            $ticket->catatan_teknis = $data['catatan_teknis'] ?? null;
            $ticket->priority = $data['priority'];
            $ticket->created_by = $actor->id;
            $ticket->handler = TicketHandler::HELPDESK;
            $ticket->status = TicketHandlingStatus::OPEN;
            $ticket->save();

            $conflicts = [];

            if ($fopOrigin) {
                $fopTask = $this->syncToFopTask($ticket, $customer, $actor, $assignment['task_date'] ?? null);

                $ticket->fop_task_id = $fopTask->id;
                $ticket->handler = TicketHandler::FOP;
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
            $this->assertNocCheckedBeforeClose($ticket, $actor);

            $fromHandler = $ticket->handler->value;
            $ticket->status = TicketHandlingStatus::CLOSED;
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
            $ticket->handler = TicketHandler::NOC;
            // Window "pending NOC" mulai dari sini — null berarti NOC belum
            // resmi ambil alih (lihat onCheckNoc()), Helpdesk MASIH boleh
            // act (assertActorOwnsTicket() izinin dua-duanya selama null).
            $ticket->noc_checked_at = null;
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

        return $ticket;
    }

    /**
     * NOC resmi ambil alih tiket yang lagi "pending" (baru dikirim Helpdesk,
     * belum di-check). Sebelum ini, Helpdesk MASIH pegang kendali penuh
     * (assertActorOwnsTicket() izinin helpdesk & noc dua-duanya). Begitu
     * di-check, Helpdesk kehilangan akses — cuma NOC yang bisa lanjut.
     *
     * Guard beda dari close()/escalate() lain: BUKAN assertActorOwnsTicket()
     * biasa (yang di window pending justru izinin helpdesk JUGA) — aksi ini
     * spesifik cuma buat NOC, gak peduli window pending atau enggak.
     */
    public function onCheckNoc(Ticket $ticket, User $actor, ?string $reason = null): Ticket
    {
        $ticket = DB::transaction(function () use ($ticket, $actor, $reason) {
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $this->assertTicketStillOpen($ticket);

            if ($ticket->handler !== TicketHandler::NOC) {
                throw ValidationException::withMessages([
                    'target' => 'Cuma tiket yang lagi di tangan NOC yang bisa di-check.',
                ]);
            }

            if (! $actor->hasFullAccess() && ! $actor->hasRole('noc')) {
                throw ValidationException::withMessages([
                    'target' => 'Cuma NOC yang bisa Oncheck tiket ini.',
                ]);
            }

            if ($ticket->noc_checked_at !== null) {
                throw ValidationException::withMessages([
                    'target' => 'Tiket ini udah di-check NOC.',
                ]);
            }

            $ticket->noc_checked_at = now();
            $ticket->save();

            $ticket->histories()->create([
                'action' => TicketHistoryAction::DICEK_NOC,
                'from_status' => TicketHandler::NOC->value,
                'to_status' => TicketHandler::NOC->value,
                'reason' => $reason,
                'actor_id' => $actor->id,
                'happened_at' => now(),
            ]);

            return $ticket->fresh();
        });

        broadcast(new TicketQueueUpdated($ticket->pop_id))->toOthers();

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
            // Reset — kalau dikirim ulang ke NOC belakangan, window pending
            // harus fresh lagi, bukan mewarisi status checked yang lama.
            $ticket->noc_checked_at = null;
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

        return $ticket;
    }

    /**
     * Cuma pihak yang lagi pegang tiket yang boleh close/escalate — Helpdesk
     * gak boleh utak-atik tiket yang udah dilempar ke NOC, begitu juga
     * sebaliknya. Full-access (owner/admin) dibebaskan dari batasan ini.
     *
     * Pengecualian window "pending NOC": begitu Helpdesk kirim ke NOC tapi
     * NOC BELUM Oncheck (`noc_checked_at` null), Helpdesk MASIH boleh act —
     * dua role (`helpdesk` + `noc`) sama-sama valid di window ini. Begitu
     * NOC Oncheck, cuma `noc` yang lolos (lihat holderRoles()).
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

    /**
     * NOC wajib Oncheck dulu sebelum bisa Selesaikan tiket — gak boleh
     * langsung Close dari tab "Ticket Masuk" (pending, belum di-check).
     * Cuma berlaku buat role `noc` non-full-access; Helpdesk yang close
     * tiketnya sendiri (baik masih di dia, atau masih window pending-NOC)
     * gak kena guard ini.
     */
    private function assertNocCheckedBeforeClose(Ticket $ticket, User $actor): void
    {
        if ($actor->hasFullAccess() || ! $actor->hasRole('noc')) {
            return;
        }

        if ($ticket->handler === TicketHandler::NOC && $ticket->noc_checked_at === null) {
            throw ValidationException::withMessages([
                'target' => 'NOC wajib Oncheck dulu sebelum bisa Selesaikan tiket ini.',
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
}
