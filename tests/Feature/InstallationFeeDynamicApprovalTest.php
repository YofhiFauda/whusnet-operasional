<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\PackageCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\CustomerAcquisitionFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Validasi Biaya Instalasi" — paket Bisnis butuh Busdev isi nominal
 * terpisah, paket Home Broadband TIDAK (alur tetap seperti biasa). Siapa
 * yang boleh isi ditentukan DINAMIS lewat pilihan ROLE di
 * `package_categories.installation_fee_approval_role_id` (Master Kategori
 * Paket, nama role biasa — bukan kode permission mentah, dikoreksi user
 * karena versi permission terlalu teknis buat admin non-developer). Jalur
 * teknis (permission `customer_acquisitions.installation_fee.update` lewat
 * Role Matrix) tetap ada sebagai override kedua.
 */
class InstallationFeeDynamicApprovalTest extends TestCase
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

    private function makeAcquisition(string $categoryName): CustomerAcquisition
    {
        $category = PackageCategory::firstOrCreate(['name' => $categoryName]);
        $package = InternetPackage::create([
            'package_code' => 'PKG-'.uniqid(),
            'name' => $category->name.' 50 Mbps',
            'category' => $category->name,
            'package_group' => $category->name,
            'bandwidth_label' => '50 Mbps',
            'monthly_price' => 500000,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create(['status' => 'active']);
        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 500000,
            'total_monthly_bill' => 500000,
            'service_status' => 'aktif',
            'billing_status' => 'pending',
        ]);

        return CustomerAcquisition::factory()->for($customer)->create([
            'periode' => now()->format('Y-m'),
            'verified_at' => now(),
        ]);
    }

    public function test_home_broadband_category_does_not_need_installation_fee_validation(): void
    {
        $record = $this->makeAcquisition('Paket Home Broadband');

        $record->load('customer.customerService.internetPackage');

        $this->assertFalse($record->needsInstallationFeeValidation());
        $this->assertNull($record->installationFeeApprovalRole());
    }

    public function test_bisnis_category_needs_installation_fee_validation_via_configured_role(): void
    {
        $record = $this->makeAcquisition('Paket Bisnis Broadband');

        $record->load('customer.customerService.internetPackage');

        $this->assertTrue($record->needsInstallationFeeValidation());
        $this->assertSame('business_development', $record->installationFeeApprovalRole()?->code);
    }

    public function test_user_with_unconfigured_role_and_no_override_permission_cannot_fill_installation_fee(): void
    {
        // Owner tetap bisa lewat wildcard '*' — pakai role netral (atasan)
        // yang TIDAK sama dengan role terkonfigurasi DAN TIDAK di-grant
        // 'customer_acquisitions.installation_fee.update' di
        // RolePermissionSeeder, biar gerbangnya benar-benar diuji.
        $atasanRole = Role::where('code', 'atasan')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $atasanRole->id]);
        $this->actingAs($user);

        $record = $this->makeAcquisition('Paket Bisnis Broadband');

        $response = $this->put(route('customer-acquisitions.installation-fee.update', $record), [
            'installation_fee' => 2000000,
        ]);

        $response->assertForbidden();
        $this->assertNull($record->fresh()->installation_fee);
    }

    /**
     * Isi Biaya Instalasi BUKAN cuma catatan — langsung menerbitkan tagihan
     * sungguhan (INSIDENTAL, kategori Jasa Instalasi), terpisah dari Invoice
     * Awal yang dibuat CS (`extra_installation_fee`).
     */
    public function test_configured_role_can_fill_installation_fee_and_it_issues_a_real_invoice(): void
    {
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $bdRole->id]);
        $this->giveAllPopScope($user);
        $this->actingAs($user);

        $record = $this->makeAcquisition('Paket Bisnis Broadband');

        $response = $this->put(route('customer-acquisitions.installation-fee.update', $record), [
            'installation_fee' => 2000000,
        ]);

        $response->assertRedirect();
        $record->refresh();
        $this->assertEquals(2000000, $record->installation_fee);
        $this->assertNotNull($record->installation_fee_invoice_id);

        $invoice = $record->installationFeeInvoice;
        $this->assertSame(InvoiceType::INSIDENTAL, $invoice->invoice_type);
        $this->assertEquals(2000000, $invoice->total_amount);
        $this->assertSame($record->customer_id, $invoice->customer_id);
    }

    /**
     * Regresi: `invoice_status` sudah di-cast enum di model `Invoice` — view
     * yang salah wrap ulang (`InvoiceStatus::from($enumInstance)`) atau
     * salah bandingin (`$enum === 'lunas'`) bakal meledak/salah warna diam-
     * diam, cuma ketauan kalau baris yang SUDAH punya invoice ikut dirender.
     */
    public function test_index_page_renders_after_installation_fee_invoice_is_issued(): void
    {
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $bdRole->id]);
        $this->giveAllPopScope($user);
        $this->actingAs($user);

        $record = $this->makeAcquisition('Paket Bisnis Broadband');
        $this->put(route('customer-acquisitions.installation-fee.update', $record), [
            'installation_fee' => 2000000,
        ]);

        $response = $this->get(route('customer-acquisitions.index'));

        $response->assertOk();
        $response->assertSee($record->fresh()->installationFeeInvoice->invoice_number);
    }

    public function test_installation_fee_locked_once_invoice_is_issued(): void
    {
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $bdRole->id]);
        $this->giveAllPopScope($user);
        $this->actingAs($user);

        $record = $this->makeAcquisition('Paket Bisnis Broadband');

        $this->put(route('customer-acquisitions.installation-fee.update', $record), [
            'installation_fee' => 2000000,
        ]);

        $firstInvoiceId = $record->fresh()->installation_fee_invoice_id;

        $response = $this->put(route('customer-acquisitions.installation-fee.update', $record), [
            'installation_fee' => 9999999,
        ]);

        $response->assertRedirect();
        $record->refresh();
        $this->assertEquals(2000000, $record->installation_fee);
        $this->assertSame($firstInvoiceId, $record->installation_fee_invoice_id);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_installation_fee_rejected_for_category_that_does_not_need_it(): void
    {
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $bdRole->id]);
        $this->giveAllPopScope($user);
        $this->actingAs($user);

        $record = $this->makeAcquisition('Paket Home Broadband');

        $response = $this->put(route('customer-acquisitions.installation-fee.update', $record), [
            'installation_fee' => 2000000,
        ]);

        $response->assertNotFound();
        $this->assertNull($record->fresh()->installation_fee);
    }

    /**
     * Inti "dinamis": ganti role terkonfigurasi lewat Master Kategori Paket,
     * TANPA ubah kode — user lama (business_development) langsung kehilangan
     * akses begitu kategorinya dialihkan ke role lain.
     */
    public function test_reassigning_category_role_changes_who_can_approve_without_code_change(): void
    {
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $bdUser = User::factory()->create(['status' => 'active', 'role_id' => $bdRole->id]);
        $this->giveAllPopScope($bdUser);

        $record = $this->makeAcquisition('Paket Bisnis Dedicated');

        // Admin mengalihkan kategori ini ke role lain (sales) yang BD bukan
        // anggotanya, dan BD tidak punya permission override.
        $salesRole = Role::where('code', 'sales')->firstOrFail();
        PackageCategory::where('name', 'Paket Bisnis Dedicated')
            ->update(['installation_fee_approval_role_id' => $salesRole->id]);

        $this->actingAs($bdUser);
        $response = $this->put(route('customer-acquisitions.installation-fee.update', $record), [
            'installation_fee' => 1000000,
        ]);
        $response->assertForbidden();

        // User role Sales (role baru yang dikonfigurasi) sekarang lolos.
        $salesUser = User::factory()->create(['status' => 'active', 'role_id' => $salesRole->id]);
        $this->giveAllPopScope($salesUser);
        $this->actingAs($salesUser);
        $response = $this->put(route('customer-acquisitions.installation-fee.update', $record), [
            'installation_fee' => 1000000,
        ]);
        $response->assertRedirect();
        $this->assertEquals(1000000, $record->fresh()->installation_fee);
    }

    public function test_override_permission_via_role_matrix_always_passes_regardless_of_configured_role(): void
    {
        $record = $this->makeAcquisition('Paket Bisnis UKM');

        // Admin (wildcard '*') lolos meski role-nya bukan yang dikonfigurasi
        // di kategori (Business Development).
        $this->loginAsAdmin();
        $response = $this->put(route('customer-acquisitions.installation-fee.update', $record), [
            'installation_fee' => 750000,
        ]);
        $response->assertRedirect();
        $this->assertEquals(750000, $record->fresh()->installation_fee);
    }
}
