<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ganti paket internet pelanggan aktif (Paket A → Paket B).
 *
 * `customers.detail.packages.change` sengaja permission TERPISAH dari
 * `customers.detail.packages.update` (dipakai form edit pelanggan umum) —
 * lihat App\Enums\ActionCode::CHANGE.
 */
class CustomerPackageChangeTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private InternetPackage $paketLama;

    private InternetPackage $paketBaru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        Village::create(['district_id' => $district->id, 'name' => 'Babadan']);

        $this->pop = Pop::create([
            'name' => 'POP Babadan',
            'type' => 'cabang',
            'code' => 'BBD-PKT',
            'cid_prefix' => 'BBD-PKT',
            'registration_prefix' => 'REG-BBD-PKT',
        ]);

        $this->paketLama = InternetPackage::create([
            'name' => 'Paket A 10 Mbps',
            'package_code' => 'PKT-A-10',
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '10 Mbps',
            'download_speed_mbps' => 10,
            'upload_speed_mbps' => 10,
            'monthly_price' => 150000,
        ]);

        $this->paketBaru = InternetPackage::create([
            'name' => 'Paket B 20 Mbps',
            'package_code' => 'PKT-B-20',
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '20 Mbps',
            'download_speed_mbps' => 20,
            'upload_speed_mbps' => 20,
            'monthly_price' => 250000,
        ]);
    }

    private function makeActiveCustomerWithService(): Customer
    {
        $customer = Customer::create([
            'full_name' => 'Budi Santoso',
            'primary_phone' => '081234567890',
            'registration_date' => now()->subMonths(2)->toDateString(),
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->paketLama->id,
            'status' => 'active',
            'customer_code' => 'REG-BBD-PKT-0001',
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->paketLama->id,
            'package_name_snapshot' => $this->paketLama->name,
            'download_speed_snapshot' => '10 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => $this->paketLama->monthly_price,
            'discount' => 5000,
            'ppn' => 11,
            'other_fee' => 2000,
            'total_monthly_bill' => (150000 - 5000) * 1.11 + 2000,
            'activation_date' => now()->subMonths(2)->toDateString(),
            'due_date' => now()->subMonth()->toDateString(),
            'billing_cycle' => 'monthly',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return $customer->fresh();
    }

    public function test_admin_bisa_ganti_paket_pelanggan_aktif(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();

        $response = $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        $response->assertRedirect(route('customers.show', $customer));
        $response->assertSessionHas('success');

        $service = $customer->customerService()->first();
        $this->assertSame($this->paketBaru->id, $service->internet_package_id);
        $this->assertSame('Paket B 20 Mbps', $service->package_name_snapshot);
        $this->assertSame('20.00 Mbps', $service->download_speed_snapshot);
        $this->assertEqualsWithDelta(250000.0, (float) $service->monthly_price, 0.01);

        // Diskon, PPN, biaya lain TIDAK ikut berubah — cuma harga & identitas paket.
        $this->assertEqualsWithDelta(5000.0, (float) $service->discount, 0.01);
        $this->assertEqualsWithDelta(11.0, (float) $service->ppn, 0.01);
        $expectedTotal = (250000 - 5000) * 1.11 + 2000;
        $this->assertEqualsWithDelta($expectedTotal, (float) $service->total_monthly_bill, 0.01);

        // customers.internet_package_id (FK legacy) ikut disinkron.
        $this->assertSame($this->paketBaru->id, $customer->fresh()->internet_package_id);
    }

    public function test_ganti_paket_ke_paket_yang_sama_ditolak(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();

        $response = $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketLama->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame($this->paketLama->id, $customer->fresh()->customerService->internet_package_id);
    }

    public function test_role_tanpa_permission_change_ditolak_403(): void
    {
        // Role custom yang cuma punya .view + .update (edit form umum),
        // TANPA .change — RBAC dinamis, dua permission independen.
        $role = Role::create(['name' => 'Helpdesk Tanpa Ganti Paket', 'code' => 'helpdesk-no-change-'.uniqid(), 'is_system' => false]);
        $role->permissions()->attach(Permission::whereIn('code', [
            'customers.view',
            'customers.detail.view',
            'customers.detail.packages.view',
            'customers.detail.packages.update',
        ])->get());

        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $this->actingAs($user);

        $customer = $this->makeActiveCustomerWithService();

        $response = $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        $response->assertForbidden();
        $this->assertSame($this->paketLama->id, $customer->fresh()->customerService->internet_package_id);
    }

    public function test_tagihan_bulan_berjalan_yang_sudah_terbit_tidak_berubah(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeActiveCustomerWithService();

        // Simulasikan tagihan BULANAN periode berjalan yang sudah terbit
        // dengan harga paket lama — harus tetap utuh setelah ganti paket.
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-0001',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $customer->customerService->id,
            'internet_package_id' => $this->paketLama->id,
            'billing_period' => now()->format('Y-m'),
            'issue_date' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->day(10)->toDateString(),
            'subtotal' => 150000,
            'discount' => 5000,
            'ppn' => 11,
            'total_amount' => (150000 - 5000) * 1.11,
            'paid_amount' => 0,
            'remaining_amount' => (150000 - 5000) * 1.11,
            'invoice_status' => 'belum_dibayar',
        ]);

        $this->put(route('customers.package.update', $customer), [
            'internet_package_id' => $this->paketBaru->id,
        ]);

        $invoice->refresh();
        $this->assertEqualsWithDelta(150000.0, (float) $invoice->subtotal, 0.01);
        $this->assertSame($this->paketLama->id, $invoice->internet_package_id);
    }
}
