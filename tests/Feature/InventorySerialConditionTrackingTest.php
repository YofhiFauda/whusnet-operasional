<?php

namespace Tests\Feature;

use App\Enums\ItemCondition;
use App\Enums\SerialStatus;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryIssueService;
use App\Services\InventoryReassignService;
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
 * docs/plan/warehouse/analisa-gap-kondisi-barang.md — kondisi fisik unit
 * SERIALIZED (Baru/Bekas-Kondisi Baik/Bekas-Rusak), gate Issue buat SN bekas
 * yang belum dicek, aksi "Sudah Dicek", dan `resulting_status` snapshot di
 * ledger ADJUSTMENT.
 */
class InventorySerialConditionTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

    private Item $modem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);

        $this->pusat = Pop::create(['code' => 'CD-PUSAT', 'pop_code' => 'CDP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Kondisi Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'CD-CABANG', 'pop_code' => 'CDC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Kondisi Test', 'type' => 'cabang', 'status' => 'active']);

        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $this->modem = Item::create(['code' => 'CD-MODEM', 'name' => 'Modem Kondisi Test', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
    }

    #[Test]
    public function receive_serialized_otomatis_kondisi_baru(): void
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['CD-SN-001'], 250000, $this->owner);

        $this->assertEquals(ItemCondition::NEW, $serial->condition);
        $this->assertNull($serial->condition_checked_at);
        $this->assertTrue($serial->isClearedForIssue());
    }

    #[Test]
    public function retrieve_dari_pelanggan_set_kondisi_bekas_belum_dicek(): void
    {
        $serial = $this->makeInstalledSerial('CD-SN-002');

        $txn = app(InventoryReassignService::class)->returnInstalledSerialFromCustomer($serial, 'putus_langganan_deac', $this->owner);

        $serial->refresh();
        $this->assertEquals(ItemCondition::USED_GOOD, $serial->condition);
        $this->assertNull($serial->condition_checked_at);
        $this->assertNull($serial->condition_checked_by);
        $this->assertFalse($serial->isClearedForIssue());
        $this->assertEquals(SerialStatus::AVAILABLE, $serial->status);
        $this->assertEquals($this->cabang->id, $txn->to_pop_id);
    }

    #[Test]
    public function issue_menolak_sn_bekas_yang_belum_dicek(): void
    {
        $serial = $this->makeInstalledSerial('CD-SN-003');
        app(InventoryReassignService::class)->returnInstalledSerialFromCustomer($serial, 'putus_langganan_deac', $this->owner);
        $serial->refresh();

        $teknisi = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('belum dicek fisik');

        app(InventoryIssueService::class)->issue($this->cabang, $teknisi, [
            ['item_id' => $this->modem->id, 'serial_numbers' => ['CD-SN-003']],
        ], $this->owner);
    }

    #[Test]
    public function sudah_dicek_melepas_gate_dan_issue_berhasil(): void
    {
        $serial = $this->makeInstalledSerial('CD-SN-004');
        app(InventoryReassignService::class)->returnInstalledSerialFromCustomer($serial, 'putus_langganan_deac', $this->owner);
        $serial->refresh();

        $checker = User::factory()->create();
        app(InventoryReassignService::class)->markSerialConditionChecked($serial, ItemCondition::USED_GOOD, $checker);

        $serial->refresh();
        $this->assertEquals(ItemCondition::USED_GOOD, $serial->condition);
        $this->assertNotNull($serial->condition_checked_at);
        $this->assertEquals($checker->id, $serial->condition_checked_by);
        $this->assertTrue($serial->isClearedForIssue());

        $teknisi = User::factory()->create();
        $transactions = app(InventoryIssueService::class)->issue($this->cabang, $teknisi, [
            ['item_id' => $this->modem->id, 'serial_numbers' => ['CD-SN-004']],
        ], $this->owner);

        $this->assertCount(1, $transactions);
        $serial->refresh();
        $this->assertEquals(SerialStatus::ISSUED, $serial->status);
    }

    #[Test]
    public function sudah_dicek_bisa_menandai_rusak_dan_tetap_tidak_lolos_issue_kalau_ditolak_manual(): void
    {
        $serial = $this->makeInstalledSerial('CD-SN-005');
        app(InventoryReassignService::class)->returnInstalledSerialFromCustomer($serial, 'putus_langganan_deac', $this->owner);
        $serial->refresh();

        app(InventoryReassignService::class)->markSerialConditionChecked($serial, ItemCondition::USED_DAMAGED, $this->owner);

        $serial->refresh();
        $this->assertEquals(ItemCondition::USED_DAMAGED, $serial->condition);
        $this->assertNotNull($serial->condition_checked_at);
        // Sudah dicek — gate Issue lepas (nilai kondisi bukan penentu gate,
        // itu keputusan staf lewat aksi ini, lihat docblock isClearedForIssue()).
        $this->assertTrue($serial->isClearedForIssue());
    }

    #[Test]
    public function sudah_dicek_menolak_hasil_kondisi_baru(): void
    {
        $serial = $this->makeInstalledSerial('CD-SN-006');
        app(InventoryReassignService::class)->returnInstalledSerialFromCustomer($serial, 'putus_langganan_deac', $this->owner);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryReassignService::class)->markSerialConditionChecked($serial, ItemCondition::NEW, $this->owner);
    }

    #[Test]
    public function sudah_dicek_menolak_sn_yang_masih_kondisi_baru(): void
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['CD-SN-007'], 250000, $this->owner);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('gak ada yang perlu dicek');

        app(InventoryReassignService::class)->markSerialConditionChecked($serial, ItemCondition::USED_GOOD, $this->owner);
    }

    #[Test]
    public function adjust_serial_status_menyimpan_resulting_status(): void
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['CD-SN-008'], 250000, $this->owner);

        $txn = app(InventoryAdjustmentService::class)->adjustSerialStatus(
            $serial, SerialStatus::DAMAGED, 'jatuh_kena_air', $this->owner, null, 'warehouse/evidence/damaged/x.jpg'
        );

        $this->assertEquals('damaged', $txn->resulting_status);
    }

    #[Test]
    public function endpoint_sudah_dicek_mengubah_kondisi_dan_redirect_ke_traceability(): void
    {
        $serial = $this->makeInstalledSerial('CD-SN-009');
        app(InventoryReassignService::class)->returnInstalledSerialFromCustomer($serial, 'putus_langganan_deac', $this->owner);
        $serial->refresh();

        $response = $this->actingAs($this->owner)->post(route('warehouse.traceability.serial.condition-check', $serial), [
            'condition' => 'used_good',
        ]);

        $response->assertRedirect(route('warehouse.traceability.index', ['sn' => 'CD-SN-009']));
        $serial->refresh();
        $this->assertEquals(ItemCondition::USED_GOOD, $serial->condition);
        $this->assertNotNull($serial->condition_checked_at);
        $this->assertEquals($this->owner->id, $serial->condition_checked_by);
    }

    #[Test]
    public function endpoint_sudah_dicek_menolak_input_kondisi_tidak_valid(): void
    {
        $serial = $this->makeInstalledSerial('CD-SN-010');
        app(InventoryReassignService::class)->returnInstalledSerialFromCustomer($serial, 'putus_langganan_deac', $this->owner);
        $serial->refresh();

        $response = $this->actingAs($this->owner)->post(route('warehouse.traceability.serial.condition-check', $serial), [
            'condition' => 'new',
        ]);

        $response->assertSessionHasErrors('condition');
    }

    #[Test]
    public function traceability_menampilkan_badge_kondisi_dan_tombol_sudah_dicek(): void
    {
        $serial = $this->makeInstalledSerial('CD-SN-011');
        app(InventoryReassignService::class)->returnInstalledSerialFromCustomer($serial, 'putus_langganan_deac', $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['sn' => 'CD-SN-011']));

        $response->assertOk()
            ->assertSee('Bekas — Belum Dicek')
            ->assertSee('Tandai Sudah Dicek');
    }

    #[Test]
    public function riwayat_mutasi_filter_kondisi_bekas_belum_dicek(): void
    {
        [$serialBaru] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['CD-SN-012'], 250000, $this->owner);
        $serialBekas = $this->makeInstalledSerial('CD-SN-013');
        app(InventoryReassignService::class)->returnInstalledSerialFromCustomer($serialBekas, 'putus_langganan_deac', $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.history.index', ['condition' => 'unchecked']));

        $response->assertOk()
            ->assertSee('CD-SN-013')
            ->assertDontSee('CD-SN-012');
    }

    #[Test]
    public function riwayat_mutasi_filter_alasan_adjustment_by_resulting_status(): void
    {
        [$serialLost] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['CD-SN-014'], 250000, $this->owner);
        [$serialQuarantine] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['CD-SN-015'], 250000, $this->owner);

        app(InventoryAdjustmentService::class)->adjustSerialStatus($serialLost, SerialStatus::LOST, 'hilang', $this->owner, null, 'warehouse/evidence/lost/x.jpg');
        app(InventoryAdjustmentService::class)->adjustSerialStatus($serialQuarantine, SerialStatus::QUARANTINE, 'cek_dulu', $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.history.index', ['type' => 'adjustment', 'adjustment_reason' => 'lost']));

        $response->assertOk()
            ->assertSee('CD-SN-014')
            ->assertDontSee('CD-SN-015');
    }

    #[Test]
    public function views_handle_null_condition_gracefully(): void
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['CD-SN-NULL-01'], 250000, $this->owner);

        // Simulasi data legacy / baris dengan condition null di DB
        InventorySerial::where('id', $serial->id)->update(['condition' => null]);
        $serial->refresh();

        $this->assertNull($serial->condition);
        $this->assertTrue($serial->isClearedForIssue());

        // 1. Traceability view
        $traceabilityRes = $this->actingAs($this->owner)->get(route('warehouse.traceability.index', ['sn' => 'CD-SN-NULL-01']));
        $traceabilityRes->assertOk()->assertSee('Baru');

        // 2. History view
        $historyRes = $this->actingAs($this->owner)->get(route('warehouse.history.index'));
        $historyRes->assertOk()->assertSee('CD-SN-NULL-01');

        // 3. Custody view
        $teknisi = User::factory()->create();
        $serial->update([
            'status' => SerialStatus::ISSUED,
            'current_technician_id' => $teknisi->id,
            'issued_from_pop_id' => $this->cabang->id,
            'current_pop_id' => null,
        ]);
        $custodyRes = $this->actingAs($this->owner)->get(route('warehouse.custody.index'));
        $custodyRes->assertOk()->assertSee('CD-SN-NULL-01');
    }

    private function makeInstalledSerial(string $serialNumber): InventorySerial
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, [$serialNumber], 250000, $this->owner);

        // Simulasi SN yang sudah lewat Transfer→Issue→Install sampai
        // INSTALLED di pelanggan — cukup manipulasi state langsung (unit
        // test method ini sendiri, bukan alur install penuh lewat FopTask/
        // storeSpeedtest() yang di luar cakupan test ini).
        $serial->update([
            'status' => SerialStatus::INSTALLED,
            'issued_from_pop_id' => $this->cabang->id,
            'current_pop_id' => null,
        ]);

        return $serial;
    }
}
