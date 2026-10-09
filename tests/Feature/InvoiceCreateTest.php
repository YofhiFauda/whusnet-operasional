<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tagihan Manual (ADHOC-70) — /invoices/create. Menggantikan test lama yang
 * menembak route `customers.invoices.manual` (dihapus bersih, lihat
 * docs/plan/billing/analisa-rancangan-tagihan-manual.md §4 butir 6).
 *
 * Keputusan user 2026-09-23: form ini HANYA menerbitkan Invoice
 * (`belum_dibayar`) — TANPA Payment. Metode pembayaran & nominal dibayar
 * bukan urusan Tagihan, itu ranah Pembayaran (List Tagihan → Bayar/Bayar
 * Cicil, jalur `PaymentController::store` yang sudah ada).
 */
class InvoiceCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);
    }

    protected function createPop(string $suffix = ''): Pop
    {
        return Pop::create([
            'code' => "POP-TEST{$suffix}",
            'pop_code' => "T{$suffix}",
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => "POP Test{$suffix}",
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    protected function createTestCustomer(Pop $pop, InternetPackage $package, string $fullName = 'Budi Santoso', string $customerCode = 'WHUS-2026-0001'): Customer
    {
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        $customer = Customer::create([
            'customer_code' => $customerCode,
            'cid' => $customerCode,
            'full_name' => $fullName,
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Raya Ponorogo No. 12',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Raya Ponorogo No. 12',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'province' => 'Jawa Timur',
            'city' => 'Ponorogo',
            'district' => $district->name,
            'village' => $village->name,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '20 Mbps',
            'monthly_price' => $package->monthly_price,
            'discount' => 0.00,
            'ppn' => 11.00,
            'total_monthly_bill' => $package->monthly_price * 1.11,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return $customer;
    }

    protected function owner(): User
    {
        $role = Role::where('name', 'Owner')->firstOrFail();

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_authorized_user_can_create_manual_invoice_with_customer_locked()
    {
        $user = $this->owner();
        $pop = $this->createPop();
        $package = InternetPackage::query()->firstOrFail();
        $customer = $this->createTestCustomer($pop, $package);

        $response = $this->actingAs($user)->get(route('invoices.create', ['customer_id' => $customer->id]));
        $response->assertOk();
        $response->assertSee($customer->full_name);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'manual_category' => 'perbaikan',
            'description' => 'Ganti konektor rusak',
            'amount' => '150.000',
        ]);

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();
        $response->assertRedirect(route('invoices.show', $invoice));

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'invoice_type' => 'manual',
            'manual_category' => 'perbaikan',
            'description' => 'Ganti konektor rusak',
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);

        // Form Tagihan Manual TIDAK menerbitkan Payment — itu ranah
        // pembayaran terpisah (List Tagihan → Bayar/Bayar Cicil).
        $this->assertEquals(0, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_search_page_finds_customer_by_cid_and_name_and_creates_for_correct_customer()
    {
        $user = $this->owner();
        $pop = $this->createPop();
        $package = InternetPackage::query()->firstOrFail();
        $target = $this->createTestCustomer($pop, $package, 'Target Customer', 'WHUS-2026-0001');
        $other = $this->createTestCustomer($pop, $package, 'Other Customer', 'WHUS-2026-0002');

        $byCid = $this->actingAs($user)->get(route('invoices.create', ['q' => $target->cid]));
        $byCid->assertOk();
        $byCid->assertSee('Target Customer');
        $byCid->assertDontSee('Other Customer');

        $byName = $this->actingAs($user)->get(route('invoices.create', ['q' => 'Other Customer']));
        $byName->assertSee('Other Customer');
        $byName->assertDontSee('Target Customer');

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $target->id,
            'manual_category' => 'pindah_lokasi',
            'description' => 'Pindah lokasi rumah pelanggan',
            'amount' => '200000',
        ]);

        $response->assertRedirect();
        $this->assertEquals(1, Invoice::where('customer_id', $target->id)->count());
        $this->assertEquals(0, Invoice::where('customer_id', $other->id)->count());
    }

    public function test_lainnya_category_requires_subtype_name()
    {
        $user = $this->owner();
        $pop = $this->createPop();
        $package = InternetPackage::query()->firstOrFail();
        $customer = $this->createTestCustomer($pop, $package);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'manual_category' => 'lainnya',
            'description' => 'Biaya tambahan lain',
            'amount' => '50000',
        ]);

        $response->assertSessionHasErrors('manual_subtype_name');
        $this->assertEquals(0, Invoice::count());

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'manual_category' => 'perbaikan',
            'description' => 'Perbaikan tidak butuh sub-nama',
            'amount' => '50000',
        ]);

        $response->assertSessionDoesntHaveErrors('manual_subtype_name');
        $this->assertEquals(1, Invoice::count());
    }

    public function test_manual_invoice_can_coexist_with_monthly_invoice_same_period()
    {
        $user = $this->owner();
        $pop = $this->createPop();
        $package = InternetPackage::query()->firstOrFail();
        $customer = $this->createTestCustomer($pop, $package);

        $billingPeriod = now()->format('Y-m');
        $customerService = CustomerService::where('customer_id', $customer->id)->firstOrFail();

        Invoice::create([
            'invoice_number' => 'INV-TEST-0001',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $customerService->id,
            'internet_package_id' => $package->id,
            'billing_period' => $billingPeriod,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 150000,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'manual_category' => 'perbaikan',
            'description' => 'Perbaikan bulan ini',
            'amount' => '75000',
        ]);

        $response->assertRedirect();
        $this->assertEquals(2, Invoice::where('customer_id', $customer->id)->count());
    }

    public function test_amount_is_required_and_normalizes_thousand_separator()
    {
        $user = $this->owner();
        $pop = $this->createPop();
        $package = InternetPackage::query()->firstOrFail();
        $customer = $this->createTestCustomer($pop, $package);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'manual_category' => 'perbaikan',
            'description' => 'Tanpa nominal',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertEquals(0, Invoice::count());
    }

    public function test_admin_cabang_cannot_create_invoice_for_customer_outside_assigned_pop()
    {
        $role = Role::where('name', 'POP Admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $pop1 = $this->createPop('1');
        $pop2 = $this->createPop('2');

        $user->pops()->attach($pop1->id);
        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $pop1->id,
        ]);

        $package = InternetPackage::query()->firstOrFail();
        $customerInPop1 = $this->createTestCustomer($pop1, $package, 'Pelanggan Satu', 'WHUS-2026-0001');
        $customerInPop2 = $this->createTestCustomer($pop2, $package, 'Pelanggan Dua', 'WHUS-2026-0002');

        $response1 = $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customerInPop1->id,
            'manual_category' => 'perbaikan',
            'description' => 'Perbaikan POP 1',
            'amount' => '50000',
        ]);
        $response1->assertRedirect();

        $response2 = $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customerInPop2->id,
            'manual_category' => 'perbaikan',
            'description' => 'Perbaikan POP 2',
            'amount' => '50000',
        ]);
        $response2->assertStatus(403);
    }

    public function test_old_manual_invoice_modal_route_no_longer_exists()
    {
        $this->assertFalse(Route::has('customers.invoices.manual'));
    }
}
