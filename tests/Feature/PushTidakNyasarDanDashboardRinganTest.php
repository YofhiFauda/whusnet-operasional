<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\AppPushNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Temuan review ADHOC-297 seputar Web Push:
 *  #1  SW menahan notifikasi OS cuma karena ADA tab terlihat — padahal tab
 *      login/sesi habis/Reverb putus/akun lain tidak menampilkan toast ⇒
 *      notifikasi hilang total. Sekarang tab ditanya dulu.
 *  #2  Klik notifikasi me-navigate() tab lain ⇒ isian belum terkirim hilang.
 *  #9  Logout tidak melepas subscription ⇒ push akun lama tampil di
 *      perangkat bersama.
 *  #11 Dashboard delivery memuat seluruh push_deliveries sepanjang masa.
 *      (Halaman dashboard dihapus 2026-11-17 — penjaga periodenya pindah ke
 *      DashboardPushJadiArtisanDanLogTest untuk `webpush:delivery-stats`.)
 *  #12 URL POST webpush dirakit klien, bukan dari route().
 */
class PushTidakNyasarDanDashboardRinganTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function service_worker_bertanya_ke_tab_sebelum_menahan_notifikasi_os(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("client.postMessage({ type: 'whusnet-push-check', uid: uid }, [channel.port2]);", $sw);
        $this->assertStringContainsString('if (answers.some(Boolean)) {', $sw);
        // Tanpa jawaban ⇒ anggap tidak sanggup ⇒ notifikasi OS tampil.
        $this->assertStringContainsString('setTimeout(function () { resolve(false); }, 1000);', $sw);
        $this->assertStringNotContainsString('.navigate(', $sw);

        $js = file_get_contents(resource_path('js/webpush.js'));
        $this->assertStringContainsString("event.data.type !== 'whusnet-push-check'", $js);
        $this->assertStringContainsString("state === 'connected'", $js);
        $this->assertStringContainsString('window.__notificationEchoBound === userId', $js);
    }

    #[Test]
    public function payload_push_staf_membawa_id_penerima(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->firstOrFail()->id]);

        $message = (new AppPushNotification('Judul', 'Pesan', '/tasks/1'))->toWebPush($user, null);

        $this->assertSame($user->id, $message->toArray()['data']['uid']);
    }

    #[Test]
    public function url_webpush_dari_route_bukan_dirakit_klien(): void
    {
        $js = file_get_contents(resource_path('js/webpush.js'));
        $this->assertDoesNotMatchRegularExpression("#fetch\\('/webpush/#", $js);

        $this->loginAsAdmin();
        $this->get(route('dashboard'))
            ->assertSee('window.WebPushRoutes', false)
            ->assertSee(json_encode(route('webpush.subscribe')), false);
    }

    #[Test]
    public function logout_melepas_subscription_browser_ini_dari_akun_yang_keluar(): void
    {
        $admin = $this->loginAsAdmin();
        $admin->updatePushSubscription('https://push.example.test/perangkat-bersama', 'pk', 'auth');
        $admin->updatePushSubscription('https://push.example.test/hp-pribadi', 'pk', 'auth');

        $this->post(route('logout'), ['push_endpoint' => 'https://push.example.test/perangkat-bersama'])
            ->assertRedirect(route('login'));

        $endpoints = $admin->pushSubscriptions()->pluck('endpoint')->all();
        $this->assertSame(['https://push.example.test/hp-pribadi'], $endpoints, 'Perangkat lain milik user tetap terdaftar.');
    }

    #[Test]
    public function logout_tidak_bisa_menghapus_subscription_user_lain(): void
    {
        $this->seed(RoleSeeder::class);
        $lain = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->firstOrFail()->id]);
        $lain->updatePushSubscription('https://push.example.test/milik-orang-lain', 'pk', 'auth');

        $this->loginAsAdmin();
        $this->post(route('logout'), ['push_endpoint' => 'https://push.example.test/milik-orang-lain']);

        $this->assertSame(1, $lain->pushSubscriptions()->count());
    }

    #[Test]
    public function form_logout_membawa_field_endpoint(): void
    {
        $this->loginAsAdmin();

        $this->get(route('dashboard'))
            ->assertSee('<input type="hidden" name="push_endpoint" value="">', false);
    }

    #[Test]
    public function realtime_dan_interval_halaman_tidak_mati_atau_bocor(): void
    {
        foreach (['tasks/own.blade.php' => '__technicianEchoBound', 'components/notification-dropdown.blade.php' => '__notificationEchoBound'] as $view => $flag) {
            $source = file_get_contents(resource_path("views/{$view}"));

            // #3: flag dipasang di dalam attach() setelah Echo pasti ada,
            // dan Echo yang belum siap ditunggu lewat echo:ready (tanpa batas 3 detik).
            $this->assertMatchesRegularExpression(
                "/const attach = \\(\\) => \\{\\s*if \\(window\\.{$flag} === userId \\|\\| typeof window\\.Echo === 'undefined'\\) \\{\\s*return;\\s*\\}\\s*window\\.{$flag} = userId;/",
                $source,
                $view
            );
            $this->assertStringContainsString("window.addEventListener('echo:ready', attach, { once: true });", $source, $view);
            $this->assertStringNotContainsString('maxAttempts', $source, $view);
        }

        // #4: interval halaman berhenti sendiri saat halaman ditinggal.
        $this->assertStringContainsString('window.pageInterval = function (fn, ms) {', file_get_contents(resource_path('views/layouts/app.blade.php')));
        $this->assertStringContainsString('window.pageInterval(updateCountdowns, 1000)', file_get_contents(resource_path('views/verifications/queue.blade.php')));
        $this->assertStringContainsString('window.__psbReportTimer = window.pageInterval(', file_get_contents(resource_path('views/installations/report.blade.php')));
        $this->assertStringContainsString('destroy() {', file_get_contents(resource_path('views/components/countdown-timer.blade.php')));
    }
}
