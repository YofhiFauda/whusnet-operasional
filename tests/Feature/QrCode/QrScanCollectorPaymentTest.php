<?php

namespace Tests\Feature\QrCode;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CustomerQrTokenService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kolektor scan QR pelanggan (2026-08-27, keputusan eksplisit user) —
 * cabang baru `QrScanController::dispatch()`. Pelanggan di worklist SENDIRI
 * → worklist tersaring (`?search=customer_code`) di Portal.
 * Pelanggan DI LUAR worklist-nya → 403 tegas, TIDAK jatuh ke cabang lain.
 *
 * Koreksi 2026-08-29 (docs/plan/qr-code/analisa-unifikasi-qr-staff-portal.md)
 * — tujuannya sekarang Portal (bawa `StaffPortalToken`), bukan lagi
 * `collector-worklist.index`/`qr.ticket.create` internal.
 *
 * Koreksi 2026-08-29 (lanjutan, keputusan eksplisit user) — role `kolektor`
 * SEKARANG juga dikasih `tickets.qr.create` (kolektor ketemu pelanggan
 * langsung di lapangan, sering dapet komplain di tempat pas nagih). Efeknya:
 * (1) pelanggan di worklist sendiri → dual-eligible → chooser, BUKAN
 * langsung ke worklist; (2) pelanggan DI LUAR worklist (bukan tanggung
 * jawabnya buat DITAGIH) TETAP bisa dilaporin tiket — 403 "bukan tanggung
 * jawab Anda" cuma berlaku buat cabang PEMBAYARAN, ticketing gak pernah
 * dibatasi per-kepemilikan pelanggan (sama kayak Helpdesk, cuma POP scope).
 */
class QrScanCollectorPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        config(['qr.secret' => 'test-qr-hmac-secret-collector', 'qr.portal_base_url' => 'https://portal.test']);

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-QR-KOL', 'pop_code' => 'QKL', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP QR Kolektor Test', 'type' => 'cabang', 'status' => 'active',
        ]);
    }

    private function createKolektor(): User
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

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

    #[Test]
    public function kolektor_scan_pelanggan_di_worklist_sendiri_diarahkan_ke_chooser(): void
    {
        $kolektor = $this->createKolektor();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ900001', $kolektor->id);
        $code = $this->scanCode($customer);

        $response = $this->actingAs($kolektor)->get("/q1/{$code}");

        // Dual-eligible (kolektor.qr.pay lewat worklist + tickets.qr.create
        // dari role) → chooser "Tagih Pembayaran"/"Lapor Komplain", bukan
        // langsung ke salah satu.
        $response->assertRedirect(route('qr.scan.choose', ['code' => $code]));
    }

    #[Test]
    public function kolektor_pilih_tagih_di_chooser_diarahkan_ke_worklist_tersaring(): void
    {
        $kolektor = $this->createKolektor();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ900001b', $kolektor->id);
        $code = $this->scanCode($customer);

        $response = $this->actingAs($kolektor)->post(route('qr.scan.choose.confirm', ['code' => $code]), ['action' => 'kolektor']);

        $response->assertRedirect();
        $this->assertStringStartsWith('https://portal.test/staff/kolektor?code=', $response->headers->get('Location'));
        $this->assertDatabaseHas('staff_portal_tokens', [
            'user_id' => $kolektor->id,
            'customer_id' => $customer->id,
            'purpose' => 'kolektor',
        ]);
    }

    /**
     * Pelanggan di luar worklist (tanggung jawab kolektor LAIN) — kolektor
     * yang scan TETAP gak boleh nagih pelanggan itu (kolektor.qr.pay gak
     * eligible), tapi lapor tiket TETAP boleh (tickets.qr.create gak
     * disyaratkan kepemilikan worklist). Cuma satu opsi eligible → langsung
     * ke ticketing, TANPA chooser.
     */
    #[Test]
    public function kolektor_scan_pelanggan_di_luar_worklist_tetap_bisa_lapor_tiket_bukan_403(): void
    {
        $kolektorA = $this->createKolektor();
        $kolektorB = $this->createKolektor();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ900002', $kolektorB->id);
        $code = $this->scanCode($customer);

        $response = $this->actingAs($kolektorA)->get("/q1/{$code}");

        $response->assertRedirect();
        $this->assertStringStartsWith('https://portal.test/staff/tickets?code=', $response->headers->get('Location'));
    }

    #[Test]
    public function kolektor_scan_pelanggan_tanpa_collector_id_tetap_bisa_lapor_tiket_bukan_403(): void
    {
        $kolektor = $this->createKolektor();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ900003', null);
        $code = $this->scanCode($customer);

        $response = $this->actingAs($kolektor)->get("/q1/{$code}");

        $response->assertRedirect();
        $this->assertStringStartsWith('https://portal.test/staff/tickets?code=', $response->headers->get('Location'));
    }

    /**
     * Staf NON-kolektor yang scan pelanggan yang KEBETULAN punya
     * `collector_id` terisi TETAP jalan normal (ticketing/detail) —
     * cabang kolektor cuma aktif kalau PEMINDAI-nya kolektor, bukan
     * gara-gara pelanggannya punya kolektor.
     */
    #[Test]
    public function staf_bukan_kolektor_scan_pelanggan_yang_punya_collector_id_tetap_ke_alur_biasa(): void
    {
        $helpdeskRole = Role::where('code', 'helpdesk')->firstOrFail();
        $helpdesk = User::factory()->create(['role_id' => $helpdeskRole->id, 'status' => 'active']);
        UserRoleScope::create(['user_id' => $helpdesk->id, 'role_id' => $helpdeskRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $kolektor = $this->createKolektor();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ900004', $kolektor->id);

        $response = $this->actingAs($helpdesk)->get('/q1/'.$this->scanCode($customer));

        // helpdesk punya tickets.qr.create — jatuh ke cabang ticketing (Portal), BUKAN 403.
        $response->assertRedirect();
        $this->assertStringStartsWith('https://portal.test/staff/tickets?code=', $response->headers->get('Location'));
    }
}
