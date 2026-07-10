<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskScheduled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Task $task;

    /**
     * Konteks event: 'created' (task baru) atau 'rescheduled' (jadwal diubah).
     * Digunakan frontend untuk menampilkan teks notifikasi yang tepat.
     */
    public string $eventType;

    /**
     * Create a new event instance.
     */
    public function __construct(Task $task, string $eventType = 'created')
    {
        $this->task      = $task;
        $this->eventType = $eventType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $this->task->loadMissing('teamMembers');

        return $this->task->teamMembers->map(fn ($member) => new PrivateChannel('teknisi.' . $member->user_id))->all();
    }

    public function broadcastWith(): array
    {
        $this->task->loadMissing(['customer', 'pop']);

        return [
            'id'           => $this->task->id,
            'task_number'  => $this->task->task_number,
            'title'        => $this->task->title,
            'description'  => $this->task->description,
            'task_type'    => $this->task->task_type instanceof \App\Enums\TaskType ? $this->task->task_type->value : $this->task->task_type,
            'status'       => $this->task->status instanceof \App\Enums\TaskStatus ? $this->task->status->value : $this->task->status,
            'scheduled_at' => $this->task->scheduled_at ? $this->task->scheduled_at->toIso8601String() : null,
            'sla_minutes'  => $this->task->sla_minutes,
            'pop_id'       => $this->task->pop_id,
            'event_type'   => $this->eventType,
            'customer'     => $this->task->customer ? [
                'id'   => $this->task->customer->id,
                'name' => $this->task->customer->name,
            ] : null,
        ];
    }
}
