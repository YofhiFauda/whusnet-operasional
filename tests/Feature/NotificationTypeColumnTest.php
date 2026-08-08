<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 5.2 — kolom nyata `notification_type` menggantikan filter
 * `where('data->type', ...)` di atas kolom TEXT (full-scan + parse JSON).
 *
 * Menjaga: (a) notifikasi baru mengisi kolom otomatis via creating hook,
 * (b) command backfill mengisi data lama, (c) filter kolom bekerja.
 */
class NotificationTypeColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifikasi_baru_mengisi_notification_type_dari_data(): void
    {
        $user = User::factory()->create();

        $user->notify(new AppNotification('Judul', 'Pesan', null, NotificationType::WARNING));

        $notif = DatabaseNotification::query()->firstOrFail();
        $this->assertSame('warning', $notif->notification_type);
        // Sinkron dengan data['type'] (sumber).
        $this->assertSame($notif->data['type'], $notif->notification_type);
    }

    public function test_backfill_mengisi_kolom_dari_data_untuk_notif_lama(): void
    {
        $user = User::factory()->create();

        // Simulasi baris lama: notification_type NULL, type tersimpan di data.
        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => AppNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => 'x', 'type' => 'error'],
            'notification_type' => null,
        ]);

        $this->artisan('notifications:backfill-type')->assertSuccessful();

        $this->assertSame('error', DatabaseNotification::query()->firstOrFail()->notification_type);
    }

    public function test_filter_by_type_pakai_kolom(): void
    {
        $user = User::factory()->create();
        $user->notify(new AppNotification('A', 'a', null, NotificationType::ERROR));
        $user->notify(new AppNotification('B', 'b', null, NotificationType::INFO));

        $this->assertSame(1, DatabaseNotification::query()->where('notification_type', 'error')->count());
        $this->assertSame(1, DatabaseNotification::query()->where('notification_type', 'info')->count());
    }
}
