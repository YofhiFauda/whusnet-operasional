<?php

namespace Tests\Feature\QrCode;

use App\Enums\ScopeType;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\QrFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Halaman Scan QR Internal (2026-08-27, keputusan eksplisit user) —
 * QrInAppScanController. WAJIB permission-gated (`qr_scan.view`) — beda
 * dari QrScanController::dispatch() yang publik tanpa auth sama sekali.
 */
class QrInAppScanPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(QrFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function staffWithPermission(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        return $user;
    }

    #[Test]
    public function helpdesk_yang_punya_permission_bisa_buka_halaman(): void
    {
        $staff = $this->staffWithPermission('helpdesk');

        $response = $this->actingAs($staff)->get(route('qr.scan.show'));

        $response->assertOk();
        $response->assertSee('qr-scan-video', false);
    }

    #[Test]
    public function fop_yang_punya_permission_bisa_buka_halaman(): void
    {
        $staff = $this->staffWithPermission('fop');

        $response = $this->actingAs($staff)->get(route('qr.scan.show'));

        $response->assertOk();
    }

    #[Test]
    public function teknisi_yang_punya_permission_bisa_buka_halaman(): void
    {
        $staff = $this->staffWithPermission('teknisi');

        $response = $this->actingAs($staff)->get(route('qr.scan.show'));

        $response->assertOk();
    }

    #[Test]
    public function sales_tanpa_permission_ditolak_403(): void
    {
        $staff = $this->staffWithPermission('sales');

        $response = $this->actingAs($staff)->get(route('qr.scan.show'));

        $response->assertForbidden();
    }

    #[Test]
    public function tamu_belum_login_diarahkan_ke_login(): void
    {
        $response = $this->get(route('qr.scan.show'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Regresi — tombol "Scan QR" di dropdown Profil Saya SEMPAT jadi pola
     * yang gampang ketinggalan (sama kayak tombol "QR Pelanggan" di
     * customers.show, ADHOC-46) — halaman/route/permission ada tapi gak
     * ada jalan masuk dari UI yang staf beneran buka.
     */
    #[Test]
    public function link_scan_qr_muncul_di_dropdown_buat_staf_yang_punya_permission(): void
    {
        $staff = $this->staffWithPermission('helpdesk');

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('qr.scan.show'), false);
    }

    #[Test]
    public function link_scan_qr_tidak_muncul_buat_staf_tanpa_permission(): void
    {
        $staff = $this->staffWithPermission('sales');

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('qr.scan.show'), false);
    }
}
