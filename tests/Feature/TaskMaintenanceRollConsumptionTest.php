<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\RollStatus;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventoryRoll;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMaterial;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan Maintenance — dropdown Roll Kabel, OPSIONAL sama pola SN modem
 * (`TaskMaintenanceModemInstallTest`). One-shot: gak ada draft pointer, pilih
 * roll langsung potong meter di `store()` yang sama. Lihat docs/TASKS.md
 * ADHOC kabel-per-roll.
 */
class TaskMaintenanceRollConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Customer $customer;

    protected User $technician;

    protected Task $task;

    protected FopTask $fopTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);

        $this->pop = Pop::create(['code' => 'MTN-ROLL', 'pop_code' => 'MTR', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Roll Maintenance Test', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-MTN-ROLL-001',
            'full_name' => 'Roll Maintenance Customer',
            'primary_phone' => '0812340004',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $this->task = Task::create([
            'task_number' => 'TASK-MTN-ROLL-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Potong Roll',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $this->task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-MTN-ROLL-001',
            'task_date' => now(),
            'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Uji Roll Maintenance',
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'task_id' => $this->task->id,
            'issue' => 'Uji potong roll',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
    }

    #[Test]
    public function tanpa_pilih_roll_laporan_tetap_tersimpan(): void
    {
        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), [
            'kendala_teknis' => 'Cek sinyal, tidak perlu potong kabel.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $this->task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $this->task->status);
        $this->assertEquals(0, TaskMaterial::count());
    }

    #[Test]
    public function pilih_roll_dari_custody_memotong_sisa_meter(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabelItem = Item::create(['code' => 'MTR-ROLL', 'name' => 'Kabel FO Maintenance Roll', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 400]);
        $roll = InventoryRoll::create([
            'item_id' => $kabelItem->id,
            'roll_code' => 'MTR-ROLL-'.date('Ymd').'-000001',
            'length_total' => 400,
            'length_remaining' => 400,
            'unit_price_snapshot' => 1200000,
            'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), [
            'kendala_teknis' => 'Kabel putus, sambung ulang pakai roll cadangan.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
            'selected_inventory_roll_id' => $roll->id,
            'roll_meters_used' => 25,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $roll->refresh();
        $this->assertEquals(375, $roll->length_remaining);
        $this->assertEquals(RollStatus::IN_USE, $roll->status);

        $material = TaskMaterial::where('lot_no', $roll->roll_code)->firstOrFail();
        $this->assertEquals(25, $material->qty);
        $this->assertEquals('meter', $material->unit);
    }

    #[Test]
    public function roll_di_luar_custody_ditolak_validasi(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabelItem = Item::create(['code' => 'MTR-ROLL-2', 'name' => 'Kabel FO Maintenance Roll 2', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 400]);
        $rollLain = InventoryRoll::create([
            'item_id' => $kabelItem->id,
            'roll_code' => 'MTR-ROLL-2-'.date('Ymd').'-000001',
            'length_total' => 400,
            'length_remaining' => 400,
            'status' => RollStatus::AVAILABLE,
        ]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), [
            'kendala_teknis' => 'Coba pakai roll bukan custody sendiri.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
            'selected_inventory_roll_id' => $rollLain->id,
            'roll_meters_used' => 10,
        ]);

        $response->assertSessionHasErrors('selected_inventory_roll_id');
    }
}
