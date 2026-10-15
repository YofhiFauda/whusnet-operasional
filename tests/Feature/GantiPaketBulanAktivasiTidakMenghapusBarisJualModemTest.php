<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use App\Models\CustomerBalanceMutation;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Services\CustomerPackageService;
use App\Services\InitialInvoiceService;
use Carbon\Carbon;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Ganti paket di bulan aktivasi (invoice AWAL masih periode berjalan) dulu
 * membangun ulang `invoice_items` cuma dari kolom invoice — baris "Jual Modem"
 * (Beli dari Gudang) tidak punya kolom, jadi lenyap diam-diam. Subtotal &
 * daftar baris kompak salah (invarian builder tetap lolos), dan kalau invoice
 * sudah lunas, nominal modem malah dikreditkan balik sebagai "deposit
 * downgrade" — padahal modemnya tetap milik pelanggan.
 *
 * Invoice AWAL diterbitkan lewat `InitialInvoiceService::calculate()/issue()`
 * — jalur yang sama dipakai `finalVerify()` untuk PSB Beli dari Gudang.
 */
class GantiPaketBulanAktivasiTidakMenghapusBarisJualModemTest extends TestCase
{
    use RefreshDatabase;

    private const MODEM_PRICE = 300000.0;

    private const INSTALLATION_FEE = 50000.0;

    private Pop $pop;

    private InternetPackage $paketMurah;

    private InternetPackage $paketMahal;

    protected function setUp(): void
    {
        parent::setUp();

        // September 2026 = 30 hari; aktivasi tgl 10 → jendela AWAL Sep11-30
        // (20 hari), ganti paket efektif Sep15.
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'name' => 'POP Jual Modem',
            'type' => 'cabang',
            'code' => 'JMD-PKT',
            'cid_prefix' => 'JMD-PKT',
            'registration_prefix' => 'REG-JMD-PKT',
        ]);

        $this->paketMurah = InternetPackage::create([
            'name' => 'Paket A 10 Mbps', 'package_code' => 'JMD-A-10', 'category' => 'Home', 'package_group' => 'Basic',
            'bandwidth_label' => '10 Mbps', 'download_speed_mbps' => 10, 'upload_speed_mbps' => 10, 'monthly_price' => 150000,
        ]);

        $this->paketMahal = InternetPackage::create([
            'name' => 'Paket B 20 Mbps', 'package_code' => 'JMD-B-20', 'category' => 'Home', 'package_group' => 'Basic',
            'bandwidth_label' => '20 Mbps', 'download_speed_mbps' => 20, 'upload_speed_mbps' => 20, 'monthly_price' => 250000,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array{0: Customer, 1: Invoice}
     */
    private function customerWithAwalInvoice(InternetPackage $package, bool $paid): array
    {
        $admin = $this->loginAsAdmin();

        $customer = Customer::create([
            'full_name' => 'Pelanggan Jual Modem',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-09-01',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $package->id,
            'status' => 'active',
            'customer_code' => 'REG-JMD-PKT-'.$package->id,
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => $package->bandwidth_label,
            'upload_speed_snapshot' => $package->bandwidth_label,
            'monthly_price' => $package->monthly_price,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $package->monthly_price,
            'activation_date' => '2026-09-10',
            'due_date' => '2026-10-10',
            'billing_cycle' => 'monthly',
            'service_status' => 'aktif',
            'billing_status' => 'active',
            'contract_type' => 'beli',
        ]);

        $initial = app(InitialInvoiceService::class);
        $billing = $initial->calculate($service, '2026-09-10', ['extra_installation_fee' => self::INSTALLATION_FEE], self::MODEM_PRICE);
        $invoice = $initial->issue($customer, $service, $billing, '2026-09-10', $admin->id, self::MODEM_PRICE);

        $this->assertEqualsWithDelta(self::MODEM_PRICE, $this->jualModemAmount($invoice), 0.01, 'Prasyarat: invoice AWAL punya baris Jual Modem.');

        if ($paid) {
            Payment::create([
                'payment_number' => 'PAY-JMD-'.uniqid(),
                'invoice_id' => $invoice->id,
                'customer_id' => $customer->id,
                'pop_id' => $invoice->pop_id,
                'payment_date' => now()->toDateString(),
                'payment_method' => PaymentMethod::CASH->value,
                'amount' => $invoice->total_amount,
                'payment_status' => PaymentStatus::VALID->value,
            ]);
            $invoice->recalculateFromPayments();
        }

        return [$customer->fresh(), $invoice->fresh()];
    }

    private function jualModemAmount(Invoice $invoice): float
    {
        return (float) $invoice->items()
            ->whereHas('subcategory', fn ($query) => $query->where('code', 'jual_modem'))
            ->sum('amount');
    }

    /**
     * Porsi paket hasil prorate ulang (rumus CustomerPackageService untuk AWAL:
     * harga / hari-jendela × hari-terpakai; lama Sep11-14 = 4 hari, baru
     * Sep15-30 = 16 hari).
     */
    private function expectedPaketPortion(InternetPackage $old, InternetPackage $new): float
    {
        return round(($old->monthly_price / 20) * 4 + ($new->monthly_price / 20) * 16, 2);
    }

    private function assertJualModemSurvived(Invoice $invoice, float $expectedTotal): void
    {
        $invoice->refresh();

        $this->assertEqualsWithDelta(self::MODEM_PRICE, $this->jualModemAmount($invoice), 0.01, 'Baris Jual Modem wajib tetap ada dengan nominal aslinya.');
        $this->assertEqualsWithDelta($expectedTotal, (float) $invoice->total_amount, 1.0);
        $this->assertEqualsWithDelta((float) $invoice->subtotal, (float) $invoice->items()->sum('amount'), 0.01);
    }

    #[Test]
    public function upgrade_invoice_belum_dibayar_baris_jual_modem_tetap_ada(): void
    {
        [$customer, $invoice] = $this->customerWithAwalInvoice($this->paketMurah, paid: false);

        $this->put(route('customers.package.update', $customer), ['internet_package_id' => $this->paketMahal->id])
            ->assertSessionHasNoErrors();

        $expectedTotal = $this->expectedPaketPortion($this->paketMurah, $this->paketMahal) + self::INSTALLATION_FEE + self::MODEM_PRICE;
        $this->assertJualModemSurvived($invoice, $expectedTotal);
    }

    #[Test]
    public function upgrade_invoice_lunas_tidak_mengkreditkan_nominal_modem_sebagai_deposit(): void
    {
        [$customer, $invoice] = $this->customerWithAwalInvoice($this->paketMurah, paid: true);
        $paid = (float) $invoice->paid_amount;

        $this->put(route('customers.package.update', $customer), ['internet_package_id' => $this->paketMahal->id])
            ->assertSessionHasNoErrors();

        $expectedTotal = $this->expectedPaketPortion($this->paketMurah, $this->paketMahal) + self::INSTALLATION_FEE + self::MODEM_PRICE;
        $this->assertJualModemSurvived($invoice, $expectedTotal);

        // Upgrade → total naik, tidak ada deposit sama sekali.
        $this->assertSame(0, CustomerBalanceMutation::where('customer_id', $customer->id)->count());
        $this->assertEqualsWithDelta($expectedTotal - $paid, (float) $invoice->fresh()->remaining_amount, 1.0);
    }

    #[Test]
    public function downgrade_invoice_lunas_deposit_hanya_dari_selisih_paket_bukan_modem(): void
    {
        [$customer, $invoice] = $this->customerWithAwalInvoice($this->paketMahal, paid: true);
        $paid = (float) $invoice->paid_amount;

        $this->put(route('customers.package.update', $customer), ['internet_package_id' => $this->paketMurah->id])
            ->assertSessionHasNoErrors();

        $expectedTotal = $this->expectedPaketPortion($this->paketMahal, $this->paketMurah) + self::INSTALLATION_FEE + self::MODEM_PRICE;
        $this->assertJualModemSurvived($invoice, $expectedTotal);

        $expectedDeposit = max(0, round($paid - $expectedTotal, 2));
        $depositTotal = (float) CustomerBalanceMutation::where('customer_id', $customer->id)->sum('amount');
        $this->assertEqualsWithDelta($expectedDeposit, $depositTotal, 1.0, 'Deposit tidak boleh memuat nominal modem yang tetap jadi milik pelanggan.');
        $this->assertLessThan(self::MODEM_PRICE, $depositTotal);
    }

    #[Test]
    public function test_package_change_rejected_when_additional_devices_exceed_new_limit(): void
    {
        // Paket lama max_additional_devices = 2, pelanggan punya 2 device
        // tambahan. Paket baru max_additional_devices = 1 → change() wajib
        // RuntimeException SEBELUM mutasi apa pun (ADHOC perangkat tambahan).
        $this->paketMurah->update(['max_additional_devices' => 2]);

        $paketLimitKetat = InternetPackage::create([
            'name' => 'Paket C 5 Mbps', 'package_code' => 'JMD-C-05', 'category' => 'Home', 'package_group' => 'Basic',
            'bandwidth_label' => '5 Mbps', 'download_speed_mbps' => 5, 'upload_speed_mbps' => 5, 'monthly_price' => 100000,
            'max_additional_devices' => 1,
        ]);

        [$customer] = $this->customerWithAwalInvoice($this->paketMurah, paid: false);

        CustomerAdditionalDevice::create(['customer_id' => $customer->id, 'device_type' => 'AP']);
        CustomerAdditionalDevice::create(['customer_id' => $customer->id, 'device_type' => 'STB']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('perangkat tambahan');

        app(CustomerPackageService::class)->change($customer, $paketLimitKetat);
    }
}
