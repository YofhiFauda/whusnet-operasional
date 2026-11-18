<?php

namespace Tests\Feature;

use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\ModemSaleReconciliationService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ModemSaleReconciliationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\TestCase;

/**
 * Halaman Rekonsiliasi Modem Terjual: filter (cari, Cabang, Status, Tanggal
 * Jual) + Export Excel yang ikut filter aktif. Data dari
 * ModemSaleReconciliationDemoSeeder (9 sale — Cabang A 5 baris/2 anomali,
 * Cabang B 4 baris/1 anomali).
 */
class ModemSaleReconciliationFilterDanExportExcelTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(ModemSaleReconciliationDemoSeeder::class);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);
    }

    private function index(array $query = [])
    {
        return $this->actingAs($this->owner)->get(route('reports.modem-sale-reconciliation.index', $query));
    }

    #[Test]
    public function ringkasan_tanpa_filter_menghitung_semua_baris_dan_nilai_belum_ditagih(): void
    {
        $summary = $this->index()->viewData('summary');

        $this->assertSame(9, $summary['total']);
        $this->assertSame(3, $summary['anomaly']);
        $this->assertSame(6, $summary['waiting']);
        $this->assertEquals(3850000, $summary['unbilled_amount']);
    }

    #[Test]
    public function filter_cabang(): void
    {
        $cabangA = Pop::where('code', 'REKDEMO-CABANG-A')->firstOrFail();

        $summary = $this->index(['pop_id' => $cabangA->id])->viewData('summary');

        $this->assertSame(5, $summary['total']);
        $this->assertSame(2, $summary['anomaly']);
    }

    #[Test]
    public function filter_status(): void
    {
        $response = $this->index(['status' => ModemSaleReconciliationService::STATUS_ANOMALY]);

        $this->assertSame(3, $response->viewData('summary')['total']);
        $this->assertTrue(collect($response->viewData('rows')->items())->every(fn ($row) => $row['status'] === 'anomaly'));
    }

    #[Test]
    public function filter_status_tak_dikenal_diabaikan(): void
    {
        $this->assertSame(9, $this->index(['status' => 'billed'])->viewData('summary')['total']);
    }

    #[Test]
    public function filter_tanggal_jual(): void
    {
        $summary = $this->index(['date_from' => now()->subDays(5)->toDateString()])->viewData('summary');
        $this->assertSame(6, $summary['total']);

        $summary = $this->index(['date_to' => now()->subDays(10)->toDateString()])->viewData('summary');
        $this->assertSame(2, $summary['total']);
    }

    #[Test]
    public function cari_sn_dan_nama_pelanggan(): void
    {
        $this->assertSame(1, $this->index(['search' => 'REKDEMO-ONT-0007'])->viewData('summary')['total']);
        $this->assertSame(1, $this->index(['search' => 'Rekonsiliasi 3'])->viewData('summary')['total']);
    }

    #[Test]
    public function tabel_memakai_paginasi_dan_pilihan_baris(): void
    {
        $response = $this->index();

        $this->assertSame(9, $response->viewData('rows')->total());
        $response->assertSee('name="per_page"', false);
        $response->assertSee('Menampilkan');
    }

    #[Test]
    public function tampilan_mengikuti_design_system_type_a(): void
    {
        $response = $this->index();

        $response->assertSee('summary-strip', false);
        $response->assertSee('badge badge-error', false);
        $response->assertSee('Export Excel');
        $response->assertDontSee('Ekspor CSV');
        // Card ringkasan lama berlatar merah — dilarang Design.md §1.5
        // (warna status hanya di badge/teks, bukan background section).
        $response->assertDontSee('border-rose-200 bg-rose-50 p-4', false);
    }

    #[Test]
    public function tombol_export_membawa_filter_aktif(): void
    {
        $response = $this->index(['status' => 'anomaly']);

        $response->assertSee(route('reports.modem-sale-reconciliation.export', ['status' => 'anomaly']));
    }

    #[Test]
    public function export_menghasilkan_excel_sesuai_filter(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('reports.modem-sale-reconciliation.export', ['status' => 'anomaly']));

        $response->assertOk();
        $this->assertStringEndsWith('.xlsx', $response->headers->get('content-disposition'));

        $rows = SimpleExcelReader::create($response->getFile()->getPathname(), 'xlsx')->getRows()->all();

        $this->assertCount(3, $rows);
        $this->assertSame(['SN', 'Barang', 'Pelanggan', 'CID', 'Cabang', 'Harga Jual', 'Tanggal Jual', 'Status', 'Keterangan'], array_keys($rows[0]));
        $this->assertSame('Anomali', $rows[0]['Status']);
        $this->assertIsNumeric($rows[0]['Harga Jual']);
    }
}
