<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
        
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_technician_can_fill_survey()
    {
        Storage::fake('public');

        $this->seed(\Database\Seeders\DatabaseSeeder::class);

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
            'phone' => '0812345678',
            'status' => 'waiting_survey',
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
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
            'survey_note' => 'Test survey note',
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
        $this->assertEquals('surveyed', $customer->status);
        
        $survey = $customer->surveys()->first();
        $this->assertNotNull($survey->survey_photo);
        Storage::disk('public')->assertExists($survey->survey_photo);
    }

    public function test_unauthorized_user_cannot_fill_survey()
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

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
        $financeRole = Role::where('name', 'Finance/Kasir')->first();
        $finance->role_id = $financeRole->id;
        $finance->save();

        $customer = Customer::create([
            'customer_code' => 'TEST-002',
            'full_name' => 'Test Customer 2',
            'phone' => '0812345679',
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
}
