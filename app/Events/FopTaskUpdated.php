<?php

namespace App\Events;

use App\Models\FopTask;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sinyal "baris Task FOP ini berubah" — dipakai /fop-tasks (fop_tasks/index.blade.php)
 * buat refetch 3 sel (Teknisi/Team/Status) di tempat, ganti setTimeout(reload)
 * lama (docs/plan/analisa-realtime-spa-operasional.md §2.2 no. 13).
 *
 * SENGAJA dispatch manual di titik aksi user (switchTechnician/assignToTeam/
 * update controller), BUKAN dari FopTaskObserver::updated() kayak
 * InvoiceStatusUpdated/CustomerVerificationStatusChanged — FopTaskController::
 * index() jalanin autoSyncAndCalculatePriority() yang diam-diam ->update()
 * priority/client_request_date PULUHAN row tiap kali halaman dibuka. Observer
 * bakal nge-broadcast storm tiap page view kalau dipasang di situ.
 *
 * Payload SENGAJA cuma bawa ID (bukan render lengkap) — baris ini punya banyak
 * cabang tombol (switch teknisi, team conflict, cancel), listener refetch
 * lewat endpoint fop-tasks.row yang udah lolos scope & permission user.
 */
class FopTaskUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FopTask $fopTask) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('fop-tasks.'.$this->fopTask->pop_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'FopTaskUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'fop_task_id' => $this->fopTask->id,
        ];
    }
}
