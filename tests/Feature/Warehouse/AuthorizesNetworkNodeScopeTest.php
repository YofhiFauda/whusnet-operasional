<?php

namespace Tests\Feature\Warehouse;

use App\Enums\ScopeType;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Task 9: `AuthorizesWarehousePop::assertNetworkNodeInScope()`.
 *
 * Trait ini tidak punya test langsung sebelumnya (4 method lamanya cuma
 * diuji tidak langsung lewat route controller Gudang) — jadi di sini dipakai
 * teknik (a) dari brief: kelas anonim yang `use` trait-nya plus wrapper
 * publik, diinstansiasi langsung. Lebih langsung daripada merakit satu
 * controller Gudang HTTP cuma buat memanggil method baru yang belum punya
 * caller nyata (Task 12/13 yang bakal jadi caller sungguhan).
 */
class AuthorizesNetworkNodeScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /**
     * Wrapper publik di atas trait — method aslinya `protected`.
     */
    private function wrapper(): object
    {
        return new class
        {
            use AuthorizesWarehousePop;

            public function callAssertNetworkNodeInScope(NetworkNode $node, User $user, EffectiveAccessService $access): void
            {
                $this->assertNetworkNodeInScope($node, $user, $access);
            }
        };
    }

    /**
     * Actor ber-role $roleCode, scope `selected_pop` cuma ke $pop (otomatis
     * nyakup Mini POP di bawah $pop lewat `resolvePopTree()` —
     * `EffectiveAccessService::getAllowedPopIds()`).
     */
    private function makeScopedUser(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    #[Test]
    public function node_di_mini_pop_cabang_lain_ditolak_403(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $miniPopB = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabangB->id]);

        $user = $this->makeScopedUser('helpdesk', $cabangA);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $miniPopB->id, 'parent_id' => null]);

        $access = app(EffectiveAccessService::class);

        try {
            $this->wrapper()->callAssertNetworkNodeInScope($node, $user, $access);
            $this->fail('Seharusnya melempar HttpException 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    #[Test]
    public function node_di_mini_pop_cabang_sendiri_lolos(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $miniPopA = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabangA->id]);

        $user = $this->makeScopedUser('helpdesk', $cabangA);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $miniPopA->id, 'parent_id' => null]);

        $access = app(EffectiveAccessService::class);

        $this->wrapper()->callAssertNetworkNodeInScope($node, $user, $access);

        $this->assertTrue(true, 'Tidak melempar exception — lolos.');
    }

    #[Test]
    public function user_dengan_akses_semua_pop_selalu_lolos(): void
    {
        $cabangLain = Pop::factory()->create(['type' => 'cabang']);
        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);

        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabangLain->id, 'parent_id' => null]);

        $access = app(EffectiveAccessService::class);

        $this->wrapper()->callAssertNetworkNodeInScope($node, $owner, $access);

        $this->assertTrue(true, 'Owner (hasAllPopAccess) tidak pernah di-403.');
    }

    #[Test]
    public function node_tanpa_pop_yang_bisa_diresolve_di_tolak_403(): void
    {
        // Node model SENGAJA tidak menolak kombinasi ilegal (XOR ditegakkan
        // NetworkNodeService, bukan model) — jadi node "orphan" (parent_id
        // dan anchor_pop_id dua-duanya null) memang bisa dikonstruksi lewat
        // factory langsung, melewati service. resolvePop() akan
        // mengembalikan null untuk node semacam ini.
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $user = $this->makeScopedUser('helpdesk', $cabangA);

        $node = NetworkNode::factory()->create(['anchor_pop_id' => null, 'parent_id' => null]);

        $this->assertNull($node->resolvePop());

        $access = app(EffectiveAccessService::class);

        try {
            $this->wrapper()->callAssertNetworkNodeInScope($node, $user, $access);
            $this->fail('Seharusnya melempar HttpException 403 karena POP tidak bisa diresolve.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    #[Test]
    public function node_beberapa_level_dalam_pohon_resolve_ke_pop_anchor_puncak(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $miniPopA = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabangA->id]);

        $user = $this->makeScopedUser('helpdesk', $cabangA);

        // ODC (anchor ke Mini POP A) -> ODP (child) -> splitter (child dalam child)
        $odc = NetworkNode::factory()->create(['anchor_pop_id' => $miniPopA->id, 'parent_id' => null]);
        $odp = NetworkNode::factory()->child($odc)->create();
        $splitter = NetworkNode::factory()->child($odp)->create();

        $access = app(EffectiveAccessService::class);

        // Splitter tidak punya anchor sendiri — scope check-nya harus naik
        // rantai lewat resolvePop() sampai ke Cabang A, bukan 403 karena
        // dikira "tidak ada POP".
        $this->wrapper()->callAssertNetworkNodeInScope($splitter, $user, $access);
        $this->assertSame($cabangA->id, $splitter->resolvePop()?->id);

        // Sanity: splitter yang resolve-nya ke Cabang B (beda subtree) tetap
        // ditolak lewat jalur yang sama.
        $miniPopB = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabangB->id]);
        $odcB = NetworkNode::factory()->create(['anchor_pop_id' => $miniPopB->id, 'parent_id' => null]);
        $odpB = NetworkNode::factory()->child($odcB)->create();

        try {
            $this->wrapper()->callAssertNetworkNodeInScope($odpB, $user, $access);
            $this->fail('Seharusnya melempar HttpException 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
