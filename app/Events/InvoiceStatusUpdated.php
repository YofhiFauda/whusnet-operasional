<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast tunggal untuk "status/nominal invoice berubah karena payment" —
 * dipicu satu tempat, Invoice::recalculateFromPayments(), jadi otomatis
 * menutupi semua jalur pembayaran (single/bulk/batch kolektor/reject) tanpa
 * perlu dispatch manual di tiap controller (docs/plan/analisa-realtime-spa-
 * operasional.md §2.1 no. 2 & 11).
 */
class InvoiceStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('invoices.'.$this->invoice->pop_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'InvoiceStatusUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_status' => $this->invoice->invoice_status->value,
            'invoice_status_label' => $this->invoice->invoice_status->label(),
            'paid_amount' => (float) $this->invoice->paid_amount,
            'remaining_amount' => (float) $this->invoice->remaining_amount,
        ];
    }
}
