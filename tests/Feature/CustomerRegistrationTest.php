<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    private District $district;

    private Village $village;

    private Pop $pop;

    private InternetPackage $package;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup initial data required for validation
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

        $role = Role::create(['name' => 'Owner']);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_registration_requires_ktp_and_nik_16_digits()
    {
        Storage::fake('public');

        // Missing KTP and NIK only 10 digits
        $response = $this->actingAs($this->admin)->post('/customers', [
            'full_name' => 'John Doe',
            'identity_number' => '1234567890', // 10 digits
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => now()->format('Y-m-d'),
            'pop_id' => $this->pop->id,
            'address' => 'Jl. Test No. 1',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'village_id' => $this->village->id,
            'internet_package_id' => $this->package->id,
            'contract_period_months' => 12,
            'status' => 'registered', // frontend will send this
            // Missing foto_ktp
        ]);

        $response->assertSessionHasErrors(['identity_number', 'foto_ktp']);
    }

    public function test_registration_success_with_valid_data()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('ktp.jpg');

        $response = $this->actingAs($this->admin)->post('/customers', [
            'full_name' => 'John Doe',
            'identity_number' => '1234567890123456', // 16 digits
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => now()->format('Y-m-d'),
            'pop_id' => $this->pop->id,
            'address' => 'Jl. Test No. 1',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'village_id' => $this->village->id,
            'internet_package_id' => $this->package->id,
            'contract_period_months' => 12,
            'status' => 'registered', // from hidden field
            'foto_ktp' => $file,
        ]);

        $this->assertDatabaseHas('customers', [
            'full_name' => 'John Doe',
            'identity_number' => '1234567890123456',
            'status' => 'waiting_survey', // Enforced by controller
        ]);

        $customer = Customer::first();
        // Registrasi mengarahkan ke halaman detail pelanggan baru (bukan list).
        $response->assertRedirect("/customers/{$customer->id}");
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Test No. 1',
        ]);
    }
}
