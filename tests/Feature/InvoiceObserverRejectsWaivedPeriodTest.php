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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-87 §4.4/§6 — periode yang sudah dibebaskan tidak boleh ditagih ulang
 * lewat JALUR MANA PUN (bukan cuma generator terjadwal): input manual,
 * import, tinker. Ditegakkan di `InvoiceObserver`, bukan cuma di command.
 */
class InvoiceObserverRejectsWaivedPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InternetPackageSeeder::class);
    }

    #[Test]
    public function invoice_create_langganan_untuk_periode_ber_waiver_ditolak(): void
    {
        $actor = $this->loginAsAdmin();

        $pop = Pop::create([
            'code' => 'POP-OBS',
            'pop_code' => 'OBS',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Observer Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-OBS-000001',
            'full_name' => 'Pelanggan Observer',
            'primary_phone' => '081100000001',
            'registration_date' => now()->subYears(2),
            'pop_id' => $pop->id,
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

        $period = now()->addMonth()->format('Y-m');

        app(BillingPeriodWaiverService::class)->waive($customer, [$period], 'Cuti', BillingWaiverSource::LEAVE, $actor);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sudah dibebaskan/');

        Invoice::create([
            'invoice_number' => 'INV-OBS-TEST',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $service->internet_package_id,
            'billing_period' => $period,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);
    }
}
