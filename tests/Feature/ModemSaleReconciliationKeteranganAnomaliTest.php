<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TaskCreqDetail;
use App\Models\User;
use App\Services\ModemSaleReconciliationService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ModemSaleReconciliationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\TestCase;

/**
 * Baris Anomali wajib membawa keterangan singkat penyebabnya, supaya admin
 * langsung tahu apa yang dicek (tagihan pelanggan aktif vs keputusan CS).
 * Baris "Menunggu …" tidak diberi keterangan — statusnya sudah menjelaskan.
 */
class ModemSaleReconciliationKeteranganAnomaliTest extends TestCase
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

    /**
     * @return array<string, string|null> SN => keterangan
     */
    private function reasonsBySn(): array
    {
        return app(ModemSaleReconciliationService::class)->rows($this->owner)
            ->mapWithKeys(fn (array $row) => [$row['sale']->inventorySerial->serial_number => $row['reason']])
            ->all();
    }

    #[Test]
    public function anomali_pemasangan_menyebut_status_pelanggan(): void
    {
        $reasons = $this->reasonsBySn();

        // Skenario ke-7 & ke-8 demo = Pemasangan, pelanggan sudah aktif.
        $this->assertSame('Pelanggan sudah Aktif, tapi modem tidak ada di tagihan.', $reasons['REKDEMO-ONT-0007']);
        $this->assertSame('Pelanggan sudah Aktif, tapi modem tidak ada di tagihan.', $reasons['REKDEMO-ONT-0008']);
    }

    #[Test]
    public function anomali_creq_disetujui_cs(): void
    {
        $this->assertSame('C-REQ sudah disetujui CS, tapi modem tidak masuk tagihan.', $this->reasonsBySn()['REKDEMO-ONT-0009']);
    }

    #[Test]
    public function anomali_creq_ditolak_cs(): void
    {
        TaskCreqDetail::query()->where('verification_status', 'verified')->update(['verification_status' => 'rejected']);

        $this->assertSame('C-REQ ditolak CS, padahal modem sudah terpasang.', $this->reasonsBySn()['REKDEMO-ONT-0009']);
    }

    #[Test]
    public function anomali_creq_tidak_ditandai_berbayar(): void
    {
        TaskCreqDetail::query()->where('verification_status', 'verified')->update(['is_billable' => false]);

        $this->assertSame('C-REQ tidak ditandai berbayar, padahal modem dijual.', $this->reasonsBySn()['REKDEMO-ONT-0009']);
    }

    /**
     * verification_status default 'pending', tapi antrean approve CS cuma
     * memuat task berbayar — C-REQ tak berbayar dulu tertahan "Menunggu
     * approve CS" selamanya. Sekarang langsung Anomali.
     */
    #[Test]
    public function creq_tidak_berbayar_yang_masih_pending_jadi_anomali_bukan_menunggu(): void
    {
        TaskCreqDetail::query()->where('verification_status', 'pending')->update(['is_billable' => false]);

        $rows = app(ModemSaleReconciliationService::class)->rows($this->owner)
            ->keyBy(fn (array $row) => $row['sale']->inventorySerial->serial_number);

        $this->assertSame(ModemSaleReconciliationService::STATUS_ANOMALY, $rows['REKDEMO-ONT-0005']['status']);
        $this->assertSame('C-REQ tidak ditandai berbayar, padahal modem dijual.', $rows['REKDEMO-ONT-0005']['reason']);
    }

    #[Test]
    public function baris_menunggu_tanpa_keterangan(): void
    {
        $reasons = $this->reasonsBySn();

        $this->assertNull($reasons['REKDEMO-ONT-0001']); // Menunggu BD
        $this->assertNull($reasons['REKDEMO-ONT-0005']); // Menunggu approve CS
    }

    #[Test]
    public function keterangan_tampil_di_tabel_dan_excel(): void
    {
        $this->actingAs($this->owner)
            ->get(route('reports.modem-sale-reconciliation.index'))
            ->assertSee('Pelanggan sudah Aktif, tapi modem tidak ada di tagihan.');

        $response = $this->actingAs($this->owner)
            ->get(route('reports.modem-sale-reconciliation.export', ['status' => 'anomaly']));
        $rows = SimpleExcelReader::create($response->getFile()->getPathname(), 'xlsx')->getRows()->all();

        $this->assertArrayHasKey('Keterangan', $rows[0]);
        $this->assertNotSame('', $rows[0]['Keterangan']);
    }
}
