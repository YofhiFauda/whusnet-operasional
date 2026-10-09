<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Models\WarehousePopPic;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-120 — Teknisi merangkap PIC Gudang Cabang. Lihat
 * docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md §12 (Pilar 1-3).
 * Pembagian tugas PIC vs POP Admin (Pilar 4) ada di
 * PembagianTugasPicGudangPopAdminTest.
 */
class TeknisiPicGudangCabangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function createCabang(string $code, string $name): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => strtoupper($code),
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => $name,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    protected function makePicGudang(Pop $picPop, string $scopeType = 'selected_pop', ?Pop $scopePop = null): User
    {
        $role = Role::where('code', 'pic_gudang')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scopeType === 'all_pop' ? ScopeType::ALL_POP : ScopeType::SELECTED_POP,
        ]);

        if ($scopeType !== 'all_pop') {
            UserRoleScopeTarget::create([
                'user_role_scope_id' => $scope->id,
                'pop_id' => ($scopePop ?? $picPop)->id,
            ]);
        }

        WarehousePopPic::create(['pop_id' => $picPop->id, 'user_id' => $user->id]);

        return $user;
    }

    #[Test]
    public function pic_gudang_role_is_seeded_with_system_locked_code(): void
    {
        $role = Role::where('code', 'pic_gudang')->first();

        $this->assertNotNull($role);
        $this->assertTrue($role->is_system);
    }

    #[Test]
    public function pic_gudang_technician_permissions_match_teknisi_role_exactly(): void
    {
        // Aturan sinkron §6.1 — kalau hak role teknisi berubah di Role
        // Matrix, bagian teknisi di pic_gudang wajib ikut berubah.
        $teknisi = Role::where('code', 'teknisi')->firstOrFail()->permissions()->pluck('code');
        $picGudang = Role::where('code', 'pic_gudang')->firstOrFail()->permissions()->pluck('code');

        foreach ($teknisi as $code) {
            $this->assertTrue(
                $picGudang->contains($code),
                "pic_gudang kehilangan permission teknisi: {$code}"
            );
        }
    }

    #[Test]
    public function pic_gudang_user_counts_as_technician(): void
    {
        $cabang = $this->createCabang('TPG-01', 'Cabang TPG 1');
        $pic = $this->makePicGudang($cabang);

        $this->assertTrue($pic->isTechnician());
        $this->assertTrue(User::technicians()->whereKey($pic->id)->exists());
    }

    #[Test]
    public function pic_gudang_with_all_pop_scope_is_still_recognized_as_technician_and_pic_of_only_its_assigned_branch(): void
    {
        // Kasus inti revisi 2026-09-30b: scope global, PIC cuma 1 cabang.
        $jetis = $this->createCabang('TPG-JETIS', 'Jetis');
        $siman = $this->createCabang('TPG-SIMAN', 'Siman');

        $pic = $this->makePicGudang($jetis, scopeType: 'all_pop');

        $this->assertTrue($pic->isTechnician());
        $this->assertTrue($pic->isPicGudangOf($jetis));
        $this->assertFalse($pic->isPicGudangOf($siman));
        $this->assertTrue($jetis->hasActivePicGudang());
        $this->assertFalse($siman->hasActivePicGudang());
    }

    #[Test]
    public function inactive_pic_gudang_user_is_not_counted_as_active_pic(): void
    {
        $cabang = $this->createCabang('TPG-02', 'Cabang TPG 2');
        $pic = $this->makePicGudang($cabang);
        $pic->update(['status' => 'inactive']);

        $this->assertFalse($cabang->fresh()->hasActivePicGudang());
    }

    #[Test]
    public function one_pop_can_have_more_than_one_active_pic_gudang(): void
    {
        $cabang = $this->createCabang('TPG-03', 'Cabang TPG 3');
        $picA = $this->makePicGudang($cabang);
        $roleId = $picA->role_id;
        $picB = User::factory()->create(['status' => 'active', 'role_id' => $roleId]);
        $scope = UserRoleScope::create(['user_id' => $picB->id, 'role_id' => $roleId, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        WarehousePopPic::create(['pop_id' => $cabang->id, 'user_id' => $picB->id]);

        $this->assertCount(2, $cabang->fresh()->gudangPics);
    }

    #[Test]
    public function one_user_can_be_pic_of_more_than_one_branch(): void
    {
        $jetis = $this->createCabang('TPG-04A', 'Jetis 4A');
        $siman = $this->createCabang('TPG-04B', 'Siman 4B');

        $pic = $this->makePicGudang($jetis, scopeType: 'all_pop');
        WarehousePopPic::create(['pop_id' => $siman->id, 'user_id' => $pic->id]);

        $this->assertTrue($pic->isPicGudangOf($jetis));
        $this->assertTrue($pic->isPicGudangOf($siman));
    }
}
