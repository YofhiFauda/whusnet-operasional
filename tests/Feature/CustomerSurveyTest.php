<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerSurveyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_technician_can_fill_survey()
    {
        Storage::fake('public');

        $this->seed(DatabaseSeeder::class);

        $pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $technician = User::factory()->create();
        $teknisiRole = Role::where('name', 'Teknisi')->first();
        $technician->role_id = $teknisiRole->id;
        $technician->save();

        $customer = Customer::create([
            'customer_code' => 'TEST-001',
            'full_name' => 'Test Customer',
            'primary_phone' => '0812345678',
            'status' => 'survey_in_progress',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $surveyData = [
            'survey_status' => 'completed',
            'survey_date' => now()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'technician_id' => $technician->id,
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-TEST-01',
            'house_photo' => UploadedFile::fake()->image('house.jpg'),
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
            'survey_note' => 'Test survey note',
            'difficulty_level' => 'SEDANG',
        ];

        $response = $this->actingAs($technician)
            ->post(route('customers.survey.store', $customer->id), $surveyData);

        $response->assertStatus(302);
        $this->assertDatabaseHas('customer_surveys', [
            'customer_id' => $customer->id,
            'survey_status' => 'completed',
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-TEST-01',
        ]);

        $customer->refresh();
        $this->assertEquals('waiting_acc', $customer->status);

        $survey = $customer->surveys()->first();
        $this->assertNotNull($survey->survey_photo);
        Storage::disk('public')->assertExists($survey->survey_photo);
    }

    /**
     * Regression: customer punya 2 baris Task tipe SURVEY (satu orphan lama yang
     * udah `selesai`, satu lagi yang beneran `in_progress` sekarang) — kejadian
     * nyata di data lapangan (Customer #33). Laporan survey harus nutup task yang
     * BENERAN in_progress, bukan task lama yang gak relevan (yang bakal ke-pilih
     * kalau query `Task::where(...)->first()` gak difilter status).
     */
    public function test_survey_report_completes_the_correct_task_when_customer_has_duplicate_survey_tasks()
    {
        Storage::fake('public');

        $this->seed(DatabaseSeeder::class);

        $pop = Pop::create([
            'code' => 'SMN3',
            'pop_code' => 'SMN3',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 3',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $technician = User::factory()->create();
        $teknisiRole = Role::where('name', 'Teknisi')->first();
        $technician->role_id = $teknisiRole->id;
        $technician->save();

        $customer = Customer::create([
            'customer_code' => 'TEST-003',
            'full_name' => 'Test Customer Duplicate Task',
            'primary_phone' => '0812345680',
            'status' => 'survey_in_progress',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        // Task lama, orphan, ID lebih kecil — SUDAH selesai, gak relevan lagi.
        $oldTask = Task::create([
            'task_number' => 'TASK-OLD-0001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey lama (orphan)',
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => now()->subDays(3),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);

        // Task yang beneran aktif sekarang, ID lebih besar — ini yang harus di-complete.
        $activeTask = Task::create([
            'task_number' => 'TASK-NEW-0001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey aktif',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $activeTask->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        $surveyData = [
            'survey_status' => 'completed',
            'survey_date' => now()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'technician_id' => $technician->id,
            'cable_estimation_meter' => 40,
            'nearest_odp' => 'ODP-TEST-03',
            'house_photo' => UploadedFile::fake()->image('house.jpg'),
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
            'survey_note' => 'Test survey note',
            'difficulty_level' => 'MUDAH',
        ];

        $this->actingAs($technician)
            ->post(route('customers.survey.store', $customer->id), $surveyData)
            ->assertStatus(302);

        $activeTask->refresh();
        $oldTask->refresh();

        $this->assertEquals(TaskStatus::SELESAI->value, $activeTask->status->value);
        $this->assertNotNull($activeTask->completed_at);

        // Task lama gak boleh ke-utak-atik sama sekali.
        $this->assertEquals(TaskStatus::SELESAI->value, $oldTask->status->value);
        $this->assertTrue($oldTask->completed_at->lt(now()->subDay()));
    }

    public function test_unauthorized_user_cannot_fill_survey()
    {
        $this->seed(DatabaseSeeder::class);

        $pop = Pop::create([
            'code' => 'SMN2',
            'pop_code' => 'SMN2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 2',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $finance = User::factory()->create();
        $financeRole = Role::where('name', 'Helpdesk')->first();
        $finance->role_id = $financeRole->id;
        $finance->save();

        $customer = Customer::create([
            'customer_code' => 'TEST-002',
            'full_name' => 'Test Customer 2',
            'primary_phone' => '0812345679',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $surveyData = [
            'survey_status' => 'completed',
            'survey_date' => now()->format('Y-m-d'),
        ];

        $response = $this->actingAs($finance)
            ->post(route('customers.survey.store', $customer->id), $surveyData);

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_view_survey_queue()
    {
        $this->seed(DatabaseSeeder::class);

        $pop = Pop::create([
            'code' => 'SMN4',
            'pop_code' => 'SMN4',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 4',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $technician = User::factory()->create();
        $teknisiRole = Role::where('name', 'Teknisi')->first();
        $technician->role_id = $teknisiRole->id;
        $technician->save();

        Customer::create([
            'customer_code' => 'TEST-004',
            'full_name' => 'Test Customer 4',
            'primary_phone' => '0812345681',
            'status' => 'waiting_survey',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $response = $this->actingAs($technician)
            ->get(route('surveys.queue'));

        $response->assertStatus(200);
        $response->assertSee('Test Customer 4');
    }
}
