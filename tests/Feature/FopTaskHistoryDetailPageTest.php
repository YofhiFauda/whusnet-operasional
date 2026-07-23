<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerSurvey;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FopTaskHistoryDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private User $unauthorizedUser;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $fopRole = Role::where('code', 'fop')->first();
        $salesRole = Role::where('code', 'sales')->first();

        $this->fopUser = User::factory()->create(['role_id' => $fopRole->id]);
        $this->unauthorizedUser = User::factory()->create(['role_id' => $salesRole->id]);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);

        $this->pop = Pop::create([
            'name' => 'POP Polorejo',
            'code' => 'POP-PLR',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
            'city_id' => $city->id,
        ]);

        $this->village = $village;
    }

    private Village $village;

    protected function makeSurveyFopTaskWithReport(): FopTask
    {
        $customer = Customer::factory()->create(['pop_id' => $this->pop->id]);

        $task = Task::create([
            'task_number' => 'TASK-HIST-0001',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey: '.$customer->full_name,
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now(),
            'sla_minutes' => 120,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-HIST-0001',
            'task_date' => now(),
            'category' => 'SURVEY',
            'tugas' => 'Survey Pelanggan: '.$customer->full_name,
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'issue' => 'Auto-Sync dari antrean survey',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'task_id' => $task->id,
        ]);

        // Trigger TaskObserver: buka siklus 1 (sinkron FopTaskStatusHistory
        // & TaskReport, keduanya butuh FopTask sudah terhubung sebelum status berubah).
        $task->update(['status' => TaskStatus::IN_PROGRESS->value, 'started_at' => now()]);
        $task->update(['status' => TaskStatus::SELESAI->value, 'completed_at' => now()]);
        $fopTask->update(['status' => 'selesai']);

        CustomerSurvey::create([
            'customer_id' => $customer->id,
            'survey_status' => 'completed',
            'required_tools' => 'Tang Krimping, OPM, Tangga',
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-PLR-01',
            'survey_note' => 'Lokasi mudah dijangkau',
            'technician_id' => $this->fopUser->id,
        ]);

        return $fopTask->refresh();
    }

    public function test_guests_cannot_access_history_detail_page(): void
    {
        $fopTask = $this->makeSurveyFopTaskWithReport();

        $response = $this->get(route('fop-tasks.history.show', $fopTask->id));
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_users_cannot_access_history_detail_page(): void
    {
        $fopTask = $this->makeSurveyFopTaskWithReport();

        $response = $this->actingAs($this->unauthorizedUser)->get(route('fop-tasks.history.show', $fopTask->id));
        $response->assertStatus(403);
    }

    public function test_history_detail_page_shows_report_duration_sla_and_status_history(): void
    {
        $fopTask = $this->makeSurveyFopTaskWithReport();

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.history.show', $fopTask->id));

        $response->assertOk();
        $response->assertSee($fopTask->task_number);
        // Laporan survey — alat dibaca langsung dari CustomerSurvey, bukan kolom baru.
        $response->assertSee('Tang Krimping, OPM, Tangga');
        $response->assertSee('ODP-PLR-01');
        // Durasi & SLA dari TaskReport (Task 10).
        $response->assertSee('120 menit');
        // Histori status (Task 9) tetap tampil di halaman ini.
        $response->assertSee('Sedang Dikerjakan');
    }
}
