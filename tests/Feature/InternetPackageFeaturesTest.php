<?php

namespace Tests\Feature;

use App\Models\InternetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternetPackageFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_fitur_paket_yang_diisi_dari_form_tersimpan_dan_tampil_di_daftar(): void
    {
        $this->loginAsAdmin();

        $this->post(route('master.paket.store'), $this->packageData([
            'features_text' => "Unlimited\r\n\r\n  3 AP & 1 Router  \n",
        ]))->assertSessionHasNoErrors();

        $package = InternetPackage::query()->where('package_code', 'MANUAL1G')->firstOrFail();
        $this->assertSame(['Unlimited', '3 AP & 1 Router'], $package->features);

        $this->get(route('master.paket.index'))
            ->assertOk()
            ->assertSee('FITUR PACKAGE')
            ->assertSee('3 AP &amp; 1 Router', false);
    }

    public function test_fitur_paket_dapat_diedit_dan_dikosongkan(): void
    {
        $this->loginAsAdmin();
        $this->post(route('master.paket.store'), $this->packageData([
            'features_text' => "Unlimited\n1 Router",
        ]))->assertSessionHasNoErrors();

        $package = InternetPackage::query()->where('package_code', 'MANUAL1G')->firstOrFail();

        $this->get(route('master.paket.edit', $package))
            ->assertOk()
            ->assertSee('Unlimited'."\n".'1 Router');

        $this->put(route('master.paket.update', $package), $this->packageData([
            'features_text' => '2 Public Static',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(['2 Public Static'], $package->fresh()->features);

        $this->put(route('master.paket.update', $package), $this->packageData([
            'features_text' => '',
        ]))->assertSessionHasNoErrors();
        $this->assertSame([], $package->fresh()->features);
    }

    public function test_fitur_paket_terlalu_panjang_ditolak(): void
    {
        $this->loginAsAdmin();

        $this->post(route('master.paket.store'), $this->packageData([
            'features_text' => str_repeat('a', 151),
        ]))->assertSessionHasErrors('features_text');

        $this->assertFalse(InternetPackage::query()->where('package_code', 'MANUAL1G')->exists());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function packageData(array $overrides = []): array
    {
        return array_merge([
            'package_code' => 'MANUAL1G',
            'name' => 'Paket Manual 1G',
            'category' => 'Paket Bisnis Broadband',
            'package_group' => 'Bisnis Broadband',
            'bandwidth_label' => '1 Gbps',
            'monthly_price' => '8900000',
            'is_active' => 1,
        ], $overrides);
    }
}
