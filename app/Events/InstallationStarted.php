<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstallationStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Customer $customer;

    public string $startedAt;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
        $installation = $customer->installations()
            ->whereIn('installation_status', ['in_progress'])
            ->latest('started_at')
            ->first();
        $this->startedAt = $installation?->started_at
            ? $installation->started_at->toIso8601String()
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
            'started_at' => $this->startedAt,
        ];
    }
}
