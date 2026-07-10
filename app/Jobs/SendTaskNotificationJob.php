<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTaskNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int    $taskId,
        public readonly int    $userId,
        public readonly string $message,
    ) {}

    public function handle(TelegramBotService $telegram): void
    {
        $task = Task::with(['customer', 'pop'])->find($this->taskId);
        $user = User::find($this->userId);

        if (!$task || !$user) {
            Log::warning('SendTaskNotificationJob: task or user not found', [
                'task_id' => $this->taskId,
                'user_id' => $this->userId,
            ]);
            return;
        }

        // Telegram chat ID disimpan di field telegram_chat_id pada tabel users
        // Jika tidak ada, skip (tidak error)
        $chatId = $user->telegram_chat_id ?? null;
        if (!$chatId) {
            return;
        }

        $scheduledAt = $task->scheduled_at
            ? $task->scheduled_at->timezone('Asia/Jakarta')->format('d M Y H:i')
            : '-';

        $text = implode("\n", [
            "📋 <b>{$this->message}</b>",
            "",
            "🔖 <b>Task:</b> [{$task->task_number}] {$task->title}",
            "📌 <b>Tipe:</b> {$task->task_type->label()}",
            "🗓 <b>Jadwal:</b> {$scheduledAt} WIB",
            "🏢 <b>POP:</b> " . ($task->pop->name ?? '-'),
            "👤 <b>Pelanggan:</b> " . ($task->customer?->full_name ?? 'Tidak terkait pelanggan'),
            "🔑 <b>CID:</b> " . ($task->customer?->display_id ?? '-'),
        ]);

        $telegram->sendMessage($text, $chatId);
    }
}
