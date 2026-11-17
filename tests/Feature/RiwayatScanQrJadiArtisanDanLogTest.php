<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\QrScanLog;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerQrTokenService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\QrFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Halaman Riwayat Scan QR (`/qr-scan-logs`) dihapus 2026-11-17 (keputusan
 * user): absen QR masih tahap uji, datanya cukup dibaca developer lewat
 * `php artisan qr:scan-logs` + file log `storage/logs/qr-scan-*.log`.
 * Tabel `qr_scan_logs` TETAP dicatat — yang hilang cuma UI-nya.
 */
class RiwayatScanQrJadiArtisanDanLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function halaman_riwayat_scan_qr_sudah_tidak_ada(): void
    {
        $this->loginAsAdmin();

        $this->get('/qr-scan-logs')->assertNotFound();
        $this->assertFalse(app('router')->has('qr-scan-logs.index'));
        $this->get(route('dashboard'))->assertDontSee('Riwayat Scan QR');
    }

    #[Test]
    public function permission_qr_scan_logs_tidak_lagi_digenerate_seeder(): void
    {
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(QrFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertDatabaseMissing('features', ['code' => 'qr_scan_logs']);
        $this->assertDatabaseMissing('permissions', ['code' => 'qr_scan_logs.view']);
        // Fitur QR lain tidak ikut tersapu.
        $this->assertDatabaseHas('features', ['code' => 'tasks.qr_attendance']);
        $this->assertDatabaseHas('features', ['code' => 'qr_scan']);
    }

    #[Test]
    public function migration_menghapus_feature_permission_dan_grant_role_kedua_halaman(): void
    {
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $viewActionId = Action::where('code', 'view')->value('id');
        $atasan = Role::where('code', 'atasan')->firstOrFail();

        foreach (['qr_scan_logs', 'webpush_dashboard'] as $code) {
            $feature = Feature::create(['code' => $code, 'name' => $code, 'type' => 'root', 'sort_order' => 99, 'is_active' => true]);
            $permission = Permission::create(['feature_id' => $feature->id, 'action_id' => $viewActionId, 'code' => "{$code}.view", 'name' => $code]);
            DB::table('role_permissions')->insert(['role_id' => $atasan->id, 'permission_id' => $permission->id]);
        }

        $migrationPath = glob(database_path('migrations/*_remove_qr_scan_logs_and_webpush_dashboard_features.php'))[0];
        (require $migrationPath)->up();

        foreach (['qr_scan_logs', 'webpush_dashboard'] as $code) {
            $this->assertDatabaseMissing('features', ['code' => $code]);
            $this->assertDatabaseMissing('permissions', ['code' => "{$code}.view"]);
        }
        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $atasan->id)->count());
    }

    #[Test]
    public function artisan_menampilkan_scan_gagal_dan_berhasil_dalam_periode(): void
    {
        $customer = Customer::factory()->create(['pop_id' => Pop::create(['code' => 'QRA', 'name' => 'POP QR A', 'type' => 'cabang', 'status' => 'active'])->id]);
        $teknisi = User::factory()->create(['name' => 'Teknisi Lokal']);

        QrScanLog::create(['customer_id' => $customer->id, 'user_id' => $teknisi->id, 'purpose' => 'attendance', 'result' => 'out_of_radius', 'distance_meters' => 820, 'scanned_at' => now()]);
        QrScanLog::create(['customer_id' => $customer->id, 'user_id' => $teknisi->id, 'purpose' => 'attendance', 'result' => 'success', 'reason' => 'perlu_review', 'distance_meters' => 210, 'scanned_at' => now()]);
        QrScanLog::create(['customer_id' => $customer->id, 'user_id' => $teknisi->id, 'purpose' => 'attendance', 'result' => 'bad_signature', 'scanned_at' => now()->subDays(10)]);

        $hariIni = $this->runCommand();
        $this->assertStringContainsString('Teknisi Lokal', $hariIni);
        $this->assertStringContainsString('out_of_radius', $hariIni);
        $this->assertStringContainsString('perlu_review', $hariIni);
        $this->assertStringContainsString('820', $hariIni);
        $this->assertStringNotContainsString('bad_signature', $hariIni);

        $this->assertStringContainsString('bad_signature', $this->runCommand(['--days' => 30]));
    }

    /**
     * Baca output utuh, bukan `expectsOutputToContain()`: mock itu
     * mengonsumsi satu baris output per ekspektasi, padahal satu baris tabel
     * memuat beberapa teks yang mau dicek sekaligus.
     *
     * @param  array<string, mixed>  $options
     */
    private function runCommand(array $options = [], int $expectedExit = 0): string
    {
        $exit = Artisan::call('qr:scan-logs', $options);
        $this->assertSame($expectedExit, $exit);

        return Artisan::output();
    }

    #[Test]
    public function artisan_bisa_difilter_per_hasil_dan_per_teknisi(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Teknisi', 'email' => 'budi@example.test']);
        $andi = User::factory()->create(['name' => 'Andi Teknisi']);

        QrScanLog::create(['user_id' => $budi->id, 'purpose' => 'attendance', 'result' => 'out_of_radius', 'scanned_at' => now()]);
        QrScanLog::create(['user_id' => $budi->id, 'purpose' => 'attendance', 'result' => 'success', 'scanned_at' => now()]);
        QrScanLog::create(['user_id' => $andi->id, 'purpose' => 'attendance', 'result' => 'out_of_radius', 'scanned_at' => now()]);

        $output = $this->runCommand(['--result' => 'out_of_radius', '--user' => 'budi@example.test']);
        $this->assertStringContainsString('Budi Teknisi', $output);
        $this->assertStringNotContainsString('Andi Teknisi', $output);
        $this->assertStringNotContainsString('success', $output);

        $this->assertStringContainsString('Andi Teknisi', $this->runCommand(['--user' => (string) $andi->id]));

        $this->assertStringContainsString(
            'User tidak ditemukan',
            $this->runCommand(['--user' => 'tidak-ada@example.test'], expectedExit: 1)
        );
    }

    #[Test]
    public function setiap_scan_yang_dicatat_juga_ditulis_ke_file_log_qr(): void
    {
        $path = sys_get_temp_dir().'/qr-scan-test-'.uniqid().'.log';
        config(['logging.channels.qr_scan' => ['driver' => 'single', 'path' => $path]]);

        $teknisi = User::factory()->create();
        app(CustomerQrTokenService::class)->recordScan([
            'user_id' => $teknisi->id,
            'purpose' => 'attendance',
            'result' => 'out_of_radius',
            'distance_meters' => 640,
        ]);

        $this->assertDatabaseHas('qr_scan_logs', ['user_id' => $teknisi->id, 'result' => 'out_of_radius']);
        $isi = file_get_contents($path);
        $this->assertStringContainsString('out_of_radius', $isi);
        $this->assertStringContainsString('"distance_meters":640', $isi);
        $this->assertStringContainsString('WARNING', $isi);

        @unlink($path);
    }
}
