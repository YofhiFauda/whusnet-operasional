<?php

namespace Tests\Feature;

use App\Models\Customer;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_detail_view_loads_successfully_with_all_tabs(): void
    {
        // 1. Seed database with master data and customer records
        $this->seed(DatabaseSeeder::class);
        $this->seed(CustomerSeeder::class);
        $this->loginAsAdmin();

        // 2. Retrieve first seeded customer with active status
        $customer = Customer::query()->where('status', 'active')->firstOrFail();

        // 3. Request customer detail route
        $response = $this->get("/customers/{$customer->id}");

        // 4. Assert response status is 200 OK
        $response->assertStatus(200);

        // 5. Verify customer info and tab headings are present in response body
        $response->assertSee($customer->full_name);
        $response->assertSee($customer->phone);
        $response->assertSee($customer->email);

        // 6. Verify 11 Tab existence in layout
        $tabs = [
            'Ringkasan',
            'Identitas',
            'Alamat',
            'POP/Cabang',
            'Pemasangan',
            'Paket & Layanan',
            'Billing',
            'Tagihan',
            'Pembayaran',
            'Dokumen',
            'Riwayat Perubahan',
        ];

        foreach ($tabs as $tab) {
            if ($tab === 'Paket & Layanan') {
                $response->assertSee($tab, false);
            } else {
                $response->assertSee($tab);
            }
        }

        // 7. Verify process timeline
        $response->assertSee('TIMELINE PROSES');
        $response->assertSee('Tanggal Registrasi');
        $response->assertSee('Pendaftaran Pelanggan');
        $response->assertSee('Survey Lokasi & Kelayakan');

        // 8. Verify Data Completeness Card
        $response->assertSee('Kelengkapan Data Profil Pelanggan');
        $response->assertSee('Persentase Terisi');
    }
}
