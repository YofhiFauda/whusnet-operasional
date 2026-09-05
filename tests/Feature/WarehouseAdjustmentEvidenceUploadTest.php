<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InventoryReceiveService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fase 2 P1 — jalur HTTP `WarehouseAdjustmentController` (upload file
 * sungguhan lewat form), pelengkap `InventoryAdjustmentEvidenceRequiredTest`
 * yang nge-test Service langsung. Di sini yang dites: validasi
 * `required_if` di controller + `FileUploadService::uploadWarehouseEvidence()`
 * beneran nyimpen file & path-nya nyampe ke ledger.
 */
class WarehouseAdjustmentEvidenceUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

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

        $this->pusat = Pop::create(['code' => 'EVU-PUSAT', 'pop_code' => 'EVUP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Evidence Upload Test', 'type' => 'pusat', 'status' => 'active']);

        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $this->modem = Item::create(['code' => 'EVU-MODEM', 'name' => 'Modem Evidence Upload Test', 'item_category_id' => $catAktif->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
    }

    #[Test]
    public function tandai_lost_tanpa_upload_foto_ditolak_validasi(): void
    {
        Storage::fake('public');
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['EVU-SN-001'], 250000, $this->owner);

        $response = $this->actingAs($this->owner)->post(route('warehouse.adjustments.serial.store', $serial), [
            'new_status' => 'lost',
            'reason' => 'hilang_di_lapangan',
        ]);

        $response->assertSessionHasErrors('evidence');
        $serial->refresh();
        $this->assertNotEquals('lost', $serial->status->value);
    }

    #[Test]
    public function tandai_lost_dengan_upload_foto_berhasil_dan_path_tersimpan(): void
    {
        Storage::fake('public');
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['EVU-SN-002'], 250000, $this->owner);

        $response = $this->actingAs($this->owner)->post(route('warehouse.adjustments.serial.store', $serial), [
            'new_status' => 'lost',
            'reason' => 'hilang_di_lapangan',
            'evidence' => UploadedFile::fake()->image('bap-kehilangan.jpg'),
        ]);

        $response->assertRedirect(route('warehouse.custody.index'));
        $response->assertSessionHas('success');

        $serial->refresh();
        $this->assertEquals('lost', $serial->status->value);

        $txn = InventoryTransaction::where('serial_id', $serial->id)->where('reason', 'hilang_di_lapangan')->firstOrFail();
        $this->assertNotNull($txn->evidence_file_path);
        Storage::disk('public')->assertExists($txn->evidence_file_path);
    }

    #[Test]
    public function tandai_quarantine_tanpa_foto_tetap_berhasil(): void
    {
        Storage::fake('public');
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['EVU-SN-003'], 250000, $this->owner);

        $response = $this->actingAs($this->owner)->post(route('warehouse.adjustments.serial.store', $serial), [
            'new_status' => 'quarantine',
            'reason' => 'cek_kondisi_dulu',
        ]);

        $response->assertRedirect(route('warehouse.custody.index'));
        $serial->refresh();
        $this->assertEquals('quarantine', $serial->status->value);
    }

    #[Test]
    public function custody_reason_damaged_tanpa_foto_ditolak_validasi(): void
    {
        Storage::fake('public');
        $technician = User::factory()->create();
        $custody = TechnicianCustody::create([
            'technician_id' => $technician->id,
            'issued_from_pop_id' => $this->pusat->id,
            'item_id' => Item::create(['code' => 'EVU-KABEL', 'name' => 'Kabel Evidence Upload', 'item_category_id' => ItemCategory::where('code', 'kabel_dropcore')->firstOrFail()->id, 'unit' => 'meter', 'tracking_type' => 'quantity'])->id,
            'lot_no' => null,
            'qty_remaining' => 50,
            'unit_price_snapshot' => 5000,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->post(route('warehouse.adjustments.custody.store', $custody), [
            'qty_delta' => -10,
            'reason' => 'damaged',
        ]);

        $response->assertSessionHasErrors('evidence');
        $custody->refresh();
        $this->assertEquals(50, (float) $custody->qty_remaining);
    }
}
