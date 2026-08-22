<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerSurvey;
use App\Models\District;
use App\Models\FopTask;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Skenario nyata: user Sales dari `SalesSeeder` (bukan factory ad-hoc) login
 * lalu registrasi pelanggan dengan Skip Survey aktif. Ini regresi yang ngiket
 * seeder ke fitur — kalau nanti seeder-nya lupa di-assign role/scope/permission
 * yang benar, test ini yang pertama gagal.
 */
class SalesSeederSkipSurveyScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SalesSeeder::class);
    }

    public function test_seeded_sales_user_exists_with_skip_survey_permission_and_all_pop_scope(): void
    {
        $sales = User::where('email', 'sales@whusnet.com')->firstOrFail();

        $this->assertSame('sales', $sales->role->code);
        $this->assertSame('active', $sales->status->value);
        $this->assertTrue($sales->hasPermission('customers.registration.skip_survey'));

        $scope = $sales->roleScopes()->first();
        $this->assertNotNull($scope);
        $this->assertSame('all_pop', $scope->scope_type->value);
    }

    public function test_seeded_sales_user_can_run_the_full_skip_survey_scenario(): void
    {
        Storage::fake('public');

        $sales = User::where('email', 'sales@whusnet.com')->firstOrFail();

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Babadan']);
        $pop = Pop::create([
            'name' => 'POP Babadan',
            'type' => 'cabang',
            'code' => 'BBD',
            'cid_prefix' => 'BBD-01',
            'registration_prefix' => 'REG-BBD',
        ]);
        $package = InternetPackage::create([
            'name' => 'Paket 1',
            'package_code' => 'PKT1',
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '10 Mbps',
            'monthly_price' => 150000,
        ]);

        $response = $this->actingAs($sales)->post('/customers', [
            'full_name' => 'Pelanggan Skip Survey Seeder',
            'identity_number' => '3502182039200077',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234500077',
            'registration_date' => now()->format('Y-m-d'),
            'pop_id' => $pop->id,
            'address' => 'Jl. Seeder Test No. 1',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'contract_period_months' => 12,
            'skip_survey' => '1',
            'latitude' => '-7.86940',
            'longitude' => '111.46210',
            'nearest_odp' => 'ODP-BBD-77',
            'cable_estimation_meter' => 60,
            'difficulty_level' => 'SEDANG',
            'foto_rumah' => UploadedFile::fake()->image('rumah.jpg'),
            'survey_photo' => UploadedFile::fake()->image('odp.jpg'),
        ]);

        $response->assertSessionHasNoErrors();

        $customer = Customer::where('full_name', 'Pelanggan Skip Survey Seeder')->firstOrFail();

        $this->assertSame('waiting_acc', $customer->status);
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'latitude' => -7.86940,
            'longitude' => 111.46210,
        ]);

        $survey = CustomerSurvey::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('completed', $survey->survey_status);
        $this->assertSame($sales->id, $survey->technician_id);
        $this->assertSame('ODP-BBD-77', $survey->nearest_odp);

        $this->assertSame(0, Task::where('customer_id', $customer->id)->count());
        $this->assertSame(0, FopTask::where('customer_id', $customer->id)->count());
    }
}
