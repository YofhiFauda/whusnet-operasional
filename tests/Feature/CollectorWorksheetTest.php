<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Models\Village;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Worksheet Admin — halaman ADMIN atas para kolektor: index 2 panel (daftar
 * kolektor + pelanggan belum di-assign) dan show 2 tab (Pembayaran / Atur
 * Pelanggan). Dipisah dari Worklist Kolektor menurut siapa penggunanya
 * (docs/plan/kolektor/analisa-alur-kolektor-2.0.md §9), dan digerbang
 * permission sendiri `collector_worksheet.view` — bukan numpang
 * customers.update/payments.create seperti versi lama.
 */
class CollectorWorksheetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createAdmin(Pop $pop): User
    {
        $role = Role::where('name', 'POP Admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->pops()->attach($pop->id);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C'.substr($code, -1),
            'cid_prefix' => 'D'.substr($code, -1),
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    public function test_index_lists_collectors_with_customer_count_and_unpaid_total(): void
    {
        $pop = $this->createPop('HUB1');
        $admin = $this->createAdmin($pop);

        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['name' => 'Budi Kolektor', 'role_id' => $kolektorRole->id, 'status' => 'active']);

        $package = InternetPackage::query()->firstOrFail();
        $customer = Customer::create([
            'customer_code' => 'C-HUB-001',
            'full_name' => 'Pelanggan Hub Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Hub Test',
            'collector_id' => $kolektor->id,
        ]);
        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Hub Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);
        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);
        Invoice::create([
            'invoice_number' => 'INV-HUB-001',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
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

        $response = $this->actingAs($admin)->get(route('collector-worksheet.index'));

        $response->assertOk();
        $response->assertSee('Budi Kolektor');
        $response->assertSee('150.000'); // total tunggakan tampil
    }

    public function test_show_default_tab_is_pembayaran_and_assign_tab_accessible(): void
    {
        $pop = $this->createPop('HUB2');
        $admin = $this->createAdmin($pop);

        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active']);

        $responseDefault = $this->actingAs($admin)->get(route('collector-worksheet.show', $kolektor->id));
        $responseDefault->assertOk();
        // String khas tab Pembayaran — bukan sekadar kata "Pembayaran" yang
        // juga muncul di menu sidebar.
        $responseDefault->assertSee('Seluruh tunggakan kolektor ini');

        $responseAssign = $this->actingAs($admin)->get(route('collector-worksheet.show', ['collector' => $kolektor->id, 'tab' => 'assign']));
        $responseAssign->assertOk();
        $responseAssign->assertSee('Atur Pelanggan');
    }

    /**
     * Panel kanan index cuma boleh memuat pelanggan tanpa kolektor DAN dalam
     * POP scope admin. Tanpa applyUserScope() panel ini jadi jalur bocor
     * paling gampang: isinya "semua pelanggan yang belum di-assign", lintas
     * cabang, tanpa filter apa pun.
     */
    public function test_index_unassigned_panel_only_shows_in_scope_customers_without_collector(): void
    {
        $pop = $this->createPop('HUB4');
        $otherPop = $this->createPop('HUB5');
        $admin = $this->createAdmin($pop);

        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['name' => 'Sari Kolektor', 'role_id' => $kolektorRole->id, 'status' => 'active']);

        $this->createCustomer($pop, 'C-HUB-FREE', null);
        $this->createCustomer($pop, 'C-HUB-TAKEN', $kolektor->id);
        $this->createCustomer($otherPop, 'C-HUB-OUTSCOPE', null);

        $response = $this->actingAs($admin)->get(route('collector-worksheet.index'));

        $response->assertOk();
        $response->assertSee('Pelanggan C-HUB-FREE');
        $response->assertDontSee('Pelanggan C-HUB-TAKEN');
        $response->assertDontSee('Pelanggan C-HUB-OUTSCOPE');
    }

    /**
     * Regresi: panel index dulu menyusun URL tujuan di klien lewat Alpine
     * (`:action`), dan Alpine dimuat dari CDN. Begitu CDN tak termuat,
     * form-nya mem-POST ke URL halaman sendiri — dialog konfirmasi tetap
     * muncul (skrip lokal), diklik "Ya", lalu pelanggan tidak pindah ke
     * kolektor mana pun tanpa pesan error apa pun.
     *
     * Sekarang action-nya statis dan kolektor tujuan dikirim di body. Test ini
     * memastikan jalur itu benar-benar ada dan memindahkan pelanggan.
     */
    public function test_assign_from_index_panel_uses_body_collector_id_and_moves_customer(): void
    {
        $pop = $this->createPop('HUB6');
        $admin = $this->createAdmin($pop);
        $kolektor = $this->createKolektorWithScope($pop);

        $customer = $this->createCustomer($pop, 'C-HUB-MOVE', null);

        $response = $this->actingAs($admin)->post(route('collector-worksheet.assign-selected'), [
            'collector_id' => $kolektor->id,
            'customer_ids' => [$customer->id],
            'redirect_to' => 'index',
        ]);

        $response->assertRedirect(route('collector-worksheet.index'));
        $this->assertSame($kolektor->id, (int) $customer->fresh()->collector_id);
    }

    public function test_assign_from_index_panel_requires_a_collector(): void
    {
        $pop = $this->createPop('HUB7');
        $admin = $this->createAdmin($pop);
        $customer = $this->createCustomer($pop, 'C-HUB-NOCOL', null);

        $this->actingAs($admin)->post(route('collector-worksheet.assign-selected'), [
            'customer_ids' => [$customer->id],
            'redirect_to' => 'index',
        ])->assertSessionHasErrors('collector_id');

        $this->assertNull($customer->fresh()->collector_id);
    }

    /**
     * Guard POP tetap berlaku di jalur body — bukan cuma di jalur
     * route-parameter. Kalau guard-nya cuma disalin ke salah satu, jalur yang
     * lain jadi pintu belakang.
     */
    public function test_assign_from_index_panel_still_enforces_collector_pop_scope(): void
    {
        $pop = $this->createPop('HUB8');
        $otherPop = $this->createPop('HUB9');
        $admin = $this->createAdmin($pop);
        $kolektorLuar = $this->createKolektorWithScope($otherPop);

        $customer = $this->createCustomer($pop, 'C-HUB-OUT', null);

        $this->actingAs($admin)->post(route('collector-worksheet.assign-selected'), [
            'collector_id' => $kolektorLuar->id,
            'customer_ids' => [$customer->id],
            'redirect_to' => 'index',
        ])->assertSessionHasErrors('customer_ids');

        $this->assertNull($customer->fresh()->collector_id);
    }

    public function test_assign_target_must_be_a_kolektor(): void
    {
        $pop = $this->createPop('HUB10');
        $admin = $this->createAdmin($pop);
        $customer = $this->createCustomer($pop, 'C-HUB-NOTKOL', null);

        $teknisi = User::factory()->create([
            'role_id' => Role::where('code', 'teknisi')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('collector-worksheet.assign-selected'), [
            'collector_id' => $teknisi->id,
            'customer_ids' => [$customer->id],
        ])->assertNotFound();

        $this->assertNull($customer->fresh()->collector_id);
    }

    private function createKolektorWithScope(Pop $pop): User
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    /**
     * Regresi (review Fase 1–3 temuan #1): `show()` dulu cuma memeriksa target
     * ber-role kolektor. Admin POP A bisa membuka kolektor POP B dan membaca
     * saldo, riwayat setoran, catatan verifikasi, sampai alasan hapus buku —
     * `$balance`, `$outstandingShortfall`, dan daftar `$deposits` nol POP scope.
     */
    public function test_admin_cannot_open_cash_page_of_collector_from_another_pop(): void
    {
        $popA = $this->createPop('HUB11');
        $popB = $this->createPop('HUB12');

        $adminA = $this->createAdmin($popA);
        $kolektorB = $this->createKolektorWithScope($popB);

        // Jejak uang kolektor B ada di POP B.
        $this->createCustomer($popB, 'C-HUB-POPB', $kolektorB->id);

        $this->actingAs($adminA)
            ->get(route('collector-worksheet.show', $kolektorB->id))
            ->assertForbidden();
    }

    public function test_admin_can_open_cash_page_of_collector_inside_own_pop(): void
    {
        $pop = $this->createPop('HUB13');
        $admin = $this->createAdmin($pop);
        $kolektor = $this->createKolektorWithScope($pop);

        $this->createCustomer($pop, 'C-HUB-SAMEPOP', $kolektor->id);

        $this->actingAs($admin)
            ->get(route('collector-worksheet.show', $kolektor->id))
            ->assertOk();
    }

    /**
     * Kolektor yang jejak uangnya melintasi dua POP hanya boleh dibuka admin
     * yang membawahi KEDUANYA — sama syaratnya dengan verifikasi setoran
     * (§14.2). Menyaring sebagian akan membuat angka total berbohong.
     */
    public function test_admin_cannot_open_collector_whose_footprint_spans_outside_scope(): void
    {
        $popA = $this->createPop('HUB14');
        $popB = $this->createPop('HUB15');

        $adminA = $this->createAdmin($popA);
        $kolektor = $this->createKolektorWithScope($popA);

        $this->createCustomer($popA, 'C-HUB-IN', $kolektor->id);
        $this->createCustomer($popB, 'C-HUB-SPILL', $kolektor->id);

        $this->actingAs($adminA)
            ->get(route('collector-worksheet.show', $kolektor->id))
            ->assertForbidden();
    }

    public function test_owner_can_open_any_collector_cash_page(): void
    {
        $pop = $this->createPop('HUB16');
        $kolektor = $this->createKolektorWithScope($pop);
        $this->createCustomer($pop, 'C-HUB-OWNER', $kolektor->id);

        $owner = User::factory()->create([
            'role_id' => Role::where('code', 'owner')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->get(route('collector-worksheet.show', $kolektor->id))
            ->assertOk();
    }

    /**
     * Regresi (review #8): paginator tab Atur Pelanggan tanpa `withQueryString()`
     * menjatuhkan `tab=assign`, jadi `show()` kembali ke tab default dan user
     * terlempar ke halaman lain di tengah paginasi.
     */
    public function test_assign_tab_pagination_keeps_the_active_tab(): void
    {
        $pop = $this->createPop('HUB17');
        $admin = $this->createAdmin($pop);
        $kolektor = $this->createKolektorWithScope($pop);

        $this->createCustomer($pop, 'C-HUB-PG1', $kolektor->id);

        $response = $this->actingAs($admin)->get(route('collector-worksheet.show', [
            'collector' => $kolektor->id,
            'tab' => 'assign',
        ]));

        $response->assertOk();

        // Diperiksa lewat URL paginator, bukan lewat link yang terender:
        // dengan 50 baris per halaman, menunggu tombol "2" muncul berarti
        // menyeed 51 pelanggan cuma untuk menguji satu query string.
        $this->assertStringContainsString('tab=assign', $response->viewData('assignedCustomers')->url(2));
        $this->assertStringContainsString('tab=assign', $response->viewData('invoices')->url(2));
    }

    public function test_kolektor_role_cannot_access_worksheet_without_permission(): void
    {
        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active']);

        $response = $this->actingAs($kolektor)->get(route('collector-worksheet.index'));
        $response->assertForbidden();
    }

    public function test_index_unassigned_customers_can_be_filtered_by_pop_district_and_village(): void
    {
        Cache::flush();
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();

        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        User::factory()->create(['name' => 'Test Kolektor', 'role_id' => $kolektorRole->id, 'status' => 'active']);

        $popA = $this->createPop('HUB-FLT-A');
        $popB = $this->createPop('HUB-FLT-B');

        $districtA = District::create(['city_id' => 1, 'name' => 'Kecamatan Filter A']);
        $districtB = District::create(['city_id' => 1, 'name' => 'Kecamatan Filter B']);
        $villageA = Village::create(['district_id' => $districtA->id, 'name' => 'Desa Filter A']);
        $villageB = Village::create(['district_id' => $districtB->id, 'name' => 'Desa Filter B']);

        $c1 = $this->createCustomer($popA, 'C-FLT-001', null);
        $c1->update(['district_id' => $districtA->id, 'village_id' => $villageA->id]);

        $c2 = $this->createCustomer($popB, 'C-FLT-002', null);
        $c2->update(['district_id' => $districtB->id, 'village_id' => $villageB->id]);

        // Filter by POP A
        $response = $this->actingAs($owner)->get(route('collector-worksheet.index', [
            'pop_id' => [$popA->id],
        ]));
        $response->assertOk();
        $response->assertSee('Pelanggan C-FLT-001');
        $response->assertDontSee('Pelanggan C-FLT-002');

        // Filter by District B
        $response = $this->actingAs($owner)->get(route('collector-worksheet.index', [
            'district_id' => [$districtB->id],
        ]));
        $response->assertOk();
        $response->assertDontSee('Pelanggan C-FLT-001');
        $response->assertSee('Pelanggan C-FLT-002');

        // Filter by Village A
        $response = $this->actingAs($owner)->get(route('collector-worksheet.index', [
            'village_id' => [$villageA->id],
        ]));
        $response->assertOk();
        $response->assertSee('Pelanggan C-FLT-001');
        $response->assertDontSee('Pelanggan C-FLT-002');
    }

    private function createAdminWithPops(array $pops): User
    {
        $role = Role::where('name', 'POP Admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->pops()->attach(array_map(fn ($p) => $p->id, $pops));

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        foreach ($pops as $p) {
            UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $p->id]);
        }

        return $user;
    }

    private function createCustomer(Pop $pop, string $code, ?int $collectorId): Customer
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => InternetPackage::query()->firstOrFail()->id,
            'address' => 'Jl. '.$code,
            'collector_id' => $collectorId,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. '.$code,
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        return $customer;
    }
}
