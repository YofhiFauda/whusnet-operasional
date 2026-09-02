<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\InternetPackage;
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
 * Regression: Section 3 (Layanan & Paket) di form Lapor Survey sekarang bisa
 * dikoreksi teknisi/surveyor — paket yang dipilih saat registrasi kadang
 * gak sesuai temuan lapangan. Perubahan langsung update
 * customers.internet_package_id + customer_services (bukan cuma catatan).
 */
class SurveyReportPackageCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected User $technician;

    protected InternetPackage $oldPackage;

    protected InternetPackage $newPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN5',
            'pop_code' => 'SMN5',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 5',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->technician = User::factory()->create();
        $teknisiRole = Role::where('name', 'Teknisi')->first();
        $this->technician->role_id = $teknisiRole->id;
        $this->technician->save();

        $this->oldPackage = InternetPackage::create([
            'package_code' => 'PKG-OLD',
            'name' => 'Home 10 Mbps',
            'category' => 'Paket Home Broadband',
            'package_group' => 'Broadband',
            'bandwidth_label' => '10 Mbps',
            'download_speed_mbps' => 10,
            'upload_speed_mbps' => 10,
            'monthly_price' => 150000,
            'is_active' => true,
        ]);

        $this->newPackage = InternetPackage::create([
            'package_code' => 'PKG-NEW',
            'name' => 'Business 50 Mbps',
            'category' => 'Paket Bisnis Broadband',
            'package_group' => 'Broadband',
            'bandwidth_label' => '50 Mbps',
            'download_speed_mbps' => 50,
            'upload_speed_mbps' => 50,
            'monthly_price' => 500000,
            'is_active' => true,
        ]);
    }

    protected function makeCustomerWithActiveSurveyTask(string $code): Customer
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Test Customer '.$code,
            'primary_phone' => '0812345678',
            'status' => 'survey_in_progress',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->oldPackage->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $customer->customerService()->create([
            'internet_package_id' => $this->oldPackage->id,
            'package_name_snapshot' => $this->oldPackage->name,
            'monthly_price' => $this->oldPackage->monthly_price,
            'discount' => 10000,
            'ppn' => 11,
            'other_fee' => 5000,
            'total_monthly_bill' => 150000,
        ]);

        $task = Task::create([
            'task_number' => 'TASK-'.$code,
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey '.$code,
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        return $customer;
    }

    protected function baseSurveyPayload(): array
    {
        return [
            'survey_status' => 'completed',
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-TEST-01',
            'house_photo' => UploadedFile::fake()->image('house.jpg'),
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
            'survey_note' => 'Test survey note',
            'difficulty_level' => 'SEDANG',
        ];
    }

    public function test_technician_can_correct_internet_package_on_survey_report(): void
    {
        Storage::fake('public');

        $customer = $this->makeCustomerWithActiveSurveyTask('PKG-001');

        $payload = $this->baseSurveyPayload();
        $payload['internet_package_id'] = $this->newPackage->id;

        $this->actingAs($this->technician)
            ->post(route('customers.survey.store', $customer->id), $payload)
            ->assertStatus(302);

        $customer->refresh();
        $this->assertEquals($this->newPackage->id, $customer->internet_package_id);

        $service = $customer->customerService()->first();
        $this->assertEquals($this->newPackage->id, $service->internet_package_id);
        $this->assertEquals('Business 50 Mbps', $service->package_name_snapshot);
        $this->assertEquals(500000, (float) $service->monthly_price);

        // Diskon/PPN/other_fee lama dipertahankan, cuma harga dasar + total yang berubah.
        $this->assertEquals(10000, (float) $service->discount);
        $this->assertEquals(11, (float) $service->ppn);
        $this->assertEqualsWithDelta(548900, (float) $service->total_monthly_bill, 1);
    }

    public function test_survey_report_without_package_change_leaves_package_untouched(): void
    {
        Storage::fake('public');

        $customer = $this->makeCustomerWithActiveSurveyTask('PKG-002');

        $payload = $this->baseSurveyPayload();
        // internet_package_id gak dikirim sama sekali (form dikirim apa adanya).

        $this->actingAs($this->technician)
            ->post(route('customers.survey.store', $customer->id), $payload)
            ->assertStatus(302);

        $customer->refresh();
        $this->assertEquals($this->oldPackage->id, $customer->internet_package_id);

        $service = $customer->customerService()->first();
        $this->assertEquals(150000, (float) $service->monthly_price);
    }
}
