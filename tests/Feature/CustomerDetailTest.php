<?php

namespace Tests\Feature;

use App\Models\Customer;
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
        $this->seed(\Database\Seeders\CustomerSeeder::class);
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

        // 6. Verify 12 Tab existence in layout
        $tabs = [
            'Ringkasan',
            'Data Diri',
            'Dokumen',
            'Layanan',
            'Referral',
            'Survey',
            'FOP',
            'Pemasangan',
            'Aktivasi',
            'Teknis',
            'Uji Layanan',
            'Pembayaran Awal'
        ];

        foreach ($tabs as $tab) {
            $response->assertSee($tab);
        }

        // 7. Verify new process logs
        $response->assertSee('RIWAYAT PROSES');
        $response->assertSee('Tanggal Registrasi');
        $response->assertSee('Nama Pengguna A');
        $response->assertSee('Nama Pengguna E');

        // 8. Verify Data Completeness Card
        $response->assertSee('Kelengkapan Data Profil Pelanggan');
        $response->assertSee('Persentase Terisi');
    }
}
