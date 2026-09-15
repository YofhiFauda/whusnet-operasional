<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\InventoryTransactionType;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan Maintenance — dropdown SN modem/perangkat aktif, OPSIONAL (beda
 * dari Laporan Pemasangan yang wajib). Teknisi gak bawa/ganti modem → SN
 * gak tersimpan sama sekali. Teknisi bawa & pilih dari custody-nya →
 * `installSerial()` jalan persis pola Pemasangan (ADHOC, 2026-09-12).
 */
class TaskMaintenanceModemInstallTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Customer $customer;

    protected User $technician;

    protected Task $task;

    protected FopTask $fopTask;

    protected Item $modem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'MTN-MODEM', 'pop_code' => 'MDM', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Modem Test', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-MTN-MODEM-001',
            'full_name' => 'Modem Maintenance Customer',
            'primary_phone' => '0812340003',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $this->task = Task::create([
            'task_number' => 'TASK-MTN-MODEM-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Ganti Modem',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $this->task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-MTN-MODEM-001',
            'task_date' => now(),
            'category' => TaskType::MAINTENANCE->value,
            'tugas' => 'Uji Modem Maintenance',
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'task_id' => $this->task->id,
            'issue' => 'Uji ganti modem',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        $category = ItemCategory::create(['code' => 'modem_ont', 'name' => 'Modem/ONT Pelanggan', 'default_unit' => 'pcs']);
        $this->modem = Item::create([
            'code' => 'ONT-MTN-TEST', 'name' => 'Modem ONT Pengganti', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
    }

    #[Test]
    public function tanpa_pilih_sn_laporan_tetap_tersimpan_tanpa_install(): void
    {
        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), [
            'kendala_teknis' => 'Cek sinyal, tidak perlu ganti modem.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $this->task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $this->task->status);
        $this->assertSame(0, InventoryTransaction::where('type', InventoryTransactionType::INSTALL->value)->count());
    }

    #[Test]
    public function pilih_sn_dari_custody_menjalankan_install(): void
    {
        $serial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-MTN-001',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), [
            'kendala_teknis' => 'Modem lama rusak, diganti unit baru.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
            'selected_inventory_serial_id' => $serial->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $this->task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $this->task->status);

        $serial->refresh();
        $this->assertEquals(SerialStatus::INSTALLED, $serial->status);
        $this->assertEquals($this->customer->id, $serial->customer_id);
        $this->assertNull($serial->current_technician_id);

        $transaction = InventoryTransaction::where('type', InventoryTransactionType::INSTALL->value)->firstOrFail();
        $this->assertEquals($this->modem->id, $transaction->item_id);
        $this->assertEquals($this->fopTask->id, $transaction->fop_task_id);
    }

    #[Test]
    public function sn_di_luar_custody_ditolak(): void
    {
        $lainTeknisi = User::factory()->create(['role_id' => Role::where('name', 'Teknisi')->firstOrFail()->id]);
        $serialOrangLain = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => 'SN-MTN-BUKAN-MILIK',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $lainTeknisi->id,
        ]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $this->task), [
            'kendala_teknis' => 'Coba pasang SN bukan milik custody sendiri.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
            'selected_inventory_serial_id' => $serialOrangLain->id,
        ]);

        $response->assertSessionHasErrors('selected_inventory_serial_id');
        $this->task->refresh();
        $this->assertNotEquals(TaskStatus::SELESAI, $this->task->status);
    }
}
