<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Empat rancangan dari docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md
 * yang dikerjakan bersamaan:
 *
 * 1. Lebih bayar informatif (`payments.overpay_amount`) — catatan, BUKAN saldo
 *    kredit (§D-5 tetap di luar scope: `amount` masih dibatasi sisa tagihan).
 * 2. Baris anak "Cicilan Ke-N" yang bisa di-expand di list tagihan (§D-4).
 * 3. Kolom "Cicilan Ke-N" + badge Cicil/Lunas di Detail tagihan (§D-4).
 * 4. Petunjuk konsekuensi cicil di form input pembayaran.
 */
class InstallmentAndOverpayDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    private function owner(): User
    {
        return User::where('email', 'owner@whusnet.net')->firstOrFail();
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => 'POP-'.$code,
            'pop_code' => $code,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createInvoice(Pop $pop, float $totalAmount = 150000): Invoice
    {
        $customer = Customer::create([
            'customer_code' => 'C-CIC-'.random_int(1000, 9999),
            'full_name' => 'Pelanggan Cicilan Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Cicilan Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Cicilan Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $totalAmount,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-CIC-'.random_int(1000, 9999),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'remaining_amount' => $totalAmount,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    private function addPayment(Invoice $invoice, float $amount, string $date, string $status = 'valid'): Payment
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-CIC-'.random_int(10000, 99999),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => $date,
            'payment_method' => 'cash',
            'amount' => $amount,
            'received_by' => $this->owner()->id,
            'payment_status' => $status,
        ]);

        $invoice->recalculateFromPayments();

        return $payment;
    }

    // ---------------------------------------------------------------
    // 1. Lebih bayar
    // ---------------------------------------------------------------

    public function test_amount_over_remaining_is_auto_split_into_overpay(): void
    {
        // Skenario laporan asli: sisa 141.097, admin terima 200.000 total.
        // Sebelum diperbaiki, admin harus HITUNG SENDIRI "200000 - 141097"
        // dan pisahkan ke dua field — gampang salah ketik, dan field
        // `amount` yang dibatasi `max` bikin submit 200000 langsung ditolak
        // browser sebelum sempat kirim ke server. Sekarang: server yang
        // membagi otomatis.
        $pop = $this->createPop('OVR1');
        $invoice = $this->createInvoice($pop, 141097);

        $response = $this->actingAs($this->owner())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 200000,
        ]);

        $response->assertRedirect(route('invoices.show', $invoice->id));

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('141097.00', $payment->amount);
        $this->assertSame('58903.00', $payment->overpay_amount);

        $invoice->refresh();
        $this->assertSame('141097.00', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->remaining_amount);
        $this->assertSame('lunas', $invoice->invoice_status->value);
    }

    public function test_amount_exactly_equal_to_remaining_has_no_overpay(): void
    {
        $pop = $this->createPop('OVR2');
        $invoice = $this->createInvoice($pop, 150000);

        $this->actingAs($this->owner())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 150000,
        ]);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('150000.00', $payment->amount);
        $this->assertNull($payment->overpay_amount);
    }

    public function test_amount_under_remaining_stays_a_normal_partial_payment(): void
    {
        $pop = $this->createPop('OVR2B');
        $invoice = $this->createInvoice($pop, 150000);

        $this->actingAs($this->owner())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
        ]);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('50000.00', $payment->amount);
        $this->assertNull($payment->overpay_amount);

        $invoice->refresh();
        $this->assertSame('sebagian', $invoice->invoice_status->value);
    }

    public function test_cannot_pay_an_already_lunas_invoice(): void
    {
        $pop = $this->createPop('OVR2C');
        $invoice = $this->createInvoice($pop, 150000);
        $this->addPayment($invoice, 150000, '2026-06-01');

        $invoice->refresh();
        $this->assertSame('lunas', $invoice->invoice_status->value);

        $response = $this->actingAs($this->owner())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_overpay_badge_appears_on_customer_detail_and_ignores_rejected_payment(): void
    {
        $pop = $this->createPop('OVR3');
        $invoice = $this->createInvoice($pop, 150000);

        $valid = $this->addPayment($invoice, 100000, '2026-06-13');
        $valid->update(['overpay_amount' => 30000]);

        $rejected = $this->addPayment($invoice, 50000, '2026-06-14', 'ditolak');
        $rejected->update(['overpay_amount' => 99000]);

        $response = $this->actingAs($this->owner())->get(route('customers.show', $invoice->customer_id));

        $response->assertOk();
        $response->assertSee('Lebih Bayar: Rp 30.000');
        $response->assertDontSee('Lebih Bayar: Rp 129.000');
    }

    // ---------------------------------------------------------------
    // 2. Baris anak cicilan di list tagihan
    // ---------------------------------------------------------------

    public function test_invoice_list_renders_installment_child_rows_for_partially_paid_invoice(): void
    {
        $pop = $this->createPop('CIC1');
        $invoice = $this->createInvoice($pop, 150000);
        $this->addPayment($invoice, 50000, '2026-06-13');
        $this->addPayment($invoice, 40000, '2026-06-20');

        $invoice->refresh();
        $this->assertSame('sebagian', $invoice->invoice_status->value);

        $response = $this->actingAs($this->owner())->get(route('invoices.index'));

        $response->assertOk();
        $response->assertSee('Cicilan Ke-1');
        $response->assertSee('Cicilan Ke-2');
        $response->assertSee('toggleInstallments('.$invoice->id.',', false);
    }

    public function test_invoice_list_has_no_installment_rows_once_invoice_is_lunas(): void
    {
        $pop = $this->createPop('CIC2');
        $invoice = $this->createInvoice($pop, 150000);
        $this->addPayment($invoice, 100000, '2026-06-13');
        $this->addPayment($invoice, 50000, '2026-06-20');

        $invoice->refresh();
        $this->assertSame('lunas', $invoice->invoice_status->value);

        $response = $this->actingAs($this->owner())->get(route('invoices.index'));

        $response->assertOk();
        // §D-4: begitu lunas, card induk tak lagi jadi parent — cukup Total & Sisa.
        $response->assertDontSee('toggleInstallments('.$invoice->id.',', false);
        $response->assertDontSee('Cicilan Ke-1');
    }

    // ---------------------------------------------------------------
    // 3. Kolom Cicilan Ke-N di Detail tagihan
    // ---------------------------------------------------------------

    public function test_invoice_detail_numbers_installments_ascending_and_marks_the_settling_one(): void
    {
        $pop = $this->createPop('CIC3');
        $invoice = $this->createInvoice($pop, 150000);
        $this->addPayment($invoice, 50000, '2026-06-13');
        $this->addPayment($invoice, 50000, '2026-06-20');
        $this->addPayment($invoice, 50000, '2026-06-27');

        $response = $this->actingAs($this->owner())->get(route('invoices.show', $invoice->id));

        $response->assertOk();
        $response->assertSee('Cicilan Ke-1');
        $response->assertSee('Cicilan Ke-2');
        $response->assertSee('Cicilan Ke-3');
        $response->assertSee('Cicil');
        $response->assertSee('Lunas');
    }

    public function test_rejected_payment_does_not_consume_an_installment_number(): void
    {
        $pop = $this->createPop('CIC4');
        $invoice = $this->createInvoice($pop, 150000);

        $this->addPayment($invoice, 50000, '2026-06-13');
        $this->addPayment($invoice, 50000, '2026-06-20', 'ditolak');
        $this->addPayment($invoice, 50000, '2026-06-27');

        $response = $this->actingAs($this->owner())->get(route('invoices.show', $invoice->id));

        $response->assertOk();
        $response->assertSee('Cicilan Ke-1');
        $response->assertSee('Cicilan Ke-2');
        // Hanya 2 pembayaran valid — nomor tak boleh melompat ke Ke-3.
        $response->assertDontSee('Cicilan Ke-3');
    }

    // ---------------------------------------------------------------
    // 4. Petunjuk cicilan di form input pembayaran
    // ---------------------------------------------------------------

    public function test_payment_form_exposes_next_installment_number_and_overpay_field(): void
    {
        $pop = $this->createPop('CIC5');
        $invoice = $this->createInvoice($pop, 150000);
        $this->addPayment($invoice, 50000, '2026-06-13');

        $response = $this->actingAs($this->owner())->get(route('invoices.payments.create', $invoice->id));

        $response->assertOk();
        $response->assertSee('Nominal Diterima dari Pelanggan');
        // Sudah ada 1 pembayaran valid, jadi input berikutnya = Cicilan Ke-2.
        $response->assertSee('const nextInstallment = 2;', false);
    }

    // ---------------------------------------------------------------
    // 5. Tab Khusus Lebih Bayar
    // ---------------------------------------------------------------

    public function test_overpay_tab_lists_only_payments_with_overpay_and_ignores_rejected(): void
    {
        $pop = $this->createPop('OVRTAB1');
        $invoice1 = $this->createInvoice($pop, 150000);
        $withOverpay = $this->addPayment($invoice1, 150000, '2026-06-13');
        $withOverpay->update(['overpay_amount' => 20000]);

        $invoice2 = $this->createInvoice($pop, 150000);
        $noOverpay = $this->addPayment($invoice2, 150000, '2026-06-14');

        $invoice3 = $this->createInvoice($pop, 150000);
        $rejectedOverpay = $this->addPayment($invoice3, 150000, '2026-06-15', 'ditolak');
        $rejectedOverpay->update(['overpay_amount' => 40000]);

        $response = $this->actingAs($this->owner())->get(route('payments.overpay'));

        $response->assertOk();
        $response->assertSee($withOverpay->payment_number);
        $response->assertDontSee($noOverpay->payment_number);
        $response->assertDontSee($rejectedOverpay->payment_number);
        $response->assertSee('Rp 20.000,00');
    }

    public function test_overpay_tab_link_is_visible_on_payments_index(): void
    {
        $response = $this->actingAs($this->owner())->get(route('payments.index'));

        $response->assertOk();
        $response->assertSee(route('payments.overpay'), false);
    }

    // ---------------------------------------------------------------
    // 6. Modal Bayar Cepat (/invoices index & tab Tagihan pelanggan) —
    //    jalur pembayaran yang SEBENARNYA paling sering dipakai, terpisah
    //    dari payments/create.blade.php. Field lebih bayar + hint cicilan
    //    harus konsisten di sini juga, bukan cuma di form halaman penuh.
    // ---------------------------------------------------------------

    public function test_quick_payment_modal_partial_has_overpay_field_and_installment_hint_elements(): void
    {
        $pop = $this->createPop('QPM1');
        $invoice = $this->createInvoice($pop, 150000);

        $owner = $this->owner();

        // AppServiceProvider::boot() mendaftarkan Gate dari Permission::all()
        // SEKALI saat aplikasi di-boot — yang terjadi SEBELUM RefreshDatabase
        // menyeed tabel permissions. Akibatnya @can('kode.dinamis') di Blade
        // selalu false di test (walau hasPermission() langsung tetap benar),
        // karena Gate::define tak pernah lihat baris permission apa pun.
        // Middleware route `permission:` tak kena masalah ini — dia panggil
        // hasPermission() langsung, bukan lewat Gate. Re-boot manual di sini
        // supaya assertSee terhadap markup yang digerbangi @can() valid.
        (new \App\Providers\AppServiceProvider($this->app))->boot();

        $response = $this->actingAs($owner)->get(route('invoices.index'));

        $response->assertOk();
        $response->assertSee('quick-payment-modal', false);
        $response->assertSee('id="qp-amount"', false);
        $response->assertSee('id="qp-overpay-hint"', false);
        $response->assertSee('id="qp-installment-hint"', false);
        // Field lebih bayar terpisah SUDAH DIHAPUS (2026-08-04) — nominal
        // lebih sekarang otomatis dihitung server dari selisih qp-amount
        // vs sisa tagihan, bukan diketik manual admin.
        $response->assertDontSee('id="qp-overpay"', false);
    }

    public function test_quick_payment_modal_json_path_auto_splits_overpay(): void
    {
        $pop = $this->createPop('QPM2');
        $invoice = $this->createInvoice($pop, 141097);

        $response = $this->actingAs($this->owner())->postJson(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 200000,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('141097.00', $payment->amount);
        $this->assertSame('58903.00', $payment->overpay_amount);

        $invoice->refresh();
        $this->assertSame('141097.00', $invoice->paid_amount);
        $this->assertSame('lunas', $invoice->invoice_status->value);
    }

    // ---------------------------------------------------------------
    // 7. Overpay + cicilan harus konsisten muncul di: Kwitansi, Tagihan
    //    Lunas, Detail Pembayaran, dan semua permukaan Riwayat Pembayaran.
    // ---------------------------------------------------------------

    public function test_payment_detail_shows_installment_and_overpay_badges(): void
    {
        $pop = $this->createPop('SURF1');
        $invoice = $this->createInvoice($pop, 141097);

        $this->actingAs($this->owner())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 200000,
        ]);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();

        $response = $this->actingAs($this->owner())->get(route('payments.show', $payment->id));

        $response->assertOk();
        // Payment ini MELUNASI tagihan (sekaligus lebih bayar) — bukan
        // cicilan yang menyisakan sisa. Label header wajib bilang "Melunasi
        // Tagihan", bukan "Cicilan Ke-1" (regresi 2026-08-04: sebelumnya
        // cuma warna badge yang berubah, teksnya tetap "Cicilan Ke-N"
        // walau payment ini yang melunasi).
        $response->assertSee('Melunasi Tagihan');
        $response->assertDontSee('Cicilan Ke-1');
        $response->assertSee('Lebih Bayar Rp 58.903');
        $response->assertSee('58.903');
    }

    public function test_payment_detail_shows_cicilan_label_when_payment_does_not_settle_invoice(): void
    {
        $pop = $this->createPop('SURF1B');
        $invoice = $this->createInvoice($pop, 150000);

        $this->actingAs($this->owner())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
        ]);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();

        $response = $this->actingAs($this->owner())->get(route('payments.show', $payment->id));

        $response->assertOk();
        $response->assertSee('Cicilan Ke-1');
        $response->assertDontSee('Melunasi Tagihan');
    }

    public function test_payment_receipt_kwitansi_shows_installment_and_overpay(): void
    {
        $pop = $this->createPop('SURF2');
        $invoice = $this->createInvoice($pop, 141097);

        $this->actingAs($this->owner())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 200000,
        ]);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();

        $response = $this->actingAs($this->owner())->get(route('payments.receipt', $payment->id));

        $response->assertOk();
        $response->assertSee('Melunasi Tagihan');
        $response->assertSee('Lebih Bayar');
        $response->assertSee('58.903');
    }

    public function test_invoice_detail_header_shows_total_overpay_when_lunas(): void
    {
        $pop = $this->createPop('SURF3');
        $invoice = $this->createInvoice($pop, 141097);

        $this->actingAs($this->owner())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 200000,
        ]);

        $response = $this->actingAs($this->owner())->get(route('invoices.show', $invoice->id));

        $response->assertOk();
        $response->assertSee('Lebih Bayar Rp 58.903');
    }

    public function test_customer_payment_history_and_global_payment_list_show_overpay_note(): void
    {
        $pop = $this->createPop('SURF4');
        $invoice = $this->createInvoice($pop, 141097);

        $this->actingAs($this->owner())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 200000,
        ]);

        $customerResponse = $this->actingAs($this->owner())->get(route('customers.show', $invoice->customer_id));
        $customerResponse->assertOk();
        $customerResponse->assertSee('+58.903 lebih');

        $listResponse = $this->actingAs($this->owner())->get(route('payments.index'));
        $listResponse->assertOk();
        $listResponse->assertSee('+58.903 lebih');
    }

    public function test_user_without_payments_view_permission_cannot_open_overpay_tab(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $response = $this->actingAs($teknisi)->get(route('payments.overpay'));

        $response->assertForbidden();
    }
}
