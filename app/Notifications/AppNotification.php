<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * SENGAJA bukan ShouldQueue (`docs/plan/analisa-status-implementasi-
 * notifikasi.md` §6.3/§8) — sebelumnya lewat queue, kalau Horizon down/
 * nge-hang notifikasi ketunda diam-diam tanpa alert ke siapa pun. Volume
 * per panggilan kecil (database insert + 1 broadcast event per penerima,
 * bukan API eksternal), jadi kirim sinkron di request/command yang manggil
 * lebih murah ketimbang ketergantungan availability queue worker buat fitur
 * yang butuh sampai SEKARANG (lonceng notifikasi), bukan nanti.
 */
class AppNotification extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $actionUrl = null,
        public readonly NotificationType $type = NotificationType::INFO
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'type' => $this->type->value,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'type' => $this->type->value,
            'created_at' => now()->toIso8601String(),
            'read_at' => null,
        ]);
    }
}
