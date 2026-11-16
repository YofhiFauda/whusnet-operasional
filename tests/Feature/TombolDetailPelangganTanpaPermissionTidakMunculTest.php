<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bug 2026-11-16: role List-only (contoh sales yang `customers.detail.view`-nya
 * dicabut di Role Matrix) tetap melihat tombol Detail di Quick Hub List
 * Pelanggan, antrean Survey, dan antrean Verifikasi Pemasangan — begitu diklik
 * langsung 403. Penyebabnya: tujuan link dipilih dari STATUS pelanggan, lalu
 * fallback buta ke customers.show / customers.fieldwork tanpa cek permission
 * route tujuannya. Aturan sekarang: tombol cuma dirender kalau route
 * tujuannya memang boleh dibuka user itu.
 *
 * Role dibuat dengan attach permission langsung (bukan lewat
 * RoleManagementService::syncPermissions) supaya auto-grant chain view tidak
 * diam-diam menambah permission yang justru sedang diuji ketiadaannya.
 */
class TombolDetailPelangganTanpaPermissionTidakMunculTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN-DTLBTN',
            'pop_code' => 'DTB',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Tombol Detail',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function userDengan(string ...$kode): User
    {
        $role = Role::create([
            'code' => 'tombol_detail_test',
            'name' => 'Tombol Detail Test',
            'is_system' => false,
        ]);
        $role->permissions()->attach(Permission::whereIn('code', $kode)->pluck('id'));

        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    private function pelanggan(string $status): Customer
    {
        return Customer::create([
            'customer_code' => 'DTB-'.rand(10000, 99999),
            'full_name' => 'Pelanggan Tombol Detail',
            'primary_phone' => '081234567890',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    #[Test]
    public function list_pelanggan_tanpa_detail_view_tidak_menampilkan_tombol_detail_quick_hub(): void
    {
        $user = $this->userDengan('customers.view');
        $customer = $this->pelanggan('active');

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee('Pelanggan Tombol Detail');
        $response->assertDontSee('Detail Full Pelanggan');
        $response->assertDontSee('data-detail-url="'.route('customers.show', $customer->id).'"', false);
        $response->assertSee('data-detail-url=""', false);
    }

    #[Test]
    public function list_pelanggan_dengan_detail_view_tetap_menampilkan_tombol_detail_quick_hub(): void
    {
        $user = $this->userDengan('customers.view', 'customers.detail.view');
        $customer = $this->pelanggan('active');

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee('Detail Full Pelanggan');
        $response->assertSee('data-detail-url="'.route('customers.show', $customer->id).'"', false);
    }

    #[Test]
    public function antrean_survey_tanpa_permission_tujuan_tidak_menampilkan_tombol_detail(): void
    {
        $user = $this->userDengan('customers.detail.survey.view');
        $customer = $this->pelanggan('waiting_survey');

        $response = $this->actingAs($user)->get(route('surveys.queue'));

        $response->assertOk();
        $response->assertSee('Pelanggan Tombol Detail');
        $response->assertDontSee('href="'.route('customers.show', $customer).'"', false);
        $response->assertDontSee(route('customers.fieldwork', $customer));
        $response->assertDontSee(route('customers.verification.admin', $customer));
    }

    #[Test]
    public function antrean_survey_dengan_survey_validate_saja_tidak_diarahkan_ke_verifikasi_admin(): void
    {
        // verification.admin digerbangi installation.validate, bukan survey.validate.
        $user = $this->userDengan('customers.detail.survey.view', 'customers.detail.survey.validate');
        $customer = $this->pelanggan('waiting_survey');

        $response = $this->actingAs($user)->get(route('surveys.queue'));

        $response->assertOk();
        $response->assertDontSee(route('customers.verification.admin', $customer));
        $this->actingAs($user)->get(route('customers.verification.admin', $customer))->assertForbidden();
    }

    #[Test]
    public function antrean_survey_dengan_detail_view_menampilkan_link_detail_pelanggan(): void
    {
        $user = $this->userDengan('customers.detail.survey.view', 'customers.detail.view');
        $customer = $this->pelanggan('waiting_survey');

        $response = $this->actingAs($user)->get(route('surveys.queue'));

        $response->assertOk();
        $response->assertSee('href="'.route('customers.show', $customer).'"', false);
    }

    #[Test]
    public function antrean_verifikasi_tanpa_permission_survey_tidak_diarahkan_ke_laporan_survey(): void
    {
        // Dulu status waiting_acc → survey.report tanpa cek permission → 403.
        // Sekarang jatuh ke Perangkat & Pemasangan yang memang boleh dibuka.
        $user = $this->userDengan('customers.detail.installation.view');
        $customer = $this->pelanggan('waiting_acc');

        $response = $this->actingAs($user)->get(route('verifications.queue'));

        $response->assertOk();
        $response->assertSee('Pelanggan Tombol Detail');
        $response->assertDontSee(route('customers.survey.report', ['customer' => $customer, 'return_to' => route('verifications.queue')]));
        $response->assertDontSee('href="'.route('customers.show', $customer).'"', false);
        $response->assertSee(route('customers.fieldwork', $customer));
        $this->actingAs($user)->get(route('customers.fieldwork', $customer))->assertOk();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function halamanArsip(): array
    {
        return [
            'list putus' => ['customers.terminated', 'customers.terminated.view', 'terminated'],
            'list gagal' => ['customers.failed', 'customers.failed.view', 'rejected'],
        ];
    }

    #[Test]
    #[DataProvider('halamanArsip')]
    public function list_putus_dan_gagal_tanpa_detail_view_tidak_menampilkan_tombol_detail(string $route, string $permission, string $status): void
    {
        $user = $this->userDengan($permission);
        $customer = $this->pelanggan($status);

        $response = $this->actingAs($user)->get(route($route));

        $response->assertOk();
        $response->assertSee('Pelanggan Tombol Detail');
        $response->assertDontSee('href="'.route('customers.show', $customer->id).'"', false);
    }

    #[Test]
    #[DataProvider('halamanArsip')]
    public function list_putus_dan_gagal_dengan_detail_view_tetap_menampilkan_tombol_detail(string $route, string $permission, string $status): void
    {
        $user = $this->userDengan($permission, 'customers.detail.view');
        $customer = $this->pelanggan($status);

        $response = $this->actingAs($user)->get(route($route));

        $response->assertOk();
        $response->assertSee('href="'.route('customers.show', $customer->id).'"', false);
    }
}
