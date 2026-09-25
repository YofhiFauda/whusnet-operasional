<?php

namespace Tests\Feature;

use App\Enums\BillingWaiverSource;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Services\BillingPeriodWaiverService;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-87 §4.4/§6 — `GenerateMonthlyInvoicesCommand` tidak boleh menerbitkan
 * tagihan untuk periode yang sudah dibebaskan (Cuti Berlangganan), dan
 * mencabut pembebasan mengembalikan periode itu supaya digenerate lagi.
 */
class GenerateMonthlyInvoicesSkipsWaivedPeriodTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InternetPackageSeeder::class);

        $this->pop = Pop::create([
            'code' => 'POP-GEN',
            'pop_code' => 'GEN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Generator Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function customerWithService(string $code): array
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '0811'.substr($code, -7),
            'registration_date' => now()->subYears(2),
            'pop_id' => $this->pop->id,
            'status' => 'active',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => InternetPackage::first()->id,
            'service_status' => 'aktif',
            'billing_cycle' => 'monthly',
            'package_name_snapshot' => 'Paket Test',
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
            'activation_date' => now()->subYears(2),
        ]);

        return [$customer, $service];
    }

    #[Test]
    public function periode_belum_terbit_yang_dicuti_tidak_ikut_digenerate_pelanggan_lain_tetap(): void
    {
        $actor = $this->loginAsAdmin();
        [$customerCuti] = $this->customerWithService('C-GEN-000001');
        [$customerNormal] = $this->customerWithService('C-GEN-000002');

        $targetPeriod = now()->addMonth()->format('Y-m');

        app(BillingPeriodWaiverService::class)->waive(
            $customerCuti,
            [$targetPeriod],
            'Cuti bulan depan',
            BillingWaiverSource::LEAVE,
            $actor,
        );

        $this->artisan('billing:generate-monthly-invoices', ['--period' => $targetPeriod])
            ->assertExitCode(0);

        $this->assertSame(0, Invoice::where('customer_id', $customerCuti->id)->where('billing_period', $targetPeriod)->count());
        $this->assertSame(1, Invoice::where('customer_id', $customerNormal->id)->where('billing_period', $targetPeriod)->count());
    }

    #[Test]
    public function periode_sudah_terbit_yang_dibebaskan_tidak_digenerate_ulang(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService('C-GEN-000003');
        $period = now()->format('Y-m');

        Invoice::create([
            'invoice_number' => 'INV-GEN-EXIST',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $service->internet_package_id,
            'billing_period' => $period,
            'issue_date' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->addDays(9)->toDateString(),
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);

        app(BillingPeriodWaiverService::class)->waive($customer, [$period], 'Dibebaskan', BillingWaiverSource::LEAVE, $actor);

        $this->artisan('billing:generate-monthly-invoices', ['--period' => $period])
            ->assertExitCode(0);

        $this->assertSame(1, Invoice::where('customer_id', $customer->id)->where('billing_period', $period)->count());
        $this->assertSame('batal', Invoice::where('customer_id', $customer->id)->where('billing_period', $period)->first()->invoice_status->value);
    }

    #[Test]
    public function cabut_waiver_membuat_generator_menerbitkan_lagi(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer] = $this->customerWithService('C-GEN-000004');
        $targetPeriod = now()->addMonth()->format('Y-m');

        $waiver = app(BillingPeriodWaiverService::class)->waive(
            $customer,
            [$targetPeriod],
            'Cuti dulu',
            BillingWaiverSource::LEAVE,
            $actor,
        )->first();

        $this->artisan('billing:generate-monthly-invoices', ['--period' => $targetPeriod])->assertExitCode(0);
        $this->assertSame(0, Invoice::where('customer_id', $customer->id)->where('billing_period', $targetPeriod)->count());

        app(BillingPeriodWaiverService::class)->revoke($waiver, $actor, 'Batal cuti, ternyata masih pakai');

        $this->artisan('billing:generate-monthly-invoices', ['--period' => $targetPeriod])->assertExitCode(0);
        $this->assertSame(1, Invoice::where('customer_id', $customer->id)->where('billing_period', $targetPeriod)->count());
    }
}
