<?php

namespace Tests\Feature;

use App\Enums\WorkflowTransition;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\PackageCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\BusinessDevelopmentVerificationFeatureSeeder;
use Database\Seeders\CustomerAcquisitionFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Gate "Menunggu Verifikasi BD" — pelanggan kategori Bisnis TIDAK langsung
 * ACTIVE di `finalVerify()` (CS), nyangkut di
 * WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION sampai
 * Business Development (BD) isi Biaya Instalasi & verifikasi di
 * `/business-development-verifications`. Pelanggan Home Broadband TIDAK
 * PERNAH kena gate ini (regresi dijaga CustomerFinalVerificationTest,
 * dites ulang di sini demi kelengkapan).
 */
class BusinessDevelopmentVerificationGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(CustomerAcquisitionFeatureSeeder::class);
        $this->seed(BusinessDevelopmentVerificationFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);
    }

    private function createCustomerWithCategory(string $categoryName): Customer
    {
        $pop = Pop::create([
            'code' => 'POP-TEST-'.uniqid(),
            'pop_code' => 'TST'.random_int(1000, 9999),
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $category = PackageCategory::firstOrCreate(['name' => $categoryName]);
        $package = InternetPackage::create([
            'package_code' => 'PKG-'.uniqid(),
            'name' => $categoryName.' 50 Mbps',
            'category' => $category->name,
            'package_group' => $category->name,
            'bandwidth_label' => '50 Mbps',
            'monthly_price' => 500000,
            'is_active' => true,
        ]);

        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'D00C'.random_int(100000, 999999),
            'full_name' => 'PT Uji Bisnis',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'installed',
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
            'monthly_price' => $package->monthly_price,
            'discount' => 0.00,
            'ppn' => 11.00,
            'total_monthly_bill' => $package->monthly_price * 1.11,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'menunggu_pemasangan',
            'billing_status' => 'pending',
        ]);

        return $customer;
    }

    private function finalVerify(Customer $customer): TestResponse
    {
        return $this->post(route('customers.verification.final', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-08',
            'subtotal' => 500000,
            'discount' => 0,
            'ppn' => 55000,
            'prorate_amount' => 0,
            'total_amount' => 555000,
        ]);
    }

    public function test_home_category_customer_still_activates_immediately(): void
    {
        $this->loginAsAdmin();
        $customer = $this->createCustomerWithCategory('Paket Home Broadband');

        $response = $this->finalVerify($customer);

        $response->assertRedirect('/verifications/queue');
        $this->assertEquals(WorkflowTransition::ACTIVE->value, $customer->fresh()->status);
        $this->assertDatabaseHas('customer_acquisitions', ['customer_id' => $customer->id]);
    }

    public function test_bisnis_category_customer_waits_for_bd_instead_of_activating(): void
    {
        $this->loginAsAdmin();
        $customer = $this->createCustomerWithCategory('Paket Bisnis Broadband');

        $response = $this->finalVerify($customer);

        $response->assertRedirect('/verifications/queue');
        $customer->refresh();
        $this->assertEquals(WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION->value, $customer->status);

        // Invoice Awal SENGAJA BELUM terbit (ditandai user sebagai bug
        // 2026-09-14) — baru terbit saat BD verifikasi. Snapshotnya
        // dititipkan di pending_initial_invoice.
        $this->assertDatabaseMissing('invoices', ['customer_id' => $customer->id, 'invoice_type' => 'awal']);
        $this->assertNotNull($customer->pending_initial_invoice);
        $this->assertEquals('2026-06-01', $customer->pending_initial_invoice['issue_date']);

        // Baris CustomerAcquisition belum ada — observer cuma bikin baris
        // itu begitu status BENERAN jadi ACTIVE.
        $this->assertDatabaseMissing('customer_acquisitions', ['customer_id' => $customer->id]);
    }

    /**
     * Regresi (laporan user, CID C00RQ002023): form verifikasi admin
     * prefill "Biaya Pemasangan" dari `internet_packages.installation_fee`
     * bawaan paket — buat kategori Bisnis itu keliru, dobel sama tagihan
     * Biaya Instalasi yang diterbitkan BD. Server WAJIB menimpa ke 0 di
     * `finalVerify()`, bukan cuma disable input di form (POST manual/replay
     * masih bisa kirim nilai apa pun).
     */
    public function test_extra_installation_fee_is_forced_to_zero_for_bisnis_category_even_if_client_sends_nonzero(): void
    {
        $this->loginAsAdmin();
        $customer = $this->createCustomerWithCategory('Paket Bisnis Broadband');
        InternetPackage::where('id', $customer->internet_package_id)->update(['installation_fee' => 250000]);

        $response = $this->post(route('customers.verification.final', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-08',
            'extra_installation_fee' => '250.000', // seolah client masih kirim prefill lama
            'subtotal' => 500000,
            'discount' => 0,
            'ppn' => 55000,
            'prorate_amount' => 0,
            'total_amount' => 805000,
        ]);

        $response->assertRedirect('/verifications/queue');

        // Invoice Awal kategori Bisnis belum terbit di titik ini (lihat
        // test di atas) — assersinya pindah ke snapshot yang dititipkan.
        $pending = $customer->fresh()->pending_initial_invoice;
        $this->assertEquals(0, (float) $pending['billing']['extra_installation_fee']);
    }

    public function test_pending_customer_appears_in_business_development_verification_queue(): void
    {
        $user = $this->loginAsAdmin();
        $this->giveAllPopScope($user);
        $customer = $this->createCustomerWithCategory('Paket Bisnis Broadband');
        $this->finalVerify($customer);

        $response = $this->get(route('business-development-verifications.index'));

        $response->assertOk();
        $response->assertSee($customer->full_name);
    }

    /**
     * Kebalikan dari regresi di atas — nilai bawaan paket TETAP dipakai,
     * cuma dipindah ke tempat yang benar: prefill "Biaya Instalasi" di
     * halaman BD, bukan "Biaya Pemasangan" di verifikasi CS.
     */
    public function test_business_development_show_page_prefills_installation_fee_from_package_base_value(): void
    {
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $bdUser = User::factory()->create(['status' => 'active', 'role_id' => $bdRole->id]);
        $this->giveAllPopScope($bdUser);

        $this->loginAsAdmin();
        $customer = $this->createCustomerWithCategory('Paket Bisnis Broadband');
        InternetPackage::where('id', $customer->internet_package_id)->update(['installation_fee' => 250000]);
        $this->finalVerify($customer);

        $this->actingAs($bdUser);
        $response = $this->get(route('business-development-verifications.show', $customer));

        $response->assertOk();
        $response->assertSee('value="250.000"', false);
    }

    /**
     * Ditanyakan user: halaman BD tidak nunjukin data yang sudah diverifikasi
     * CS (tanggal aktivasi, prorata, biaya pemasangan CS=0, total tagihan) —
     * sebelumnya cuma "Ringkasan Layanan" statis dari master paket. Fix:
     * `CustomerVerificationDetailService` menampilkan snapshot hasil hitung
     * CS (`pending_initial_invoice`) ditandai "Belum Terbit" — Invoice AWAL
     * SUNGGUHAN belum ada di titik ini (baru terbit saat BD verifikasi,
     * lihat susulan bug tanggal aktivasi/tagihan awal 2026-09-14).
     */
    public function test_business_development_show_page_displays_pending_cs_verification_result(): void
    {
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $bdUser = User::factory()->create(['status' => 'active', 'role_id' => $bdRole->id]);
        $this->giveAllPopScope($bdUser);

        $this->loginAsAdmin();
        $customer = $this->createCustomerWithCategory('Paket Bisnis Broadband');
        $this->finalVerify($customer);

        $this->assertDatabaseMissing('invoices', ['customer_id' => $customer->id, 'invoice_type' => 'awal']);

        $this->actingAs($bdUser);
        $response = $this->get(route('business-development-verifications.show', $customer));

        $response->assertOk();
        $response->assertSee('Hasil Verifikasi CS');
        $response->assertSee('Belum Terbit');
    }

    /**
     * Invoice AWAL kategori Bisnis baru sah terbit setelah BD verifikasi —
     * bukan lagi di titik CS (`finalVerify()`). Koreksi 2026-09-16 (laporan
     * user — "kenapa muncul 2 tagihan pada 1 pelanggan"): sebelumnya BD
     * menerbitkan INVOICE KEDUA terpisah (INSIDENTAL) buat Biaya Instalasi,
     * niat aslinya SATU invoice yang mencatat biaya CS *dan* biaya BD
     * sekaligus. Sekarang cuma SATU invoice AWAL — `extra_installation_fee`-nya
     * nominal BD (bukan 0), `total_amount` dihitung ulang dengan nominal itu
     * (bukan cuma dijumlah dari snapshot CS + fee BD mentah — PPN ikut
     * dihitung ulang di atas subtotal barunya).
     */
    public function test_business_development_verify_issues_one_merged_initial_invoice(): void
    {
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $bdUser = User::factory()->create(['status' => 'active', 'role_id' => $bdRole->id]);
        $this->giveAllPopScope($bdUser);

        $this->loginAsAdmin();
        $customer = $this->createCustomerWithCategory('Paket Bisnis Broadband');
        $this->finalVerify($customer);
        $pendingBilling = $customer->fresh()->pending_initial_invoice['billing'];

        $this->actingAs($bdUser);
        $response = $this->put(route('business-development-verifications.verify', $customer), [
            'installation_fee' => 300000,
        ]);
        $response->assertRedirect(route('business-development-verifications.index'));

        // Cuma SATU invoice buat pelanggan ini — bukan dua.
        $this->assertEquals(1, Invoice::where('customer_id', $customer->id)->count());

        $invoice = Invoice::where('customer_id', $customer->id)->where('invoice_type', 'awal')->firstOrFail();
        $this->assertEquals(300000, (float) $invoice->extra_installation_fee);

        // Total = (prorata + 300rb Biaya Instalasi + 0 kabel/tiang/lain) - diskon(0),
        // lalu PPN 11% (sama formula InitialInvoiceService::calculate()/withInstallationFee()).
        $expectedSubtotal = $pendingBilling['prorate_amount'] + 300000;
        $expectedTotal = round($expectedSubtotal * 1.11, 2);
        $this->assertEquals($expectedTotal, (float) $invoice->total_amount);

        $this->assertNull($customer->fresh()->pending_initial_invoice);

        $ca = CustomerAcquisition::where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals(300000, $ca->installation_fee);
        $this->assertEquals($invoice->id, $ca->installation_fee_invoice_id);
    }

    /**
     * Diminta eksplisit user: halaman `/business-development-verifications/{id}`
     * harus tampil PERSIS seperti `/verifications/{id}/admin` (tab Registrasi/
     * Survey/Pemasangan/Pengujian, badge status), bukan form kosong terpisah
     * — cuma tab "Verifikasi"-nya yang beda isi (form BD, bukan form CS).
     */
    public function test_business_development_show_page_reuses_verification_admin_view(): void
    {
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $bdUser = User::factory()->create(['status' => 'active', 'role_id' => $bdRole->id]);
        $this->giveAllPopScope($bdUser);

        $this->loginAsAdmin();
        $customer = $this->createCustomerWithCategory('Paket Bisnis Broadband');
        $this->finalVerify($customer);

        $this->actingAs($bdUser);
        $response = $this->get(route('business-development-verifications.show', $customer));

        $response->assertOk();
        // Badge status & tab nav — bukti view yang dipakai memang
        // verifications/admin.blade.php, bukan halaman terpisah.
        $response->assertSee('Verifikasi BD');
        $response->assertSee('id="tab-btn-registrasi"', false);
        $response->assertSee('id="tab-btn-survey"', false);
        $response->assertSee('id="tab-btn-pemasangan"', false);
        $response->assertSee('id="tab-btn-pengujian"', false);
        $response->assertSee('id="tab-btn-verifikasi"', false);
        // Form CS (finalVerify) TIDAK ikut ke-render di halaman BD.
        $response->assertDontSee('name="extra_installation_fee"', false);
        $response->assertDontSee(route('customers.verification.final', $customer), false);
    }

    /**
     * Kebalikan: kalau pelanggan WAITING_BUSINESS_DEVELOPMENT_VERIFICATION
     * dibuka lewat route CS (`/verifications/{id}/admin`, mis. CS yang
     * hasFullAccess/owner iseng klik URL lama), view yang SAMA otomatis
     * nampilin cabang BD juga (murni berdasar status pelanggan, bukan route
     * mana yang dipakai) — bukti satu sumber kebenaran, bukan dua page yang
     * bisa saling menyimpang.
     */
    public function test_verification_admin_route_shows_bd_branch_for_waiting_bd_customer(): void
    {
        $this->loginAsAdmin();
        $customer = $this->createCustomerWithCategory('Paket Bisnis Broadband');
        $this->finalVerify($customer);

        $response = $this->get(route('customers.verification.admin', $customer));

        $response->assertOk();
        $response->assertSee('Verifikasi BD');
        $response->assertSee('name="installation_fee"', false);
        $response->assertDontSee('name="extra_installation_fee"', false);
    }

    /**
     * Label tombol & dialog konfirmasi di halaman verifikasi CS harus JUJUR
     * soal apa yang beneran terjadi — kategori Bisnis TIDAK langsung Aktif
     * di sini (nyangkut di antrean BD dulu), jadi tombolnya gak boleh
     * bilang "Aktivasi" kalau kategorinya Bisnis.
     */
    public function test_verification_admin_button_label_reflects_category(): void
    {
        $this->loginAsAdmin();

        $home = $this->createCustomerWithCategory('Paket Home Broadband');
        $this->get(route('customers.verification.admin', $home))
            ->assertSee('Aktivasi & Terbitkan Tagihan');

        $bisnis = $this->createCustomerWithCategory('Paket Bisnis Broadband');
        $response = $this->get(route('customers.verification.admin', $bisnis));
        $response->assertSee('Verifikasi & Terbitkan Tagihan');
        $response->assertDontSee('Aktivasi & Terbitkan Tagihan');
    }

    public function test_role_without_configured_access_cannot_open_verification_page(): void
    {
        $customer = $this->createCustomerWithCategory('Paket Bisnis Broadband');
        $this->loginAsAdmin();
        $this->finalVerify($customer);

        $atasanRole = Role::where('code', 'atasan')->firstOrFail();
        $atasan = User::factory()->create(['status' => 'active', 'role_id' => $atasanRole->id]);
        $this->actingAs($atasan);

        $response = $this->get(route('business-development-verifications.show', $customer));
        $response->assertForbidden();
    }

    public function test_configured_role_can_verify_and_activate_with_prefilled_installation_fee(): void
    {
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $bdUser = User::factory()->create(['status' => 'active', 'role_id' => $bdRole->id]);
        $this->giveAllPopScope($bdUser);

        $this->loginAsAdmin();
        $customer = $this->createCustomerWithCategory('Paket Bisnis UKM');
        $this->finalVerify($customer);

        $this->actingAs($bdUser);
        $response = $this->put(route('business-development-verifications.verify', $customer), [
            'installation_fee' => 1500000,
        ]);

        $response->assertRedirect(route('business-development-verifications.index'));

        $customer->refresh();
        $this->assertEquals(WorkflowTransition::ACTIVE->value, $customer->status);

        $ca = CustomerAcquisition::where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals(1500000, $ca->installation_fee);
        $this->assertNotNull($ca->installation_fee_invoice_id);
        // SATU invoice AWAL yang sudah menyatukan biaya CS + BD (koreksi
        // 2026-09-16) — bukan INSIDENTAL terpisah lagi.
        $this->assertEquals('awal', $ca->installationFeeInvoice->invoice_type->value);
    }

    public function test_verifying_an_already_active_customer_is_rejected(): void
    {
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $bdUser = User::factory()->create(['status' => 'active', 'role_id' => $bdRole->id]);
        $this->giveAllPopScope($bdUser);

        $this->loginAsAdmin();
        $customer = $this->createCustomerWithCategory('Paket Bisnis UKM');
        $this->finalVerify($customer);

        $this->actingAs($bdUser);
        $this->put(route('business-development-verifications.verify', $customer), ['installation_fee' => 1000000]);

        // Sudah ACTIVE — percobaan verifikasi kedua ditolak.
        $response = $this->put(route('business-development-verifications.verify', $customer), ['installation_fee' => 999999]);
        $response->assertNotFound();
    }
}
