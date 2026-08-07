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
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Halaman Laporan Survey/Pemasangan diakses dari beberapa entry point
 * (Detail Task teknisi, Dashboard Task Saya, Antrean Survey, Verifikasi
 * Queue, Detail Pelanggan) — sebelumnya tombol Kembali & redirect sukses
 * submit HARDCODED ke satu tujuan tetap (Antrean Survey / Verifikasi Queue),
 * jadi teknisi yang masuk dari Detail Task malah dilempar ke halaman admin
 * yang gak relevan buat dia. `return_to` sekarang dikirim eksplisit oleh
 * pemanggil dan dipakai buat "Kembali" + redirect sukses.
 */
class SurveyInstallationReportReturnToTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN-RTN',
            'pop_code' => 'RTN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Return To Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);
    }

    private function makeCustomer(string $status): Customer
    {
        return Customer::create([
            'customer_code' => 'RTN-'.rand(10000, 99999),
            'full_name' => 'Pelanggan Return To Test',
            'primary_phone' => '081234500000',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    public function test_survey_report_page_membawa_return_to_ke_kembali_dan_redirect_sukses(): void
    {
        Storage::fake('public');

        $customer = $this->makeCustomer('survey_in_progress');

        $task = Task::create([
            'task_number' => 'TASK-RTN-SURVEY',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Return To Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $customer->surveys()->create([
            'survey_status' => 'pending',
            'started_at' => now(),
            'technician_id' => $this->technician->id,
        ]);

        $taskDetailUrl = route('tasks.show', $task);

        // GET halaman laporan dengan return_to = Detail Task → tombol Kembali
        // harus mengarah ke Detail Task, bukan Antrean Survey (fallback lama).
        $reportPage = $this->actingAs($this->technician)
            ->get(route('customers.survey.report', ['customer' => $customer->id, 'return_to' => $taskDetailUrl]));

        $reportPage->assertOk();
        $reportPage->assertSee($taskDetailUrl, false);

        // Submit sukses → redirect harus balik ke Detail Task, bukan
        // verifications.queue (hardcoded lama).
        $response = $this->actingAs($this->technician)->post(route('customers.survey.store', $customer->id), [
            'return_to' => $taskDetailUrl,
            'survey_status' => 'completed',
            'survey_date' => now()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'technician_id' => $this->technician->id,
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-RTN-01',
            'house_photo' => UploadedFile::fake()->image('house.jpg'),
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
            'survey_note' => 'Test',
            'difficulty_level' => 'SEDANG',
        ]);

        $response->assertRedirect($taskDetailUrl);
    }

    public function test_survey_report_page_fallback_ke_antrean_survey_tanpa_return_to(): void
    {
        $customer = $this->makeCustomer('survey_in_progress');

        $task = Task::create([
            'task_number' => 'TASK-RTN-FALLBACK',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Fallback Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $customer->surveys()->create([
            'survey_status' => 'pending',
            'started_at' => now(),
            'technician_id' => $this->technician->id,
        ]);

        $reportPage = $this->actingAs($this->technician)
            ->get(route('customers.survey.report', $customer->id));

        $reportPage->assertOk();
        $reportPage->assertSee(route('surveys.queue'), false);
    }

    public function test_survey_report_menolak_return_to_ke_domain_luar(): void
    {
        $customer = $this->makeCustomer('survey_in_progress');

        $task = Task::create([
            'task_number' => 'TASK-RTN-EVIL',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Open Redirect Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $customer->surveys()->create([
            'survey_status' => 'pending',
            'started_at' => now(),
            'technician_id' => $this->technician->id,
        ]);

        $reportPage = $this->actingAs($this->technician)
            ->get(route('customers.survey.report', ['customer' => $customer->id, 'return_to' => 'https://evil.example.com/phish']));

        $reportPage->assertOk();
        $reportPage->assertDontSee('evil.example.com', false);
        $reportPage->assertSee(route('surveys.queue'), false);
    }

    public function test_installation_report_page_membawa_return_to_ke_kembali_dan_redirect_sukses(): void
    {
        Storage::fake('public');

        $customer = $this->makeCustomer('installation_in_progress');

        $task = Task::create([
            'task_number' => 'TASK-RTN-INSTALL',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Return To Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $customer->installations()->create([
            'installation_status' => 'in_progress',
            'started_at' => now(),
            'technician_id' => $this->technician->id,
        ]);

        $taskDetailUrl = route('tasks.show', $task);

        $reportPage = $this->actingAs($this->technician)
            ->get(route('customers.installation.report', ['customer' => $customer->id, 'return_to' => $taskDetailUrl]));

        $reportPage->assertOk();
        $reportPage->assertSee($taskDetailUrl, false);
    }
}
