<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Notifications\AppPushNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Push device pelanggan (ADHOC-288 Tahap 7) buat tagihan terbit & pembayaran
 * berhasil — lewat `InvoiceObserver::created()`/`PaymentObserver::created()`
 * supaya SEMUA jalur insert (form manual, command bulanan, kolektor, dst)
 * otomatis ikut, bukan cuma satu controller.
 */
class InvoiceAndPaymentPushCustomerTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(Customer $customer, array $overrides = []): Invoice
    {
        $package = InternetPackage::firstOrFail();

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
        ]);

        return Invoice::create(array_merge([
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'invoice_type' => 'manual',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => now()->format('Y-m'),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 150000,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ], $overrides));
    }

    #[Test]
    public function new_invoice_pushes_customer(): void
    {
        $this->seed(DatabaseSeeder::class);
        $pop = Pop::create(['code' => 'SMN', 'pop_code' => 'SMN', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'POP Sooko', 'type' => 'cabang', 'status' => 'active']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $customer->updatePushSubscription('https://push.example.test/invoice', 'key', 'token');

        Notification::fake();

        $invoice = $this->makeInvoice($customer);

        Notification::assertSentTo(
            $customer,
            AppPushNotification::class,
            fn ($notification) => str_contains($notification->title, 'Tagihan') && str_contains($notification->message, $invoice->invoice_number)
        );
    }

    #[Test]
    public function legacy_replay_invoice_does_not_push(): void
    {
        $this->seed(DatabaseSeeder::class);
        $pop = Pop::create(['code' => 'SMN', 'pop_code' => 'SMN', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'POP Sooko', 'type' => 'cabang', 'status' => 'active']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        Notification::fake();

        $this->makeInvoice($customer, ['old_invoice_id' => 999]);

        Notification::assertNotSentTo($customer, AppPushNotification::class);
    }

    #[Test]
    public function new_payment_pushes_customer(): void
    {
        $this->seed(DatabaseSeeder::class);
        $pop = Pop::create(['code' => 'SMN', 'pop_code' => 'SMN', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'POP Sooko', 'type' => 'cabang', 'status' => 'active']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $invoice = $this->makeInvoice($customer);
        $customer->updatePushSubscription('https://push.example.test/payment', 'key', 'token');

        Notification::fake();

        $payment = Payment::create([
            'payment_number' => 'PAY-TEST-'.uniqid(),
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => 150000,
            'balance_used_amount' => 0,
            'received_by' => null,
            'payment_status' => 'valid',
        ]);

        Notification::assertSentTo(
            $customer,
            AppPushNotification::class,
            fn ($notification) => str_contains($notification->title, 'Pembayaran') && str_contains($notification->message, $payment->payment_number)
        );
    }

    #[Test]
    public function legacy_replay_payment_does_not_push(): void
    {
        $this->seed(DatabaseSeeder::class);
        $pop = Pop::create(['code' => 'SMN', 'pop_code' => 'SMN', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'POP Sooko', 'type' => 'cabang', 'status' => 'active']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $invoice = $this->makeInvoice($customer);

        Notification::fake();

        Payment::create([
            'payment_number' => 'PAY-TEST-'.uniqid(),
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => 150000,
            'balance_used_amount' => 0,
            'received_by' => null,
            'payment_status' => 'valid',
            'old_payment_id' => 999,
        ]);

        Notification::assertNotSentTo($customer, AppPushNotification::class);
    }
    /**
     * Review ADHOC-297 #7: mayoritas pelanggan tidak pernah mengaktifkan push.
     * Generate ~15 ribu tagihan bulanan dulu = ~15 ribu job antrean kosong.
     */
    #[Test]
    public function pelanggan_tanpa_subscription_tidak_diantrekan_push(): void
    {
        $this->seed(DatabaseSeeder::class);
        $pop = Pop::create(['code' => 'SMN', 'pop_code' => 'SMN', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'POP Sooko', 'type' => 'cabang', 'status' => 'active']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        Notification::fake();

        $this->makeInvoice($customer);

        Notification::assertNotSentTo($customer, AppPushNotification::class);
    }

    /**
     * Review ADHOC-297 #7: job push baru masuk antrean SETELAH transaksi
     * commit — rollback batch tidak boleh menyisakan push "Tagihan Terbit"/
     * "Pembayaran Diterima" untuk data yang tidak pernah ada.
     */
    #[Test]
    public function push_diantrekan_setelah_commit(): void
    {
        $this->assertTrue((new AppPushNotification('Judul', 'Pesan'))->afterCommit);
    }
}
