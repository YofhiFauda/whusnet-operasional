<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\StaffPortalToken;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CustomerQrTokenService;
use App\Services\EffectiveAccessService;
use App\Services\StaffPortalTokenService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * Teknisi mencatat pembayaran lewat Portal (ADHOC-122, purpose
 * `teknisi_bayar`). Yang dikunci: pelanggan TIDAK perlu di-assign, tapi
 * tetap POP scope; token kolektor & teknisi tidak saling lolos; sumber
 * pembayaran tercatat `teknisi`; token hanya hangus setelah batch sukses.
 */
class PortalStaffTeknisiTest extends TestCase
{
    use InteractsWithPortalAuth;
    use RefreshDatabase;

    private Pop $pop;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
        config(['qr.secret' => 'test-qr-hmac-secret-staff-teknisi', 'qr.portal_base_url' => 'https://portal.test']);

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-ST', 'pop_code' => 'PST', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Staff Teknisi Test', 'type' => 'cabang', 'status' => 'active',
        ]);
    }

    private function createUserWithRole(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    private function createCustomerWithUnpaidInvoice(string $code): Customer
    {
        $customer = Customer::create([
            'customer_code' => $code, 'full_name' => 'Pelanggan '.$code, 'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01', 'status' => 'active', 'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id, 'internet_package_id' => $this->package->id, 'address' => 'Jl. '.$code,
            'collector_id' => null,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id, 'full_address' => 'Jl. '.$code,
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
            'invoice_number' => 'INV-'.$code, 'invoice_type' => 'bulanan',
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

    private function authHeaders(string $plaintext): array
    {
        return array_merge($this->portalClientHeaders(), ['Authorization' => "Bearer {$plaintext}"]);
    }

    #[Test]
    public function teknisi_scan_pelanggan_tanpa_assign_diarahkan_ke_portal_teknisi(): void
    {
        $teknisi = $this->createUserWithRole('teknisi', $this->pop);
        $customer = $this->createCustomerWithUnpaidInvoice('PST-SCAN');
        $code = $this->scanCode($customer);

        // Teknisi juga punya tickets.qr.create → dual-eligible → chooser dulu.
        $this->actingAs($teknisi)->get("/q1/{$code}")
            ->assertRedirect(route('qr.scan.choose', ['code' => $code]));

        $response = $this->actingAs($teknisi)
            ->post(route('qr.scan.choose.confirm', ['code' => $code]), ['action' => 'kolektor']);

        $response->assertRedirect();
        $this->assertStringStartsWith('https://portal.test/staff/teknisi?code=', $response->headers->get('Location'));
        $this->assertDatabaseHas('staff_portal_tokens', [
            'user_id' => $teknisi->id,
            'customer_id' => $customer->id,
            'purpose' => StaffPortalTokenService::PURPOSE_TEKNISI,
        ]);
    }

    #[Test]
    public function teknisi_worklist_returns_unassigned_customer_invoices(): void
    {
        $teknisi = $this->createUserWithRole('teknisi', $this->pop);
        $customer = $this->createCustomerWithUnpaidInvoice('PST-LIST');
        $plaintext = StaffPortalToken::issue($teknisi->id, $customer->id, 'teknisi_bayar', 15, '127.0.0.1')['plaintext'];

        $response = $this->withHeaders($this->authHeaders($plaintext))
            ->getJson("/api/customer-portal/teknisi/worklist/{$this->scanCode($customer)}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.invoices'));
    }

    #[Test]
    public function teknisi_payment_via_portal_is_tagged_teknisi_and_consumes_token(): void
    {
        $teknisi = $this->createUserWithRole('teknisi', $this->pop);
        $customer = $this->createCustomerWithUnpaidInvoice('PST-PAY');
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();
        $token = StaffPortalToken::issue($teknisi->id, $customer->id, 'teknisi_bayar', 15, '127.0.0.1');

        $response = $this->withHeaders($this->authHeaders($token['plaintext']))
            ->postJson('/api/customer-portal/teknisi/payments', [
                'idempotency_key' => 'portal-tek-001',
                'rows' => [
                    ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
                ],
            ]);

        $response->assertOk()->assertJson(['success' => true, 'processed' => 1]);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('teknisi', $payment->collected_by_role);
        $this->assertSame($teknisi->id, (int) $payment->collected_by);
        $this->assertNotNull(StaffPortalToken::query()->where('purpose', 'teknisi_bayar')->value('consumed_at'));
    }

    #[Test]
    public function token_pelanggan_a_tidak_bisa_membayar_tagihan_pelanggan_b(): void
    {
        $teknisi = $this->createUserWithRole('teknisi', $this->pop);
        $customerA = $this->createCustomerWithUnpaidInvoice('PST-A');
        $customerB = $this->createCustomerWithUnpaidInvoice('PST-B');
        $invoiceB = Invoice::where('customer_id', $customerB->id)->firstOrFail();
        $token = StaffPortalToken::issue($teknisi->id, $customerA->id, 'teknisi_bayar', 15, '127.0.0.1');

        $this->withHeaders($this->authHeaders($token['plaintext']))
            ->postJson('/api/customer-portal/teknisi/payments', [
                'idempotency_key' => 'portal-tek-lintas',
                'rows' => [
                    ['invoice_id' => $invoiceB->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, Payment::query()->count());
        $this->assertNull(StaffPortalToken::find($token['model']->id)->consumed_at);
    }

    #[Test]
    public function tagihan_periode_mendatang_ditolak_lewat_portal(): void
    {
        $teknisi = $this->createUserWithRole('teknisi', $this->pop);
        $customer = $this->createCustomerWithUnpaidInvoice('PST-FUT');
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();
        $invoice->forceFill(['billing_period' => '2099-01'])->saveQuietly();
        $plaintext = StaffPortalToken::issue($teknisi->id, $customer->id, 'teknisi_bayar', 15, '127.0.0.1')['plaintext'];

        $this->withHeaders($this->authHeaders($plaintext))
            ->postJson('/api/customer-portal/teknisi/payments', [
                'idempotency_key' => 'portal-tek-future',
                'rows' => [
                    ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
                ],
            ])
            ->assertStatus(422);

        $this->assertSame(0, Payment::query()->count());
    }

    /**
     * Pembayaran Portal digerbang DUA permission (kolektor.pay & kolektor.qr.pay).
     * Mencabut `kolektor.pay` (gerbang web teknisi) harus langsung menutup jalur
     * Portal juga — dulu hanya kolektor.qr.pay yang dicek di sini.
     */
    #[Test]
    public function mencabut_kolektor_pay_menutup_pembayaran_portal_teknisi(): void
    {
        $teknisi = $this->createUserWithRole('teknisi', $this->pop);
        $customer = $this->createCustomerWithUnpaidInvoice('PST-REVOKE');
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();
        $token = StaffPortalToken::issue($teknisi->id, $customer->id, 'teknisi_bayar', 15, '127.0.0.1');

        $teknisi->role->permissions()->detach(Permission::where('code', 'kolektor.pay')->firstOrFail()->id);
        app(EffectiveAccessService::class)->clearCache($teknisi);

        $this->withHeaders($this->authHeaders($token['plaintext']))
            ->postJson('/api/customer-portal/teknisi/payments', [
                'idempotency_key' => 'portal-tek-revoke',
                'rows' => [
                    ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(0, Payment::query()->count());
        $this->assertNull(StaffPortalToken::query()->where('purpose', 'teknisi_bayar')->value('consumed_at'));
    }

    /**
     * Token yang lewat TTL tidak boleh dipakai bayar, walau sudah diterbitkan
     * dan belum dikonsumsi.
     */
    #[Test]
    public function token_kedaluwarsa_ditolak_401_tanpa_pembayaran(): void
    {
        $teknisi = $this->createUserWithRole('teknisi', $this->pop);
        $customer = $this->createCustomerWithUnpaidInvoice('PST-EXPIRED');
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();
        $plaintext = StaffPortalToken::issue($teknisi->id, $customer->id, 'teknisi_bayar', 15, '127.0.0.1')['plaintext'];
        StaffPortalToken::query()->where('purpose', 'teknisi_bayar')->update(['expires_at' => now()->subMinute()]);

        $this->withHeaders($this->authHeaders($plaintext))
            ->postJson('/api/customer-portal/teknisi/payments', [
                'idempotency_key' => 'portal-tek-expired',
                'rows' => [
                    ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
                ],
            ])
            ->assertUnauthorized();

        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function token_yang_sudah_dikonsumsi_ditolak_401(): void
    {
        $teknisi = $this->createUserWithRole('teknisi', $this->pop);
        $customer = $this->createCustomerWithUnpaidInvoice('PST-USED');
        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();
        $plaintext = StaffPortalToken::issue($teknisi->id, $customer->id, 'teknisi_bayar', 15, '127.0.0.1')['plaintext'];
        StaffPortalToken::query()->where('purpose', 'teknisi_bayar')->update(['consumed_at' => now()]);

        $this->withHeaders($this->authHeaders($plaintext))
            ->postJson('/api/customer-portal/teknisi/payments', [
                'idempotency_key' => 'portal-tek-used',
                'rows' => [
                    ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
                ],
            ])
            ->assertUnauthorized();

        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function kolektor_token_cannot_be_used_on_teknisi_endpoint(): void
    {
        $kolektor = $this->createUserWithRole('kolektor', $this->pop);
        $customer = $this->createCustomerWithUnpaidInvoice('PST-KOL');
        $plaintext = StaffPortalToken::issue($kolektor->id, $customer->id, 'kolektor', 15, '127.0.0.1')['plaintext'];

        $this->withHeaders($this->authHeaders($plaintext))
            ->getJson("/api/customer-portal/teknisi/worklist/{$this->scanCode($customer)}")
            ->assertUnauthorized();
    }
}
