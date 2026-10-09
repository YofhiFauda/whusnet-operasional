<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Pop;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke test — Detail Pelanggan wajib render tanpa error PHP/Blade setelah
 * ADHOC-87 (disederhanakan 2026-09-24): form Request Putus Langganan gak
 * lagi bawa dropdown periode, section "Cuti Berlangganan / Bebaskan Tagihan"
 * tampil independen (termasuk untuk pelanggan `terminated`).
 */
class CustomerShowRendersWithBillingWaiverSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InternetPackageSeeder::class);
    }

    private function customerWithService(string $status): Customer
    {
        $pop = Pop::create([
            'code' => 'POP-SHOW', 'pop_code' => 'SHW', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Show Test', 'type' => 'cabang', 'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-SHW-'.$status,
            'full_name' => 'Pelanggan Show '.$status,
            'primary_phone' => '0813'.substr(md5($status), 0, 7),
            'registration_date' => now()->subYears(2),
            'pop_id' => $pop->id,
            'status' => $status,
            'terminated_at' => $status === 'terminated' ? now() : null,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => InternetPackage::first()->id,
            'service_status' => $status === 'terminated' ? 'berhenti' : 'aktif',
            'billing_cycle' => 'monthly',
            'package_name_snapshot' => 'Paket Test',
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
            'activation_date' => now()->subYears(2),
        ]);

        return $customer->fresh();
    }

    #[Test]
    public function detail_pelanggan_aktif_render_ok(): void
    {
        $this->loginAsAdmin();
        $customer = $this->customerWithService('active');

        $response = $this->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Request Putus Langganan');
        $response->assertSee('Cuti Berlangganan');
        $response->assertDontSee('Bebaskan Tagihan Periode (opsional)');
    }

    #[Test]
    public function detail_pelanggan_terminated_tetap_tampil_bebaskan_tagihan(): void
    {
        $this->loginAsAdmin();
        $customer = $this->customerWithService('terminated');

        $response = $this->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertSee('CUTI BERLANGGANAN / BEBASKAN TAGIHAN', false);
    }
}
