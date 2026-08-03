<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDocument;
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
 * Modal Hub Aksi Cepat kehilangan dua jalur yang ada di desain acuan:
 * tombol "Cetak Struk" (kwitansi pembayaran terakhir) dan kartu berkas
 * KTP/Foto Rumah beserta form uploadnya.
 *
 * Keduanya bergantung pada payload /customers/{id}/payment-info — kalau field
 * receipt_url / documents hilang dari sana, tombol dan kartu jadi mati tanpa
 * error yang kelihatan di UI, jadi payload ikut dites.
 */
class CustomerHubModalReceiptAndDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_hub_modal_renders_receipt_button_and_document_upload_forms(): void
    {
        $this->loginAsAdmin();
        $this->createPop();

        $response = $this->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertSee('onclick="printLatestReceipt()"', false);
        $response->assertSee('Cetak Struk', false);
        $response->assertSee('Ganti / Upload KTP', false);
        $response->assertSee('Upload Foto Lokasi', false);
        $response->assertSee('name="document_type" value="ktp"', false);
        $response->assertSee('name="document_type" value="rumah"', false);
    }

    public function test_upload_form_hidden_without_document_upload_permission(): void
    {
        $this->createPop();

        // NOC boleh lihat list pelanggan tapi tidak mengunggah berkas pelanggan.
        $role = Role::where('code', 'noc')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertDontSee('name="document_type" value="ktp"', false);
    }

    public function test_payment_info_returns_receipt_url_and_document_state(): void
    {
        $this->loginAsAdmin();
        $pop = $this->createPop();
        $customer = $this->createCustomer($pop);
        $payment = $this->createPayment($customer, $pop);

        CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type' => 'ktp',
            'file_path' => 'customers/ktp-test.jpg',
            'uploaded_by' => auth()->id(),
        ]);

        $response = $this->getJson(route('customers.payment-info', $customer->id));

        $response->assertStatus(200);
        $response->assertJsonPath('recent_payments.0.receipt_url', route('payments.receipt', $payment->id));
        $response->assertJsonPath('documents.ktp.exists', true);
        // Foto rumah belum diunggah — kartu harus tampil sebagai "Belum".
        $response->assertJsonPath('documents.rumah.exists', false);
        $response->assertJsonPath('documents.rumah.url', null);
        $response->assertJsonPath('documents_upload_url', route('customers.documents.store', $customer->id));
    }

    protected function createPop(): Pop
    {
        return Pop::firstOrCreate(['pop_code' => 'HUB1'], [
            'code' => 'POP-HUB-1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Hub Modal',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    protected function createCustomer(Pop $pop): Customer
    {
        return Customer::create([
            'customer_code' => 'CHUB-0001',
            'full_name' => 'Customer Hub Modal',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Hub Modal',
        ]);
    }

    protected function createPayment(Customer $customer, Pop $pop): Payment
    {
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

        $invoice = Invoice::create([
            'invoice_number' => 'INV-202606-7001',
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
            'paid_amount' => 75000,
            'remaining_amount' => 75000,
            'invoice_status' => 'sebagian',
        ]);

        return Payment::create([
            'payment_number' => 'PAY-202606-7001',
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 75000,
            'received_by' => auth()->id(),
            'payment_status' => 'valid',
        ]);
    }
}
