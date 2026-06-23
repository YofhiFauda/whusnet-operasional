<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SurveyStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public \App\Models\Customer $customer;
    public string $startedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(\App\Models\Customer $customer)
    {
        $this->customer = $customer;
        $survey = $customer->latestSurvey()->first();
        $this->startedAt = $survey && $survey->started_at ? $survey->started_at->toIso8601String() : now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('customer.' . $this->customer->id . '.workflow'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->customer->id,
            'status' => $this->customer->status,
            'started_at' => $this->startedAt,
        ];
    }
}
