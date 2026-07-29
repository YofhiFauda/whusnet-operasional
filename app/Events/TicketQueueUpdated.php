<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sinyal "sesuatu di antrean Ticketing POP ini berubah" — dibuat/diselesaikan/
 * dieskalasi. SENGAJA gak bawa payload tiket lengkap (beda dari TaskStarted dkk)
 * — listener (worksheet panel & index bucket) refetch data sendiri lewat
 * endpoint JSON/HTML yang udah lolos scope POP & permission user, bukan percaya
 * payload broadcast mentah. Nutup Gap #3 (docs/plan/analisa-efektivitas-worksheet-ticketing.md)
 * — sebelumnya panel worksheet gak ada auto-refresh sama sekali.
 */
class TicketQueueUpdated implements ShouldBroadcast
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
