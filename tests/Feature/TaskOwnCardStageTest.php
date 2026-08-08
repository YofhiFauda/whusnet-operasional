<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * S8.4-T011: sebelum teknisi klik "Mulai X", info pelanggan (nama/alamat/POP)
 * dan link "Buka Detail" (yang berisi koordinat/Maps) harus digate — jangan
 * tampil selama status task masih `terjadwal`. Begitu status berubah (in_progress
 * dst), info + Buka Detail muncul. Berlaku di own.blade.php DAN partial AJAX
 * own-card.blade.php (dua-duanya harus sinkron).
 */
class TaskOwnCardStageTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }

        $this->pop = Pop::create([
            'code' => 'SMN9',
            'pop_code' => 'SMN9',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 9',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id]);
    }

    protected function makeTask(string $taskNumber, TaskStatus $status, ?Customer $customer = null): Task
    {
        $task = Task::create([
            'task_number' => $taskNumber,
            'customer_id' => $customer?->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Rutin',
            'status' => $status->value,
            'scheduled_at' => now(),
            'started_at' => $status === TaskStatus::TERJADWAL ? null : now(),
            'sla_minutes' => 120,
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        return $task;
    }

    public function test_own_page_shows_customer_info_but_hides_detail_and_coordinates_while_terjadwal(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->pop->id, 'status' => 'active']);
        $customer->customerAddress()->create([
            'latitude' => '-7.54321',
            'longitude' => '112.98765',
            'full_address' => 'Jl. Sooko No. 9',
        ]);
        $task = $this->makeTask('TASK-STAGE-0001', TaskStatus::TERJADWAL, $customer);

        $response = $this->actingAs($this->technician)->get(route('tasks.own'));

        $response->assertOk();
        $response->assertSee($customer->full_name);
        $response->assertDontSee('Buka Detail');
        $response->assertDontSee('Koordinat Lokasi');
        $response->assertDontSee('-7.54321');
        $response->assertDontSee('112.98765');
        $response->assertSee('Mulai Maintenance');
    }

    public function test_own_page_shows_all_details_after_in_progress(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->pop->id, 'status' => 'active']);
        $customer->customerAddress()->create([
            'latitude' => '-7.54321',
            'longitude' => '112.98765',
            'full_address' => 'Jl. Sooko No. 9',
        ]);
        $task = $this->makeTask('TASK-STAGE-0002', TaskStatus::IN_PROGRESS, $customer);

        $response = $this->actingAs($this->technician)->get(route('tasks.own'));

        $response->assertOk();
        $response->assertSee($customer->full_name);
        $response->assertSee('Buka Detail');
        $response->assertSee('Koordinat Lokasi');
        $response->assertSee('-7.54321');
        $response->assertSee('112.98765');
        $response->assertSee('Maps');
    }

    public function test_own_card_partial_shows_customer_info_but_hides_detail_and_coordinates_while_terjadwal(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->pop->id, 'status' => 'active']);
        $customer->customerAddress()->create([
            'latitude' => '-7.54321',
            'longitude' => '112.98765',
            'full_address' => 'Jl. Sooko No. 9',
        ]);
        $task = $this->makeTask('TASK-STAGE-0003', TaskStatus::TERJADWAL, $customer);

        $response = $this->actingAs($this->technician)->get(route('tasks.own.card-partial', $task));

        $response->assertOk();
        $response->assertSee($customer->full_name);
        $response->assertDontSee('Buka Detail');
        $response->assertDontSee('Koordinat Lokasi');
        $response->assertDontSee('-7.54321');
        $response->assertDontSee('112.98765');
        // own-card.blade.php belum punya label spesifik per task_type di tombol
        // Mulai (beda dari own.blade.php) — di luar scope Task 11, cukup pastikan
        // tombol Mulai tetap tampil.
        $response->assertSee('Mulai Task');
    }

    public function test_own_card_partial_shows_all_details_after_in_progress(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->pop->id, 'status' => 'active']);
        $customer->customerAddress()->create([
            'latitude' => '-7.54321',
            'longitude' => '112.98765',
            'full_address' => 'Jl. Sooko No. 9',
        ]);
        $task = $this->makeTask('TASK-STAGE-0004', TaskStatus::IN_PROGRESS, $customer);

        $response = $this->actingAs($this->technician)->get(route('tasks.own.card-partial', $task));

        $response->assertOk();
        $response->assertSee($customer->full_name);
        $response->assertSee('Buka Detail');
        $response->assertSee('Koordinat Lokasi');
        $response->assertSee('-7.54321');
        $response->assertSee('112.98765');
        $response->assertSee('Maps');
    }

    public function test_own_card_partial_isi_laporan_triggers_report_choice_dialog_for_in_progress(): void
    {
        $task = $this->makeTask('TASK-STAGE-0005', TaskStatus::IN_PROGRESS);

        $response = $this->actingAs($this->technician)->get(route('tasks.own.card-partial', $task));

        $response->assertOk();
        $response->assertSee('Lapor Sekarang');
        $response->assertSee('Lapor Nanti');
    }
}
