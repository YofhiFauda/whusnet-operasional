<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sinyal "isi antrean Monitoring Isolir di POP ini berubah" lewat jalur yang
 * TIDAK mengubah status layanan pelanggan (docs/plan/Rancangan_Monitoring_
 * Isolir_Pelanggan.md §21) — tandai manual & batalkan kasus. Jalur yang
 * MEMANG mengubah status layanan (isolir/aktifkan lewat `toggleSuspend()`,
 * pembayaran lewat `InvoiceStatusUpdated`) sudah punya broadcast sendiri
 * (`CustomerVerificationStatusChanged` / `InvoiceStatusUpdated`) yang
 * didengarkan badge sidebar — jangan dobel-broadcast untuk jalur itu.
 *
 * Payload SENGAJA cuma pop_id, sama alasannya dengan
 * `RegistrationVerificationQueueChanged`: listener ambil ulang angkanya
 * sendiri lewat endpoint yang sudah lolos permission + POP scope user.
 */
class IsolationCaseQueueChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $popId) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('isolation-cases.'.$this->popId)];
    }

    public function broadcastAs(): string
    {
        return 'IsolationCaseQueueChanged';
    }

    /**
     * @return array{pop_id: int}
     */
    public function broadcastWith(): array
    {
        return ['pop_id' => $this->popId];
    }
}
