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
 * Dokumentasi API (/docs/api, Scramble) memetakan seluruh endpoint integrasi
 * + skema token. Dulu: bebas di env local, di luar local hasFullAccess().
 * Kini khusus owner di semua env, tamu diarahkan ke login — sama dengan
 * Horizon & /log-viewer.
 */
class ApiDocsAccessOwnerOnlyTest extends TestCase
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
    public function owner_boleh_membuka_dokumentasi_api(): void
    {
        $owner = $this->userWithRole('owner');

        $this->assertTrue(Gate::forUser($owner)->allows('viewApiDocs'));
        $this->actingAs($owner)->get('/docs/api')->assertOk();
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
        $user = $this->userWithRole($roleCode);

        $this->assertFalse(Gate::forUser($user)->allows('viewApiDocs'));
        $this->actingAs($user)->get('/docs/api')->assertForbidden();
        $this->actingAs($user)->get('/docs/api.json')->assertForbidden();
    }

    #[Test]
    public function tamu_diarahkan_ke_login(): void
    {
        $this->get('/docs/api')->assertRedirect(route('login'));
        $this->get('/docs/api.json')->assertRedirect(route('login'));
    }

    #[Test]
    public function env_local_tidak_membuka_dokumentasi_untuk_non_owner(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs($this->userWithRole('admin'))->get('/docs/api')->assertForbidden();
    }
}
