<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bypass Pelanggan (pelanggan legacy terlewat migrasi → langsung aktif) cuma
 * boleh dipegang Owner & Customer Service (keputusan user 2026-11-15). Jalur
 * ini melompati verifikasi, survey, pemasangan, dan Tagihan Awal — kalau
 * permission-nya ikut terseed ke role lain, siapa pun bisa mengaktifkan
 * pelanggan tanpa proses lapangan.
 */
class CustomerBypassPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function roleHasBypass(string $roleCode): bool
    {
        return Role::where('code', $roleCode)->firstOrFail()
            ->permissions()
            ->where('code', 'customers.registration.bypass')
            ->exists();
    }

    #[Test]
    #[DataProvider('roleBerhak')]
    public function role_berhak_mendapat_permission_bypass(string $roleCode): void
    {
        $this->assertTrue($this->roleHasBypass($roleCode), "Role {$roleCode} seharusnya punya customers.registration.bypass");
    }

    #[Test]
    #[DataProvider('roleTidakBerhak')]
    public function role_lain_tidak_mendapat_permission_bypass(string $roleCode): void
    {
        $this->assertFalse($this->roleHasBypass($roleCode), "Role {$roleCode} tidak boleh punya customers.registration.bypass");
    }

    public static function roleBerhak(): array
    {
        return [['owner'], ['customer_service']];
    }

    public static function roleTidakBerhak(): array
    {
        return [['sales'], ['helpdesk'], ['admin'], ['teknisi'], ['noc'], ['fop']];
    }
}
