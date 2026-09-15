<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FAB (Formulir Akan Berlangganan Bisnis) wajib diunggah saat Registrasi
 * kalau kategori paket yang dipilih adalah kategori Bisnis (mis. "Paket
 * Bisnis Broadband/UKM/Dedicated" di package_categories) — BUKAN
 * restricted_packages (itu whitelist paket per role, Skema 1, isinya bisa
 * paket reguler dan gak ada hubungan dengan jenis pelanggan bisnis/rumahan).
 * Lihat CustomerRegistrationRequest::rules() & CustomerController::store().
 */
class CustomerRegistrationFabDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function makePackage(string $code, string $name, string $category): InternetPackage
    {
        return InternetPackage::create([
            'package_code' => $code,
            'name' => $name,
            'category' => $category,
            'package_group' => 'Net',
            'bandwidth_label' => '50 Mbps',
            'monthly_price' => 500000,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validRegistrationPayload(int $packageId): array
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $city = City::create(['name' => 'Kota Uji '.uniqid()]);
        $district = District::create(['city_id' => $city->id, 'name' => 'Kecamatan Uji']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Desa Uji']);

        return [
            'full_name' => 'Pelanggan Bisnis Uji',
            'identity_number' => '1234567890123456',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => now()->toDateString(),
            'pop_id' => $pop->id,
            'address' => 'Jl. Uji No. 1',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $packageId,
            'contract_period_months' => 12,
        ];
    }

    public function test_business_category_package_without_fab_document_is_rejected(): void
    {
        $package = $this->makePackage('BIZ100', 'Bisnis 100', 'Paket Bisnis Broadband');

        $this->loginAsAdmin();

        $response = $this->post(route('customers.store'), $this->validRegistrationPayload($package->id));

        $response->assertSessionHasErrors('fab_document');
        $this->assertDatabaseMissing('customers', ['internet_package_id' => $package->id]);
    }

    public function test_business_category_package_with_fab_document_succeeds_and_stores_document(): void
    {
        Storage::fake('public');

        $package = $this->makePackage('BIZ100', 'Bisnis 100', 'Paket Bisnis UKM');

        $this->loginAsAdmin();

        $payload = $this->validRegistrationPayload($package->id);
        $payload['fab_document'] = UploadedFile::fake()->create('fab.pdf', 500, 'application/pdf');

        $response = $this->post(route('customers.store'), $payload);

        $response->assertSessionDoesntHaveErrors('fab_document');
        $customer = Customer::where('internet_package_id', $package->id)->firstOrFail();

        $document = $customer->documents()->where('document_type', DocumentType::FAB->value)->first();
        $this->assertNotNull($document);
        Storage::disk('public')->assertExists($document->file_path);
    }

    public function test_home_category_package_does_not_require_fab_document(): void
    {
        $package = $this->makePackage('HOME50', 'Home 50', 'Paket Home Broadband');

        $this->loginAsAdmin();

        $response = $this->post(route('customers.store'), $this->validRegistrationPayload($package->id));

        $response->assertSessionDoesntHaveErrors('fab_document');
        $this->assertDatabaseHas('customers', ['internet_package_id' => $package->id]);
    }
}
