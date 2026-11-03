<?php

namespace Tests\Feature;

use App\Enums\CustodyStatus;
use App\Enums\FopTaskPriority;
use App\Enums\RollStatus;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TechnicianCustody;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan Maintenance & C-REQ — picker SN modem/Roll Kabel/Material Pasif
 * digabung jadi 1 dropdown growable (komponen installation-item-rows, sama
 * yang dipakai Laporan Pemasangan PSB — ADHOC unify-dropdown-item-psb,
 * perluasan 2026-11-02). Fokus file ini: render gabungan ketiganya tampil
 * bersamaan di satu halaman, untuk MTN dan C-REQ. `TaskMaintenanceModemInstallTest`,
 * `TaskMaintenanceOwnershipRadioTest`, dll menutupi perilaku `store()` —
 * TIDAK diubah sama sekali oleh perluasan ini, cuma UI picker-nya.
 */
class TaskMaintenanceUnifiedItemDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Customer $customer;

    protected User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'MTN-GABUNG', 'pop_code' => 'GBG', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Gabungan Test', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-MTN-GABUNG-001',
            'full_name' => 'Gabungan Maintenance Customer',
            'primary_phone' => '0812340005',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);
    }

    private function seedCustody(): void
    {
        $modemCategory = ItemCategory::create(['code' => 'modem_ont', 'name' => 'Modem/ONT Pelanggan', 'default_unit' => 'pcs']);
        $modem = Item::create([
            'code' => 'ONT-GABUNG-TEST', 'name' => 'Modem ONT Gabungan', 'unit' => 'pcs',
            'item_category_id' => $modemCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
        InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'SN-MTN-GABUNG-001',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $kabelCategory = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabelItem = Item::create(['code' => 'IRC-ROLL-MTN-GABUNG', 'name' => 'Kabel FO Maintenance Gabungan', 'item_category_id' => $kabelCategory->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 500]);
        InventoryRoll::create([
            'item_id' => $kabelItem->id,
            'roll_code' => 'IRC-ROLL-MTN-GABUNG-'.date('Ymd').'-000001',
            'length_total' => 500,
            'length_remaining' => 500,
            'unit_price_snapshot' => 1600000,
            'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $rj45 = Item::create(['code' => 'IRC-RJ45-MTN-GABUNG', 'name' => 'RJ45 Maintenance Gabungan', 'item_category_id' => $kabelCategory->id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);
        TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
            'item_id' => $rj45->id,
            'lot_no' => 1,
            'qty_remaining' => 10,
            'unit_price_snapshot' => 2000,
            'status' => CustodyStatus::ISSUED->value,
            'issued_at' => now(),
        ]);
    }

    private function createTask(string $taskNumber, TaskType $taskType): Task
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => $taskType->value,
            'title' => 'Gabungan Dropdown Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        FopTask::create([
            'task_number' => 'T'.$taskNumber,
            'task_date' => now(),
            'category' => $taskType->value,
            'tugas' => 'Uji Dropdown Gabungan',
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'task_id' => $task->id,
            'issue' => 'Uji dropdown gabungan',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        return $task;
    }

    #[Test]
    public function laporan_maintenance_render_dropdown_gabungan_modem_roll_dan_pasif(): void
    {
        $this->seedCustody();
        $task = $this->createTask('TASK-MTN-GABUNG-001', TaskType::MAINTENANCE);

        $response = $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $task));

        $response->assertOk();
        $response->assertSee('SN-MTN-GABUNG-001');
        $response->assertSee('IRC-ROLL-MTN-GABUNG-'.date('Ymd').'-000001', false);
        $response->assertSee('RJ45 Maintenance Gabungan');
        $response->assertSee('id="selected_inventory_serial_id"', false);
    }

    #[Test]
    public function laporan_creq_render_dropdown_gabungan_modem_roll_dan_pasif(): void
    {
        $this->seedCustody();
        $task = $this->createTask('TASK-CREQ-GABUNG-001', TaskType::CREQ);

        $response = $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $task));

        $response->assertOk();
        $response->assertSee('SN-MTN-GABUNG-001');
        $response->assertSee('IRC-ROLL-MTN-GABUNG-'.date('Ymd').'-000001', false);
        $response->assertSee('RJ45 Maintenance Gabungan');
    }
}
