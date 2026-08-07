<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sinkron unread count lonceng notifikasi lintas tab — sebelumnya mark-read
 * di tab A gak mendorong update ke tab B (docs/plan/analisa-status-
 * implementasi-notifikasi.md §4 no. 2), masing-masing state Alpine lokal
 * sampai reload manual.
 *
 * `ShouldBroadcastNow` (bukan `ShouldBroadcast`/queue) — konsisten sama
 * keputusan `AppNotification` (§6.3/§8): event UI-sync kecil kayak gini gak
 * boleh nunggu queue worker, telat dikit aja user ngerasa badge-nya "nyangkut".
 *
 * Channel-nya SAMA persis `App.Models.User.{id}` yang dipakai broadcast
 * notifikasi (routes/channels.php) — gak perlu channel baru, klien tinggal
 * `.listen('.NotificationsMarkedRead', …)` di object channel yang sama
 * dengan `.notification(...)`.
 */
class NotificationsMarkedRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $userId,
        public int $unreadCount,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NotificationsMarkedRead';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['unread_count' => $this->unreadCount];
    }
}
