<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMaterial;
use App\Models\TechnicianCustody;
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
 * ADHOC-54 Fase integrasi — `CustomerInstallationController::reconcileMaterialsAgainstCustody()`,
 * dipanggil dari `storeSpeedtest()` (SATU-SATUNYA titik penyelesaian
 * pemasangan, keputusan eksplisit user: custody TIDAK boleh dipotong di
 * storePemasangan() karena itu bisa disubmit berkali-kali). Lihat komentar
 * lengkap di titik pemanggilan.
 */
class InstallationMaterialCustodyReconcileTest extends TestCase
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
        $pusat = Pop::create(['code' => 'PUSAT-RC', 'pop_code' => 'PST', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Reconcile Test', 'type' => 'pusat', 'status' => 'active']);
        $pop = Pop::create(['code' => 'CABANG-RC', 'pop_code' => 'CBG', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Reconcile Test', 'type' => 'cabang', 'status' => 'active']);

        $technician = User::factory()->create();
        $role = Role::where('name', 'Teknisi')->firstOrFail();
        $technician->role_id = $role->id;
        $technician->save();
        $technician->load('role');
        $technician->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        $customer = Customer::create([
            'customer_code' => 'TEST-RECONCILE-001',
            'full_name' => 'Reconcile Test Customer',
            'primary_phone' => '0812340001',
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
            'task_number' => 'TASK-TEST-RECONCILE-001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Reconcile Test Customer',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        return [$customer, $technician, $task, $pusat, $pop];
    }

    private function basePemasanganPayload(): array
    {
        return [
            'device_type' => 'ont',
            'connection_mode' => 'pppoe',
            'wifi_ssid' => 'WHUSNET_RECONCILE_TEST',
            'wifi_password' => 'password123',
            'serial_number' => 'ZTERECONCILE01',
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
    public function material_freeform_tanpa_item_id_tetap_lolos_seperti_sebelumnya(): void
    {
        Storage::fake('public');
        [$customer, $technician] = $this->setupInProgressInstallation();

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload() + [
            'materials' => [
                ['item_name' => 'Kabel Dropcore', 'item_type' => 'kabel_dropcore', 'qty' => 50, 'unit' => 'meter'],
            ],
        ]);

        $response = $this->actingAs($technician)->post(route('customers.installation.speedtest', $customer->id), [
            'test_download' => 20,
            'test_upload' => 10,
            'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
        ]);

        $response->assertSessionHas('success');
        $customer->refresh();
        $this->assertEquals('verification_admin', $customer->status, 'regresi: baris freeform tanpa item_id gak boleh diblokir reconcile');
    }

    #[Test]
    public function material_item_tracked_dengan_custody_cukup_direkonsiliasi_dan_memotong_custody(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'KABEL-RC', 'name' => 'Dropcore Reconcile', 'item_category_id' => $catPasif->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        $admin = User::factory()->create();
        app(InventoryReceiveService::class)->receiveQuantity($pusat, $kabel, 100, 5000, null, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $kabel->id, 'qty' => 100]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$kabel->id => 100], $admin);
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $kabel->id, 'qty' => 80]], $admin);

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload() + [
            'materials' => [
                ['item_id' => $kabel->id, 'qty' => 30, 'unit' => 'meter'],
            ],
        ]);

        $response = $this->actingAs($technician)->post(route('customers.installation.speedtest', $customer->id), [
            'test_download' => 20,
            'test_upload' => 10,
            'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
        ]);

        $response->assertSessionHas('success');
        $customer->refresh();
        $this->assertEquals('verification_admin', $customer->status);

        $custody = TechnicianCustody::where('technician_id', $technician->id)->where('item_id', $kabel->id)->firstOrFail();
        $this->assertEquals(50, $custody->qty_remaining, '80 diissue - 30 dipakai = 50 sisa custody');

        $balance = InventoryBalance::where('pop_id', $cabang->id)->where('item_id', $kabel->id)->where('lot_no', '')->firstOrFail();
        $this->assertEquals(20, $balance->qty, 'stok gudang cabang TIDAK ikut terpotong lagi saat reconcile (sudah kepotong pas ISSUE)');

        $material = TaskMaterial::where('item_id', $kabel->id)->firstOrFail();
        $this->assertEquals(5000, $material->unit_price_snapshot, 'harga tersalin dari custody, bukan kosong');
    }

    #[Test]
    public function material_item_tracked_dengan_custody_kurang_gagal_dan_tidak_menyelesaikan_task(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'KABEL-RC2', 'name' => 'Dropcore Reconcile 2', 'item_category_id' => $catPasif->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        $admin = User::factory()->create();
        app(InventoryReceiveService::class)->receiveQuantity($pusat, $kabel, 100, 5000, null, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $kabel->id, 'qty' => 100]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$kabel->id => 100], $admin);
        // SENGAJA cuma issue 10m, tapi teknisi klaim 30m di laporan — overclaim.
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $kabel->id, 'qty' => 10]], $admin);

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload() + [
            'materials' => [
                ['item_id' => $kabel->id, 'qty' => 30, 'unit' => 'meter'],
            ],
        ]);

        $response = $this->actingAs($technician)->post(route('customers.installation.speedtest', $customer->id), [
            'test_download' => 20,
            'test_upload' => 10,
            'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
        ]);

        $response->assertSessionHas('error');
        $customer->refresh();
        $this->assertEquals('installation_in_progress', $customer->status, 'overclaim ditolak — customer TIDAK boleh ikut pindah status (rollback penuh)');

        $custody = TechnicianCustody::where('technician_id', $technician->id)->where('item_id', $kabel->id)->firstOrFail();
        $this->assertEquals(10, $custody->qty_remaining, 'custody TIDAK ikut kepotong sebagian — rollback bersih');
    }
}
