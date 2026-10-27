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

    #[Test]
    public function test_package_can_be_created_with_max_additional_devices(): void
    {
        $admin = $this->loginAsAdmin();

        $response = $this->actingAs($admin)->post(route('master.paket.store'), $this->payloadPaket([
            'package_code' => 'PKT-ADDDEV-NEW',
            'max_additional_devices' => 3,
        ]));

        $response->assertRedirect(route('master.paket.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('internet_packages', [
            'package_code' => 'PKT-ADDDEV-NEW',
            'max_additional_devices' => 3,
        ]);
    }

    #[Test]
    public function test_package_store_without_max_additional_devices_defaults_to_zero(): void
    {
        $admin = $this->loginAsAdmin();

        // Form lama (atau klien yang belum kirim field ini) tidak boleh gagal.
        $this->actingAs($admin)->post(route('master.paket.store'), $this->payloadPaket([
            'package_code' => 'PKT-ADDDEV-DEF',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('internet_packages', [
            'package_code' => 'PKT-ADDDEV-DEF',
            'max_additional_devices' => 0,
        ]);
    }

    #[Test]
    public function test_package_update_saves_max_additional_devices(): void
    {
        $admin = $this->loginAsAdmin();
        $package = InternetPackage::create($this->payloadPaket([
            'package_code' => 'PKT-ADDDEV-UPD',
            'is_active' => true,
        ]));

        $this->actingAs($admin)->put(route('master.paket.update', $package), $this->payloadPaket([
            'package_code' => 'PKT-ADDDEV-UPD',
            'max_additional_devices' => 5,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(5, $package->fresh()->max_additional_devices);
    }

    #[Test]
    public function test_package_forms_render_max_additional_devices_field(): void
    {
        $admin = $this->loginAsAdmin();
        $package = InternetPackage::create($this->payloadPaket([
            'package_code' => 'PKT-ADDDEV-FORM',
            'is_active' => true,
            'max_additional_devices' => 4,
        ]));

        $this->actingAs($admin)->get(route('master.paket.create'))
            ->assertOk()
            ->assertSee('name="max_additional_devices"', false);

        $this->actingAs($admin)->get(route('master.paket.edit', $package))
            ->assertOk()
            ->assertSee('name="max_additional_devices"', false)
            ->assertSee('value="4"', false);
    }

    #[Test]
    public function test_package_rejects_negative_or_above_limit_additional_devices(): void
    {
        $admin = $this->loginAsAdmin();

        foreach ([-1, 21] as $invalid) {
            $this->actingAs($admin)->post(route('master.paket.store'), $this->payloadPaket([
                'package_code' => 'PKT-ADDDEV-BAD-'.$invalid,
                'max_additional_devices' => $invalid,
            ]))->assertSessionHasErrors('max_additional_devices');
        }

        $this->assertDatabaseMissing('internet_packages', ['package_code' => 'PKT-ADDDEV-BAD--1']);
        $this->assertDatabaseMissing('internet_packages', ['package_code' => 'PKT-ADDDEV-BAD-21']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payloadPaket(array $overrides = []): array
    {
        return array_merge([
            'package_code' => 'PKT-ADDDEV',
            'name' => 'Paket Perangkat Tambahan',
            'category' => 'Paket Home Broadband',
            'package_group' => 'Home',
            'bandwidth_label' => '20 Mbps',
            'download_speed_mbps' => 20,
            'upload_speed_mbps' => 10,
            'monthly_price' => '150000',
            'discount_default' => 0,
            'ppn' => 11,
            'contract_period_months' => 12,
            'is_active' => 1,
        ], $overrides);
    }
}
