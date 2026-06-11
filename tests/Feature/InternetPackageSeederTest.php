<?php

namespace Tests\Feature;

use App\Models\InternetPackage;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternetPackageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_whusnet_internet_package_seed_has_expected_totals(): void
    {
        $this->seed(InternetPackageSeeder::class);

        $this->assertSame(27, InternetPackage::query()->count());
        $this->assertSame(10, InternetPackage::query()->where('category', 'Paket Home Broadband')->count());
        $this->assertSame(7, InternetPackage::query()->where('category', 'Paket Bisnis Broadband')->count());
        $this->assertSame(6, InternetPackage::query()->where('category', 'Paket Bisnis UKM')->count());
        $this->assertSame(4, InternetPackage::query()->where('category', 'Paket Bisnis Dedicated')->count());
    }

    public function test_whusnet_internet_package_seed_stores_package_details(): void
    {
        $this->seed(InternetPackageSeeder::class);

        $homePackage = InternetPackage::query()->where('package_code', 'Net198')->firstOrFail();
        $businessPackage = InternetPackage::query()->where('package_code', 'NetSo1G')->firstOrFail();
        $smePackage = InternetPackage::query()->where('package_code', 'NetBLite110')->firstOrFail();

        $this->assertSame('200.00', $homePackage->download_speed_mbps);
        $this->assertSame('198000.00', $homePackage->monthly_price);
        $this->assertSame('Dualband Wifi6', $homePackage->modem);
        $this->assertSame(['CCTV 1CH'], $homePackage->features);
        $this->assertSame(6, $homePackage->contract_period_months);

        $this->assertSame('1000.00', $businessPackage->download_speed_mbps);
        $this->assertSame(4, $businessPackage->contention_ratio);
        $this->assertSame('2 Public Static', $businessPackage->ip_address_type);

        $this->assertSame(15, $smePackage->max_users);
        $this->assertSame(['Login Portal', 'Bandwidth Management', '1 AP Wifi6 + 1 AP Wifi5'], $smePackage->features);
    }

    public function test_whusnet_internet_package_seed_is_idempotent(): void
    {
        $this->seed(InternetPackageSeeder::class);
        $this->seed(InternetPackageSeeder::class);

        $this->assertSame(27, InternetPackage::query()->count());
    }
}
