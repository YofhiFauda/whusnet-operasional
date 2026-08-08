<?php

namespace App\Events;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Task $task;

    /**
     * Create a new event instance.
     */
    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('fop.'.$this->task->pop_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->task->loadMissing('teamMembers.user');

        return [
            'id' => $this->task->id,
            'task_number' => $this->task->task_number,
            'title' => $this->task->title,
            'status' => $this->task->status instanceof TaskStatus ? $this->task->status->value : $this->task->status,
            'pop_id' => $this->task->pop_id,
            'started_at' => $this->task->started_at ? $this->task->started_at->toIso8601String() : now()->toIso8601String(),
            'team_members' => $this->task->teamMembers->map(fn ($member) => [
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'role_in_task' => $member->role_in_task,
            ])->toArray(),
        ];
    }
}
