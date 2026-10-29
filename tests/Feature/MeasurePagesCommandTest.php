<?php

namespace Tests\Feature;

use App\Console\Commands\MeasurePagesCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MeasurePagesCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function daftar_halaman_hanya_memuat_get_staf_tanpa_parameter_dan_tanpa_export(): void
    {
        $this->assertSame(0, Artisan::call('perf:pages', ['--list' => true]));
        $output = Artisan::output();

        $this->assertStringContainsString("\n/\n", "\n".$output);
        $this->assertStringContainsString('/customers', $output);
        $this->assertStringNotContainsString('/api/', $output, 'endpoint api bukan halaman');
        $this->assertStringNotContainsString('{', $output, 'route ber-parameter butuh --with-samples');
        $this->assertDoesNotMatchRegularExpression('#/\S*(export|download)\S*#', $output);
    }

    #[Test]
    public function mode_satu_halaman_mengeluarkan_metrik_json(): void
    {
        $user = $this->loginAsAdmin();

        $this->assertSame(0, Artisan::call('perf:pages', ['--uri' => '/customers', '--user' => $user->email]));

        $this->assertSame(1, preg_match('/PERF_RESULT:(\{.*\})/', Artisan::output(), $m));
        $result = json_decode($m[1], true);

        foreach (['uri', 'status', 'ms', 'peak_mb', 'queries', 'models', 'sql_ms', 'slowest'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
        $this->assertSame('/customers', $result['uri']);
        $this->assertGreaterThan(0, $result['queries']);
    }

    #[Test]
    public function signature_melebur_binding_dan_daftar_in_supaya_n_plus_1_terkelompok(): void
    {
        $a = MeasurePagesCommand::signature("select * from `customers` where `id` = 12 and `x` = 'abc'");
        $b = MeasurePagesCommand::signature("select * from `customers` where `id` = 99 and `x` = 'zzz'");
        $this->assertSame($a, $b);

        $this->assertSame(
            MeasurePagesCommand::signature('select * from t where id in (1, 2, 3)'),
            MeasurePagesCommand::signature('select * from t where id in (4, 5)'),
        );
    }

    #[Test]
    public function hasil_satu_halaman_memuat_kunci_repeated_dan_explain(): void
    {
        $user = $this->loginAsAdmin();
        Artisan::call('perf:pages', ['--uri' => '/customers', '--user' => $user->email]);
        preg_match('/PERF_RESULT:(\{.*\})/', Artisan::output(), $m);
        $result = json_decode($m[1], true);

        $this->assertArrayHasKey('repeated', $result);
        $this->assertArrayHasKey('explain', $result);
    }

    #[Test]
    public function user_tidak_ditemukan_gagal_dengan_jelas(): void
    {
        $this->artisan('perf:pages', ['--uri' => '/customers', '--user' => 'tidak-ada@x.test'])->assertFailed();
        $this->assertSame(0, User::where('email', 'tidak-ada@x.test')->count());
    }
}
