<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryReceiveService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fase 2 Gudang, Prioritas 1 — bukti fisik wajib untuk klaim kerugian
 * (kontrol-anti-manipulasi.md §2, fase-2-adaptasi-wms.md P1). Guard
 * ditegakkan di SERVICE (`InventoryAdjustmentService`), bukan cuma
 * validasi UI — request langsung ke Service tanpa lewat form tetap harus
 * ditolak kalau evidence kosong.
 */
class InventoryAdjustmentEvidenceRequiredTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Item $modem;

    private Item $kabel;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed penuh (bukan cuma RoleSeeder) — pola ini pure-Service test,
        // tapi kelas ini dipanggil di full-suite run bareng test lain yang
        // urutan/state-nya gak bisa diasumsikan; seed eksplisit biar gak
        // gantung ke seed ambient dari test lain (lihat catatan sesi
        // sebelumnya soal role seeding tidak konsisten antar run).
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);

        $this->pusat = Pop::create(['code' => 'EV-PUSAT', 'pop_code' => 'EVP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Evidence Test', 'type' => 'pusat', 'status' => 'active']);

        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $this->modem = Item::create(['code' => 'EV-MODEM', 'name' => 'Modem Evidence Test', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'EV-KABEL', 'name' => 'Kabel Evidence Test', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
    }

    #[Test]
    public function serial_ke_lost_tanpa_evidence_ditolak(): void
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['EV-SN-001'], 250000, $this->owner);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bukti fisik');

        app(InventoryAdjustmentService::class)->adjustSerialStatus($serial, SerialStatus::LOST, 'hilang_di_lapangan', $this->owner);
    }

    #[Test]
    public function serial_ke_damaged_tanpa_evidence_ditolak(): void
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['EV-SN-002'], 250000, $this->owner);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryAdjustmentService::class)->adjustSerialStatus($serial, SerialStatus::DAMAGED, 'jatuh_kena_air', $this->owner);
    }

    #[Test]
    public function serial_ke_scrapped_tanpa_evidence_ditolak(): void
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['EV-SN-003'], 250000, $this->owner);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryAdjustmentService::class)->adjustSerialStatus($serial, SerialStatus::SCRAPPED, 'mati_total', $this->owner);
    }

    #[Test]
    public function serial_ke_lost_dengan_evidence_berhasil(): void
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['EV-SN-004'], 250000, $this->owner);

        $txn = app(InventoryAdjustmentService::class)->adjustSerialStatus(
            $serial, SerialStatus::LOST, 'hilang_di_lapangan', $this->owner, null, 'warehouse/evidence/lost/lost_20260903_120000.jpg'
        );

        $this->assertEquals('warehouse/evidence/lost/lost_20260903_120000.jpg', $txn->evidence_file_path);
        $serial->refresh();
        $this->assertEquals(SerialStatus::LOST, $serial->status);
    }

    #[Test]
    public function serial_ke_quarantine_tanpa_evidence_tetap_boleh(): void
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['EV-SN-005'], 250000, $this->owner);

        $txn = app(InventoryAdjustmentService::class)->adjustSerialStatus($serial, SerialStatus::QUARANTINE, 'cek_kondisi_dulu', $this->owner);

        $this->assertNull($txn->evidence_file_path);
        $serial->refresh();
        $this->assertEquals(SerialStatus::QUARANTINE, $serial->status);
    }

    private function makeCustody(float $qty = 50): TechnicianCustody
    {
        $technician = User::factory()->create();

        return TechnicianCustody::create([
            'technician_id' => $technician->id,
            'issued_from_pop_id' => $this->pusat->id,
            'item_id' => $this->kabel->id,
            'lot_no' => null,
            'qty_remaining' => $qty,
            'unit_price_snapshot' => 5000,
            'status' => 'issued',
            'issued_at' => now(),
        ]);
    }

    #[Test]
    public function custody_reason_lost_tanpa_evidence_ditolak(): void
    {
        $custody = $this->makeCustody();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bukti fisik');

        app(InventoryAdjustmentService::class)->adjustCustody($custody, -10, 'lost', $this->owner);
    }

    #[Test]
    public function custody_reason_damaged_tanpa_evidence_ditolak(): void
    {
        $custody = $this->makeCustody();

        $this->expectException(InvalidArgumentException::class);

        app(InventoryAdjustmentService::class)->adjustCustody($custody, -5, 'damaged', $this->owner);
    }

    #[Test]
    public function custody_reason_quarantine_tanpa_evidence_tetap_boleh(): void
    {
        $custody = $this->makeCustody();

        $txn = app(InventoryAdjustmentService::class)->adjustCustody($custody, -5, 'quarantine', $this->owner);

        $this->assertNull($txn->evidence_file_path);
    }

    #[Test]
    public function custody_reason_diluar_kategori_ditolak(): void
    {
        $custody = $this->makeCustody();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tidak dikenal');

        app(InventoryAdjustmentService::class)->adjustCustody($custody, -5, 'ngarang_bebas', $this->owner);
    }

    #[Test]
    public function custody_reason_lost_dengan_evidence_berhasil(): void
    {
        $custody = $this->makeCustody();

        $txn = app(InventoryAdjustmentService::class)->adjustCustody($custody, -10, 'lost', $this->owner, null, 'warehouse/evidence/lost/lost_20260903_130000.jpg');

        $this->assertEquals('warehouse/evidence/lost/lost_20260903_130000.jpg', $txn->evidence_file_path);
        $custody->refresh();
        $this->assertEquals(40, $custody->qty_remaining);
    }
}
