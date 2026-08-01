<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkTool;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategoryFeatureSeeder;
use Database\Seeders\ItemFeatureSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkToolFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regresi: permission master barang/kategori/alat kerja benar-benar lahir.
 *
 * Gejalanya halus dan tidak pernah melempar error — `PermissionGeneratorService`
 * melakukan loop atas `config/rbac.php` → `allowed_actions`, BUKAN atas tabel
 * `features`. Feature yang punya seeder tapi lupa didaftarkan di config
 * dilewati diam-diam: halamannya tetap jalan untuk Owner (yang lolos lewat
 * wildcard `*`), tapi permission-nya tidak ada di Role Matrix sehingga tidak
 * bisa diberikan ke role lain selamanya.
 *
 * `items` sempat begitu sejak ADHOC-11 dan baru ketahuan di ADHOC-13.
 */
class MasterBarangPermissionGeneratedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(ItemFeatureSeeder::class);
        $this->seed(ItemCategoryFeatureSeeder::class);
        $this->seed(WorkToolFeatureSeeder::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function featureProvider(): array
    {
        return [
            'master barang' => ['items'],
            'master kategori barang' => ['item_categories'],
            'master alat kerja' => ['work_tools'],
        ];
    }

    #[DataProvider('featureProvider')]
    public function test_permission_crud_tergenerate_untuk_feature(string $featureCode): void
    {
        $this->assertTrue(
            Feature::where('code', $featureCode)->exists(),
            "Feature {$featureCode} tidak ada — seeder-nya belum jalan."
        );

        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $this->assertTrue(
                Permission::where('code', "{$featureCode}.{$action}")->exists(),
                "Permission {$featureCode}.{$action} tidak tergenerate. "
                    ."Kemungkinan besar '{$featureCode}' belum didaftarkan di config/rbac.php → allowed_actions."
            );
        }
    }

    public function test_menu_master_benar_benar_dirender_di_sidebar(): void
    {
        // Regresi: ada DUA berkas sidebar di repo — `layouts/app.blade.php`
        // (yang benar-benar dirender) dan `components/layout/sidebar.blade.php`
        // (tidak dirujuk dari mana pun). Menambah menu di berkas yang salah
        // tidak menghasilkan error apa pun, cuma menu yang tidak pernah muncul.
        // Test ini menegaskan lewat HTML hasil render, bukan lewat isi berkas.
        $ownerRole = Role::where('name', 'Owner')->first();
        $owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('master.work-tools.index'));

        $response->assertOk();
        $response->assertSee('Master Alat Kerja');
        $response->assertSee('/master/work-tools');
        // Dua menu Batch A ikut dijaga di halaman yang sama.
        $response->assertSee('Master Item (Barang)');
        $response->assertSee('Master Kategori Item');
    }

    public function test_permission_bisa_diberikan_ke_role_non_owner(): void
    {
        // Inti masalahnya: tanpa permission yang lahir, halaman master cuma bisa
        // dibuka Owner lewat wildcard `*` dan tidak akan pernah muncul di Role
        // Matrix untuk role lain.
        $adminRole = Role::where('name', 'Admin')->first();
        $permission = Permission::where('code', 'work_tools.view')->first();

        $adminRole->permissions()->syncWithoutDetaching([$permission->id]);

        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);

        WorkTool::create(['code' => 'TANGGA', 'name' => 'Tangga', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('master.work-tools.index'))
            ->assertOk();

        // Tanpa permission create, tombol/route tambah tetap tertutup —
        // permission per-aksi, bukan satu saklar per halaman.
        $this->actingAs($admin)
            ->get(route('master.work-tools.create'))
            ->assertForbidden();
    }
}
