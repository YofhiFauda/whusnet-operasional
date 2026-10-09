<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Teknisi boleh mengoreksi Data Diri (Step 1) langsung dari Pelaporan Survey.
 * Guard-nya sama dengan store(): tahap survey harus berjalan dan teknisi wajib
 * anggota tim Task survey. Field jaringan & wilayah tidak ikut terubah.
 */
class SurveyIdentityEditTest extends TestCase
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
            'code' => 'SMN-IDEDIT',
            'pop_code' => 'IDE',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Identity Edit Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeUser(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->first();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    private function makeCustomer(string $status = 'survey_in_progress'): Customer
    {
        return Customer::create([
            'customer_code' => 'IDE-'.rand(10000, 99999),
            'full_name' => 'Nama Salah Ketik',
            'primary_phone' => '081234500000',
            'address' => 'Jl. Lama No. 1',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function assignSurveyTask(Customer $customer, User $technician): void
    {
        $task = Task::create([
            'task_number' => 'TASK-IDE-'.rand(10000, 99999),
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Task Identity Edit Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);

        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'full_name' => 'Nama Sudah Benar',
            'identity_number' => '3507011234560001',
            'primary_phone' => '081299887766',
            'alternative_phone' => '',
            'email' => 'pelanggan@example.com',
            'address' => 'Jl. Baru No. 99',
            'latitude' => '-7.8754321',
            'longitude' => '111.4623456',
        ];
    }

    #[Test]
    public function test_assigned_technician_can_correct_identity_during_survey(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignSurveyTask($customer, $technician);

        $response = $this->actingAs($technician)->put(
            route('customers.survey.update-identity', $customer->id),
            $this->validPayload()
        );

        $response->assertRedirect(route('customers.survey.report', $customer->id));
        $response->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('Nama Sudah Benar', $customer->full_name);
        $this->assertSame('081299887766', $customer->primary_phone);
        $this->assertSame('Jl. Baru No. 99', $customer->address);
        $this->assertSame('Jl. Baru No. 99', $customer->customerAddress()->value('full_address'));
        $this->assertEqualsWithDelta(-7.8754321, (float) $customer->latitude, 0.0000001);
        $this->assertEqualsWithDelta(111.4623456, (float) $customer->longitude, 0.0000001);

        $this->assertTrue(AuditLog::where('action', 'update_identity_verifikasi')
            ->where('auditable_id', $customer->id)
            ->where('user_id', $technician->id)
            ->exists());
    }

    #[Test]
    public function test_assigned_technician_can_correct_city_district_and_village(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignSurveyTask($customer, $technician);

        $city = City::create(['name' => 'Kota Uji Wilayah']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Kecamatan Uji']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Desa Uji']);

        $response = $this->actingAs($technician)->put(
            route('customers.survey.update-identity', $customer->id),
            array_merge($this->validPayload(), [
                'city_id' => $city->id,
                'district_id' => $district->id,
                'village_id' => $village->id,
            ])
        );

        $response->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame($city->id, $customer->city_id);
        $this->assertSame($district->id, $customer->district_id);
        $this->assertSame($village->id, $customer->village_id);
        $this->assertSame('Desa Uji', $customer->customerAddress()->value('village'));
    }

    #[Test]
    public function test_technician_not_assigned_cannot_edit_identity(): void
    {
        $technician = $this->makeUser('teknisi');
        $otherTechnician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignSurveyTask($customer, $otherTechnician);

        $response = $this->actingAs($technician)->put(
            route('customers.survey.update-identity', $customer->id),
            $this->validPayload()
        );

        $response->assertStatus(403);
        $this->assertSame('Nama Salah Ketik', $customer->fresh()->full_name);
    }

    #[Test]
    public function test_identity_cannot_be_edited_after_survey_stage(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer('surveyed');
        $this->assignSurveyTask($customer, $technician);

        $response = $this->actingAs($technician)->put(
            route('customers.survey.update-identity', $customer->id),
            $this->validPayload()
        );

        $response->assertStatus(403);
        $this->assertSame('Nama Salah Ketik', $customer->fresh()->full_name);
    }

    #[Test]
    public function test_invalid_phone_is_rejected(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignSurveyTask($customer, $technician);

        $response = $this->actingAs($technician)->put(
            route('customers.survey.update-identity', $customer->id),
            array_merge($this->validPayload(), ['primary_phone' => '12345'])
        );

        $response->assertSessionHasErrors('primary_phone');
        $this->assertSame('081234500000', $customer->fresh()->primary_phone);
    }

    #[Test]
    public function test_survey_report_page_shows_identity_edit_form(): void
    {
        $technician = $this->makeUser('teknisi');
        $customer = $this->makeCustomer();
        $this->assignSurveyTask($customer, $technician);
        $customer->latestSurvey()->create(['started_at' => now()]);

        $response = $this->actingAs($technician)->get(route('customers.survey.report', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('id="identity-form"', false);
        $response->assertSee(route('customers.survey.update-identity', $customer->id), false);
    }
}
