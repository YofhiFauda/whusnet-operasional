<?php

namespace Tests\Feature;

use App\Enums\DeviceAcquisitionSource;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\InternetPackage;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Badge asal modem (Sewa/Beli Sendiri/Beli dari Gudang) di Detail Pelanggan
 * & List Pelanggan — satu sumber kebenaran `customer_devices.acquisition_source`
 * (DeviceAcquisitionSource), bukan string literal baru. Lihat Task 8 rancangan
 * ownership modem sewa/beli.
 */
class CustomerDetailShowsAcquisitionSourceBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'P-TEST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->package = InternetPackage::create([
            'name' => 'Test Package',
            'package_code' => 'PKG-TEST',
            'category' => 'broadband',
            'package_group' => 'basic',
            'bandwidth_label' => '10 Mbps',
            'download_speed' => 10,
            'upload_speed' => 10,
            'monthly_price' => 150000,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function modem_sewa_tidak_menampilkan_badge(): void
    {
        $device = new CustomerDevice([
            'device_type' => 'ONT',
            'acquisition_source' => DeviceAcquisitionSource::SEWA,
        ]);

        $html = Blade::render('<x-warehouse.acquisition-badge :device="$device" />', ['device' => $device]);

        $this->assertSame('', trim($html));
    }

    #[Test]
    public function detail_pelanggan_tampilkan_badge_beli_dari_gudang(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'status' => 'active',
        ]);

        CustomerDevice::create([
            'customer_id' => $customer->id,
            'device_type' => 'ONT',
            'acquisition_source' => DeviceAcquisitionSource::BELI_GUDANG,
        ]);

        $response = $this->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Beli dari Gudang');
    }

    #[Test]
    public function list_pelanggan_tampilkan_badge_sesuai_acquisition_source(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'status' => 'active',
        ]);

        CustomerDevice::create([
            'customer_id' => $customer->id,
            'device_type' => 'ONT',
            'acquisition_source' => DeviceAcquisitionSource::BELI_SENDIRI,
        ]);

        $response = $this->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee('Beli Sendiri');
    }
}
