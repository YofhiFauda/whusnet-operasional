<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankAccount;
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
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Metode Bayar "Transfer" wajib memilih rekening dari Master Rekening Bank
 * (ADHOC-95, PaymentMethod::requiresBankDetails()). `bank_name`/
 * `account_number` di payment = SNAPSHOT master, bukan input request.
 * Plus field opsional "Nama Pengirim" (Transfer & Kolektor saja).
 */
class PaymentMethodTransferBankFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        // Tanggal bayar di tes ini hardcode Juni 2026. Sejak tutup buku otomatis
        // (ADHOC-96) bulan lewat terkunci, jadi waktu dibekukan di Juni.
        $this->travelTo(Carbon::parse('2026-06-20 10:00:00'));

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_transfer_tanpa_rekening_ditolak(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-TRF-1', 'TRF1', 'POP Transfer Test');
        $invoice = $this->createInvoice($pop, 'INV-TRF-0001');

        // Input teks bebas gaya lama tak lagi diterima sebagai pengganti.
        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'transfer',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'amount' => 150000,
        ]);

        $response->assertSessionHasErrors('bank_account_id');
        $this->assertSame(0, Payment::count());
    }

    public function test_transfer_menyimpan_fk_dan_snapshot_rekening_dari_master_serta_diaudit(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-TRF-2', 'TRF2', 'POP Transfer Test 2');
        $invoice = $this->createInvoice($pop, 'INV-TRF-0002');
        $account = BankAccount::factory()->create([
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
        ]);

        // bank_name/account_number di request SENGAJA beda — harus diabaikan.
        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'transfer',
            'bank_account_id' => $account->id,
            'bank_name' => 'Bank Palsu',
            'account_number' => '999',
            'amount' => 150000,
        ]);

        $response->assertRedirect(route('invoices.show', $invoice->id));

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame($account->id, $payment->bank_account_id);
        $this->assertSame('BCA', $payment->bank_name);
        $this->assertSame('1234567890', $payment->account_number);

        $auditLog = AuditLog::where('auditable_type', Payment::class)
            ->where('auditable_id', $payment->id)
            ->where('action', 'create')
            ->firstOrFail();

        $this->assertSame('BCA', $auditLog->new_values['bank_name']);
        $this->assertSame($account->id, $auditLog->new_values['bank_account_id']);
    }

    public function test_transfer_ke_rekening_nonaktif_ditolak_dengan_pesan_jelas(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-TRF-3', 'TRF3', 'POP Transfer Test 3');
        $invoice = $this->createInvoice($pop, 'INV-TRF-0003');
        $account = BankAccount::factory()->inactive()->create();

        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'transfer',
            'bank_account_id' => $account->id,
            'amount' => 150000,
        ]);

        $response->assertSessionHasErrors(['bank_account_id' => "Rekening {$account->bank_name} {$account->account_number} sudah tidak aktif — pilih rekening lain."]);
        $this->assertSame(0, Payment::count());
        $this->assertSame('belum_dibayar', $invoice->fresh()->invoice_status->value);
    }

    public function test_snapshot_payment_lama_tak_berubah_saat_rekening_diedit_atau_dinonaktifkan(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-TRF-4', 'TRF4', 'POP Transfer Test 4');
        $invoice = $this->createInvoice($pop, 'INV-TRF-0004');
        $account = BankAccount::factory()->create([
            'bank_name' => 'BRI',
            'account_number' => '5550001',
        ]);

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'transfer',
            'bank_account_id' => $account->id,
            'amount' => 150000,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->put(route('master.rekening.update', $account), [
            'bank_name' => 'Mandiri',
            'account_number' => '7770002',
            'account_holder_name' => 'PT Baru',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('master.rekening.toggle', $account));

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertFalse($account->fresh()->is_active);
        $this->assertSame('BRI', $payment->bank_name);
        $this->assertSame('5550001', $payment->account_number);
        $this->assertSame($account->id, $payment->bank_account_id);
    }

    public function test_nama_pengirim_tersimpan_untuk_transfer(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-TRF-5', 'TRF5', 'POP Transfer Test 5');
        $invoice = $this->createInvoice($pop, 'INV-TRF-0005');

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'transfer',
            'bank_account_id' => BankAccount::factory()->create()->id,
            'sender_name' => '  Budi Anak Pelanggan  ',
            'amount' => 150000,
        ])->assertSessionHasNoErrors();

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('Budi Anak Pelanggan', $payment->sender_name);

        $this->actingAs($admin)->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Budi Anak Pelanggan');
    }

    public function test_nama_pengirim_dan_rekening_diabaikan_untuk_cash(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-TRF-6', 'TRF6', 'POP Transfer Test 6');
        $invoice = $this->createInvoice($pop, 'INV-TRF-0006');

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'bank_account_id' => BankAccount::factory()->create()->id,
            'sender_name' => 'Tidak Relevan',
            'amount' => 150000,
        ])->assertSessionHasNoErrors();

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertNull($payment->sender_name);
        $this->assertNull($payment->bank_account_id);
        $this->assertNull($payment->bank_name);
    }

    public function test_nama_pengirim_tersimpan_untuk_kolektor(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-TRF-7', 'TRF7', 'POP Transfer Test 7');
        $invoice = $this->createInvoice($pop, 'INV-TRF-0007');
        $kolektor = User::factory()->create([
            'role_id' => Role::where('code', 'kolektor')->value('id'),
        ]);

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'kolektor',
            'collected_by' => $kolektor->id,
            'sender_name' => 'Tetangga Pelanggan',
            'amount' => 150000,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Tetangga Pelanggan', Payment::where('invoice_id', $invoice->id)->value('sender_name'));
    }

    public function test_payload_json_modal_bayar_cuma_berisi_rekening_aktif(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-TRF-8', 'TRF8', 'POP Transfer Test 8');
        $invoice = $this->createInvoice($pop, 'INV-TRF-0008');
        $active = BankAccount::factory()->create(['bank_name' => 'BCA', 'account_number' => '111', 'account_holder_name' => 'PT A']);
        $inactive = BankAccount::factory()->inactive()->create();

        $response = $this->actingAs($admin)->getJson(route('invoices.show', $invoice->id));

        $response->assertOk();
        $ids = collect($response->json('available_bank_accounts'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertSame('BCA — 111 (a.n. PT A)', collect($response->json('available_bank_accounts'))->firstWhere('id', $active->id)['name']);
    }

    protected function createPop(string $code, string $popCode, string $name): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $popCode,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => $name,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    protected function createInvoice(Pop $pop, string $invoiceNumber): Invoice
    {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $invoiceNumber),
            'full_name' => 'Customer Transfer Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Transfer Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Transfer Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => 'Paket Test 20 Mbps',
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
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
