<?php

namespace App\Events;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
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
     * Konteks event: 'created' (task baru), 'rescheduled' (jadwal diubah),
     * 'team_changed' (tim berubah tanpa ganti jadwal), 'removed'/'cancelled'
     * (teknisi dilepas dari task / task dibatalkan — kartu harus hilang dari layar).
     * Digunakan frontend untuk menampilkan teks notifikasi yang tepat dan
     * memutuskan render (inject/replace) vs hapus kartu.
     */
    public string $eventType;

    /**
     * Target user_id eksplisit buat broadcastOn(), dipakai saat penerima BUKAN
     * anggota tim task saat ini — mis. teknisi yang baru saja di-drop dari tim
     * (sudah tidak ada di $task->teamMembers begitu event ini dibuat, jadi fallback
     * ke teamMembers gak akan pernah sampai ke dia). Null = pakai teamMembers task
     * seperti biasa (perilaku lama, tidak berubah).
     *
     * @var array<int>|null
     */
    public ?array $targetUserIds;

    /**
     * Create a new event instance.
     *
     * @param  array<int>|null  $targetUserIds
     */
    public function __construct(Task $task, string $eventType = 'created', ?array $targetUserIds = null)
    {
        $this->task = $task;
        $this->eventType = $eventType;
        $this->targetUserIds = $targetUserIds;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        if ($this->targetUserIds !== null) {
            return collect($this->targetUserIds)->map(fn ($userId) => new PrivateChannel('teknisi.'.$userId))->all();
        }

        $this->task->loadMissing('teamMembers');

        return $this->task->teamMembers->map(fn ($member) => new PrivateChannel('teknisi.'.$member->user_id))->all();
    }

    public function broadcastWith(): array
    {
        $this->task->loadMissing(['customer', 'pop']);

        return [
            'id' => $this->task->id,
            'task_number' => $this->task->task_number,
            'title' => $this->task->title,
            'description' => $this->task->description,
            'task_type' => $this->task->task_type instanceof TaskType ? $this->task->task_type->value : $this->task->task_type,
            'status' => $this->task->status instanceof TaskStatus ? $this->task->status->value : $this->task->status,
            'scheduled_at' => $this->task->scheduled_at ? $this->task->scheduled_at->toIso8601String() : null,
            'sla_minutes' => $this->task->sla_minutes,
            'pop_id' => $this->task->pop_id,
            'event_type' => $this->eventType,
            'customer' => $this->task->customer ? [
                'id' => $this->task->customer->id,
                'name' => $this->task->customer->name,
            ] : null,
        ];
    }
}
