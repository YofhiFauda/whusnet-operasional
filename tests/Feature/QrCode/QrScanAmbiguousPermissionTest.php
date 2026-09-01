<?php

namespace Tests\Feature\QrCode;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\CustomerQrTokenService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Staf yang eligible DUA-DUANYA `tickets.qr.create` & `kolektor.qr.pay`
 * (2026-08-29, ditemukan lewat uji coba akun full-access — beda dari staf
 * lapangan biasa yang cuma pegang satu peran) — `QrScanController::dispatch()`
 * TIDAK BOLEH memilih diam-diam lewat urutan `if`. Lihat docblock kelas
 * §"Ambiguitas dua permission sekaligus" & docs/plan/qr-code/
 * analisa-unifikasi-qr-staff-portal.md.
 */
class QrScanAmbiguousPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        config(['qr.secret' => 'test-qr-hmac-secret-ambiguous', 'qr.portal_base_url' => 'https://portal.test']);

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-AMBIG', 'pop_code' => 'PAM', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Ambiguous Test', 'type' => 'cabang', 'status' => 'active',
        ]);
    }

    /**
     * Role `kolektor` — SEKARANG dual-eligible BY DEFAULT (keputusan
     * eksplisit user 2026-08-29: kolektor dikasih `tickets.qr.create` juga,
     * lihat `RolePermissionSeeder`), bukan lagi kasus tepi akun full-access.
     * `syncWithoutDetaching` di sini jadi no-op kalau seeder udah nempelin,
     * dibiarin sebagai jaring pengaman kalau test ini jalan sebelum seeder
     * ke-update di environment lain.
     */
    private function createDualEligibleStaff(): User
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $ticketsQrCreate = Permission::where('code', 'tickets.qr.create')->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$ticketsQrCreate->id]);

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

    #[Test]
    public function staf_eligible_dua_duanya_diarahkan_ke_chooser_bukan_dipilihkan_diam_diam(): void
    {
        $staff = $this->createDualEligibleStaff();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ920001', $staff->id);
        $code = $this->scanCode($customer);

        $response = $this->actingAs($staff)->get("/q1/{$code}");

        $response->assertRedirect(route('qr.scan.choose', ['code' => $code]));
    }

    #[Test]
    public function halaman_chooser_render_dua_pilihan(): void
    {
        $staff = $this->createDualEligibleStaff();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ920002', $staff->id);
        $code = $this->scanCode($customer);

        $response = $this->actingAs($staff)->get(route('qr.scan.choose', ['code' => $code]));

        $response->assertOk();
        $response->assertSee('Tagih Pembayaran');
        $response->assertSee('Lapor Komplain');
    }

    #[Test]
    public function chooser_pilih_tiket_redirect_portal_staff_tickets(): void
    {
        $staff = $this->createDualEligibleStaff();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ920003', $staff->id);
        $code = $this->scanCode($customer);

        $response = $this->actingAs($staff)->post(route('qr.scan.choose.confirm', ['code' => $code]), ['action' => 'tickets']);

        $response->assertRedirect();
        $this->assertStringStartsWith('https://portal.test/staff/tickets?code=', $response->headers->get('Location'));
        $this->assertDatabaseHas('staff_portal_tokens', ['user_id' => $staff->id, 'customer_id' => $customer->id, 'purpose' => 'tickets']);
    }

    #[Test]
    public function chooser_pilih_kolektor_redirect_portal_staff_kolektor(): void
    {
        $staff = $this->createDualEligibleStaff();
        $customer = $this->createCustomerWithUnpaidInvoice('RQ920004', $staff->id);
        $code = $this->scanCode($customer);

        $response = $this->actingAs($staff)->post(route('qr.scan.choose.confirm', ['code' => $code]), ['action' => 'kolektor']);

        $response->assertRedirect();
        $this->assertStringStartsWith('https://portal.test/staff/kolektor?code=', $response->headers->get('Location'));
        $this->assertDatabaseHas('staff_portal_tokens', ['user_id' => $staff->id, 'customer_id' => $customer->id, 'purpose' => 'kolektor']);
    }

    #[Test]
    public function chooser_diakses_langsung_tanpa_beneran_ambigu_bounce_balik_ke_dispatch(): void
    {
        // Staf cuma eligible ticketing (kolektor role biasa, TANPA invoice
        // due — bukan worklist-nya) — buka URL chooser manual harus mental
        // balik ke dispatch, bukan nampilin chooser dengan 1 opsi doang.
        $role = Role::where('code', 'helpdesk')->firstOrFail();
        $staff = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create(['user_id' => $staff->id, 'role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        $customer = $this->createCustomerWithUnpaidInvoice('RQ920005', null);
        $code = $this->scanCode($customer);

        $response = $this->actingAs($staff)->get(route('qr.scan.choose', ['code' => $code]));

        $response->assertRedirect(route('qr.dispatch', ['code' => $code]));
    }

    /**
     * Koreksi 2026-08-29, DUA PUTARAN (lihat docblock
     * `QrScanController::resolveEligibility()` buat kronologi lengkap).
     * Sempat diubah ke permission-only supaya owner eligible kolektor cuma
     * modal `*` — DIBALIKIN, karena `hasRole('kolektor')` di seluruh modul
     * Kolektor 2.0 (termasuk endpoint TULIS `PortalStaffKolektorController::
     * payments()`) BUKAN gerbang RBAC, itu identitas yang dipakai pembukuan
     * kolektor (saldo/setoran/worklist). Kalau eligibility QR dilonggarin
     * sendirian tanpa ngubah endpoint tulisnya, owner cuma ke-route ke
     * halaman kolektor lalu 403 pas submit — lebih buruk.
     *
     * Test ini SEKARANG membuktikan owner TETAP gak pernah eligible
     * kolektor — walau permission `*` DAN `collector_id` pelanggan itu
     * kebetulan owner sendiri. Itu benar & disengaja: owner secara
     * struktural bukan kolektor lapangan, gak boleh "jadi kolektor" cuma
     * modal permission.
     */
    #[Test]
    public function owner_full_access_tetap_gak_eligible_kolektor_walau_collector_id_cocok(): void
    {
        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);
        // Owner gak perlu UserRoleScope eksplisit — hasAllPopAccess() lolos
        // lewat permission '*'.

        // collector_id sengaja diisi user owner sendiri — buat buktiin
        // eligibility TETAP gak lolos walau kepemilikan data pun kebetulan
        // cocok. hasRole('kolektor')-lah yang jadi gerbang penentu, bukan
        // collector_id doang.
        $customer = $this->createCustomerWithUnpaidInvoice('RQ920006', $owner->id);
        $code = $this->scanCode($customer);

        $response = $this->actingAs($owner)->get("/q1/{$code}");

        // Cuma eligible tickets (lewat '*') — langsung ke Portal tickets,
        // BUKAN chooser, BUKAN kolektor.
        $response->assertRedirect();
        $this->assertStringStartsWith('https://portal.test/staff/tickets?code=', $response->headers->get('Location'));
    }

    /**
     * Pembanding — kolektor ASLI (role code `kolektor`) tetap eligible
     * normal, membuktikan pembatasan di atas gak nutup jalur yang sah.
     */
    #[Test]
    public function kolektor_asli_tetap_eligible_normal(): void
    {
        $kolektor = $this->createDualEligibleStaff(); // role kolektor + tickets.qr.create nempel
        $customer = $this->createCustomerWithUnpaidInvoice('RQ920007', $kolektor->id);
        $code = $this->scanCode($customer);

        $response = $this->actingAs($kolektor)->get("/q1/{$code}");

        // Eligible dua-duanya (role kolektor asli + tickets.qr.create nempel
        // di test setup) → chooser, bukti hasRole('kolektor') gak menghalangi
        // staf yang beneran berhak.
        $response->assertRedirect(route('qr.scan.choose', ['code' => $code]));
    }
}
