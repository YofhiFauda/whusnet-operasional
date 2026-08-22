<?php

namespace App\Events;

use App\Enums\TaskStatus;
use App\Models\FopTask;
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
     * `fop.{pop_id}` — Dashboard FOP (stat widget, sudah lama).
     * `fop-tasks.{pop_id}` — papan /fop-tasks (baru): teknisi mulai task dari
     * halamannya sendiri, bukan dari papan, jadi papan butuh kabar sendiri buat
     * refresh baris tanpa reload manual (lihat `docs/fop-task/` § realtime).
     * `teknisi.{user_id}` per anggota tim — Task Teknisi (`tasks/own.blade.php`):
     * kalau start dipencet anggota tim LAIN (bukan yang lagi liat halaman),
     * kartu tetap harus update sendiri. Pola sama persis `TaskScheduled`.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $this->task->loadMissing('teamMembers');

        $channels = [
            new PrivateChannel('fop.'.$this->task->pop_id),
            new PrivateChannel('fop-tasks.'.$this->task->pop_id),
        ];

        foreach ($this->task->teamMembers as $member) {
            $channels[] = new PrivateChannel('teknisi.'.$member->user_id);
        }

        return $channels;
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
            // Dipakai papan /fop-tasks buat nentuin baris mana yang di-refresh —
            // FopTask.id BEDA dari Task.id (lihat CLAUDE.md § sinkronisasi
            // Ticket↔FopTask↔Task). Null kalau task ini gak punya FopTask
            // terhubung (mis. dibuat manual lewat /tasks tanpa lewat FOP).
            'fop_task_id' => FopTask::where('task_id', $this->task->id)->value('id'),
            'started_at' => $this->task->started_at ? $this->task->started_at->toIso8601String() : now()->toIso8601String(),
            'team_members' => $this->task->teamMembers->map(fn ($member) => [
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'role_in_task' => $member->role_in_task,
            ])->toArray(),
        ];
    }
}
