<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerSurvey;
use App\Models\District;
use App\Models\InternetPackage;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Teknisi boleh mengoreksi Step 1 (Data Diri), Step 3 (Paket), dan Step 4
 * (Laporan Survey) dari Laporan Pemasangan. Guard sama dengan report():
 * status pemasangan berjalan + anggota tim Task pemasangan.
 */
class InstallationReportEditTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN-INSTEDIT',
            'pop_code' => 'INE',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Installation Edit Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeUser(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->first();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    private function makeCustomer(string $status = 'installation_in_progress'): Customer
    {
        return Customer::create([
            'customer_code' => 'INE-'.rand(10000, 99999),
            'full_name' => 'Nama Salah Ketik',
            'primary_phone' => '081234500000',
            'address' => 'Jl. Lama No. 1',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function assignInstallationTask(Customer $customer, User $technician): void
    {
        $task = Task::create([
            'task_number' => 'TASK-INE-'.rand(10000, 99999),
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Task Installation Edit Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);

        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);
    }

    private function makePackage(string $code): InternetPackage
    {
        return InternetPackage::create([
            'package_code' => $code,
            'name' => 'Paket '.$code,
            'category' => 'home',
            'package_group' => 'Net',
            'bandwidth_label' => '50 Mbps',
            'monthly_price' => 500000,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function test_assigned_technician_can_correct_identity_during_installation(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignInstallationTask($customer, $technician);

        $city = City::create(['name' => 'Kota Pemasangan']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Kecamatan Pemasangan']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Desa Pemasangan']);

        $response = $this->actingAs($technician)->put(route('customers.installation.update-identity', $customer->id), [
            'full_name' => 'Nama Sudah Benar',
            'primary_phone' => '081299887766',
            'address' => 'Jl. Baru No. 99',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'latitude' => '-7.8754321',
            'longitude' => '111.4623456',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('Nama Sudah Benar', $customer->full_name);
        $this->assertSame('081299887766', $customer->primary_phone);
        $this->assertSame($city->id, $customer->city_id);
        $this->assertSame($village->id, $customer->village_id);
    }

    #[Test]
    public function test_technician_not_assigned_cannot_edit_identity_or_survey(): void
    {
        $technician = $this->makeUser('teknisi');
        $otherTechnician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignInstallationTask($customer, $otherTechnician);
        $customer->latestSurvey()->create(['started_at' => now(), 'nearest_odp' => 'ODP-LAMA']);

        $this->actingAs($technician)->put(route('customers.installation.update-identity', $customer->id), [
            'full_name' => 'Diubah Orang Lain',
            'primary_phone' => '081299887766',
            'address' => 'Jl. Baru',
        ])->assertStatus(403);

        $this->actingAs($technician)->put(route('customers.installation.update-survey', $customer->id), [
            'nearest_odp' => 'ODP-BARU',
        ])->assertStatus(403);

        $this->assertSame('Nama Salah Ketik', $customer->fresh()->full_name);
        $this->assertSame('ODP-LAMA', $customer->latestSurvey()->value('nearest_odp'));
    }

    #[Test]
    public function test_edit_is_rejected_outside_installation_stage(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('surveyed');
        $this->assignInstallationTask($customer, $technician);

        $this->actingAs($technician)->put(route('customers.installation.update-identity', $customer->id), [
            'full_name' => 'Diubah',
            'primary_phone' => '081299887766',
            'address' => 'Jl. Baru',
        ])->assertStatus(403);

        $this->assertSame('Nama Salah Ketik', $customer->fresh()->full_name);
    }

    #[Test]
    public function test_assigned_technician_can_correct_package_during_installation(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignInstallationTask($customer, $technician);

        $oldPackage = $this->makePackage('OLD-PKG');
        $newPackage = $this->makePackage('NEW-PKG');
        $customer->update(['internet_package_id' => $oldPackage->id]);
        $customer->customerService()->create([
            'internet_package_id' => $oldPackage->id,
            'package_name_snapshot' => $oldPackage->name,
            'total_monthly_bill' => $oldPackage->monthly_price,
            'monthly_price' => $oldPackage->monthly_price,
            'discount' => 0,
            'ppn' => 0,
            'other_fee' => 0,
        ]);

        $response = $this->actingAs($technician)->put(route('customers.installation.update-package', $customer->id), [
            'internet_package_id' => $newPackage->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame($newPackage->id, $customer->fresh()->internet_package_id);
    }

    #[Test]
    public function test_assigned_technician_can_correct_survey_report_during_installation(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignInstallationTask($customer, $technician);
        $customer->latestSurvey()->create(['started_at' => now(), 'nearest_odp' => 'ODP-LAMA', 'cable_estimation_meter' => 10]);

        $response = $this->actingAs($technician)->put(route('customers.installation.update-survey', $customer->id), [
            'nearest_odp' => 'ODP-BARU',
            'cable_estimation_meter' => 25,
            'difficulty_level' => 'SULIT',
            'survey_note' => 'Tiang jauh',
        ]);

        $response->assertSessionHasNoErrors();

        $survey = $customer->latestSurvey()->first();
        $this->assertSame('ODP-BARU', $survey->nearest_odp);
        $this->assertSame(25, (int) $survey->cable_estimation_meter);
        $this->assertSame('SULIT', $survey->difficulty_level);
        $this->assertSame('Tiang jauh', $survey->survey_note);
    }

    #[Test]
    public function test_legacy_survey_note_is_split_into_difficulty_and_note(): void
    {
        $survey = new CustomerSurvey([
            'survey_note' => "Tingkat Kesulitan: SEDANG\nCatatan: Jalur becek",
        ]);

        $this->assertSame(
            ['difficulty_level' => 'SEDANG', 'survey_note' => 'Jalur becek'],
            $survey->difficultyAndNote()
        );
    }

    #[Test]
    public function test_assigned_technician_can_replace_survey_photos_during_installation(): void
    {
        Storage::fake('public');

        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignInstallationTask($customer, $technician);
        $survey = $customer->latestSurvey()->create([
            'started_at' => now(),
            'house_photo' => 'surveys/rumah/lama.jpg',
            'survey_photo' => 'surveys/odp/lama.jpg',
        ]);
        Storage::disk('public')->put('surveys/rumah/lama.jpg', 'x');
        Storage::disk('public')->put('surveys/odp/lama.jpg', 'x');

        $response = $this->actingAs($technician)->put(route('customers.installation.update-photos', $customer->id), [
            'house_photo' => UploadedFile::fake()->image('rumah.jpg'),
        ]);

        $response->assertSessionHasNoErrors();

        $survey->refresh();
        $this->assertStringStartsWith('surveys/rumah/', $survey->house_photo);
        $this->assertNotSame('surveys/rumah/lama.jpg', $survey->house_photo);
        Storage::disk('public')->assertMissing('surveys/rumah/lama.jpg');
        // Foto ODP tidak dikirim → tetap.
        $this->assertSame('surveys/odp/lama.jpg', $survey->survey_photo);
    }

    #[Test]
    public function test_installation_report_shows_survey_photos(): void
    {
        Storage::fake('public');

        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignInstallationTask($customer, $technician);
        $customer->installations()->create(['installation_status' => 'in_progress', 'started_at' => now()]);
        $customer->latestSurvey()->create([
            'started_at' => now(),
            'house_photo' => 'surveys/rumah/ada.jpg',
            'survey_photo' => 'surveys/odp/ada.jpg',
        ]);
        Storage::disk('public')->put('surveys/rumah/ada.jpg', 'x');
        Storage::disk('public')->put('surveys/odp/ada.jpg', 'x');

        $response = $this->actingAs($technician)->get(route('customers.installation.report', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('surveys/rumah/ada.jpg', false);
        $response->assertSee('surveys/odp/ada.jpg', false);
    }

    #[Test]
    public function test_installation_report_page_shows_edit_forms_for_steps_1_to_4(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignInstallationTask($customer, $technician);
        $customer->installations()->create(['installation_status' => 'in_progress', 'started_at' => now()]);
        $customer->latestSurvey()->create(['started_at' => now()]);

        $response = $this->actingAs($technician)->get(route('customers.installation.report', $customer->id));

        $response->assertStatus(200);
        $response->assertSee(route('customers.installation.update-identity', $customer->id), false);
        $response->assertSee(route('customers.installation.update-package', $customer->id), false);
        $response->assertSee(route('customers.installation.update-survey', $customer->id), false);
    }
}
