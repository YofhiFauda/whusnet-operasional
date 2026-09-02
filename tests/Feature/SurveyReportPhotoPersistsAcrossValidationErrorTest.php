<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerSurvey;
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

/**
 * Regression: Step 2 (Foto Rumah/ODP) di Laporan Survey Lapangan gak boleh
 * ke-reset cuma karena Step 4 (Laporan Survey Lapangan) gagal validasi.
 * Browser gak bisa mempertahankan isi <input type=file> lintas request, jadi
 * foto yang sudah lolos validasi harus diupload & di-stage duluan (session)
 * SEBELUM validasi field lain jalan.
 */
class SurveyReportPhotoPersistsAcrossValidationErrorTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected User $technician;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DatabaseSeeder::class);

        Storage::fake('public');

        $this->pop = Pop::create([
            'code' => 'SMN6',
            'pop_code' => 'SMN6',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 6',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->technician = User::factory()->create();
        $teknisiRole = Role::where('name', 'Teknisi')->first();
        $this->technician->role_id = $teknisiRole->id;
        $this->technician->save();

        $this->customer = Customer::create([
            'customer_code' => 'PHOTO-001',
            'full_name' => 'Test Customer Photo',
            'primary_phone' => '0812345678',
            'status' => 'survey_in_progress',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $task = Task::create([
            'task_number' => 'TASK-PHOTO-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Test Photo',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        // report()/store() butuh CustomerSurvey yang sudah "started" (normalnya
        // dibuat oleh CustomerSurveyController::start()) — dibuat manual di sini
        // biar test fokus ke perilaku staging foto, bukan alur mulai survey.
        CustomerSurvey::create([
            'customer_id' => $this->customer->id,
            'survey_status' => 'pending',
            'started_at' => now(),
        ]);
    }

    public function test_photos_stay_staged_and_reused_when_step_4_fails_validation(): void
    {
        // Attempt 1: foto Step 2 valid, tapi difficulty_level (Step 4) kosong
        // → gagal validasi. Foto tetap harus keupload & ke-stage.
        $firstAttempt = [
            'survey_status' => 'completed',
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-TEST-01',
            'house_photo' => UploadedFile::fake()->image('house.jpg'),
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
            // difficulty_level sengaja gak diisi — bikin Step 4 gagal.
        ];

        $response = $this->actingAs($this->technician)
            ->post(route('customers.survey.store', $this->customer->id), $firstAttempt);

        $response->assertSessionHasErrors('difficulty_level');

        $staged = session("survey_report_photos.{$this->customer->id}");
        $this->assertNotNull($staged);
        $this->assertNotEmpty($staged['house_photo']);
        $this->assertNotEmpty($staged['survey_photo']);
        Storage::disk('public')->assertExists($staged['house_photo']);
        Storage::disk('public')->assertExists($staged['survey_photo']);

        // Halaman Lapor Survey kalau dibuka lagi harus nampilin preview foto
        // yang udah ke-stage, gak nyuruh upload ulang.
        $reportPage = $this->actingAs($this->technician)
            ->get(route('customers.survey.report', $this->customer->id));
        $reportPage->assertOk();
        $reportPage->assertSee('Sudah diunggah', false);

        // Attempt 2: lengkapi difficulty_level TANPA kirim ulang foto — harus
        // sukses karena foto dipakai dari staging attempt 1.
        $secondAttempt = [
            'survey_status' => 'completed',
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-TEST-01',
            'difficulty_level' => 'SEDANG',
        ];

        $this->actingAs($this->technician)
            ->post(route('customers.survey.store', $this->customer->id), $secondAttempt)
            ->assertSessionDoesntHaveErrors()
            ->assertStatus(302);

        $survey = $this->customer->surveys()->first();
        $this->assertNotNull($survey);
        $this->assertEquals($staged['house_photo'], $survey->house_photo);
        $this->assertEquals($staged['survey_photo'], $survey->survey_photo);

        // Staging session dibersihkan begitu laporan beneran tersimpan.
        $this->assertNull(session("survey_report_photos.{$this->customer->id}"));
    }

    public function test_explicit_remove_clears_staged_photo_and_requires_reupload(): void
    {
        $firstAttempt = [
            'survey_status' => 'completed',
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-TEST-01',
            'house_photo' => UploadedFile::fake()->image('house.jpg'),
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
        ];

        $this->actingAs($this->technician)
            ->post(route('customers.survey.store', $this->customer->id), $firstAttempt);

        $staged = session("survey_report_photos.{$this->customer->id}");
        $this->assertNotEmpty($staged['house_photo']);

        // Teknisi klik "Hapus File" buat foto rumah, lalu submit ulang tanpa
        // difficulty_level (masih gagal di step 4) DAN tanpa foto rumah baru.
        $secondAttempt = [
            'survey_status' => 'completed',
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-TEST-01',
            'house_photo_removed' => '1',
            'survey_photo' => UploadedFile::fake()->image('survey2.jpg'),
        ];

        $response = $this->actingAs($this->technician)
            ->post(route('customers.survey.store', $this->customer->id), $secondAttempt);

        $response->assertSessionHasErrors(['difficulty_level', 'house_photo']);

        $restaged = session("survey_report_photos.{$this->customer->id}");
        $this->assertArrayNotHasKey('house_photo', $restaged);
        Storage::disk('public')->assertMissing($staged['house_photo']);
    }
}
