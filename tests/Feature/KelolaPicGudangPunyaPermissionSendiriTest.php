<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\WarehousePopPic;
use App\Services\EffectiveAccessService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-313 (2026-11-23): halaman Kelola PIC Gudang dulu numpang
 * `users.update` — gak ada baris di Role Matrix, siapa pun yang boleh edit
 * user otomatis bisa menunjuk/mencabut PIC. Kini permission sendiri
 * `warehouse_pic_gudang.view|create|delete`, default cuma owner + admin_gudang.
 */
class KelolaPicGudangPunyaPermissionSendiriTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->cabang = Pop::create([
            'code' => 'POP-PICX',
            'pop_code' => 'PICX',
            'registration_prefix' => 'RX',
            'cid_prefix' => 'CX',
            'name' => 'Cabang X',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP,
        ]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user->fresh();
    }

    private function calonPic(): User
    {
        return $this->userWithRole('pic_gudang');
    }

    #[Test]
    public function permission_kelola_pic_gudang_digenerate(): void
    {
        foreach (['view', 'create', 'delete'] as $action) {
            $this->assertTrue(
                Permission::where('code', "warehouse_pic_gudang.{$action}")->exists(),
                "Permission warehouse_pic_gudang.{$action} harus ada supaya muncul di Role Matrix."
            );
        }
    }

    #[Test]
    public function admin_gudang_dapat_full_access_default(): void
    {
        $adminGudang = $this->userWithRole('admin_gudang');

        foreach (['view', 'create', 'delete'] as $action) {
            $this->assertTrue($adminGudang->hasPermission("warehouse_pic_gudang.{$action}"));
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roleTanpaDefault(): array
    {
        return [
            'admin' => ['admin'],
            'noc' => ['noc'],
            'pop_admin' => ['pop_admin'],
            'atasan' => ['atasan'],
            'pic_gudang' => ['pic_gudang'],
        ];
    }

    #[Test]
    #[DataProvider('roleTanpaDefault')]
    public function role_lain_tidak_dapat_permission_secara_default(string $roleCode): void
    {
        $user = $this->userWithRole($roleCode);

        foreach (['view', 'create', 'delete'] as $action) {
            $this->assertFalse(
                $user->hasPermission("warehouse_pic_gudang.{$action}"),
                "{$roleCode} tidak boleh dapat warehouse_pic_gudang.{$action} secara default — lewat Role Matrix."
            );
        }
    }

    #[Test]
    public function users_update_saja_tidak_lagi_membuka_halaman_pic_gudang(): void
    {
        $noc = $this->userWithRole('noc');
        $this->assertTrue($noc->hasPermission('users.update'), 'Prasyarat: noc pegang users.update.');

        $pic = WarehousePopPic::create(['pop_id' => $this->cabang->id, 'user_id' => $this->calonPic()->id]);

        $this->actingAs($noc)->get(route('warehouse.pic-gudang.index'))->assertForbidden();
        $this->actingAs($noc)->post(route('warehouse.pic-gudang.store'), [
            'pop_id' => $this->cabang->id,
            'user_id' => $this->calonPic()->id,
        ])->assertForbidden();
        $this->actingAs($noc)->delete(route('warehouse.pic-gudang.destroy', $pic))->assertForbidden();

        $this->assertDatabaseCount('warehouse_pop_pics', 1);
    }

    #[Test]
    public function admin_gudang_bisa_buka_tunjuk_dan_cabut_pic(): void
    {
        $adminGudang = $this->userWithRole('admin_gudang');
        $calon = $this->calonPic();

        $this->actingAs($adminGudang)->get(route('warehouse.pic-gudang.index'))->assertOk();

        $this->actingAs($adminGudang)->post(route('warehouse.pic-gudang.store'), [
            'pop_id' => $this->cabang->id,
            'user_id' => $calon->id,
        ])->assertRedirect(route('warehouse.pic-gudang.index'))->assertSessionHas('success');

        $pic = WarehousePopPic::where('pop_id', $this->cabang->id)->where('user_id', $calon->id)->firstOrFail();

        $this->actingAs($adminGudang)->delete(route('warehouse.pic-gudang.destroy', $pic))->assertRedirect();
        $this->assertDatabaseMissing('warehouse_pop_pics', ['id' => $pic->id]);
    }

    #[Test]
    public function role_matrix_menampilkan_kelola_pic_gudang_di_grup_gudang(): void
    {
        $this->loginAsAdmin();

        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $html = $this->get(route('roles.matrix', $role))->assertOk()->getContent();

        $start = strpos($html, 'data-category-key="group_warehouse"');
        $this->assertNotFalse($start, 'Grup Gudang harus dirender di Role Matrix.');
        $next = strpos($html, 'data-category-key="', $start + 1);
        $blokGudang = $next === false ? substr($html, $start) : substr($html, $start, $next - $start);

        $this->assertStringContainsString('Kelola PIC Gudang per Cabang', $blokGudang);
        $this->assertStringContainsString('Tunjuk user ber-role Teknisi PIC Gudang', $blokGudang);
    }

    #[Test]
    public function view_saja_tidak_bisa_tunjuk_atau_cabut(): void
    {
        $role = Role::where('code', 'pop_admin')->firstOrFail();
        $role->permissions()->syncWithoutDetaching(
            Permission::where('code', 'warehouse_pic_gudang.view')->pluck('id')
        );
        $user = $this->userWithRole('pop_admin');
        $pic = WarehousePopPic::create(['pop_id' => $this->cabang->id, 'user_id' => $this->calonPic()->id]);

        $this->actingAs($user)->get(route('warehouse.pic-gudang.index'))->assertOk();
        $this->actingAs($user)->post(route('warehouse.pic-gudang.store'), [
            'pop_id' => $this->cabang->id,
            'user_id' => $this->calonPic()->id,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('warehouse.pic-gudang.destroy', $pic))->assertForbidden();
    }
}
