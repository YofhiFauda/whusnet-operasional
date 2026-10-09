<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Semua alat kerja bersifat opsional: di Laporan Survey & Pemasangan checklist
 * diberi label "Alat Kerja Opsional", bukan lagi "wajib dibawa".
 */
class WorkToolOptionalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->owner = User::factory()->create([
            'role_id' => Role::where('code', 'owner')->value('id'),
            'status' => 'active',
        ]);

        $pop = Pop::create([
            'code' => 'WTO',
            'pop_code' => 'WTO',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Opsional',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'WTO-001',
            'full_name' => 'Pelanggan Opsional',
            'primary_phone' => '0812345678',
            'status' => 'survey_in_progress',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    #[Test]
    public function test_survey_report_labels_work_tools_as_optional(): void
    {
        $this->customer->latestSurvey()->create(['started_at' => now()]);

        $response = $this->actingAs($this->owner)->get(route('customers.survey.report', $this->customer->id));

        $response->assertStatus(200);
        $response->assertSee('Alat Kerja Opsional');
        $response->assertDontSee('Alat Kerja Yang Perlu Dibawa Teknisi');
    }

    #[Test]
    public function test_installation_report_labels_work_tools_as_optional(): void
    {
        $this->customer->update(['status' => 'installation_in_progress']);
        $task = Task::create([
            'task_number' => 'TASK-WTO-PSB',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->customer->pop_id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Task Pemasangan Opsional',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->owner->id, 'role_in_task' => 'lead']);
        $this->customer->installations()->create(['installation_status' => 'in_progress', 'started_at' => now()]);

        $response = $this->actingAs($this->owner)->get(route('customers.installation.report', $this->customer->id));

        $response->assertStatus(200);
        $response->assertSee('Alat Kerja Opsional');
        $response->assertDontSee('Alat Kerja Yang Dibawa / Dipakai');
    }
}
