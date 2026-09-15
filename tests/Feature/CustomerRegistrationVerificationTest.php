<?php

namespace Tests\Feature;

use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\FopTask;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\CustomerRegistrationVerificationFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ADHOC-73 — Verifikasi Registrasi oleh Admin/CS. Pelanggan hasil Registrasi
 * (non-Skip-Survey) berhenti di `WorkflowTransition::REGISTERED` TANPA
 * Task/FopTask Survey sampai disetujui di
 * `CustomerRegistrationVerificationController`. Skip Survey tidak
 * tersentuh (dikonfirmasi user) — jalur itu tetap langsung bikin Task
 * PEMASANGAN seperti sebelumnya.
 *
 * Lihat docs/plan/pendaftaran-pelanggan/analisa-verifikasi-registrasi.md.
 */
class CustomerRegistrationVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(CustomerRegistrationVerificationFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function makePackage(): InternetPackage
    {
        return InternetPackage::create([
            'package_code' => 'NET100',
            'name' => 'Net 100',
            'category' => 'Paket Home Broadband',
            'package_group' => 'Net',
            'bandwidth_label' => '100 Mbps',
            'monthly_price' => 150000,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validRegistrationPayload(int $packageId, int $popId): array
    {
        $city = City::create(['name' => 'Kota Uji '.uniqid()]);
        $district = District::create(['city_id' => $city->id, 'name' => 'Kecamatan Uji']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Desa Uji']);

        return [
            'full_name' => 'Pelanggan Verifikasi Uji',
            'identity_number' => '1234567890123456',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => now()->toDateString(),
            'pop_id' => $popId,
            'address' => 'Jl. Uji No. 1',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $packageId,
            'contract_period_months' => 12,
        ];
    }

    public function test_registrasi_non_skip_survey_tidak_langsung_bikin_task_atau_foptask(): void
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $package = $this->makePackage();
        $this->loginAsAdmin();

        $this->post(route('customers.store'), $this->validRegistrationPayload($package->id, $pop->id))
            ->assertSessionDoesntHaveErrors();

        $customer = Customer::where('internet_package_id', $package->id)->firstOrFail();

        $this->assertSame('registered', $customer->status);
        $this->assertDatabaseMissing('tasks', ['customer_id' => $customer->id]);
        $this->assertDatabaseMissing('fop_tasks', ['customer_id' => $customer->id]);
    }

    public function test_approve_membuat_task_dan_foptask_lalu_pindah_ke_waiting_survey(): void
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $package = $this->makePackage();
        $this->loginAsAdmin();

        $this->post(route('customers.store'), $this->validRegistrationPayload($package->id, $pop->id));
        $customer = Customer::where('internet_package_id', $package->id)->firstOrFail();

        $response = $this->put(route('customer-registration-verifications.approve', $customer));

        $response->assertRedirect(route('customer-registration-verifications.index'));
        $customer->refresh();

        $this->assertSame('waiting_survey', $customer->status);
        $this->assertDatabaseHas('tasks', ['customer_id' => $customer->id, 'task_type' => 'SURVEY']);
        $this->assertDatabaseHas('fop_tasks', ['customer_id' => $customer->id, 'category' => 'SURVEY']);
    }

    public function test_reject_memindahkan_status_ke_rejected_tanpa_task_atau_foptask(): void
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $package = $this->makePackage();
        $this->loginAsAdmin();

        $this->post(route('customers.store'), $this->validRegistrationPayload($package->id, $pop->id));
        $customer = Customer::where('internet_package_id', $package->id)->firstOrFail();

        $response = $this->put(route('customer-registration-verifications.reject', $customer), [
            'reason' => 'Data NIK tidak valid.',
        ]);

        $response->assertRedirect(route('customer-registration-verifications.index'));
        $customer->refresh();

        $this->assertSame('rejected', $customer->status);
        $this->assertDatabaseMissing('tasks', ['customer_id' => $customer->id]);
        $this->assertDatabaseMissing('fop_tasks', ['customer_id' => $customer->id]);
    }

    public function test_reject_wajib_isi_alasan(): void
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $package = $this->makePackage();
        $this->loginAsAdmin();

        $this->post(route('customers.store'), $this->validRegistrationPayload($package->id, $pop->id));
        $customer = Customer::where('internet_package_id', $package->id)->firstOrFail();

        $response = $this->put(route('customer-registration-verifications.reject', $customer), ['reason' => '']);

        $response->assertSessionHasErrors('reason');
        $this->assertSame('registered', $customer->fresh()->status);
    }

    public function test_skip_survey_tetap_langsung_bikin_task_pemasangan_tanpa_verifikasi(): void
    {
        Storage::fake('public');

        $sales = Role::where('code', 'sales')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $sales->id]);
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $package = $this->makePackage();
        $this->actingAs($user);

        $payload = $this->validRegistrationPayload($package->id, $pop->id);
        $payload['skip_survey'] = 1;
        $payload['latitude'] = '-7.5';
        $payload['longitude'] = '111.5';
        $payload['nearest_odp'] = 'ODP-01';
        $payload['cable_estimation_meter'] = 50;
        $payload['difficulty_level'] = 'MUDAH';
        $payload['foto_rumah'] = UploadedFile::fake()->image('rumah.jpg');
        $payload['survey_photo'] = UploadedFile::fake()->image('odp.jpg');

        $this->post(route('customers.store'), $payload)->assertSessionDoesntHaveErrors();

        $customer = Customer::where('internet_package_id', $package->id)->firstOrFail();

        $this->assertSame('waiting_installation', $customer->status);
        $this->assertDatabaseHas('tasks', ['customer_id' => $customer->id, 'task_type' => TaskType::PEMASANGAN->value]);
        $this->assertDatabaseHas('fop_tasks', ['customer_id' => $customer->id, 'category' => TaskType::PEMASANGAN->value]);
    }

    public function test_actor_tanpa_permission_ditolak_403(): void
    {
        $sales = Role::where('code', 'sales')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $sales->id]);
        $this->actingAs($user);

        $this->get(route('customer-registration-verifications.index'))->assertForbidden();
    }

    public function test_actor_tanpa_pop_scope_tidak_lihat_pelanggan_pop_lain(): void
    {
        $helpdeskRole = Role::where('code', 'helpdesk')->firstOrFail();
        $helpdesk = User::factory()->create(['status' => 'active', 'role_id' => $helpdeskRole->id]);

        $pop = Pop::factory()->create(['type' => 'cabang']);
        // Dibuat langsung lewat factory (BUKAN via POST customers.store) —
        // POST bakal ninggalin flash "Pelanggan {nama} berhasil ditambahkan"
        // yang nyantol satu request lagi biar keburu ke-assert kebaca
        // assertDontSee di bawah (false positive, bukan bug scope).
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'status' => 'registered']);

        // Helpdesk BELUM diberi UserRoleScope apa pun — deny-by-default
        // (EffectiveAccessService::hasAllPopAccess()), jadi wajib gak lihat
        // pelanggan dari POP mana pun sampai scope-nya diset eksplisit.
        $this->actingAs($helpdesk);

        $this->get(route('customer-registration-verifications.index'))
            ->assertOk()
            ->assertDontSee($customer->full_name);

        $this->get(route('customer-registration-verifications.show', $customer))->assertForbidden();
    }
}
