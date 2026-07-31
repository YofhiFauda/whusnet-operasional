<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * RBAC modul Ticketing — 5 halaman, 5 permission KEPISAH:
 *
 *   New Ticket        /tickets/new            tickets.create
 *   Worksheet NOC     /noc/worksheet/masuk    noc_worksheet.masuk.view
 *                     /noc/worksheet/diproses noc_worksheet.diproses.view
 *   Dashboard NOC     /noc/dashboard          noc_dashboard.view
 *   Ticket Selesai    /tickets/selesai        tickets.selesai.view
 *   Ticket Dibatalkan /tickets/dibatalkan     tickets.dibatalkan.view
 *
 * Inti yang dijaga: nyabut satu permission CUMA nutup halamannya sendiri,
 * gak ikut nutup halaman lain (dulu semuanya numpang `tickets.view` lewat
 * route bucket generik, jadi gak bisa dipisah).
 */
class TicketingRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(TicketFeatureSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        Pop::create([
            'name' => 'POP Polorejo',
            'code' => 'POP-PLR',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
        ]);
    }

    private function makeUser(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->first();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        return $user;
    }

    /**
     * Cabut satu permission dari role user, lalu bersihin cache akses efektif
     * (wajib — permission di-cache, lihat CLAUDE.md § RBAC).
     */
    private function revokePermission(User $user, string $permissionCode): void
    {
        $permission = Permission::where('code', $permissionCode)->firstOrFail();
        $user->role->permissions()->detach($permission->id);

        app(EffectiveAccessService::class)->clearCache($user);
    }

    /**
     * @return array<string, array{0: string, 1: string}> [route name, permission]
     */
    public static function ticketingPageProvider(): array
    {
        return [
            'New Ticket' => ['tickets.create', 'tickets.create'],
            'Worksheet NOC' => ['noc.worksheet', 'noc_worksheet.view'],
            'Dashboard NOC' => ['noc.dashboard', 'noc_dashboard.view'],
            'Ticket Selesai' => ['tickets.selesai', 'tickets.selesai.view'],
            'Ticket Dibatalkan' => ['tickets.dibatalkan', 'tickets.dibatalkan.view'],
        ];
    }

    /**
     * Semua permission-nya beneran kegenerate dari config/rbac.php — kalau
     * ada yang kelewat, halamannya bakal 403 permanen buat semua role.
     */
    #[DataProvider('ticketingPageProvider')]
    public function test_permission_exists_for_page(string $routeName, string $permission): void
    {
        $this->assertDatabaseHas('permissions', ['code' => $permission]);
    }

    /**
     * Owner (`*`) tembus semua halaman.
     */
    #[DataProvider('ticketingPageProvider')]
    public function test_owner_can_access_every_page(string $routeName, string $permission): void
    {
        $this->actingAs($this->makeUser('owner'))
            ->get(route($routeName))
            ->assertOk();
    }

    /**
     * Teknisi gak dapet permission Ticketing sama sekali — ditolak di semua
     * halaman (403, bukan cuma disembunyiin menunya).
     */
    #[DataProvider('ticketingPageProvider')]
    public function test_teknisi_is_forbidden_on_every_page(string $routeName, string $permission): void
    {
        $this->actingAs($this->makeUser('teknisi'))
            ->get(route($routeName))
            ->assertForbidden();
    }

    /**
     * Inti pemisahan: cabut permission SATU halaman → cuma halaman itu yang
     * ketutup, sisanya tetap kebuka.
     *
     * Pakai `admin`, BUKAN `owner` — owner permission-nya wildcard `*` yang
     * di-bypass duluan di EffectiveAccessService::userCan(), jadi nyabut baris
     * permission spesifik gak ngefek apa-apa buat dia.
     */
    #[DataProvider('ticketingPageProvider')]
    public function test_revoking_one_permission_only_closes_that_page(string $routeName, string $permission): void
    {
        $user = $this->makeUser('admin');

        $this->revokePermission($user, $permission);

        $this->actingAs($user)->get(route($routeName))->assertForbidden();

        foreach (self::ticketingPageProvider() as [$otherRoute, $otherPermission]) {
            if ($otherPermission === $permission) {
                continue;
            }

            $this->actingAs($user)
                ->get(route($otherRoute))
                ->assertOk();
        }
    }

    // ── Matriks role default ──────────────────────────────────────

    public function test_noc_can_access_worksheet_and_dashboard(): void
    {
        $noc = $this->makeUser('noc');

        $this->actingAs($noc)->get(route('noc.worksheet'))->assertOk();
        $this->actingAs($noc)->get(route('noc.dashboard'))->assertOk();
        $this->actingAs($noc)->get(route('tickets.create'))->assertOk();
    }

    /**
     * Helpdesk kerja dari New Ticket + arsip, TAPI gak boleh masuk Worksheet
     * NOC / Dashboard NOC (itu lembar kerja tim lain).
     */
    public function test_helpdesk_can_access_worksheet_and_archives_but_not_noc_pages(): void
    {
        $helpdesk = $this->makeUser('helpdesk');

        $this->actingAs($helpdesk)->get(route('tickets.create'))->assertOk();
        $this->actingAs($helpdesk)->get(route('tickets.selesai'))->assertOk();
        $this->actingAs($helpdesk)->get(route('tickets.dibatalkan'))->assertOk();

        $this->actingAs($helpdesk)->get(route('noc.worksheet'))->assertForbidden();
        $this->actingAs($helpdesk)->get(route('noc.dashboard'))->assertForbidden();
    }

    /**
     * Atasan cuma memantau — Dashboard NOC & arsip boleh, Worksheet NOC
     * (tempat kerja) enggak, bikin tiket juga enggak.
     */
    public function test_atasan_can_monitor_but_not_work(): void
    {
        $atasan = $this->makeUser('atasan');

        $this->actingAs($atasan)->get(route('noc.dashboard'))->assertOk();
        $this->actingAs($atasan)->get(route('tickets.selesai'))->assertOk();
        $this->actingAs($atasan)->get(route('tickets.dibatalkan'))->assertOk();

        $this->actingAs($atasan)->get(route('noc.worksheet'))->assertForbidden();
        $this->actingAs($atasan)->get(route('tickets.create'))->assertForbidden();
    }

    /**
     * Worksheet NOC jadi SATU halaman tanpa tab (ADHOC-06) — gerbangnya
     * `noc_worksheet.view`. Dua permission tab lama dipensiunkan: masih ada di
     * DB (biar role yang terlanjur punya gak error) tapi gak lagi membuka
     * halaman apa pun sendirian.
     */
    public function test_worksheet_is_gated_by_root_permission_only(): void
    {
        $user = $this->makeUser('noc');
        $this->revokePermission($user, 'noc_worksheet.view');

        $this->actingAs($user)->get(route('noc.worksheet'))->assertForbidden();
    }

    public function test_forbidden_archive_page_is_not_rendered_in_navigation(): void
    {
        $user = $this->makeUser('admin');
        $this->revokePermission($user, 'tickets.dibatalkan.view');

        $this->actingAs($user)
            ->get(route('tickets.selesai'))
            ->assertOk()
            ->assertDontSee(route('tickets.dibatalkan'), false);
    }
}
