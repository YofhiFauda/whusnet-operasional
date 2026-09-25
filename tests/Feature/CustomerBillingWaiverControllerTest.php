<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerBillingWaiver;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\ActionSeeder;
use Database\Seeders\BillingWaiverFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Cuti Berlangganan" (ADHOC-87) — pintu kedua ke `BillingPeriodWaiverService`,
 * HTTP-level: permission `billing_waivers.*` terpisah dari `customers.deactivate`,
 * POP scope, dan status pelanggan TIDAK berubah.
 */
class CustomerBillingWaiverControllerTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;

    private Pop $popB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InternetPackageSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(BillingWaiverFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->popA = Pop::create([
            'code' => 'POP-CBW-A', 'pop_code' => 'CBA', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Cuti A', 'type' => 'cabang', 'status' => 'active',
        ]);
        $this->popB = Pop::create([
            'code' => 'POP-CBW-B', 'pop_code' => 'CBB', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Cuti B', 'type' => 'cabang', 'status' => 'active',
        ]);
    }

    private function customerWithService(Pop $pop, string $code): Customer
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '0812'.substr($code, -7),
            'registration_date' => now()->subYears(2),
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => InternetPackage::first()->id,
            'service_status' => 'aktif',
            'billing_cycle' => 'monthly',
            'package_name_snapshot' => 'Paket Test',
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
            'activation_date' => now()->subYears(2),
        ]);

        return $customer->fresh();
    }

    private function makeUser(string $roleCode, ?Pop $scopedTo = null): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        if ($scopedTo) {
            $user->pops()->attach($scopedTo->id);
            $scope = UserRoleScope::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_type' => ScopeType::SELECTED_POP,
            ]);
            $scope->targets()->create(['pop_id' => $scopedTo->id]);
        } else {
            // User::factory() sendirian TIDAK bikin baris UserRoleScope —
            // tanpa ini getAllowedPopIds() balikin array kosong (deny-by-
            // default), bukan akses penuh, walau role-nya admin/owner (lihat
            // TestCase::giveAllPopScope()). Test permission murni (bukan
            // test POP scope) butuh access lintas POP biar gak 403 gara-gara
            // scope, bukan gara-gara permission.
            $this->giveAllPopScope($user);
        }

        return $user;
    }

    #[Test]
    public function admin_bisa_cuti_kan_periode_belum_terbit(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->customerWithService($this->popA, 'C-CBW-000001');
        $period = now()->addMonth()->format('Y-m');

        $response = $this->actingAs($admin)->post(route('billing-waivers.store', $customer), [
            'periods' => [$period],
            'reason' => 'Pelanggan cuti sebulan',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('customer_billing_waivers', [
            'customer_id' => $customer->id,
            'billing_period' => $period,
            'source' => 'leave',
        ]);
        $this->assertSame('active', $customer->fresh()->status);
    }

    #[Test]
    public function helpdesk_tanpa_permission_ditolak_403(): void
    {
        $helpdesk = $this->makeUser('helpdesk');
        $customer = $this->customerWithService($this->popA, 'C-CBW-000002');
        $period = now()->addMonth()->format('Y-m');

        $this->actingAs($helpdesk)->post(route('billing-waivers.store', $customer), [
            'periods' => [$period],
            'reason' => 'Coba tanpa izin',
        ])->assertForbidden();

        $this->assertDatabaseMissing('customer_billing_waivers', ['customer_id' => $customer->id]);
    }

    #[Test]
    public function pop_admin_luar_scope_ditolak(): void
    {
        $popAdminB = $this->makeUser('pop_admin', $this->popB);
        $customerInPopA = $this->customerWithService($this->popA, 'C-CBW-000003');
        $period = now()->addMonth()->format('Y-m');

        $this->actingAs($popAdminB)->post(route('billing-waivers.store', $customerInPopA), [
            'periods' => [$period],
            'reason' => 'Coba lintas POP',
        ])->assertForbidden();
    }

    #[Test]
    public function cabut_waiver_via_http_menghapus_baris_tanpa_hidupkan_invoice(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->customerWithService($this->popA, 'C-CBW-000004');
        $service = $customer->customerService;
        $period = now()->format('Y-m');

        $invoice = Invoice::create([
            'invoice_number' => 'INV-CBW-EXIST',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $service->internet_package_id,
            'billing_period' => $period,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);

        $this->actingAs($admin)->post(route('billing-waivers.store', $customer), [
            'periods' => [$period],
            'reason' => 'Cuti bulan ini',
        ])->assertSessionHas('success');

        $waiver = CustomerBillingWaiver::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('billing-waivers.destroy', $waiver), [
            'reason' => 'Ternyata masih pakai, mau ditagih lagi',
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('customer_billing_waivers', ['id' => $waiver->id]);
        $this->assertSame('batal', $invoice->fresh()->invoice_status->value);
    }

    #[Test]
    public function cabut_tanpa_alasan_ditolak_validasi(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->customerWithService($this->popA, 'C-CBW-000005');
        $period = now()->addMonth()->format('Y-m');

        $this->actingAs($admin)->post(route('billing-waivers.store', $customer), [
            'periods' => [$period],
            'reason' => 'Cuti',
        ]);

        $waiver = CustomerBillingWaiver::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('billing-waivers.destroy', $waiver), [])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('customer_billing_waivers', ['id' => $waiver->id]);
    }
}
