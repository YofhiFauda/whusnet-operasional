<?php

namespace Tests\Feature\Network;

use App\Enums\InfrastructurePortKind;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructurePort;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureConnectionService;
use App\Services\InfrastructurePositionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfrastructureEndpointScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $actor;

    private Pop $miniA;

    private Pop $miniB;

    private InfrastructurePort $pon;

    private InfrastructurePort $switchPort;

    private InfrastructurePort $outsidePort;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class,
            PermissionSeeder::class, NetworkNodeTypeSeeder::class, RolePermissionSeeder::class]);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->sole()->id]);
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniA = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);
        $this->miniB = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);
        $this->actor = User::factory()->create(['role_id' => Role::where('code', 'fop')->sole()->id]);
        $scope = UserRoleScope::create(['user_id' => $this->actor->id, 'role_id' => $this->actor->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->miniA->id]);
        app(EffectiveAccessService::class)->clearCache($this->actor);
        $positions = app(InfrastructurePositionService::class);
        $this->pon = $positions->createPort($positions->createSlot($this->node('olt_chassis', $this->miniA, 'OLT-A'), 1), 1, InfrastructurePortKind::PON);
        $this->switchPort = $positions->createPort($positions->ensureLogicalSlot($this->node('switch_mini_pop', $this->miniA, 'SW-A')), 1, InfrastructurePortKind::SWITCH);
        $this->outsidePort = $positions->createPort($positions->ensureLogicalSlot($this->node('switch_mini_pop', $this->miniB, 'SW-B-SECRET')), 1, InfrastructurePortKind::SWITCH);
    }

    private function node(string $type, Pop $pop, string $code): NetworkNode
    {
        return NetworkNode::factory()->create(['node_type_id' => NetworkNodeType::where('code', $type)->sole()->id,
            'anchor_pop_id' => $pop->id, 'parent_id' => null, 'code' => $code, 'name' => $code]);
    }

    #[Test]
    public function odp_candidates_exclude_sibling_mini_pop(): void
    {
        $allowed = $this->node('odp', $this->miniA, 'ODP-ALLOWED');
        $hidden = $this->node('odp', $this->miniB, 'ODP-SECRET');

        $this->actingAs($this->actor)->get(route('network.infrastructure-ports.show', $this->pon))
            ->assertOk()->assertSee($allowed->code)->assertDontSee($hidden->code);
    }

    #[Test]
    public function released_connection_history_hides_outside_node_and_port_endpoints(): void
    {
        $node = $this->node('switch_mini_pop', $this->miniB, 'NODE-SECRET');
        $service = app(InfrastructureConnectionService::class);
        $toNode = $service->connect($this->switchPort, $this->owner, targetNode: $node, notes: 'SECRET-NODE-NOTE');
        $service->release($toNode, $this->owner, 'SECRET-NODE-RELEASE');
        $toPort = $service->connect($this->switchPort, $this->owner, targetPort: $this->outsidePort, notes: 'SECRET-PORT-NOTE');
        $service->release($toPort, $this->owner, 'SECRET-PORT-RELEASE');

        $this->actingAs($this->actor)->get(route('network.infrastructure-ports.show', $this->switchPort))
            ->assertOk()->assertViewHas('connections', fn ($rows): bool => $rows->isEmpty())
            ->assertDontSee('SECRET');
    }

    #[Test]
    public function incoming_connection_history_hides_outside_source_endpoint(): void
    {
        app(InfrastructureConnectionService::class)->connect($this->outsidePort, $this->owner,
            targetPort: $this->switchPort, notes: 'SECRET-INCOMING');

        $this->actingAs($this->actor)->get(route('network.infrastructure-ports.show', $this->switchPort))
            ->assertOk()->assertViewHas('connections', fn ($rows): bool => $rows->isEmpty());
    }

    public static function endpointActions(): array
    {
        return [['node', 'verify'], ['node', 'release'], ['port', 'verify'], ['port', 'release']];
    }

    #[Test]
    #[DataProvider('endpointActions')]
    public function connection_actions_require_both_endpoints_in_actor_scope(string $kind, string $action): void
    {
        $service = app(InfrastructureConnectionService::class);
        $connection = $kind === 'node'
            ? $service->connect($this->switchPort, $this->owner, targetNode: $this->node('switch_mini_pop', $this->miniB, 'SECRET-TARGET'))
            : $service->connect($this->switchPort, $this->owner, targetPort: $this->outsidePort);

        $this->actingAs($this->actor)->post(route('network.infrastructure-connections.'.$action, $connection), ['reason' => 'Request outside scope'])
            ->assertForbidden();
        $connection->refresh();
        $this->assertSame(1, $connection->active_key);
        $this->assertFalse($connection->isVerified());
    }

    #[Test]
    public function connect_checks_supplied_target_port_scope(): void
    {
        $this->actingAs($this->actor)->post(route('network.infrastructure-ports.connections.store', $this->switchPort),
            ['target_infrastructure_port_id' => $this->outsidePort->id, 'target_label' => 'Disguised destination'])
            ->assertForbidden();
        $this->assertDatabaseMissing('infrastructure_connections', ['source_infrastructure_port_id' => $this->switchPort->id]);
    }

    #[Test]
    public function authorized_connection_actions_and_label_destination_remain_available(): void
    {
        $this->actingAs($this->actor)->post(route('network.infrastructure-ports.connections.store', $this->switchPort), ['target_label' => 'Radio lokal'])
            ->assertRedirect();
        $connection = InfrastructureConnection::where('source_infrastructure_port_id', $this->switchPort->id)->sole();

        $this->post(route('network.infrastructure-connections.verify', $connection))->assertRedirect();
        $this->assertTrue($connection->fresh()->isVerified());
        $this->post(route('network.infrastructure-connections.release', $connection), ['reason' => 'Selesai'])->assertRedirect();
        $this->assertNull($connection->fresh()->active_key);
    }

    #[Test]
    public function orphan_odp_is_not_a_candidate_and_does_not_break_authorized_port_detail(): void
    {
        $orphan = $this->node('odp', $this->miniB, 'ODP-ORPHAN');
        $orphan->update(['anchor_pop_id' => null]);

        $this->actingAs($this->actor)->get(route('network.infrastructure-ports.show', $this->pon))
            ->assertOk()->assertDontSee('ODP-ORPHAN');
        $this->actingAs($this->owner)->get(route('network.infrastructure-ports.show', $this->pon))
            ->assertOk()->assertDontSee('ODP-ORPHAN');
    }

    #[Test]
    public function supplied_outside_target_node_is_checked_even_with_a_label_destination(): void
    {
        $node = $this->node('switch_mini_pop', $this->miniB, 'OUTSIDE-NODE');

        $this->actingAs($this->actor)->post(route('network.infrastructure-ports.connections.store', $this->switchPort),
            ['target_network_node_id' => $node->id, 'target_label' => 'Disguised node'])
            ->assertForbidden();
        $this->assertDatabaseMissing('infrastructure_connections', ['source_infrastructure_port_id' => $this->switchPort->id]);
    }

    #[Test]
    public function permitted_port_destination_is_recorded_and_can_be_verified_and_released(): void
    {
        $positions = app(InfrastructurePositionService::class);
        $target = $positions->createPort($positions->ensureLogicalSlot($this->node('switch_mini_pop', $this->miniA, 'SW-LOCAL')), 1, InfrastructurePortKind::SWITCH);

        $this->actingAs($this->actor)->post(route('network.infrastructure-ports.connections.store', $this->switchPort),
            ['target_infrastructure_port_id' => $target->id])->assertRedirect()->assertSessionHasNoErrors();
        $connection = InfrastructureConnection::where('source_infrastructure_port_id', $this->switchPort->id)->sole();
        $this->assertSame($target->id, $connection->target_infrastructure_port_id);

        $this->post(route('network.infrastructure-connections.verify', $connection))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($connection->fresh()->isVerified());
        $this->post(route('network.infrastructure-connections.release', $connection), ['reason' => 'Selesai'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($connection->fresh()->active_key);
    }
}
