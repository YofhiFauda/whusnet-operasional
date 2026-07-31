<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tanggal request pemasangan di Laporan Survey — opsional, tidak boleh menerima
 * tanggal lampau, dan tersimpan apa adanya.
 */
class SurveyRequestedInstallationDateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Owner — test ini menguji aturan tanggal request, bukan guard keanggotaan
     * tim (sudah punya test sendiri di SurveyInstallationReportAssignmentGuardTest).
     */
    private User $actor;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $ownerRole = Role::where('name', 'Owner')->first();
        $this->actor = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-REQ-001',
            'full_name' => 'Pelanggan Request Tanggal',
            'primary_phone' => '0812345678',
            'status' => 'survey_in_progress',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        Task::create([
            'task_number' => 'TASK-REQ-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Pelanggan Request Tanggal',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function surveyPayload(array $overrides = []): array
    {
        // survey_status 'pending' dipilih sengaja: foto & tingkat kesulitan cuma
        // required_if status 'completed', jadi test ini bisa fokus ke aturan
        // tanggal request tanpa menyeret upload file ke dalamnya.
        return array_merge([
            'survey_status' => 'pending',
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-TEST-01',
            'survey_note' => 'Catatan survey',
        ], $overrides);
    }

    public function test_requested_installation_date_tersimpan(): void
    {
        $requested = now()->addDays(21)->toDateString();

        $response = $this->actingAs($this->actor)
            ->post(route('customers.survey.store', $this->customer->id), $this->surveyPayload([
                'requested_installation_date' => $requested,
            ]));

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $this->assertSame(
            $requested,
            $this->customer->latestSurvey()->first()->requested_installation_date->toDateString()
        );
    }

    public function test_requested_installation_date_boleh_kosong(): void
    {
        $response = $this->actingAs($this->actor)
            ->post(route('customers.survey.store', $this->customer->id), $this->surveyPayload());

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $this->assertNull($this->customer->latestSurvey()->first()->requested_installation_date);
    }

    public function test_requested_installation_date_menolak_tanggal_lampau(): void
    {
        $response = $this->actingAs($this->actor)
            ->post(route('customers.survey.store', $this->customer->id), $this->surveyPayload([
                'requested_installation_date' => now()->subDay()->toDateString(),
            ]));

        $response->assertSessionHasErrors('requested_installation_date');
        $this->assertDatabaseCount('customer_surveys', 0);
    }
}
