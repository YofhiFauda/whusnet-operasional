<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `notifications:prune-read` — retensi database_notifications yang sebelumnya
 * gak diatur sama sekali (docs/plan/analisa-status-implementasi-notifikasi.md §6.2).
 */
class PruneReadNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeNotification(User $user, ?Carbon $readAt): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\AppNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => 'Test', 'message' => 'Test'],
            'read_at' => $readAt,
            'created_at' => $readAt ?? now(),
            'updated_at' => $readAt ?? now(),
        ]);
    }

    public function test_deletes_read_notifications_older_than_retention(): void
    {
        $user = User::factory()->create();

        $old = $this->makeNotification($user, now()->subDays(100));
        $recent = $this->makeNotification($user, now()->subDays(10));
        $unread = $this->makeNotification($user, null);

        $this->artisan('notifications:prune-read', ['--days' => 90])->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $old->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recent->id]);
        $this->assertDatabaseHas('notifications', ['id' => $unread->id]);
    }

    public function test_custom_days_option_is_honored(): void
    {
        $user = User::factory()->create();

        $notif = $this->makeNotification($user, now()->subDays(5));

        $this->artisan('notifications:prune-read', ['--days' => 3])->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $notif->id]);
    }
}
