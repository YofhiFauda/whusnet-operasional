<?php

namespace Tests\Feature\Network;

use App\Models\AuditLog;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructurePositionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Final review ADHOC-213 (I7): tes scope/izin jalur TULIS kapasitas hilang
 * saat tes legacy InfrastructureSlot/Port dihapus. Dipulihkan untuk tiga
 * route PATCH kapasitas: perangkat (`network.nodes.capacity`), Slot
 * (`network.infrastructure-slots.capacity`), PON
 * (`network.infrastructure-ports.capacity`) — aktor cabang lain 403, tanpa
 * izin `network_nodes.update` 403, dengan izin + scope berhasil dan
 * meninggalkan jejak audit.
 */
class InfrastructureCapacityWriteScopeTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabangA;

    private Pop $cabangB;

    /** @var array<string, NetworkNode> */
    private array $a = [];

    /** @var array<string, NetworkNode> */
    private array $b = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class,
            PermissionSeeder::class, NetworkNodeTypeSeeder::class, RolePermissionSeeder::class]);
        $this->cabangA = Pop::factory()->create(['type' => 'cabang']);
        $this->cabangB = Pop::factory()->create(['type' => 'cabang']);
        $this->a = $this->structure($this->cabangA, 'OLT-A');
        $this->b = $this->structure($this->cabangB, 'OLT-B');
    }

    /**
     * @return array{device: NetworkNode, slot: NetworkNode, pon: NetworkNode}
     */
    private function structure(Pop $pop, string $code): array
    {
        $device = NetworkNode::create(['node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'anchor_pop_id' => $pop->id, 'code' => $code, 'name' => $code, 'status' => 'active']);
        $positions = app(InfrastructurePositionService::class);
        $slot = $positions->createSlot($device, 1);

        return ['device' => $device, 'slot' => $slot, 'pon' => $positions->createPort($slot, 1)];
    }

    private function scopedUser(string $role, Pop $cabang): User
    {
        $user = User::factory()->create(['role_id' => Role::where('code', $role)->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $user->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    /**
     * @return array{0: string, 1: array<string, int>}
     */
    private function request(string $target, NetworkNode $node): array
    {
        return match ($target) {
            'device' => [route('network.nodes.capacity', $node), ['capacity' => 4]],
            'slot' => [route('network.infrastructure-slots.capacity', $node), ['capacity' => 8]],
            'pon' => [route('network.infrastructure-ports.capacity', $node), ['capacity' => 64, 'red_zone_threshold' => 80]],
        };
    }

    #[Test]
    public function aktor_cabang_lain_ditolak_di_ketiga_route_kapasitas(): void
    {
        $actor = $this->scopedUser('fop', $this->cabangA);

        foreach (['device', 'slot', 'pon'] as $target) {
            [$url, $payload] = $this->request($target, $this->b[$target]);
            $before = $this->b[$target]->fresh()->capacity;

            $this->actingAs($actor)->patch($url, $payload)->assertForbidden();
            $this->assertSame($before, $this->b[$target]->fresh()->capacity, "Kapasitas {$target} cabang lain tidak boleh berubah.");
        }
        $this->assertSame(0, AuditLog::where('action', 'capacity_change')->count());
    }

    #[Test]
    public function tanpa_izin_update_ditolak_walau_dalam_scope(): void
    {
        // atasan: hanya network_nodes.view.
        $viewer = $this->scopedUser('atasan', $this->cabangA);

        foreach (['device', 'slot', 'pon'] as $target) {
            [$url, $payload] = $this->request($target, $this->a[$target]);
            $this->actingAs($viewer)->patch($url, $payload)->assertForbidden();
        }
        $this->assertSame(0, AuditLog::where('action', 'capacity_change')->count());
    }

    #[Test]
    public function dengan_izin_dan_scope_berhasil_dan_teraudit(): void
    {
        $actor = $this->scopedUser('fop', $this->cabangA);

        foreach (['device' => 4, 'slot' => 8, 'pon' => 64] as $target => $expected) {
            [$url, $payload] = $this->request($target, $this->a[$target]);
            $this->actingAs($actor)->patch($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($expected, $this->a[$target]->fresh()->capacity);
            $this->assertDatabaseHas('audit_logs', [
                'action' => 'capacity_change', 'auditable_type' => NetworkNode::class,
                'auditable_id' => $this->a[$target]->id, 'user_id' => $actor->id,
            ]);
        }
    }
}
