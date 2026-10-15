<?php

namespace Tests\Feature\Master;

use App\Models\InternetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternetPackageCrudTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_new_package_defaults_to_zero_additional_devices(): void
    {
        $package = InternetPackage::create([
            'package_code' => 'NET-ADDDEV',
            'name' => 'Net Additional Device',
            'category' => 'Home Broadband',
            'package_group' => 'Net',
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => 150000,
            'is_active' => true,
        ])->fresh();

        // fresh() memaksa baca dari DB supaya default kolom migration (0) yang
        // diuji, bukan nilai yang kebetulan ada di instance model.
        $this->assertSame(0, $package->max_additional_devices);
    }
}
