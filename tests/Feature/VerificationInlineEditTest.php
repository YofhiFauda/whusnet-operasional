<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerSurvey;
use App\Models\CustomerTechnicalDetail;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use App\Services\EffectiveAccessService;
use App\Services\RoleManagementService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\CustomerRegistrationVerificationFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bug 2026-09-30 (laporan user): CS harus bisa mengoreksi data pelanggan
 * LANGSUNG dari layar Verifikasi Registrasi/Survey/Pemasangan/Validasi Admin,
 * tanpa keluar ke Edit Pelanggan. Field per tahap SENGAJA bertingkat:
 *  - Verifikasi Registrasi (status `registered`): Data Diri + Paket.
 *  - Verifikasi Survey/Pemasangan/Validasi Admin (satu halaman, `verifications/
 *    admin.blade.php`, beda tab per tahap): Data Diri + Paket + Data Survey.
 *
 * TIDAK termasuk (keputusan eksplisit, lihat CustomerVerificationEditService):
 * POP/Mini POP/Distribusi/CID, `customer_type`, dan Data Pemasangan (SN/ODP-
 * OLT device) — field terakhir itu didefer karena `CustomerController.php`
 * sedang dikerjakan paralel di area yang sama (device/technical detail).
 */
class VerificationInlineEditTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected InternetPackage $packageA;

    protected InternetPackage $packageB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(CustomerRegistrationVerificationFeatureSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN', 'pop_code' => 'SMN', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Sooko', 'type' => 'cabang', 'status' => 'active',
        ]);

        $this->packageA = InternetPackage::create([
            'package_code' => 'NET50', 'name' => 'Net 50', 'category' => 'Home', 'package_group' => 'Net',
            'bandwidth_label' => '50 Mbps', 'monthly_price' => 100000, 'is_active' => true,
        ]);
        $this->packageB = InternetPackage::create([
            'package_code' => 'NET100', 'name' => 'Net 100', 'category' => 'Home', 'package_group' => 'Net',
            'bandwidth_label' => '100 Mbps', 'monthly_price' => 200000, 'is_active' => true,
        ]);
    }

    private function village(): Village
    {
        $city = City::create(['name' => 'Kota Uji Verif '.uniqid()]);
        $district = District::create(['city_id' => $city->id, 'name' => 'Kecamatan Uji']);

        return Village::create(['district_id' => $district->id, 'name' => 'Desa Uji']);
    }

    private function customerAt(string $status, array $extra = []): Customer
    {
        $village = $this->village();

        return Customer::factory()->create(array_merge([
            'status' => $status,
            'pop_id' => $this->pop->id,
            'village_id' => $village->id,
            'city_id' => $village->district->city_id,
            'district_id' => $village->district_id,
            'internet_package_id' => $this->packageA->id,
            'full_name' => 'Pelanggan Uji Verifikasi',
            'primary_phone' => '081200000001',
        ], $extra));
    }

    private function userWithOnly(string ...$permissionCodes): User
    {
        $role = Role::create(['code' => 'verif_edit_test_'.uniqid(), 'name' => 'Verif Edit Test', 'is_system' => false]);
        app(RoleManagementService::class)->syncPermissions(
            $role,
            Permission::whereIn('code', $permissionCodes)->pluck('id')->all()
        );

        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP->value]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    // ── Verifikasi Registrasi ───────────────────────────────────────────

    #[Test]
    public function cs_bisa_edit_data_diri_di_verifikasi_registrasi(): void
    {
        $customer = $this->customerAt('registered');
        $cs = $this->userWithOnly('customer_registration_verification.view', 'customer_registration_verification.approve');
        $newVillage = $this->village();

        $this->actingAs($cs)->put(route('customer-registration-verifications.update-identity', $customer), [
            'full_name' => 'Nama Sudah Dikoreksi',
            'identity_number' => '9876543210123456',
            'primary_phone' => '081298765432',
            'email' => 'koreksi@example.test',
            'address' => 'Alamat baru hasil koreksi',
            'city_id' => $newVillage->district->city_id,
            'district_id' => $newVillage->district_id,
            'village_id' => $newVillage->id,
            'latitude' => '-7.123456',
            'longitude' => '111.123456',
        ])->assertRedirect(route('customer-registration-verifications.show', $customer));

        $customer->refresh();
        $this->assertSame('Nama Sudah Dikoreksi', $customer->full_name);
        $this->assertSame('9876543210123456', $customer->identity_number);
        $this->assertSame('081298765432', $customer->primary_phone);
        $this->assertSame($newVillage->id, $customer->village_id);
        $this->assertEquals(-7.123456, (float) $customer->latitude);

        // Mirror customer_addresses ikut disinkronkan — dibaca List Pelanggan,
        // Task Saya, kartu FOP, invoice (CustomerController::update()).
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'village_id' => $newVillage->id,
            'full_address' => 'Alamat baru hasil koreksi',
        ]);
    }

    #[Test]
    public function cs_bisa_ganti_paket_di_verifikasi_registrasi_dan_harga_ikut_dihitung_ulang(): void
    {
        $customer = $this->customerAt('registered');
        $customer->customerService()->create([
            'internet_package_id' => $this->packageA->id,
            'package_name_snapshot' => $this->packageA->name,
            'discount' => 10000,
            'ppn' => 11,
            'monthly_price' => 100000,
            'total_monthly_bill' => 100000,
        ]);
        $cs = $this->userWithOnly('customer_registration_verification.view', 'customer_registration_verification.approve');

        $this->actingAs($cs)->put(route('customer-registration-verifications.update-package', $customer), [
            'internet_package_id' => $this->packageB->id,
        ])->assertRedirect();

        $customer->refresh();
        $this->assertSame($this->packageB->id, $customer->internet_package_id);

        // (200000 - 10000) * 1.11 = 210900 — diskon/PPN yang sudah tersimpan
        // dipertahankan, cuma paket & harga dasarnya yang berubah.
        $this->assertEquals(210900, (float) $customer->customerService->total_monthly_bill);
    }

    #[Test]
    public function edit_registrasi_ditolak_setelah_pelanggan_lewat_tahap_registrasi(): void
    {
        $customer = $this->customerAt('waiting_survey');
        $cs = $this->userWithOnly('customer_registration_verification.view', 'customer_registration_verification.approve');

        $this->actingAs($cs)->put(route('customer-registration-verifications.update-identity', $customer), [
            'full_name' => 'Coba Ubah',
            'primary_phone' => '081200000009',
            'address' => 'x',
        ])->assertNotFound();

        $this->assertSame('Pelanggan Uji Verifikasi', $customer->fresh()->full_name);
    }

    #[Test]
    public function actor_tanpa_permission_approve_tidak_bisa_edit_registrasi(): void
    {
        $customer = $this->customerAt('registered');
        $viewer = $this->userWithOnly('customer_registration_verification.view');

        $this->actingAs($viewer)->put(route('customer-registration-verifications.update-identity', $customer), [
            'full_name' => 'Coba Ubah',
            'primary_phone' => '081200000009',
            'address' => 'x',
        ])->assertForbidden();
    }

    // ── Verifikasi Survey / Pemasangan / Validasi Admin ─────────────────

    #[Test]
    public function cs_bisa_edit_data_diri_paket_dan_survey_di_tahap_waiting_acc(): void
    {
        $customer = $this->customerAt('waiting_acc');
        $survey = CustomerSurvey::create([
            'customer_id' => $customer->id,
            'survey_status' => 'completed',
            'nearest_odp' => 'ODP-LAMA',
            'cable_estimation_meter' => 50,
        ]);
        $admin = $this->userWithOnly('customers.detail.installation.validate', 'customers.detail.installation.view');

        $this->actingAs($admin)->put(route('customers.verification.update-identity', $customer), [
            'full_name' => 'Nama Dikoreksi Tahap Survey',
            'primary_phone' => '081200000099',
            'address' => 'Alamat',
        ])->assertRedirect(route('customers.verification.admin', $customer));
        $this->assertSame('Nama Dikoreksi Tahap Survey', $customer->fresh()->full_name);

        $this->actingAs($admin)->put(route('customers.verification.update-package', $customer), [
            'internet_package_id' => $this->packageB->id,
        ])->assertRedirect();
        $this->assertSame($this->packageB->id, $customer->fresh()->internet_package_id);

        $this->actingAs($admin)->put(route('customers.verification.update-survey-data', $customer), [
            'nearest_odp' => 'ODP-BARU',
            'cable_estimation_meter' => 75,
            'survey_note' => 'Dikoreksi CS.',
        ])->assertRedirect();
        $this->assertSame('ODP-BARU', $survey->fresh()->nearest_odp);
        $this->assertSame(75, $survey->fresh()->cable_estimation_meter);
    }

    #[Test]
    public function edit_survey_data_ditolak_kalau_belum_ada_laporan_survey(): void
    {
        $customer = $this->customerAt('waiting_acc');
        $admin = $this->userWithOnly('customers.detail.installation.validate', 'customers.detail.installation.view');

        $this->actingAs($admin)->put(route('customers.verification.update-survey-data', $customer), [
            'nearest_odp' => 'ODP-BARU',
        ])->assertStatus(422);
    }

    #[Test]
    public function edit_di_verifikasi_admin_tetap_dibuka_sampai_pelanggan_aktif(): void
    {
        $customer = $this->customerAt('verification_admin');
        $admin = $this->userWithOnly('customers.detail.installation.validate', 'customers.detail.installation.view');

        $this->actingAs($admin)->put(route('customers.verification.update-identity', $customer), [
            'full_name' => 'Dikoreksi di Validasi Admin',
            'primary_phone' => '081200000098',
            'address' => 'Alamat',
        ])->assertRedirect();

        $this->assertSame('Dikoreksi di Validasi Admin', $customer->fresh()->full_name);
    }

    #[Test]
    public function viewer_tanpa_permission_validate_tidak_bisa_edit_di_tahap_survey(): void
    {
        $customer = $this->customerAt('waiting_acc');
        $viewer = $this->userWithOnly('customers.detail.installation.view');

        $this->actingAs($viewer)->put(route('customers.verification.update-identity', $customer), [
            'full_name' => 'Coba Ubah',
            'primary_phone' => '081200000009',
            'address' => 'x',
        ])->assertForbidden();
    }

    // ── Data Pemasangan / Data Pengujian (Validasi Admin saja) ──────────

    #[Test]
    public function cs_bisa_edit_data_pemasangan_di_tahap_validasi_admin(): void
    {
        $customer = $this->customerAt('verification_admin');
        $customer->installations()->create(['installation_status' => 'completed']);
        CustomerDevice::create(['customer_id' => $customer->id, 'device_type' => 'ont', 'serial_number' => 'SN-LAMA']);
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'odp_number' => 'ODP-LAMA']);
        $admin = $this->userWithOnly('customers.detail.installation.validate', 'customers.detail.installation.view');

        $this->actingAs($admin)->put(route('customers.verification.update-installation-data', $customer), [
            'device_type' => 'ont',
            'serial_number' => 'SN-BARU',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'odp_number' => 'ODP-BARU',
            'olt_number' => 'OLT-01',
            'installation_note' => 'Dikoreksi CS saat Validasi Admin.',
        ])->assertRedirect(route('customers.verification.admin', $customer));

        $this->assertSame('SN-BARU', $customer->customerDevice->fresh()->serial_number);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $customer->customerDevice->fresh()->mac_address);
        $this->assertSame('ODP-BARU', $customer->customerTechnicalDetail->fresh()->odp_number);
        $this->assertSame('OLT-01', $customer->customerTechnicalDetail->fresh()->olt_number);
        $this->assertSame('Dikoreksi CS saat Validasi Admin.', $customer->installations()->latest()->first()->installation_note);
    }

    #[Test]
    public function cs_bisa_edit_data_pengujian_dan_kesesuaian_paket_dihitung_ulang(): void
    {
        $customer = $this->customerAt('verification_admin', ['internet_package_id' => $this->packageA->id]);
        $this->packageA->update(['download_speed_mbps' => 50]);
        $admin = $this->userWithOnly('customers.detail.installation.validate', 'customers.detail.installation.view');

        $this->actingAs($admin)->put(route('customers.verification.update-test-report', $customer), [
            'test_download' => 45,
            'test_upload' => 20,
            'latency_ms' => 12,
            'jitter_ms' => 1.5,
            'packet_loss_percent' => 0,
            'actual_attenuation' => -18.5,
        ])->assertRedirect();

        $tech = $customer->customerTechnicalDetail()->first();
        $this->assertEquals(45, (float) $tech->test_download);
        $this->assertEquals(90, (float) $tech->speed_conformity_percent);
    }

    #[Test]
    public function edit_pemasangan_dan_pengujian_ditolak_saat_masih_tahap_pemasangan_berjalan(): void
    {
        $customer = $this->customerAt('installation_in_progress');
        $admin = $this->userWithOnly('customers.detail.installation.validate', 'customers.detail.installation.view');

        $this->actingAs($admin)->put(route('customers.verification.update-installation-data', $customer), [
            'serial_number' => 'SN-COBA',
        ])->assertStatus(422);

        $this->actingAs($admin)->put(route('customers.verification.update-test-report', $customer), [
            'test_download' => 10,
        ])->assertStatus(422);
    }

    #[Test]
    public function edit_di_tahap_survey_belum_bisa_edit_pemasangan_atau_pengujian(): void
    {
        $customer = $this->customerAt('waiting_acc');
        $admin = $this->userWithOnly('customers.detail.installation.validate', 'customers.detail.installation.view');

        $this->actingAs($admin)->put(route('customers.verification.update-installation-data', $customer), [
            'serial_number' => 'SN-COBA',
        ])->assertStatus(422);
    }

    #[Test]
    public function edit_ditolak_sebelum_pelanggan_masuk_antrean_verifikasi(): void
    {
        $customer = $this->customerAt('waiting_survey');
        $admin = $this->userWithOnly('customers.detail.installation.validate', 'customers.detail.installation.view');

        $this->actingAs($admin)->put(route('customers.verification.update-identity', $customer), [
            'full_name' => 'Coba Ubah',
            'primary_phone' => '081200000009',
            'address' => 'x',
        ])->assertStatus(422);
    }
}
