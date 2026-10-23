<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskInfrastructureAction;
use App\Models\User;
use App\Models\UserRoleScopeTarget;
use App\Notifications\AppNotification;
use App\Services\NetworkNodeAttentionService;
use Carbon\CarbonInterface;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Perangkat baru terpasang lewat laporan O-REQ/INFR-REQ: badge per node dan di
 * sidebar hilang per user saat node dibuka, notifikasi hanya ke user yang punya
 * POP scope atas node itu, dan riwayat pemasangan tampil di Detail node.
 */
class NetworkNodeInstallAttentionTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;

    private Pop $popB;

    private NetworkNode $device;

    private User $technician;

    private User $viewer;

    private User $otherViewer;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->popA = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Attention A']);
        $this->popB = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Attention B']);

        $teknisi = Role::where('code', 'teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisi->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisi->id, 'scope_type' => ScopeType::ALL_POP]);

        $fop = Role::where('code', 'fop')->firstOrFail();
        $this->viewer = $this->userWithScope($fop, ScopeType::ALL_POP);
        $this->otherViewer = $this->userWithScope($fop, ScopeType::ALL_POP);
        $this->outsider = $this->userWithScope($fop, ScopeType::SELECTED_POP, $this->popB);

        $this->device = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->firstOrFail()->id,
            'anchor_pop_id' => $this->popA->id, 'code' => 'ODP-ATT-1', 'name' => 'ODP Attention 1',
        ]);
    }

    private function userWithScope(Role $role, ScopeType $scopeType, ?Pop $pop = null): User
    {
        $user = User::factory()->create(['role_id' => $role->id]);
        $scope = $user->roleScopes()->create(['role_id' => $role->id, 'scope_type' => $scopeType]);
        if ($pop !== null) {
            UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);
        }

        return $user;
    }

    private function installAt(NetworkNode $device, CarbonInterface $performedAt): TaskInfrastructureAction
    {
        $task = Task::create([
            'task_number' => 'TASK-ATT-'.uniqid(), 'pop_id' => $this->popA->id, 'task_type' => TaskType::INFR->value,
            'title' => 'Pasang ODP', 'status' => TaskStatus::IN_PROGRESS->value, 'started_at' => now(),
            'created_by' => $this->technician->id, 'updated_by' => $this->technician->id,
        ]);
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-ATT-'.uniqid(), 'task_date' => now(), 'category' => TaskType::INFR->value,
            'tugas' => 'Uji', 'pop_id' => $this->popA->id, 'task_id' => $task->id, 'issue' => 'Uji',
            'status' => TaskStatus::DRAFT->value, 'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        return TaskInfrastructureAction::create([
            'task_id' => $task->id, 'fop_task_id' => $fopTask->id,
            'action_key' => (string) Str::uuid(), 'action_type' => 'install',
            'network_node_id' => $device->id, 'device_network_node_id' => $device->id,
            'mutation_hash' => hash('sha256', (string) Str::uuid()), 'mutation' => ['action_type' => 'install'],
            'performed_by' => $this->technician->id, 'performed_at' => $performedAt,
        ]);
    }

    #[Test]
    public function test_badge_counts_nodes_only_inside_viewer_pop_scope(): void
    {
        $this->installAt($this->device, now());
        $service = app(NetworkNodeAttentionService::class);

        $this->assertSame(1, $service->unseenCount($this->viewer));
        $this->assertSame(0, $service->unseenCount($this->outsider));
    }

    #[Test]
    public function test_opening_node_clears_badge_only_for_that_user(): void
    {
        $this->installAt($this->device, now()->subMinute());
        $service = app(NetworkNodeAttentionService::class);

        $this->actingAs($this->viewer)->get(route('network.nodes.show', $this->device))->assertOk();

        $this->assertSame(0, $service->unseenCount($this->viewer));
        $this->assertSame(1, $service->unseenCount($this->otherViewer));
    }

    #[Test]
    public function test_new_installation_after_viewing_marks_node_unseen_again(): void
    {
        $this->installAt($this->device, now()->subMinutes(2));
        $this->actingAs($this->viewer)->get(route('network.nodes.show', $this->device))->assertOk();

        $this->installAt($this->device, now()->addMinute());

        $this->assertSame(1, app(NetworkNodeAttentionService::class)->unseenCount($this->viewer));
    }

    #[Test]
    public function test_node_list_shows_new_badge_and_sidebar_count(): void
    {
        $this->installAt($this->device, now());

        $this->actingAs($this->viewer)->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee('Ada perangkat baru terpasang')
            ->assertSee('Node dengan perangkat baru terpasang');
    }

    #[Test]
    public function test_notification_reaches_in_scope_users_except_actor(): void
    {
        Notification::fake();
        $action = $this->installAt($this->device, now());

        app(NetworkNodeAttentionService::class)->notifyInstalled($this->device, $action->task, $this->viewer);

        Notification::assertSentTo($this->otherViewer, AppNotification::class);
        Notification::assertNotSentTo($this->viewer, AppNotification::class);
        Notification::assertNotSentTo($this->outsider, AppNotification::class);
    }

    #[Test]
    public function test_node_detail_lists_installation_history(): void
    {
        $action = $this->installAt($this->device, now());

        $this->actingAs($this->viewer)->get(route('network.nodes.show', $this->device))
            ->assertOk()
            ->assertSee($action->task->task_number)
            ->assertSee('Pasang');
    }

    #[Test]
    public function test_selecting_node_in_list_clears_badge_through_seen_endpoint(): void
    {
        $this->installAt($this->device, now());
        $service = app(NetworkNodeAttentionService::class);

        $this->actingAs($this->viewer)->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee(route('network.nodes.seen', $this->device));

        $this->actingAs($this->viewer)->postJson(route('network.nodes.seen', $this->device))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(0, $service->unseenCount($this->viewer));
    }

    #[Test]
    public function test_seen_endpoint_rejects_user_outside_node_scope(): void
    {
        $this->installAt($this->device, now());

        $this->actingAs($this->outsider)->postJson(route('network.nodes.seen', $this->device))->assertForbidden();

        $this->assertSame(1, app(NetworkNodeAttentionService::class)->unseenCount($this->viewer));
    }

    #[Test]
    public function test_odp_starts_collapsed_so_customer_onts_stay_hidden_until_opened(): void
    {
        $this->actingAs($this->viewer)->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee('isOpen: false');

        $this->actingAs($this->viewer)->get(route('network.map.index'))
            ->assertOk()
            ->assertSee('isOpen: false');
    }

    #[Test]
    public function test_out_of_scope_user_cannot_open_node_so_badge_is_not_cleared_for_them(): void
    {
        $this->installAt($this->device, now());

        $this->actingAs($this->outsider)->get(route('network.nodes.show', $this->device))->assertForbidden();

        $this->assertSame(0, app(NetworkNodeAttentionService::class)->unseenCount($this->outsider));
    }
}
