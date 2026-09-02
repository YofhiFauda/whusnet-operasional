<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\StaffPortalToken;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\CustomerQrTokenService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `GET /api/customer-portal/kolektor/worklist/{code}`,
 * `POST /api/customer-portal/kolektor/payments` (docs/plan/qr-code/
 * analisa-unifikasi-qr-staff-portal.md §3) — kolektor lewat scan QR →
 * Portal → API. `payments()` REUSE PENUH `RecordsCollectorBatch`, jadi test
 * ini fokus ke lapis token/otorisasi tambahan, bukan mengulang seluruh
 * matriks validasi batch (sudah dites `RecordsCollectorBatch` via
 * CollectorPaymentController).
 */
class PortalStaffKolektorTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    private Pop $pop;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
        config(['qr.secret' => 'test-qr-hmac-secret-staff-kolektor']);

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-SK', 'pop_code' => 'PSK', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Staff Kolektor Test', 'type' => 'cabang', 'status' => 'active',
        ]);
    }

    private function createKolektor(): User
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        return $user;
    }

    private function createCustomerWithUnpaidInvoice(string $customerCode, ?int $collectorId): Customer
    {
        $customer = Customer::create([
            'customer_code' => $customerCode,
            'full_name' => 'Pelanggan '.$customerCode,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$customerCode,
            'collector_id' => $collectorId,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id, 'full_address' => 'Jl. '.$customerCode,
            'village' => 'Desa Test', 'district' => 'Kecamatan Test', 'city' => 'Kota Test', 'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id, 'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name, 'monthly_price' => 150000,
            'discount' => 0, 'ppn' => 0, 'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01', 'due_date' => '2026-06-15',
            'service_status' => 'aktif', 'billing_status' => 'active',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-'.$customerCode, 'invoice_type' => 'bulanan',
            'customer_id' => $customer->id, 'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id, 'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06', 'issue_date' => '2026-06-01', 'due_date' => '2026-06-15',
            'subtotal' => 150000, 'discount' => 0, 'ppn' => 0, 'total_amount' => 150000,
            'paid_amount' => 0, 'remaining_amount' => 150000, 'invoice_status' => 'belum_dibayar',
        ]);

        return $customer;
    }

    private function scanCode(Customer $customer): string
    {
        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($customer);
        $signature = $qrService->signature((int) $this->pop->id, $customer->customer_code, $token->token);

        return "{$token->token}.{$signature}";
    }

    private function issueStaffToken(User $collector, Customer $customer): string
    {
        return StaffPortalToken::issue($collector->id, $customer->id, 'kolektor', 15, '127.0.0.1')['plaintext'];
    }

    private function authHeaders(string $plaintext): array
    {
        return array_merge($this->portalClientHeaders(), ['Authorization' => "Bearer {$plaintext}"]);
    }

    #[Test]
    public function worklist_pelanggan_sendiri_balikin_invoice_due(): void
    {
        $kolektor = $this->createKolektor();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ910001', $kolektor->id);
        $plaintext = $this->issueStaffToken($kolektor, $customer);
        $code = $this->scanCode($customer);

        $response = $this->withHeaders($this->authHeaders($plaintext))
            ->getJson("/api/customer-portal/kolektor/worklist/{$code}");

        $response->assertOk();
        $response->assertJsonPath('data.customer.customer_code', 'RQ910001');
        $response->assertJsonCount(1, 'data.invoices');
        $response->assertJsonPath('data.invoices.0.invoice_number', 'INV-RQ910001');
    }

    #[Test]
    public function worklist_pelanggan_kolektor_lain_403(): void
    {
        $kolektorA = $this->createKolektor();
        $kolektorB = $this->createKolektor();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ910002', $kolektorB->id);
        // Token diterbitkan atas nama A tapi pelanggan itu milik B — simulasi
        // token dipalsu/di-tukar setelah worklist berubah antara scan & panggil API.
        $plaintext = $this->issueStaffToken($kolektorA, $customer);
        $code = $this->scanCode($customer);

        $response = $this->withHeaders($this->authHeaders($plaintext))
            ->getJson("/api/customer-portal/kolektor/worklist/{$code}");

        $response->assertForbidden();
    }

    #[Test]
    public function worklist_code_pelanggan_beda_dari_token_404(): void
    {
        $kolektor = $this->createKolektor();
        $customerA = $this->createCustomerWithUnpaidInvoice('RQ910003', $kolektor->id);
        $customerB = $this->createCustomerWithUnpaidInvoice('RQ910004', $kolektor->id);
        $plaintext = $this->issueStaffToken($kolektor, $customerA);
        $codeB = $this->scanCode($customerB);

        $response = $this->withHeaders($this->authHeaders($plaintext))
            ->getJson("/api/customer-portal/kolektor/worklist/{$codeB}");

        $response->assertNotFound();
    }

    #[Test]
    public function worklist_token_purpose_tickets_ditolak_401(): void
    {
        $kolektor = $this->createKolektor();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ910005', $kolektor->id);
        $plaintext = StaffPortalToken::issue($kolektor->id, $customer->id, 'tickets', 15, '127.0.0.1')['plaintext'];
        $code = $this->scanCode($customer);

        $response = $this->withHeaders($this->authHeaders($plaintext))
            ->getJson("/api/customer-portal/kolektor/worklist/{$code}");

        $response->assertUnauthorized();
    }

    #[Test]
    public function payments_berhasil_catat_lalu_konsumsi_token(): void
    {
        $kolektor = $this->createKolektor();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ910006', $kolektor->id);
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();
        $plaintext = $this->issueStaffToken($kolektor, $customer);

        $response = $this->withHeaders($this->authHeaders($plaintext))
            ->postJson('/api/customer-portal/kolektor/payments', [
                'idempotency_key' => 'staff-portal-test-key-1',
                'rows' => [[
                    'invoice_id' => $invoice->id,
                    'amount' => 150000,
                    'payment_method' => 'cash',
                    'collected_date' => now()->toDateString(),
                ]],
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'collected_by' => $kolektor->id]);
        $this->assertNotNull(StaffPortalToken::where('token_hash', hash('sha256', $plaintext))->first()->consumed_at);
    }

    #[Test]
    public function payments_invoice_di_luar_worklist_ditolak_422_token_tidak_terkonsumsi(): void
    {
        $kolektorA = $this->createKolektor();
        $kolektorB = $this->createKolektor();
        $customerB = $this->createCustomerWithUnpaidInvoice('RQ910007', $kolektorB->id);
        $invoiceB = Invoice::where('customer_id', $customerB->id)->firstOrFail();
        // Token A dipakai coba bayar invoice milik pelanggan kolektor B.
        $plaintext = $this->issueStaffToken($kolektorA, $customerB);

        $response = $this->withHeaders($this->authHeaders($plaintext))
            ->postJson('/api/customer-portal/kolektor/payments', [
                'idempotency_key' => 'staff-portal-test-key-2',
                'rows' => [[
                    'invoice_id' => $invoiceB->id,
                    'amount' => 150000,
                    'payment_method' => 'cash',
                    'collected_date' => now()->toDateString(),
                ]],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('payments', ['invoice_id' => $invoiceB->id]);
        $this->assertNull(StaffPortalToken::where('token_hash', hash('sha256', $plaintext))->first()->consumed_at);
    }

    #[Test]
    public function payments_bukan_role_kolektor_403(): void
    {
        $helpdeskRole = Role::where('code', 'helpdesk')->firstOrFail();
        $helpdesk = User::factory()->create(['role_id' => $helpdeskRole->id, 'status' => 'active']);
        UserRoleScope::create(['user_id' => $helpdesk->id, 'role_id' => $helpdeskRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $customer = $this->createCustomerWithUnpaidInvoice('RQ910008', null);
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();
        // Token cuma bisa diterbitkan lewat StaffPortalToken::issue langsung
        // di sini (bypass QrScanController) buat mensimulasikan permission
        // kolektor.qr.pay ke-assign ke role lain lewat Role Matrix — guard
        // role di controller yang menutupnya, bukan cuma permission check.
        $plaintext = StaffPortalToken::issue($helpdesk->id, $customer->id, 'kolektor', 15, '127.0.0.1')['plaintext'];

        $response = $this->withHeaders($this->authHeaders($plaintext))
            ->postJson('/api/customer-portal/kolektor/payments', [
                'idempotency_key' => 'staff-portal-test-key-3',
                'rows' => [[
                    'invoice_id' => $invoice->id,
                    'amount' => 150000,
                    'payment_method' => 'cash',
                    'collected_date' => now()->toDateString(),
                ]],
            ]);

        $response->assertForbidden();
    }

    #[Test]
    public function tanpa_bearer_token_401(): void
    {
        $response = $this->withHeaders($this->portalClientHeaders())
            ->postJson('/api/customer-portal/kolektor/payments', [
                'idempotency_key' => 'staff-portal-test-key-4',
                'rows' => [],
            ]);

        $response->assertUnauthorized();
    }
}
