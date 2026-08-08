<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast "status workflow pelanggan berubah" — dipicu CustomerObserver::updated()
 * begitu kolom `status` dirty, BUKAN dispatch manual di tiap controller. Status
 * pelanggan diubah dari banyak jalur (CustomerWorkflowService::transition(),
 * tapi juga CustomerVerificationController::finalVerify() & CustomerInstallationController
 * yang update() langsung tanpa lewat service) — observer di model level satu-satunya
 * titik yang menjangkau semuanya (docs/plan/analisa-realtime-spa-operasional.md
 * §2.1 no. 10: dua admin verifikasi pelanggan yang sama tanpa realtime).
 *
 * SENGAJA gak bawa payload render lengkap (beda dari TaskStarted dkk) — baris
 * antrean verifikasi kompleks (banyak cabang tombol per status/permission),
 * jadi listener refetch partial row-nya sendiri lewat endpoint yang sudah lolos
 * scope & permission user (verifications.row), bukan percaya payload broadcast
 * mentah buat re-render.
 */
class CustomerVerificationStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Customer $customer) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('customers.'.$this->customer->pop_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CustomerVerificationStatusChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'status' => $this->customer->status,
        ];
    }
}
