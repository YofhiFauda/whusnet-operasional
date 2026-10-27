<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dashboard Horizon memuat payload job (data pelanggan/pembayaran) dan bisa
 * retry/hapus job gagal. Dulu daftar email gate-nya kosong (tak seorang pun
 * bisa buka di prod); kini khusus role owner.
 */
class HorizonAccessOwnerOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(string $code): User
    {
        return User::factory()->create(['role_id' => Role::where('code', $code)->firstOrFail()->id]);
    }

    #[Test]
    public function owner_boleh_membuka_dashboard_horizon(): void
    {
        $owner = $this->userWithRole('owner');

        $this->assertTrue(Gate::forUser($owner)->allows('viewHorizon'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonOwnerRoles(): array
    {
        return [
            'atasan' => ['atasan'],
            'admin' => ['admin'],
            'noc' => ['noc'],
            'helpdesk' => ['helpdesk'],
            'fop' => ['fop'],
            'teknisi' => ['teknisi'],
            'sales' => ['sales'],
            'pop_admin' => ['pop_admin'],
        ];
    }

    #[Test]
    #[DataProvider('nonOwnerRoles')]
    public function role_selain_owner_ditolak(string $roleCode): void
    {
        $this->assertFalse(Gate::forUser($this->userWithRole($roleCode))->allows('viewHorizon'));
    }

    #[Test]
    public function tamu_ditolak(): void
    {
        $this->assertFalse(Gate::allows('viewHorizon'));
    }

    #[Test]
    public function rute_horizon_403_untuk_admin_dan_terbuka_untuk_owner(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get('/horizon')->assertForbidden();
        $this->actingAs($this->userWithRole('owner'))->get('/horizon')->assertOk();
    }
}
