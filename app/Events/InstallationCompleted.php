<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstallationCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Customer $customer;

    public string $completedAt;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
        $installation = $customer->installations()
            ->where('installation_status', 'completed')
            ->latest('completed_at')
            ->first();
        $this->completedAt = $installation?->completed_at
            ? $installation->completed_at->toIso8601String()
            : now()->toIso8601String();
    }

    /**
     * Broadcast ke channel FOP yang bertanggung jawab atas POP customer ini.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('fop.'.$this->customer->pop_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->full_name,
            'status' => $this->customer->status,
            'pop_id' => $this->customer->pop_id,
            'completed_at' => $this->completedAt,
        ];
    }
}
