<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Enums\MaterialKind;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TaskMaterial;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WarehouseMaterialUsageReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $popAdmin;

    private User $teknisi;

    private Pop $popSiman;

    private Pop $popKauman;

    private Item $kabelItem;

    private Item $modemItem;

    private Item $patchcordItem;

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

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);

        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $this->popAdmin = User::factory()->create(['role_id' => $adminRole->id]);

        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $this->teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->popSiman = Pop::create([
            'code' => 'POP-SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'SMN',
            'cid_prefix' => 'SMN',
            'name' => 'Cabang Siman',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->popKauman = Pop::create([
            'code' => 'POP-KMN',
            'pop_code' => 'KMN',
            'registration_prefix' => 'KMN',
            'cid_prefix' => 'KMN',
            'name' => 'Cabang Kauman',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // Scope Pop Admin ke Pop Siman saja
        $scope = UserRoleScope::create([
            'user_id' => $this->popAdmin->id,
            'role_id' => $adminRole->id,
            'scope_type' => 'selected_pop',
        ]);
        $scope->targets()->create([
            'pop_id' => $this->popSiman->id,
        ]);

        $cableCat = ItemCategory::firstOrCreate(
            ['code' => 'kabel_dropcore'],
            ['name' => 'Kabel Dropcore', 'default_unit' => 'meter', 'is_active' => true]
        );
        $ontCat = ItemCategory::firstOrCreate(
            ['code' => 'modem_ont'],
            ['name' => 'Modem ONT', 'default_unit' => 'Unit', 'is_active' => true]
        );
        $passiveCat = ItemCategory::firstOrCreate(
            ['code' => 'material_pasif'],
            ['name' => 'Aksesori Pasif', 'default_unit' => 'pcs', 'is_active' => true]
        );

        $this->kabelItem = Item::create([
            'item_category_id' => $cableCat->id,
            'code' => 'DC-1C-1000',
            'name' => 'Dropcore 1 Core 1000M',
            'tracking_type' => 'roll',
            'unit' => 'meter',
            'meter_per_roll' => 1000,
        ]);

        $this->modemItem = Item::create([
            'item_category_id' => $ontCat->id,
            'code' => 'ONT-HW-01',
            'name' => 'ONT Huawei HG8245H5',
            'tracking_type' => 'serialized',
            'unit' => 'Unit',
        ]);

        $this->patchcordItem = Item::create([
            'item_category_id' => $passiveCat->id,
            'code' => 'PC-SC-03',
            'name' => 'Patchcord SC-UPC 3M',
            'tracking_type' => 'quantity',
            'unit' => 'pcs',
        ]);
    }

    #[Test]
    public function user_without_warehouse_permission_is_forbidden(): void
    {
        $this->actingAs($this->teknisi)
            ->get(route('warehouse.usage.index'))
            ->assertForbidden();
    }

    #[Test]
    public function owner_can_view_usage_page_with_kpi_and_summaries(): void
    {
        $customerSiman = Customer::factory()->create(['pop_id' => $this->popSiman->id, 'full_name' => 'Budi Siman']);
        $fopTaskSiman = FopTask::create([
            'task_number' => 'TSK-20260925-001',
            'tugas' => 'Pemasangan Baru PSB',
            'category' => TaskType::PEMASANGAN->value,
            'pop_id' => $this->popSiman->id,
            'customer_id' => $customerSiman->id,
            'status' => 'selesai',
        ]);

        // Simulasikan pemakaian kabel 150m dan 2 pcs patchcord hari ini
        TaskMaterial::create([
            'fop_task_id' => $fopTaskSiman->id,
            'customer_id' => $customerSiman->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_id' => $this->kabelItem->id,
            'item_type' => 'kabel_dropcore',
            'item_name' => $this->kabelItem->name,
            'lot_no' => 'DC-1C-20260925-000001',
            'qty' => 150,
            'unit' => 'meter',
            'recorded_by' => $this->teknisi->id,
            'created_at' => now(),
        ]);

        TaskMaterial::create([
            'fop_task_id' => $fopTaskSiman->id,
            'customer_id' => $customerSiman->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_id' => $this->patchcordItem->id,
            'item_type' => 'material_pasif',
            'item_name' => $this->patchcordItem->name,
            'qty' => 2,
            'unit' => 'pcs',
            'recorded_by' => $this->teknisi->id,
            'created_at' => now(),
        ]);

        // Simulasikan 1 modem terpasang hari ini
        $serial = InventorySerial::create([
            'item_id' => $this->modemItem->id,
            'serial_number' => 'HW001234',
            'status' => 'installed',
            'customer_id' => $customerSiman->id,
        ]);

        InventoryTransaction::create([
            'item_id' => $this->modemItem->id,
            'serial_id' => $serial->id,
            'fop_task_id' => $fopTaskSiman->id,
            'from_technician_id' => $this->teknisi->id,
            'type' => InventoryTransactionType::INSTALL->value,
            'qty' => 1,
            'created_by' => $this->teknisi->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('warehouse.usage.index', ['preset' => 'today']))
            ->assertOk();

        $response->assertSee('Dropcore 1 Core 1000M');
        $response->assertSee('150');
        $response->assertSee('Patchcord SC-UPC 3M');
        $response->assertSee('ONT Huawei HG8245H5');
        $response->assertSee('DC-1C-20260925-000001');
        $response->assertSee('HW001234');
        $response->assertSee('Budi Siman');
    }

    #[Test]
    public function filter_yesterday_shows_only_yesterday_material_consumption(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->popSiman->id, 'full_name' => 'Kemarin Customer']);
        $fopTask = FopTask::create([
            'task_number' => 'TSK-KEMARIN-001',
            'tugas' => 'Pemasangan PSB Kemarin',
            'category' => TaskType::PEMASANGAN->value,
            'pop_id' => $this->popSiman->id,
            'customer_id' => $customer->id,
            'status' => 'selesai',
        ]);

        // Pemakaian KEMARIN (1000m kabel habis)
        $materialKemarin = TaskMaterial::create([
            'fop_task_id' => $fopTask->id,
            'customer_id' => $customer->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_id' => $this->kabelItem->id,
            'item_type' => 'kabel_dropcore',
            'item_name' => $this->kabelItem->name,
            'lot_no' => 'DC-1C-20260924-000002',
            'qty' => 1000,
            'unit' => 'meter',
            'recorded_by' => $this->teknisi->id,
        ]);
        $materialKemarin->created_at = now()->subDay()->startOfHour();
        $materialKemarin->saveQuietly();

        // Pemakaian HARI INI (50m)
        TaskMaterial::create([
            'fop_task_id' => $fopTask->id,
            'customer_id' => $customer->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_id' => $this->kabelItem->id,
            'item_type' => 'kabel_dropcore',
            'item_name' => $this->kabelItem->name,
            'lot_no' => 'DC-1C-20260925-000001',
            'qty' => 50,
            'unit' => 'meter',
            'recorded_by' => $this->teknisi->id,
        ]);

        // Filter Kemarin: harus melihat DC-1C-20260924-000002 (1000m) dan TIDAK melihat 50m hari ini
        $response = $this->actingAs($this->owner)
            ->get(route('warehouse.usage.index', ['preset' => 'yesterday']))
            ->assertOk();

        $response->assertSee('DC-1C-20260924-000002');
        $response->assertSee('1.000');
        $response->assertDontSee('DC-1C-20260925-000001');
    }

    #[Test]
    public function pop_admin_can_only_see_their_allowed_pop_materials(): void
    {
        $customerSiman = Customer::factory()->create(['pop_id' => $this->popSiman->id, 'full_name' => 'Warga Siman']);
        $taskSiman = FopTask::create([
            'task_number' => 'TSK-SIMAN-01',
            'tugas' => 'Pasang Siman',
            'category' => TaskType::PEMASANGAN->value,
            'pop_id' => $this->popSiman->id,
            'customer_id' => $customerSiman->id,
            'status' => 'selesai',
        ]);

        $customerKauman = Customer::factory()->create(['pop_id' => $this->popKauman->id, 'full_name' => 'Warga Kauman']);
        $taskKauman = FopTask::create([
            'task_number' => 'TSK-KAUMAN-01',
            'tugas' => 'Pasang Kauman',
            'category' => TaskType::PEMASANGAN->value,
            'pop_id' => $this->popKauman->id,
            'customer_id' => $customerKauman->id,
            'status' => 'selesai',
        ]);

        TaskMaterial::create([
            'fop_task_id' => $taskSiman->id,
            'customer_id' => $customerSiman->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_id' => $this->kabelItem->id,
            'item_type' => 'kabel_dropcore',
            'item_name' => $this->kabelItem->name,
            'lot_no' => 'DC-SIMAN-001',
            'qty' => 75,
            'unit' => 'meter',
            'created_at' => now(),
        ]);

        TaskMaterial::create([
            'fop_task_id' => $taskKauman->id,
            'customer_id' => $customerKauman->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_id' => $this->kabelItem->id,
            'item_type' => 'kabel_dropcore',
            'item_name' => $this->kabelItem->name,
            'lot_no' => 'DC-KAUMAN-001',
            'qty' => 999,
            'unit' => 'meter',
            'created_at' => now(),
        ]);

        // Pop Admin Siman hanya boleh melihat DC-SIMAN-001 dan tidak boleh melihat DC-KAUMAN-001
        $response = $this->actingAs($this->popAdmin)
            ->get(route('warehouse.usage.index', ['preset' => 'today']))
            ->assertOk();

        $response->assertSee('DC-SIMAN-001');
        $response->assertSee('Warga Siman');
        $response->assertDontSee('DC-KAUMAN-001');
        $response->assertDontSee('Warga Kauman');
    }

    #[Test]
    public function export_usage_to_excel_streams_download(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->popSiman->id]);
        $task = FopTask::create([
            'task_number' => 'TSK-EXP-01',
            'tugas' => 'Tugas Export',
            'category' => TaskType::PEMASANGAN->value,
            'pop_id' => $this->popSiman->id,
            'customer_id' => $customer->id,
            'status' => 'selesai',
        ]);

        TaskMaterial::create([
            'fop_task_id' => $task->id,
            'customer_id' => $customer->id,
            'kind' => MaterialKind::TERPAKAI->value,
            'item_id' => $this->kabelItem->id,
            'item_type' => 'kabel_dropcore',
            'item_name' => $this->kabelItem->name,
            'qty' => 80,
            'unit' => 'meter',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('warehouse.usage.export', ['preset' => 'today']));

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
    }
}
