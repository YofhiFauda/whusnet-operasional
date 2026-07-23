<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SurveyStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Customer $customer;

    public string $startedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
        $survey = $customer->latestSurvey()->first();
        $this->startedAt = $survey && $survey->started_at ? $survey->started_at->toIso8601String() : now()->toIso8601String();
    }

    /**
     * Broadcast ke channel FOP yang bertanggung jawab atas POP customer ini.
     * Channel fop.{pop_id} digunakan agar FOP Dashboard bisa refresh kanban real-time.
     *
     * @return array<int, Channel>
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
