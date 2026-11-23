<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi 2026-11-11: halaman Kelola PIC Gudang (`/warehouse/pic-gudang`)
 * sudah ada sejak ADHOC-120 tapi tidak punya link di UI mana pun — admin
 * gak tahu PIC harus ditunjuk di sana, PIC (scope Caruban + role
 * pic_gudang) tetap ditolak saat Serah ke Teknisi.
 */
class SidebarKelolaPicGudangLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function owner_melihat_link_kelola_pic_gudang_di_sidebar(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('href="'.route('warehouse.pic-gudang.index').'"', false);
        $response->assertSee('Kelola PIC Gudang');
    }

    #[Test]
    public function user_tanpa_permission_kelola_pic_gudang_tidak_melihat_link(): void
    {
        $role = Role::where('code', 'pic_gudang')->firstOrFail();
        $pic = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        app(EffectiveAccessService::class)->clearCache($pic);

        $this->assertFalse($pic->hasPermission('warehouse_pic_gudang.view'));

        // Dashboard me-redirect role tanpa akses dashboard — pakai halaman
        // Gudang yang memang dibuka PIC, supaya sidebar grup Gudang ter-render.
        $this->assertTrue($pic->hasPermission('warehouse.view'));
        $response = $this->actingAs($pic)->get(route('warehouse.index'));

        $response->assertOk();
        $response->assertSee('Dashboard Gudang');
        $response->assertDontSee('href="'.route('warehouse.pic-gudang.index').'"', false);
    }
}
