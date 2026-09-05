<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
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
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-54 — Maintenance itu one-shot (`sync()`+`complete()` dalam satu
 * request, beda dari Pemasangan yang dua fase), jadi reconcile custody bisa
 * langsung nempel setelah `sync()` di `TaskMaintenanceController::store()`
 * tanpa masalah resubmit-double-potong. Lihat komentar di titik pemanggilannya.
 */
class MaintenanceMaterialCustodyReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);
    }

    #[Test]
    public function material_item_tracked_direkonsiliasi_saat_maintenance_selesai(): void
    {
        $pusat = Pop::create(['code' => 'PUSAT-MTN', 'pop_code' => 'PST', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat MTN Test', 'type' => 'pusat', 'status' => 'active']);
        $pop = Pop::create(['code' => 'CABANG-MTN', 'pop_code' => 'CBG', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang MTN Test', 'type' => 'cabang', 'status' => 'active']);

        $customer = Customer::create([
            'customer_code' => 'TEST-MTN-001',
            'full_name' => 'Maintenance Reconcile Customer',
            'primary_phone' => '0812340002',
            'status' => 'active',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $technician = User::factory()->create(['role_id' => $teknisiRole->id]);
        $technician->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $task = Task::create([
            'task_number' => 'TASK-MTN-RECONCILE-001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Reconcile Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        FopTask::create([
            'task_number' => 'TFOP-MTN-RECONCILE-001',
            'task_date' => now(),
            'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Uji Reconcile Maintenance',
            'pop_id' => $pop->id,
            'customer_id' => $customer->id,
            'task_id' => $task->id,
            'issue' => 'Uji reconcile',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'KABEL-MTN', 'name' => 'Dropcore Maintenance', 'item_category_id' => $catPasif->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        $admin = User::factory()->create();
        app(InventoryReceiveService::class)->receiveQuantity($pusat, $kabel, 100, 5000, null, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $pop, [['item_id' => $kabel->id, 'qty' => 100]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$kabel->id => 100], $admin);
        app(InventoryIssueService::class)->issue($pop, $technician, [['item_id' => $kabel->id, 'qty' => 40]], $admin);

        $response = $this->actingAs($technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Kabel putus, disambung ulang pakai dropcore baru.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
            'materials' => [
                ['item_id' => $kabel->id, 'qty' => 15, 'unit' => 'meter'],
            ],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $task->status, 'reconcile gak boleh gagalin penyelesaian task kalau custody cukup');

        $custody = TechnicianCustody::where('technician_id', $technician->id)->where('item_id', $kabel->id)->firstOrFail();
        $this->assertEquals(25, $custody->qty_remaining, '40 diissue - 15 dipakai = 25 sisa');

        $balance = InventoryBalance::where('pop_id', $pop->id)->where('item_id', $kabel->id)->where('lot_no', '')->firstOrFail();
        $this->assertEquals(60, $balance->qty, 'stok cabang TIDAK ikut terpotong lagi saat reconcile');

        $material = TaskMaterial::where('item_id', $kabel->id)->firstOrFail();
        $this->assertEquals(5000, $material->unit_price_snapshot);
    }
}
