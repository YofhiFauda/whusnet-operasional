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
 * Log Viewer (/log-viewer) menampilkan isi storage/logs — bisa memuat data
 * pelanggan, stack trace, payload request — dan bisa menghapus file log.
 * Khusus role owner, sama seperti Horizon.
 */
class LogViewerAccessOwnerOnlyTest extends TestCase
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
    public function owner_boleh_membuka_log_viewer(): void
    {
        $owner = $this->userWithRole('owner');

        $this->assertTrue(Gate::forUser($owner)->allows('viewLogViewer'));
        $this->actingAs($owner)->get('/log-viewer')->assertOk();
        $this->actingAs($owner)->getJson('/log-viewer/api/files')->assertOk();
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

        $this->assertFalse(Gate::forUser($user)->allows('viewLogViewer'));
        $this->actingAs($user)->get('/log-viewer')->assertForbidden();
        $this->actingAs($user)->getJson('/log-viewer/api/files')->assertForbidden();
    }

    #[Test]
    public function tamu_diarahkan_ke_login_dan_api_ditolak(): void
    {
        $this->get('/log-viewer')->assertRedirect(route('login'));
        $this->getJson('/log-viewer/api/files')->assertForbidden();
    }
}
