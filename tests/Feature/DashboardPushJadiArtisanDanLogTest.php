<?php

namespace Tests\Feature;

use App\Enums\PushDeliveryStatus;
use App\Jobs\EscalateIfNotDeliveredJob;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\PushDelivery;
use App\Notifications\AppPushNotification;
use App\Services\WebPushDeliveryTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dashboard Delivery Push Notification (`/webpush/dashboard`) dihapus
 * 2026-11-17 (keputusan user): web push masih tahap uji, delivery rate per
 * merek HP cukup dibaca developer lewat `php artisan webpush:delivery-stats`
 * + file log `storage/logs/webpush-*.log`. `push_deliveries` TETAP dicatat.
 *
 * Skenario hitungan dipindah dari WebPushDashboardTest + bagian dashboard
 * PushTidakNyasarDanDashboardRinganTest (#11: hitung periode terpilih saja).
 */
class DashboardPushJadiArtisanDanLogTest extends TestCase
{
    use RefreshDatabase;

    private function delivery(object $notifiable, PushDeliveryStatus $status, $sentAt = null): PushDelivery
    {
        $sentAt ??= now();

        return PushDelivery::create([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'notification_class' => AppPushNotification::class,
            'status' => $status->value,
            'sent_at' => $sentAt,
            'delivered_at' => $status === PushDeliveryStatus::Delivered ? $sentAt : null,
            'escalated_at' => $status === PushDeliveryStatus::Escalated ? $sentAt : null,
        ]);
    }

    private function useTempLog(): string
    {
        $path = sys_get_temp_dir().'/webpush-test-'.uniqid().'.log';
        config(['logging.channels.webpush' => ['driver' => 'single', 'path' => $path]]);

        return $path;
    }

    #[Test]
    public function halaman_dashboard_delivery_push_sudah_tidak_ada(): void
    {
        $this->loginAsAdmin();

        $this->get('/webpush/dashboard')->assertNotFound();
        $this->assertFalse(app('router')->has('webpush.dashboard'));
        $this->get(route('dashboard'))->assertDontSee('Delivery Push Notification');
    }

    #[Test]
    public function artisan_menghitung_delivery_rate_per_merek(): void
    {
        $owner = $this->loginAsAdmin();
        $owner->updatePushSubscription('https://endpoint-owner', 'pk', 'auth');
        Config::string('webpush.model')::where('endpoint', 'https://endpoint-owner')->update(['device_name' => 'Samsung']);

        $this->delivery($owner, PushDeliveryStatus::Delivered);
        $this->delivery($owner, PushDeliveryStatus::Escalated);

        $this->artisan('webpush:delivery-stats')
            ->expectsTable(
                ['Merek', 'Terkirim', 'Sampai', 'Eskalasi', 'Delivery Rate'],
                [['Samsung', 2, 1, 1, '50%']]
            )
            ->assertSuccessful();
    }

    #[Test]
    public function satu_delivery_dihitung_ke_semua_merek_device_milik_penerima(): void
    {
        $pop = Pop::create(['code' => 'SMN', 'pop_code' => 'SMN', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'POP Sooko', 'type' => 'cabang', 'status' => 'active']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $customer->updatePushSubscription('https://endpoint-a', 'pk', 'auth');
        $customer->updatePushSubscription('https://endpoint-b', 'pk2', 'auth2');
        Config::string('webpush.model')::where('endpoint', 'https://endpoint-a')->update(['device_name' => 'Xiaomi/Redmi/POCO']);
        Config::string('webpush.model')::where('endpoint', 'https://endpoint-b')->update(['device_name' => 'OPPO/realme']);

        $this->delivery($customer, PushDeliveryStatus::Delivered);

        $this->artisan('webpush:delivery-stats')
            ->expectsOutputToContain('Xiaomi/Redmi/POCO')
            ->expectsOutputToContain('OPPO/realme')
            ->assertSuccessful();
    }

    #[Test]
    public function artisan_hanya_menghitung_periode_terpilih(): void
    {
        $owner = $this->loginAsAdmin();
        $owner->updatePushSubscription('https://endpoint-owner', 'pk', 'auth');
        Config::string('webpush.model')::where('endpoint', 'https://endpoint-owner')->update(['device_name' => 'Samsung']);

        $this->delivery($owner, PushDeliveryStatus::Delivered, now()->subDays(2));
        $this->delivery($owner, PushDeliveryStatus::Delivered, now()->subDays(60));

        $this->artisan('webpush:delivery-stats')
            ->expectsTable(['Merek', 'Terkirim', 'Sampai', 'Eskalasi', 'Delivery Rate'], [['Samsung', 1, 1, 0, '100%']])
            ->assertSuccessful();

        $this->artisan('webpush:delivery-stats', ['--days' => 90])
            ->expectsTable(['Merek', 'Terkirim', 'Sampai', 'Eskalasi', 'Delivery Rate'], [['Samsung', 2, 2, 0, '100%']])
            ->assertSuccessful();

        $this->artisan('webpush:delivery-stats', ['--days' => 0])->assertFailed();
    }

    #[Test]
    public function kirim_ack_dan_eskalasi_ditulis_ke_file_log_webpush(): void
    {
        Bus::fake();
        $path = $this->useTempLog();
        $owner = $this->loginAsAdmin();

        $tracker = app(WebPushDeliveryTracker::class);
        $acked = $tracker->start($owner, AppPushNotification::class);
        $this->get($tracker->ackUrl($acked))->assertNoContent();

        $silent = $tracker->start($owner, AppPushNotification::class);
        (new EscalateIfNotDeliveredJob($silent->id))->handle();

        $this->assertSame(PushDeliveryStatus::Delivered, $acked->fresh()->status);
        $this->assertSame(PushDeliveryStatus::Escalated, $silent->fresh()->status);

        $isi = file_get_contents($path);
        $this->assertStringContainsString('Push dikirim', $isi);
        $this->assertStringContainsString('Push sampai ke device', $isi);
        $this->assertStringContainsString('tidak ter-ack dalam 5 menit', $isi);
        $this->assertStringContainsString("\"push_delivery_id\":{$silent->id}", $isi);

        @unlink($path);
    }
}
