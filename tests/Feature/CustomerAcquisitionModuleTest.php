<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerService;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\CustomerAcquisitionFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modul Customer Acquisition (dipakai tim Busdev) — "List Pelanggan Aktif
 * < 30 Hari Diverifikasi".
 *
 * Cakupan: baris kebentuk otomatis sekali per pelanggan saat status jadi
 * ACTIVE (CustomerObserver), reset "tanggal 1" via filter periode (bukan
 * job/cron), "Harga Dikurangi PPN" dihitung live (bukan input manual), dan
 * POP scope wajib.
 */
class CustomerAcquisitionModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(CustomerAcquisitionFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_customer_becoming_active_creates_acquisition_record_for_current_periode(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::factory()->create(['status' => 'verification_admin']);

        $customer->update(['status' => 'active']);

        $this->assertDatabaseHas('customer_acquisitions', [
            'customer_id' => $customer->id,
            'periode' => now()->format('Y-m'),
        ]);
    }

    public function test_reactivation_does_not_duplicate_acquisition_record(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::factory()->create(['status' => 'verification_admin']);
        $customer->update(['status' => 'active']);
        $customer->update(['status' => 'suspended']);
        $customer->update(['status' => 'active']);

        $this->assertEquals(1, CustomerAcquisition::where('customer_id', $customer->id)->count());
    }

    public function test_no_acquisition_record_created_for_other_status_changes(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::factory()->create(['status' => 'registered']);
        $customer->update(['status' => 'waiting_survey']);

        $this->assertDatabaseCount('customer_acquisitions', 0);
    }

    public function test_index_only_lists_records_for_requested_periode(): void
    {
        $user = $this->loginAsAdmin();
        $this->giveAllPopScope($user);

        $customerThisMonth = Customer::factory()->create(['status' => 'active']);
        CustomerAcquisition::factory()->for($customerThisMonth)->create([
            'periode' => now()->format('Y-m'),
            'verified_at' => now(),
        ]);

        $customerLastMonth = Customer::factory()->create(['status' => 'active']);
        CustomerAcquisition::factory()->for($customerLastMonth)->create([
            'periode' => now()->subMonth()->format('Y-m'),
            'verified_at' => now()->subMonth(),
        ]);

        $response = $this->get(route('customer-acquisitions.index'));

        $response->assertOk();
        $response->assertViewHas('records', function ($records) use ($customerThisMonth) {
            return $records->count() === 1 && $records->first()->customer_id === $customerThisMonth->id;
        });
    }

    /**
     * "Harga Dikurangi PPN" = Biaya Langganan - PPN 11% (rumus tetap khusus
     * Busdev, lihat CustomerAcquisition::getHargaDikurangiPpnAttribute()) —
     * dihitung live dari customer_services.total_monthly_bill, BUKAN kolom
     * yang diisi manual. Contoh dari tabel acuan: Rp150.000 → Rp133.500.
     */
    public function test_harga_dikurangi_ppn_is_computed_from_biaya_langganan(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::factory()->create(['status' => 'active']);
        CustomerService::create([
            'customer_id' => $customer->id,
            'package_name_snapshot' => 'Home 10 Mbps',
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
            'service_status' => 'aktif',
            'billing_status' => 'pending',
        ]);

        $record = CustomerAcquisition::factory()->for($customer)->create([
            'periode' => now()->format('Y-m'),
            'verified_at' => now(),
        ]);

        $this->assertEquals(133500.0, $record->fresh()->load('customer.customerService')->harga_dikurangi_ppn);
    }

    /**
     * Guard regresi (2026-09-12) — `omset_sales` (Dashboard Omset Sales,
     * Skema 2) TIDAK BOLEH disatukan lagi dengan `harga_dikurangi_ppn` di
     * atas. Dua metrik beda tujuan: yang ini biaya DIKURANGI PPN
     * (Rp133.500), `omset_sales` NILAI PPN itu sendiri (Rp16.500) — kalau
     * ada yang "menyederhanakan" balik jadi satu formula, test ini merah.
     */
    public function test_omset_sales_stays_a_separate_metric_from_harga_dikurangi_ppn(): void
    {
        $this->loginAsAdmin();

        $customer = Customer::factory()->create(['status' => 'active']);
        CustomerService::create([
            'customer_id' => $customer->id,
            'package_name_snapshot' => 'Home 10 Mbps',
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
            'service_status' => 'aktif',
            'billing_status' => 'pending',
        ]);

        $record = CustomerAcquisition::factory()->for($customer)->create([
            'periode' => now()->format('Y-m'),
            'verified_at' => now(),
        ]);

        $fresh = $record->fresh()->load('customer.customerService');
        $this->assertEquals(133500.0, $fresh->harga_dikurangi_ppn);
        $this->assertEquals(16500.0, $fresh->omset_sales);
    }

    public function test_pop_scoped_user_cannot_see_record_outside_scope(): void
    {
        $salesRole = Role::where('name', 'Sales')->first();
        $allowedPop = Pop::factory()->create();
        $otherPop = Pop::factory()->create();

        $user = User::factory()->create(['status' => 'active', 'role_id' => $salesRole->id]);
        // RefreshDatabase mengulang ID user dari 1 tiap test, tapi cache
        // permission/scope (`array` store) TIDAK ikut ke-reset — tanpa
        // clearCache() di sini, user id=1 di test ini bisa mewarisi cache
        // permission milik user id=1 dari test lain (lihat catatan
        // Tests\TestCase::setUp()).
        app(EffectiveAccessService::class)->clearCache($user);
        $this->actingAs($user);

        UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'scope_type' => 'selected_pop',
        ])->targets()->create(['pop_id' => $allowedPop->id]);

        app(EffectiveAccessService::class)->clearCache($user);

        $outsideCustomer = Customer::factory()->create(['status' => 'active', 'pop_id' => $otherPop->id]);
        CustomerAcquisition::factory()->for($outsideCustomer)->create([
            'periode' => now()->format('Y-m'),
            'verified_at' => now(),
        ]);

        $response = $this->get(route('customer-acquisitions.index'));
        $response->assertOk();
        $response->assertViewHas('records', fn ($records) => $records->isEmpty());
    }

    /**
     * Filter "diinput oleh" (2026-09-12, permintaan user) — role & nama,
     * KHUSUS role ber-`is_package_restricted` ("role dengan pembatasan
     * paket"). Bekerja lewat `customers.sales_user_id` (Skema 3), bukan
     * kolom baru.
     */
    public function test_filter_by_role_and_name_narrows_records_to_matching_sales_user(): void
    {
        $user = $this->loginAsAdmin();
        $this->giveAllPopScope($user);

        $salesRole = Role::where('code', 'sales')->firstOrFail();
        $sales = User::factory()->create(['status' => 'active', 'role_id' => $salesRole->id]);
        $otherSales = User::factory()->create(['status' => 'active', 'role_id' => $salesRole->id]);

        $customerBySales = Customer::factory()->create(['status' => 'active', 'sales_user_id' => $sales->id]);
        CustomerAcquisition::factory()->for($customerBySales)->create([
            'periode' => now()->format('Y-m'),
            'verified_at' => now(),
        ]);

        $customerByOtherSales = Customer::factory()->create(['status' => 'active', 'sales_user_id' => $otherSales->id]);
        CustomerAcquisition::factory()->for($customerByOtherSales)->create([
            'periode' => now()->format('Y-m'),
            'verified_at' => now(),
        ]);

        // Filter role saja — kedua Sales masih muncul.
        $byRole = $this->get(route('customer-acquisitions.index', ['role_id' => $salesRole->id]));
        $byRole->assertOk();
        $byRole->assertViewHas('records', fn ($records) => $records->count() === 2);

        // Filter role + nama spesifik — cuma satu yang muncul.
        $byName = $this->get(route('customer-acquisitions.index', [
            'role_id' => $salesRole->id,
            'sales_user_id' => $sales->id,
        ]));
        $byName->assertOk();
        $byName->assertViewHas('records', function ($records) use ($customerBySales) {
            return $records->count() === 1 && $records->first()->customer_id === $customerBySales->id;
        });
        // $otherSales->name TETAP muncul di halaman — dia opsi lain di
        // dropdown "Nama Penginput" (semua nama role itu, biar bisa
        // dipilih ganti), bukan berarti barisnya ikut lolos filter (sudah
        // dipastikan di atas: cuma 1 baris, milik $sales).
        $byName->assertSee($sales->name);
    }

    public function test_diinput_oleh_column_shows_sales_user_name(): void
    {
        $user = $this->loginAsAdmin();
        $this->giveAllPopScope($user);

        $salesRole = Role::where('code', 'sales')->firstOrFail();
        $sales = User::factory()->create(['status' => 'active', 'role_id' => $salesRole->id, 'name' => 'Budi Sales Uji']);

        $customer = Customer::factory()->create(['status' => 'active', 'sales_user_id' => $sales->id]);
        CustomerAcquisition::factory()->for($customer)->create([
            'periode' => now()->format('Y-m'),
            'verified_at' => now(),
        ]);

        $response = $this->get(route('customer-acquisitions.index'));

        $response->assertOk();
        $response->assertSee('Budi Sales Uji');
    }
}
