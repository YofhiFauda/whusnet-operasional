<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi bug tumpang-tindih REQ ID lintas cabang (jetis_db vs sand_db).
 *
 * RQ000005 ada di DUA dump legacy: di jetis_db milik Hanif Saifulloh (PE000003),
 * di sand_db milik Eva Rosdiana Sari (PE000005). Karena tiap cabang menomori
 * ulang dari 1, nomor RQ/IDBIAYA tidak unik lintas cabang. resolveLegacyInvoiceId
 * dulu mencocokkan pembayaran ke invoice lewat old_request_id + billing_period
 * TANPA scope pelanggan — jadi pembayaran Eva bisa nyangkut ke invoice Hanif
 * yang di-import lebih dulu ("penggunannya Hanif Saifulloh"). Test ini memastikan
 * pembayaran tetap menempel ke pelanggan cabangnya sendiri.
 */
class LegacyPaymentAttachesToWrongBranchInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_payment_stays_on_its_own_branch_when_request_id_collides(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        // Dua cabang, sama-sama punya RQ000005 tapi milik pelanggan berbeda.
        Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
        Pop::firstOrCreate(['pop_code' => 'D'], [
            'code' => 'D', 'name' => 'Sandya', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'D',
        ]);

        // Cabang C (Jetis) di-import DULUAN — invoice Hanif memakai RQ000005.
        $this->importBranch(
            popCode: 'C',
            oldCustomerId: 'PE000003',
            fullName: 'Hanif Saifulloh',
            oldInvoiceId: 'IN000018C',
            paymentId: 'PAY-C-1',
            paymentAmount: 176000,
        );

        // Cabang D (Sandya) di-import setelahnya — RQ000005 & periode tagihan SAMA,
        // tapi pembayaran Eva hanya membawa old_request_id (tanpa old_invoice_id),
        // sehingga memaksa fallback lookup lewat old_request_id + billing_period.
        $this->importBranch(
            popCode: 'D',
            oldCustomerId: 'PE000005',
            fullName: 'Eva Rosdiana Sari',
            oldInvoiceId: 'IN000018D',
            paymentId: 'PAY-D-1',
            paymentAmount: 165000,
            paymentByRequestOnly: true,
        );

        $hanif = Customer::where('old_customer_id', 'PE000003')->firstOrFail();
        $eva = Customer::where('old_customer_id', 'PE000005')->firstOrFail();

        // Kedua pelanggan RQ000005 harus tetap terpisah, di POP masing-masing.
        $this->assertNotSame($hanif->id, $eva->id);
        $this->assertSame('C', $hanif->pop->pop_code);
        $this->assertSame('D', $eva->pop->pop_code);

        $evaPayment = Payment::where('old_payment_id', 'PAY-D-1')->firstOrFail();
        $evaInvoice = Invoice::findOrFail($evaPayment->invoice_id);

        // Inti regresi: pembayaran Eva menempel ke invoice & pelanggan Eva,
        // BUKAN ke Hanif (cabang C) yang di-import lebih dulu dengan RQ000005 sama.
        $this->assertSame($eva->id, $evaPayment->customer_id, 'Pembayaran Eva bocor ke pelanggan cabang lain.');
        $this->assertSame($eva->id, $evaInvoice->customer_id, 'Invoice yang tercocokkan milik cabang lain.');
        $this->assertSame(165000.0, (float) $evaPayment->amount);
    }

    /**
     * Import satu cabang lewat pipeline validate → confirm dengan sheet minimal.
     */
    private function importBranch(
        string $popCode,
        string $oldCustomerId,
        string $fullName,
        string $oldInvoiceId,
        string $paymentId,
        int $paymentAmount,
        bool $paymentByRequestOnly = false,
    ): void {
        $requestId = 'RQ000005';
        $billingPeriod = '2022-01';

        $sheets = [
            'packages' => [[
                'old_package_id' => 'PKT10', 'name' => 'Paket 10 Mbps', 'monthly_price' => 165000,
                'download_speed' => 10, 'upload_speed' => 10, 'package_type' => 'Broadband', 'category' => 'Home',
                'branch_pop_code' => $popCode,
            ]],
            'customers' => [[
                'old_customer_id' => $oldCustomerId, 'old_request_id' => $requestId, 'full_name' => $fullName,
                'phone' => '0812'.random_int(1000000, 9999999), 'primary_phone' => '0812'.random_int(1000000, 9999999),
                'full_address' => 'Alamat '.$fullName, 'pop_code' => $popCode, 'pop_name' => '',
                'village' => 'Winong', 'district' => 'Jetis', 'city' => 'Ponorogo',
                'registration_date' => '2022-01-01', 'branch_pop_code' => $popCode,
            ]],
            'services' => [[
                'old_request_id' => $requestId, 'old_customer_id' => $oldCustomerId, 'old_package_id' => 'PKT10',
                'old_cost_id' => $oldInvoiceId, 'request_status' => 'ACTIVE', 'service_status' => 'aktif',
                'activation_date' => '2022-01-26', 'branch_pop_code' => $popCode,
            ]],
            'technical_details' => [],
            'invoices' => [[
                'old_invoice_id' => $oldInvoiceId, 'old_cost_id' => $oldInvoiceId, 'old_request_id' => $requestId,
                'old_customer_id' => $oldCustomerId, 'billing_period' => $billingPeriod, 'total_amount' => $paymentAmount,
                'issue_date' => '2022-01-26', 'due_date' => '2022-02-05', 'monthly_fee' => $paymentAmount,
                'status' => 'belum_dibayar', 'installation_fee' => 0, 'other_fee' => 0,
                'branch_pop_code' => $popCode,
            ]],
            'payments' => [[
                'old_payment_id' => $paymentId,
                'old_invoice_id' => $paymentByRequestOnly ? '' : $oldInvoiceId,
                'old_transaction_id' => '',
                'old_request_id' => $requestId,
                'old_customer_id' => $oldCustomerId,
                'billing_period' => $billingPeriod, 'amount' => $paymentAmount, 'payment_date' => '2022-01-26',
                'payment_method' => 'cash', 'received_by_old' => '', 'deposited_by_old' => '', 'status' => 'valid',
                'branch_pop_code' => $popCode,
            ]],
        ];

        $validate = $this->postJson('/customers/import/validate', ['sheets' => $sheets]);
        $validate->assertStatus(200);
        $validated = $validate->json();
        $this->assertTrue($validated['success']);

        $confirm = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($validated['sheets']),
            'file_name' => $popCode.'_branch.sql',
        ]);
        $confirm->assertRedirect('/customers');
        $confirm->assertSessionHas('success');
    }
}
