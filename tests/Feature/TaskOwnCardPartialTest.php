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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskOwnCardPartialTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: `tasks/partials/own-card.blade.php` (dipakai fetch() saat
     * Echo listener auto-refresh di /tasks-saya) bandingin
     * `$task->task_type->value === 'survey'`/`'pemasangan'` (lowercase) — padahal
     * value enum `TaskType` sebenarnya `SURVEY`/`PSB` (uppercase). Kondisi itu
     * gak pernah match, jadi tombol "Mulai Survey"/"Mulai Pemasangan" gak pernah
     * render di partial ini walau tampil normal di halaman awal (`own.blade.php`,
     * yang bandinginnya udah benar). Efeknya: tombol Mulai kelihatan pas load
     * awal, lalu HILANG begitu sistem auto-refresh partial ini.
     */
    public function test_own_card_partial_shows_mulai_survey_button_for_scheduled_survey_task(): void
    {
        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\TaskFeatureSeeder::class);
        $this->seed(\Database\Seeders\WorkflowTransitionPermissionSeeder::class);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                \Illuminate\Support\Facades\Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }

        $pop = Pop::create([
            'code' => 'SMN4',
            'pop_code' => 'SMN4',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 4',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $technician = User::factory()->create(['role_id' => $teknisiRole->id]);

        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'status' => 'waiting_survey']);

        $task = Task::create([
            'task_number' => 'TASK-CARD-0001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey: ' . $customer->full_name,
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now(),
            'sla_minutes' => 120,
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        $response = $this->actingAs($technician)->get(route('tasks.own.card-partial', $task));

        $response->assertOk();
        $response->assertSee('Mulai Survey');
    }

    public function test_own_card_partial_shows_mulai_pemasangan_button_for_scheduled_installation_task(): void
    {
        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\TaskFeatureSeeder::class);
        $this->seed(\Database\Seeders\WorkflowTransitionPermissionSeeder::class);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                \Illuminate\Support\Facades\Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }

        $pop = Pop::create([
            'code' => 'SMN5',
            'pop_code' => 'SMN5',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 5',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $technician = User::factory()->create(['role_id' => $teknisiRole->id]);

        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'status' => 'waiting_installation']);

        $task = Task::create([
            'task_number' => 'TASK-CARD-0002',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan: ' . $customer->full_name,
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now(),
            'sla_minutes' => 240,
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        $response = $this->actingAs($technician)->get(route('tasks.own.card-partial', $task));

        $response->assertOk();
        $response->assertSee('Mulai Pemasangan');
    }
}
