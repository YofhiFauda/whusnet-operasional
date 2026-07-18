<?php

namespace App\Observers;

use App\Enums\TaskStatus;
use App\Enums\TicketHistoryAction;
use App\Models\FopTask;
use App\Models\Ticket;

/**
 * Menjaga jejak sisi Ticketing tetap sinkron sama Task FOP.
 *
 * Pembatalan tiket = SATU aksi, DUA riwayat:
 *   1. sisi FOP     → `fop_task_status_history` (lihat catatan pembagian tugas di bawah)
 *   2. sisi Ticket  → `ticket_histories` (ditulis di sini)
 *
 * Observer ini sengaja jadi SATU-SATUNYA penulis riwayat sisi Ticket, biar dua
 * jalur cancel yang ada ketutup sekaligus tanpa nulis dobel:
 *   - FOP batalin tiket dari /fop-tasks  → FopTaskController::update()
 *   - Task eksekusi dibatalin dari /tasks → TaskService::cancel() → TaskObserver
 *
 * Pembagian tugas buat riwayat sisi FOP (jangan dipindah ke sini — bakal dobel):
 *   - Jalur /tasks  : TaskObserver yang nulis (status FopTask masih non-cancel
 *                     pas observer itu jalan, jadi lolos guard-nya).
 *   - Jalur /fop-tasks: FopTaskController::update() yang nulis, karena
 *                     TaskObserver keburu early-return begitu FopTask udah
 *                     berstatus `dibatalkan` (TaskObserver.php baris 42).
 */
class FopTaskObserver
{
    public function updated(FopTask $fopTask): void
    {
        if (!$fopTask->wasChanged('status')) {
            return;
        }

        if ($fopTask->status !== TaskStatus::DIBATALKAN) {
            return;
        }

        $ticket = Ticket::where('fop_task_id', $fopTask->id)->first();

        if (!$ticket) {
            return;
        }

        $from = $fopTask->getOriginal('status');

        $ticket->histories()->create([
            'action'      => TicketHistoryAction::DIBATALKAN,
            'from_status' => $from instanceof TaskStatus ? $from->value : $from,
            'to_status'   => TaskStatus::DIBATALKAN->value,
            'reason'      => $fopTask->cancel_reason,
            'actor_id'    => auth()->id(),
            'happened_at' => now(),
        ]);
    }
}
