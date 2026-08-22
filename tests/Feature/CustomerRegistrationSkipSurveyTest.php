<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerSurvey;
use App\Models\District;
use App\Models\FopTask;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
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
 * Skip Survey saat Registrasi — Sales bisa lewat tahap survey lapangan asal
 * input data survey (ODP, estimasi kabel, titik koordinat, foto) langsung di
 * form registrasi. Gerbang: permission `customers.registration.skip_survey`.
 */
class CustomerRegistrationSkipSurveyTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    private District $district;

    private Village $village;

    private Pop $pop;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->city = City::create(['name' => 'Ponorogo']);
        $this->district = District::create(['city_id' => $this->city->id, 'name' => 'Babadan']);
        $this->village = Village::create(['district_id' => $this->district->id, 'name' => 'Babadan']);
        $this->pop = Pop::create([
            'name' => 'POP Babadan',
            'type' => 'cabang',
            'code' => 'BBD',
            'cid_prefix' => 'BBD-01',
            'registration_prefix' => 'REG-BBD',
        ]);
        $this->package = InternetPackage::create([
            'name' => 'Paket 1',
            'package_code' => 'PKT1',
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '10 Mbps',
            'monthly_price' => 150000,
        ]);
    }

    private function makeSales(): User
    {
        $role = Role::where('code', 'sales')->firstOrFail();

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function basePayload(): array
    {
        return [
            'full_name' => 'Budi Skip Survey',
            'identity_number' => '3502182039200099',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567899',
            'registration_date' => now()->format('Y-m-d'),
            'pop_id' => $this->pop->id,
            'address' => 'Jl. Skip Survey No. 1',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'village_id' => $this->village->id,
            'internet_package_id' => $this->package->id,
            'contract_period_months' => 12,
        ];
    }

    private function skipSurveyPayload(): array
    {
        return [
            'skip_survey' => '1',
            'latitude' => '-7.86940',
            'longitude' => '111.46210',
            'nearest_odp' => 'ODP-BBD-01',
            'cable_estimation_meter' => 75,
            'difficulty_level' => 'MUDAH',
            'requested_installation_date' => now()->addDays(5)->toDateString(),
            'foto_rumah' => UploadedFile::fake()->image('rumah.jpg'),
            'survey_photo' => UploadedFile::fake()->image('odp.jpg'),
        ];
    }

    /**
     * Regresi: panel field Skip Survey sempat dirender dengan class Tailwind
     * `hidden` dicampur `grid` (dua utility display sama specificity) — kalah
     * cascade, jadi checkbox dicentang TAPI panelnya gak pernah nongol. Fix-nya
     * pindah ke inline style (pola sama surveys/report.blade.php). Cek di sini
     * biar gak keulang.
     */
    public function test_skip_survey_panel_uses_inline_style_not_conflicting_hidden_class(): void
    {
        $sales = $this->makeSales();

        $response = $this->actingAs($sales)->get(route('customers.create'));

        $response->assertOk();
        $response->assertSee('id="skip-survey-fields"', false);
        // Class list panel gak boleh lagi bawa 'hidden' bareng utility display lain.
        $response->assertDontSee('id="skip-survey-fields" class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 hidden"', false);
    }

    public function test_sales_can_skip_survey_with_complete_data(): void
    {
        Storage::fake('public');
        $sales = $this->makeSales();

        $response = $this->actingAs($sales)->post('/customers', array_merge($this->basePayload(), $this->skipSurveyPayload()));

        $response->assertSessionHasNoErrors();

        $customer = Customer::where('full_name', 'Budi Skip Survey')->firstOrFail();

        $this->assertSame('waiting_acc', $customer->status);
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'latitude' => -7.86940,
            'longitude' => 111.46210,
        ]);

        $survey = CustomerSurvey::where('customer_id', $customer->id)->first();
        $this->assertNotNull($survey);
        $this->assertSame('completed', $survey->survey_status);
        $this->assertSame('ODP-BBD-01', $survey->nearest_odp);
        $this->assertEquals(75, $survey->cable_estimation_meter);
        $this->assertNotNull($survey->house_photo);
        $this->assertNotNull($survey->survey_photo);
        $this->assertSame($sales->id, $survey->technician_id);
        $this->assertStringContainsString('Skip Survey', $survey->survey_note);
        $this->assertSame(now()->addDays(5)->toDateString(), $survey->requested_installation_date->toDateString());

        // Gak ada Task/FopTask SURVEY yang lahir sama sekali.
        $this->assertSame(0, Task::where('customer_id', $customer->id)->count());
        $this->assertSame(0, FopTask::where('customer_id', $customer->id)->count());
    }

    public function test_skip_survey_requires_coordinates_and_survey_fields(): void
    {
        Storage::fake('public');
        $sales = $this->makeSales();

        $response = $this->actingAs($sales)->post('/customers', array_merge($this->basePayload(), [
            'skip_survey' => '1',
            // latitude/longitude/nearest_odp/cable_estimation_meter/difficulty_level/foto_rumah/survey_photo sengaja dikosongkan.
        ]));

        $response->assertSessionHasErrors([
            'latitude', 'longitude', 'nearest_odp', 'cable_estimation_meter', 'difficulty_level', 'foto_rumah', 'survey_photo',
        ]);
        $this->assertSame(0, Customer::count());
    }

    public function test_skip_survey_rejects_past_requested_installation_date(): void
    {
        Storage::fake('public');
        $sales = $this->makeSales();

        $response = $this->actingAs($sales)->post('/customers', array_merge(
            $this->basePayload(),
            $this->skipSurveyPayload(),
            ['requested_installation_date' => now()->subDay()->toDateString()]
        ));

        $response->assertSessionHasErrors(['requested_installation_date']);
        $this->assertSame(0, Customer::count());
    }

    public function test_user_without_permission_cannot_skip_survey(): void
    {
        Storage::fake('public');

        $helpdeskRole = Role::where('code', 'helpdesk')->firstOrFail();
        $helpdesk = User::factory()->create(['role_id' => $helpdeskRole->id]);

        $response = $this->actingAs($helpdesk)->post('/customers', array_merge($this->basePayload(), $this->skipSurveyPayload()));

        $response->assertForbidden();
        $this->assertSame(0, Customer::count());
    }

    public function test_normal_registration_without_skip_survey_still_creates_survey_task(): void
    {
        Storage::fake('public');
        $sales = $this->makeSales();

        $response = $this->actingAs($sales)->post('/customers', $this->basePayload());

        $response->assertSessionHasNoErrors();

        $customer = Customer::where('full_name', 'Budi Skip Survey')->firstOrFail();
        $this->assertSame('waiting_survey', $customer->status);
        $this->assertSame(0, CustomerSurvey::where('customer_id', $customer->id)->count());
        $this->assertGreaterThan(0, Task::where('customer_id', $customer->id)->count());
        $this->assertGreaterThan(0, FopTask::where('customer_id', $customer->id)->count());
    }
}
