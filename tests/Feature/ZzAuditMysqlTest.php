<?php

namespace Tests\Feature;

use App\Console\Commands\Concerns\ParsesLegacySqlDump;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerTechnicalDetail;
use App\Models\ImportError;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// SEMENTARA — audit MySQL strict, dihapus setelah selesai.
class ZzAuditMysqlTest extends TestCase
{
    use ParsesLegacySqlDump;
    use RefreshDatabase;

    public static function dumps(): array
    {
        $d = [
            'caru' => ['docs/database/caru_db_caruban.sql', 'X', 'Caruban'],
            'sand' => ['docs/database/sand_db_sandya.sql', 'D', 'Siman'],
            'samb' => ['docs/database/samb_db_aplikasi_sambit.sql', 'G', 'Sambit'],
            'paci' => ['docs/database/paci_db_aplikasi_pacitan.sql', 'P', 'Pacitan'],
            'jetis' => ['docs/database/jetis_db_aplikasi_jetis.sql', 'C', 'Jetis'],
        ];
        $out = [];
        foreach ($d as $k => $v) {
            $out[$k.'-billing'] = [...$v, false];
            $out[$k.'-nobilling'] = [...$v, true];
        }

        return $out;
    }

    #[DataProvider('dumps')]
    public function test_audit(string $file, string $code, string $name, bool $withoutBilling): void
    {
        ini_set('memory_limit', '3G');
        $this->seed();
        Pop::create(['code' => 'PST', 'pop_code' => 'PST', 'name' => 'Pusat', 'type' => 'pusat', 'status' => 'active', 'registration_prefix' => 'RQ', 'cid_prefix' => 'P']);

        $args = ['file' => $file, '--branch-code' => $code, '--branch-name' => $name, '--pusat-code' => 'PST'];
        if ($withoutBilling) {
            $args['--without-billing'] = true;
        }
        $exit = Artisan::call('app:import-legacy-sql', $args);
        $output = Artisan::output();

        $sql = file_get_contents(base_path($file));
        $peng = $this->parseTableData($sql, 'pengguna');
        $req = $this->parseTableData($sql, 'prosedure_permintaan_wifi');
        $expReq = array_filter($req, fn ($r) => str_starts_with((string) $r['IDPENGGUNA'], 'PE'));
        $expCust = array_unique(array_column($expReq, 'IDPENGGUNA'));

        $line = sprintf(
            "\n[%s %s] exit=%d pelanggan %d/%d layanan %d/%d teknis=%d invoice=%d payment=%d (Rp %s) importErrors=%d\n",
            $name, $withoutBilling ? 'TANPA-BILLING' : 'BILLING', $exit,
            Customer::count(), count($expCust), CustomerService::count(), count($expReq),
            CustomerTechnicalDetail::count(), Invoice::count(), Payment::count(), Payment::sum('amount'), ImportError::count()
        );
        if ($exit !== 0) {
            $line .= collect(explode("\n", $output))->filter(fn ($l) => str_contains($l, 'Error') || str_contains($l, 'GAGAL') || str_contains($l, 'Exception'))->implode("\n")."\n";
        }
        $line .= collect(explode("\n", $output))->filter(fn ($l) => str_contains($l, 'DITOLAK') || str_starts_with(trim($l), '- ['))->implode("\n")."\n";
        file_put_contents(storage_path('framework/testing/audit-mysql.txt'), $line, FILE_APPEND);
        fwrite(STDERR, $line);
        $this->assertTrue(true);
    }
}
