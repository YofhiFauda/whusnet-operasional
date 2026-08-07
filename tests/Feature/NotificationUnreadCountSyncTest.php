<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Events\NotificationsMarkedRead;
use App\Models\User;
use App\Notifications\AppNotification;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Optimasi §4 no. 1 (cache unread count) & no. 2 (sinkron antar tab) —
 * docs/plan/analisa-status-implementasi-notifikasi.md §8.
 */
class NotificationUnreadCountSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->user = $this->loginAsAdmin();
    }

    public function test_unread_count_is_cached(): void
    {
        $this->user->notify(new AppNotification('Judul', 'Pesan', type: NotificationType::INFO));

        $this->assertSame(1, $this->user->unreadNotificationsCountCached());
        $this->assertTrue(Cache::has("user.{$this->user->id}.unread_notifications_count"));

        // Notif baru masuk TANPA lewat clearUnreadNotificationsCountCache() —
        // cache lama tetap kepakai sampai TTL habis (by design, lihat
        // docblock User::unreadNotificationsCountCached()).
        $this->user->notify(new AppNotification('Judul 2', 'Pesan 2', type: NotificationType::INFO));
        $this->assertSame(1, $this->user->unreadNotificationsCountCached());
    }

    public function test_mark_as_read_clears_cache_and_broadcasts_sync_event(): void
    {
        $this->user->notify(new AppNotification('Judul', 'Pesan', type: NotificationType::INFO));
        $this->user->unreadNotificationsCountCached(); // warm the cache
        $this->assertTrue(Cache::has("user.{$this->user->id}.unread_notifications_count"));

        $notification = DatabaseNotification::first();

        Event::fake([NotificationsMarkedRead::class]);

        $this->postJson("/notifications/{$notification->id}/read")->assertOk();

        // Cache dibersihkan LALU di-warm ulang sekalian (buat dapetin angka
        // fresh yang dikirim ke event broadcast) — jadi yang dites di sini
        // bukan "cache kosong", tapi "cache-nya udah gak nyimpen angka basi".
        $this->assertSame(0, $this->user->unreadNotificationsCountCached());

        Event::assertDispatched(
            NotificationsMarkedRead::class,
            fn ($event) => $event->userId === $this->user->id && $event->unreadCount === 0
        );
    }

    public function test_mark_all_as_read_clears_cache_and_broadcasts_sync_event(): void
    {
        $this->user->notify(new AppNotification('Judul 1', 'Pesan 1', type: NotificationType::INFO));
        $this->user->notify(new AppNotification('Judul 2', 'Pesan 2', type: NotificationType::INFO));
        $this->user->unreadNotificationsCountCached();

        Event::fake([NotificationsMarkedRead::class]);

        $this->postJson('/notifications/mark-all-read')->assertOk();

        $this->assertSame(0, $this->user->unreadNotificationsCountCached());

        Event::assertDispatched(
            NotificationsMarkedRead::class,
            fn ($event) => $event->userId === $this->user->id && $event->unreadCount === 0
        );
    }
}
