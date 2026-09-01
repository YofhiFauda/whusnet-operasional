<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleManagementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Channel Portal/QR (`customers.qr`, `tickets.qr`, `kolektor.qr`) harus
 * independen dari dashboard Operasional induknya (`customers`, `tickets`,
 * `kolektor`). Bug nyata yang diperbaiki: mencentang `tickets.qr.create` di
 * Role Matrix untuk role Kolektor TERPAKSA ikut mencentang `tickets.view`
 * (akses penuh dashboard Ticketing Operasional) gara-gara
 * RoleManagementService::syncPermissions() auto-grant `view` fitur induk
 * naik sampai ke akar tree — tidak berhenti di batas fitur `.qr`.
 *
 * config/rbac.php > view_autogrant_chain_boundary.
 */
class RoleMatrixQrChannelIndependenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function buatRoleKustom(): Role
    {
        return Role::create([
            'code' => 'kolektor_qr_test',
            'name' => 'Kolektor QR Test',
            'is_system' => false,
        ]);
    }

    public function test_mencentang_tickets_qr_create_tidak_ikut_memberi_akses_dashboard_ticketing(): void
    {
        $role = $this->buatRoleKustom();

        app(RoleManagementService::class)->syncPermissions($role, [
            Permission::where('code', 'tickets.qr.create')->firstOrFail()->id,
        ]);

        $kodeTerpasang = $role->fresh()->permissions()->pluck('code')->all();

        $this->assertContains('tickets.qr.create', $kodeTerpasang);
        $this->assertNotContains('tickets.view', $kodeTerpasang);
    }

    public function test_mencentang_kolektor_qr_pay_tidak_ikut_memberi_worklist_kolektor(): void
    {
        $role = $this->buatRoleKustom();

        app(RoleManagementService::class)->syncPermissions($role, [
            Permission::where('code', 'kolektor.qr.pay')->firstOrFail()->id,
        ]);

        $kodeTerpasang = $role->fresh()->permissions()->pluck('code')->all();

        $this->assertContains('kolektor.qr.pay', $kodeTerpasang);
        $this->assertNotContains('kolektor.view', $kodeTerpasang);
    }

    /**
     * Kebalikannya juga harus tetap benar: fitur non-`.qr` biasa TETAP
     * auto-grant `.view` induknya seperti semula — perbaikan ini cuma
     * mengecualikan channel `.qr`, bukan mematikan S6 secara umum.
     */
    public function test_fitur_non_qr_tetap_auto_grant_view_induk_seperti_semula(): void
    {
        $role = $this->buatRoleKustom();

        app(RoleManagementService::class)->syncPermissions($role, [
            Permission::where('code', 'tickets.create')->firstOrFail()->id,
        ]);

        $kodeTerpasang = $role->fresh()->permissions()->pluck('code')->all();

        $this->assertContains('tickets.create', $kodeTerpasang);
        $this->assertContains('tickets.view', $kodeTerpasang);
    }
}
