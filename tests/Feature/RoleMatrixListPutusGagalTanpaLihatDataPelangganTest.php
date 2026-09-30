<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\RoleManagementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bug 2026-09-29: di Role Matrix, "Daftar Pelanggan Putus", "Daftar Pelanggan
 * Gagal", dan "Registrasi — Skip Survey" cuma bisa diberikan kalau "Lihat
 * Data" Master Pelanggan (`customers.view`) ikut dicentang. Dua lapis:
 *  - UI: checkbox terkunci sampai `customers.view` dicentang, dan mencentangnya
 *    memaksa-centang `customers.view`.
 *  - Server: RoleManagementService::syncPermissions() auto-grant `customers.view`
 *    walau UI diakali.
 * Akibatnya role yang cuma boleh melihat List Putus ikut dapat List Data
 * Pelanggan aktif. Ketiga fitur itu cuma menumpang tree `customers` di matrix —
 * halaman & permission-nya sendiri. config/rbac.php > view_autogrant_chain_boundary.
 */
class RoleMatrixListPutusGagalTanpaLihatDataPelangganTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function roleDengan(string ...$kode): Role
    {
        $role = Role::create([
            'code' => 'arsip_pelanggan_test',
            'name' => 'Arsip Pelanggan Test',
            'is_system' => false,
        ]);

        app(RoleManagementService::class)->syncPermissions(
            $role,
            Permission::whereIn('code', $kode)->pluck('id')->all()
        );

        return $role;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function permissionIndependen(): array
    {
        return [
            'List Pelanggan Putus' => ['customers.terminated.view'],
            'List Pelanggan Gagal' => ['customers.failed.view'],
            'Registrasi Skip Survey' => ['customers.registration.skip_survey'],
        ];
    }

    #[Test]
    #[DataProvider('permissionIndependen')]
    public function simpan_matrix_tidak_ikut_memberi_lihat_data_pelanggan(string $kode): void
    {
        $role = $this->roleDengan($kode);

        $kodeTerpasang = $role->fresh()->permissions()->pluck('code')->all();

        $this->assertContains($kode, $kodeTerpasang);
        $this->assertNotContains('customers.view', $kodeTerpasang);
    }

    #[Test]
    public function checkbox_matrix_tidak_dikunci_menunggu_lihat_data_pelanggan(): void
    {
        $this->loginAsAdmin();
        $role = $this->roleDengan('customers.terminated.view');

        $html = $this->get(route('roles.matrix', $role))->assertOk()->getContent();

        foreach (['customers.terminated.view', 'customers.failed.view', 'customers.registration.skip_survey'] as $kode) {
            $this->assertMatchesRegularExpression(
                '/data-permission-code="'.preg_quote($kode, '/').'"[^>]*data-independent-channel="true"/s',
                $html,
                "Checkbox {$kode} masih dirantai ke Lihat Data Pelanggan."
            );
        }

        // Anak `customers` lain (mis. Import) tetap dirantai seperti semula.
        $this->assertMatchesRegularExpression(
            '/data-permission-code="customers\.import\.view"[^>]*data-independent-channel="false"/s',
            $html
        );
    }

    #[Test]
    public function role_cuma_list_putus_bisa_buka_list_putus_tapi_bukan_list_pelanggan(): void
    {
        $role = $this->roleDengan('customers.terminated.view');

        $user = User::factory()->create(['role_id' => $role->id]);
        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);
        app(EffectiveAccessService::class)->clearCache($user);

        $this->actingAs($user)->get(route('customers.terminated'))->assertOk();
        $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
    }

    /**
     * Kasus nyata dari user: role Teknisi dicentang "Tambah/Buat" + "Pelanggan
     * Putus" saja — setelah Simpan, "Lihat Data" Master Pelanggan ikut
     * tercentang sendiri. Pemicunya `customers.create` (aksi di fitur yang sama
     * dengan `customers.view`), bukan cuma sub-fitur.
     */
    #[Test]
    public function tambah_pelanggan_plus_list_putus_tidak_ikut_memberi_lihat_data(): void
    {
        $role = $this->roleDengan('customers.create', 'customers.terminated.view');

        $kodeTerpasang = $role->fresh()->permissions()->pluck('code')->all();

        $this->assertEqualsCanonicalizing(['customers.create', 'customers.terminated.view'], $kodeTerpasang);
    }

    #[Test]
    public function role_cuma_tambah_pelanggan_bisa_buka_form_tanpa_list_pelanggan(): void
    {
        $role = $this->roleDengan('customers.create');

        $user = User::factory()->create(['role_id' => $role->id]);
        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);
        app(EffectiveAccessService::class)->clearCache($user);

        $this->actingAs($user)->get(route('customers.create'))->assertOk();
        $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
    }

    /**
     * Pengecualian `customers` cuma menghentikan auto-grant `customers.view`.
     * Auto-grant `.view` fitur lain di bawahnya tetap jalan — mis. blok Identitas
     * tetap membawa `customers.detail.view` (halaman Detail tempat blok itu ada).
     */
    #[Test]
    public function auto_grant_view_fitur_lain_di_bawah_customers_tetap_jalan(): void
    {
        $role = $this->roleDengan('customers.detail.identity.view');

        $kodeTerpasang = $role->fresh()->permissions()->pluck('code')->all();

        $this->assertContains('customers.detail.view', $kodeTerpasang);
        $this->assertNotContains('customers.view', $kodeTerpasang);
    }
}
