<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMaterial;
use App\Models\User;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Roll kabel dipilih di Laporan Pemasangan — draft pointer di storePemasangan()
 * (Fase 5), potong-meter beneran di storeSpeedtest() (Fase 6, titik
 * penyelesaian tunggal, sama pola SN/material). Lihat docs/TASKS.md ADHOC
 * kabel-per-roll & komentar `storeSpeedtest()`.
 */
class InstallationRollConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);
    }

    private function setupInProgressInstallation(): array
    {
        $pusat = Pop::create(['code' => 'PUSAT-RR', 'pop_code' => 'PSR', 'registration_prefix' => 'C', 'cid_prefix' => 'E', 'name' => 'Pusat Roll Reconcile', 'type' => 'pusat', 'status' => 'active']);
        $pop = Pop::create(['code' => 'CABANG-RR', 'pop_code' => 'CBR', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Roll Reconcile', 'type' => 'cabang', 'status' => 'active']);

        $technician = User::factory()->create();
        $role = Role::where('name', 'Teknisi')->firstOrFail();
        $technician->role_id = $role->id;
        $technician->save();
        $technician->load('role');
        $technician->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        $customer = Customer::create([
            'customer_code' => 'TEST-ROLL-001',
            'full_name' => 'Roll Reconcile Test Customer',
            'primary_phone' => '0812340002',
            'status' => 'installation_in_progress',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $customer->installations()->create([
            'installation_status' => 'in_progress',
            'started_at' => now(),
            'start_time' => now()->toTimeString(),
            'scheduled_date' => now()->format('Y-m-d'),
            'scheduled_time' => '09:00',
            'technician_id' => $technician->id,
        ]);

        $task = Task::create([
            'task_number' => 'TASK-TEST-ROLL-001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Roll Reconcile Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        return [$customer, $technician, $task, $pusat, $pop];
    }

    private function issueActiveSerialTo(User $technician, Pop $pusat, Pop $cabang): InventorySerial
    {
        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $ont = Item::create([
            'code' => 'ONT-RR-'.uniqid(), 'name' => 'ONT Roll Reconcile', 'item_category_id' => $catAktif->id,
            'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
        $sn = 'ZTEROLLRECONCILE-'.uniqid();
        $admin = User::factory()->create();

        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $ont, [$sn], 250000, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $ont->id, 'serial_numbers' => [$sn]]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [$sn], [], $admin);
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $ont->id, 'serial_numbers' => [$sn]]], $admin);

        return $serial->refresh();
    }

    private function basePayload(User $technician, Pop $pusat, Pop $cabang): array
    {
        return [
            'device_type' => 'ont',
            'connection_mode' => 'pppoe',
            'wifi_ssid' => 'WHUSNET_ROLL_TEST',
            'wifi_password' => 'password123',
            'selected_inventory_serial_id' => $this->issueActiveSerialTo($technician, $pusat, $cabang)->id,
            'odp_number' => 'ODP-01',
            'odp_port' => '1',
            'olt_number' => 'OLT-01',
            'olt_slot' => '1',
            'olt_port' => '1',
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'contract_photo' => UploadedFile::fake()->image('contract.jpg'),
            'signature_photo' => UploadedFile::fake()->image('signature.jpg'),
        ];
    }

    #[Test]
    public function roll_dipilih_dan_dipotong_saat_aktivasi_selesai(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $pop] = $this->setupInProgressInstallation();

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabelItem = Item::create(['code' => 'IRC-ROLL', 'name' => 'Kabel FO Roll Reconcile', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 500]);

        $roll = InventoryRoll::create([
            'item_id' => $kabelItem->id,
            'roll_code' => 'IRC-ROLL-'.date('Ymd').'-000001',
            'length_total' => 500,
            'length_remaining' => 500,
            'unit_price_snapshot' => 1600000,
            'status' => RollStatus::ISSUED,
            'current_technician_id' => $technician->id,
            'issued_from_pop_id' => $pop->id,
        ]);

        // Gerbang storeSpeedtest() mensyaratkan minimal 1 baris task_materials
        // (kind=terpakai) sudah tersimpan — sengaja pakai material QUANTITY
        // biasa (bukan roll) buat lolos gerbang itu, roll diuji terpisah lewat
        // field selected_inventory_roll_id/roll_meters_used.
        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $rj45 = Item::create(['code' => 'IRC-RJ45', 'name' => 'RJ45 Roll Reconcile', 'item_category_id' => $catPasif->id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);
        $admin = User::factory()->create();
        app(InventoryReceiveService::class)->receiveQuantity($pusat, $rj45, 50, 2000, $admin);
        $rj45Transfer = app(InventoryTransferService::class)->createTransfer($pusat, $pop, [['item_id' => $rj45->id, 'qty' => 50]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($rj45Transfer, [], [$rj45->id => 50], $admin);
        app(InventoryIssueService::class)->issue($pop, $technician, [['item_id' => $rj45->id, 'qty' => 10]], $admin);

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload($technician, $pusat, $pop) + [
            'selected_inventory_roll_id' => $roll->id,
            'roll_meters_used' => 85,
            'materials' => [
                ['item_id' => $rj45->id, 'qty' => 4, 'unit' => 'pcs'],
            ],
        ])->assertSessionHasNoErrors();

        $roll->refresh();
        $this->assertEquals(500, $roll->length_remaining, 'belum kepotong di step 5 — potongnya di storeSpeedtest()');

        $response = $this->actingAs($technician)->post(route('customers.installation.speedtest', $customer->id), [
            'test_download' => 20,
            'test_upload' => 10,
            'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $roll->refresh();
        $this->assertEquals(415, $roll->length_remaining);
        $this->assertEquals(RollStatus::IN_USE, $roll->status);

        $material = TaskMaterial::where('lot_no', $roll->roll_code)->firstOrFail();
        $this->assertEquals(85, $material->qty);
        $this->assertEquals('meter', $material->unit);
        $this->assertEquals(1600000, $material->unit_price_snapshot);
    }

    #[Test]
    public function submit_tanpa_pilih_roll_tetap_sukses(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $pop] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload($technician, $pusat, $pop));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
    }

    #[Test]
    public function roll_di_luar_custody_ditolak_validasi(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $pop] = $this->setupInProgressInstallation();

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabelItem = Item::create(['code' => 'IRC-ROLL-2', 'name' => 'Kabel FO Roll Reconcile 2', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 500]);
        $rollLain = InventoryRoll::create([
            'item_id' => $kabelItem->id,
            'roll_code' => 'IRC-ROLL-2-'.date('Ymd').'-000001',
            'length_total' => 500,
            'length_remaining' => 500,
            'status' => RollStatus::AVAILABLE,
        ]);

        $response = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePayload($technician, $pusat, $pop) + [
            'selected_inventory_roll_id' => $rollLain->id,
            'roll_meters_used' => 50,
        ]);

        $response->assertSessionHasErrors('selected_inventory_roll_id');
    }
}
