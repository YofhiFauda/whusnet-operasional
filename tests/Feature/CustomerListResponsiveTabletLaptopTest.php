<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerListResponsiveTabletLaptopTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_list_renders_tablet_and_laptop_responsive_elements(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get(route('customers.index'));

        $response->assertStatus(200);

        // Verifikasi container table view beralih di breakpoint lg (hidden lg:block)
        $response->assertSee('hidden lg:block overflow-x-auto', false);

        // Verifikasi container card view beralih di breakpoint lg (block lg:hidden)
        $response->assertSee('block lg:hidden p-3 sm:p-4', false);

        // Verifikasi grid statistik 4 kolom di laptop & macbook (lg:grid-cols-4)
        $response->assertSee('grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4', false);

        // Verifikasi grid multi-filter 3 kolom di laptop (xl:grid-cols-3) dan 6 di widescreen (2xl:grid-cols-6)
        $response->assertSee('grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-6', false);
    }

    public function test_card_indikator_dan_filter_collapsible_di_mobile(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get(route('customers.index'));
        $response->assertStatus(200);

        // Verifikasi toggle Card Indikator untuk mobile
        $response->assertSee('mobileStatsOpen', false);
        $response->assertSee('id="customer-stats-content"', false);
        $response->assertSee('Ringkasan Statistik', false);

        // Verifikasi toggle Card Filter untuk mobile
        $response->assertSee('mobileFilterOpen', false);
        $response->assertSee('id="customer-filters-content"', false);
        $response->assertSee('Filter &amp; Pencarian', false);

        // Verifikasi animasi collapse Alpine terpasang
        $response->assertSee('x-collapse', false);
    }

    public function test_card_filter_menampilkan_indikator_filter_aktif(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get(route('customers.index', ['search' => 'Budi', 'status' => 'active']));
        $response->assertStatus(200);

        // Menampilkan badge filter aktif di mobile header
        $response->assertSee('Filter Aktif', false);
        $response->assertSee('Reset', false);
    }
}
