<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * `ShouldBroadcastNow` (bukan `ShouldBroadcast`/queue) — semua titik dispatch
 * event ini (`TicketService::create/close/cancel/escalate*`) dipicu langsung
 * aksi tombol admin/NOC/FOP di worksheet, payload cuma `popId` doang. Gak ada
 * alasan gantung ke availability worker Horizon buat sinyal sekecil ini
 * (docs/plan/analisa-broadcast-vs-lonceng-notif.md §2).
 *
 * Sinyal "sesuatu di antrean Ticketing POP ini berubah" — dibuat/diselesaikan/
 * dieskalasi. SENGAJA gak bawa payload tiket lengkap (beda dari TaskStarted dkk)
 * — listener (worksheet panel & index bucket) refetch data sendiri lewat
 * endpoint JSON/HTML yang udah lolos scope POP & permission user, bukan percaya
 * payload broadcast mentah. Nutup Gap #3 (docs/plan/analisa-efektivitas-worksheet-ticketing.md)
 * — sebelumnya panel worksheet gak ada auto-refresh sama sekali.
 */
class TicketQueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $popId) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tickets.'.$this->popId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TicketQueueUpdated';
    }
}
