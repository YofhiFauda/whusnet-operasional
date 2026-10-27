<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use App\Services\FopTaskInfrastructureScopeService;
use App\Services\InfrastructureLocationOptionsService;
use App\Services\InfrastructurePositionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Endpoint opsi dropdown lokasi (§10.2): server yang menurunkan anak induk
 * terpilih dalam irisan scope — browser tidak menerima semua data lalu
 * menyaring sendiri.
 */
class InfrastructureLocationOptionsTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $cabangLain;

    private Pop $miniA1;

    private Pop $miniA2;

    private Pop $miniLain;

    private NetworkNode $oltA;

    private NetworkNode $oltB;

    private NetworkNode $switchB;

    private NetworkNode $slotA1;

    private NetworkNode $ponA11;

    private NetworkNode $switchPort;

    private NetworkNode $odpA;

    private User $technician;

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

        $this->cabang = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang C1']);
        $this->cabangLain = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang D1']);
        $this->miniA1 = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id, 'name' => 'C1-M1']);
        $this->miniA2 = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id, 'name' => 'C1-M2']);
        $this->miniLain = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabangLain->id, 'name' => 'D1-M1']);

        $teknisi = Role::where('code', 'teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisi->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisi->id, 'scope_type' => ScopeType::ALL_POP]);

        $positions = app(InfrastructurePositionService::class);
        $this->oltA = $this->node('olt_chassis', $this->miniA1, 'OLT-A');
        $this->oltB = $this->node('olt_chassis', $this->miniA1, 'OLT-B');
        $this->switchB = $this->node('switch_mini_pop', $this->miniA1, 'SW-B');
        $this->node('olt_chassis', $this->miniLain, 'OLT-LAIN');
        $this->slotA1 = $positions->createSlot($this->oltA, 1);
        $positions->createSlot($this->oltB, 1);
        $this->ponA11 = $positions->createPort($this->slotA1, 1);
        $this->switchPort = $positions->createPort($positions->ensureLogicalSlot($this->switchB), 4);
        $this->odpA = $this->node('odp', $this->miniA1, 'ODP-A');
        $this->node('odp', $this->miniA1, 'ODP-LEPAS');
        // ODP anak langsung PON (parent_id), bukan sambungan.
        $this->odpA->update(['parent_id' => $this->ponA11->id]);
    }

    private function node(string $typeCode, Pop $anchor, string $code): NetworkNode
    {
        return NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', $typeCode)->firstOrFail()->id,
            'anchor_pop_id' => $anchor->id, 'code' => $code, 'name' => $code,
        ]);
    }

    /**
     * @param  array<string, int>  $scope
     */
    private function task(array $scope = []): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-OPT-'.uniqid(), 'pop_id' => $this->cabang->id, 'task_type' => 'INFR REQ',
            'title' => 'Uji opsi', 'status' => TaskStatus::IN_PROGRESS->value, 'started_at' => now(),
            'created_by' => $this->technician->id, 'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-OPT-'.uniqid(), 'task_date' => now(), 'category' => 'INFR REQ', 'tugas' => 'Uji',
            'pop_id' => $this->cabang->id, 'task_id' => $task->id, 'issue' => 'Uji',
            'status' => TaskStatus::DRAFT->value, 'priority' => FopTaskPriority::MEDIUM->value,
        ]);
        $scopes = app(FopTaskInfrastructureScopeService::class);
        $scopes->applyScope($fopTask, $scopes->resolveRequestedRoot($this->cabang, $scope),
            User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]));

        return $task;
    }

    /**
     * @return array{options: list<array<string, mixed>>, allow_none: bool, disabled: bool}
     */
    private function locationOptions(Task $task, string $level, ?int $parent = null, ?User $actor = null): array
    {
        return $this->actingAs($actor ?? $this->technician)
            ->getJson(route('tasks.infrastructure.location-options', ['task' => $task, 'level' => $level, 'parent' => $parent]))
            ->assertOk()->json();
    }

    /**
     * @param  array{options: list<array<string, mixed>>}  $response
     * @return list<string>
     */
    private function labels(array $response): array
    {
        return array_column($response['options'], 'label');
    }

    #[Test]
    public function mini_pop_hanya_dari_cabang_task(): void
    {
        $response = $this->locationOptions($this->task(), 'mini_pop', $this->cabang->id);

        $this->assertSame(['C1-M1', 'C1-M2'], $this->labels($response));
        $this->assertTrue($response['allow_none']);
    }

    #[Test]
    public function induk_dari_cabang_lain_ditolak(): void
    {
        $this->actingAs($this->technician)
            ->getJson(route('tasks.infrastructure.location-options', ['task' => $this->task(), 'level' => 'mini_pop', 'parent' => $this->cabangLain->id]))
            ->assertStatus(422);
    }

    #[Test]
    public function akar_mini_pop_hanya_menawarkan_akar_tanpa_dikosongkan(): void
    {
        $response = $this->locationOptions($this->task(['scope_mini_pop_id' => $this->miniA1->id]), 'mini_pop', $this->cabang->id);

        $this->assertSame(['C1-M1'], $this->labels($response));
        $this->assertFalse($response['allow_none']);
    }

    #[Test]
    public function perangkat_hanya_milik_mini_pop_terpilih_dan_subtree(): void
    {
        $this->assertSame(['OLT-A', 'OLT-B', 'SW-B'], $this->labels($this->locationOptions($this->task(), 'device', $this->miniA1->id)));

        $scoped = $this->locationOptions($this->task(['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->oltA->id]), 'device', $this->miniA1->id);
        $this->assertSame(['OLT-A'], $this->labels($scoped));
        $this->assertFalse($scoped['allow_none']);
    }

    #[Test]
    public function slot_pon_dan_odp_mengikuti_induk(): void
    {
        $task = $this->task();

        $this->assertSame(['Slot 1'], $this->labels($this->locationOptions($task, 'slot', $this->oltA->id)));
        $this->assertSame(['PON 1'], $this->labels($this->locationOptions($task, 'port', $this->slotA1->id)));
        $this->assertSame(['ODP-A'], $this->labels($this->locationOptions($task, 'odp', $this->ponA11->id)));
    }

    #[Test]
    public function jalur_switch_tidak_punya_odp(): void
    {
        $response = $this->locationOptions($this->task(), 'odp', $this->switchPort->id);

        $this->assertSame([], $response['options']);
        $this->assertTrue($response['disabled']);
    }

    #[Test]
    public function aktor_satu_cabang_tidak_mendapat_opsi_cabang_lain(): void
    {
        $actor = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $actor->id, 'role_id' => $actor->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->miniA2->id]);
        app(EffectiveAccessService::class)->clearCache($actor);
        $task = $this->task();
        $task->teamMembers()->create(['user_id' => $actor->id, 'role_in_task' => 'member']);

        $this->assertSame(['C1-M2'], $this->labels($this->locationOptions($task, 'mini_pop', $this->cabang->id, $actor)));
    }

    #[Test]
    public function aset_langsung_dipisah_dari_turunan_dan_sn_cabut_mengikuti_lokasi_tepat(): void
    {
        $category = ItemCategory::firstOrCreate(['code' => 'olt_module'], ['name' => 'OLT', 'default_unit' => 'pcs']);
        $item = Item::create(['code' => 'ASET', 'name' => 'Aset', 'unit' => 'pcs', 'item_category_id' => $category->id,
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset']);
        InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'SN-MINI', 'status' => SerialStatus::DEPLOYED->value, 'current_mini_pop_id' => $this->miniA1->id]);
        InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'SN-OLT-A', 'status' => SerialStatus::DEPLOYED->value, 'current_network_node_id' => $this->oltA->id]);
        InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'SN-OLT-B', 'status' => SerialStatus::DEPLOYED->value, 'current_network_node_id' => $this->oltB->id]);
        InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'SN-SLOT', 'status' => SerialStatus::DEPLOYED->value, 'current_infrastructure_slot_id' => $this->slotA1->id]);
        $task = $this->task();

        $mini = $this->actingAs($this->technician)->getJson(route('tasks.infrastructure.location-assets', ['task' => $task, 'kind' => 'mini_pop', 'id' => $this->miniA1->id]))->assertOk()->json();
        $this->assertSame(['SN-MINI'], array_column($mini['direct'], 'serial_number'));
        $this->assertSame(3, $mini['descendant_count']);

        $olt = $this->actingAs($this->technician)->getJson(route('tasks.infrastructure.location-assets', ['task' => $task, 'kind' => 'node', 'id' => $this->oltA->id]))->assertOk()->json();
        $this->assertSame(['SN-OLT-A'], array_column($olt['direct'], 'serial_number'));
        $this->assertSame(1, $olt['descendant_count']);
        $this->assertSame('Cabang C1 → C1-M1 → OLT-A', $olt['breadcrumb']);
    }

    #[Test]
    public function aset_lokasi_di_luar_scope_ditolak(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->oltA->id]);

        $this->actingAs($this->technician)->getJson(route('tasks.infrastructure.location-assets', ['task' => $task, 'kind' => 'node', 'id' => $this->oltB->id]))
            ->assertStatus(422);
    }

    #[Test]
    public function orang_luar_tim_tidak_bisa_membaca_opsi(): void
    {
        $outsider = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->firstOrFail()->id]);

        $this->actingAs($outsider)
            ->getJson(route('tasks.infrastructure.location-options', ['task' => $this->task(), 'level' => 'mini_pop', 'parent' => $this->cabang->id]))
            ->assertForbidden();
    }

    #[Test]
    public function opsi_scope_form_fop_mengikuti_cabang_dan_aktor(): void
    {
        $fop = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        $this->giveAllPopScope($fop);

        $minis = $this->actingAs($fop)->getJson(route('fop-tasks.infrastructure-scope-options', ['pop_id' => $this->cabang->id, 'level' => 'mini_pop', 'parent' => $this->cabang->id]))->assertOk()->json();
        $this->assertSame(['C1-M1', 'C1-M2'], array_column($minis['options'], 'label'));

        $odp = $this->actingAs($fop)->getJson(route('fop-tasks.infrastructure-scope-options', ['pop_id' => $this->cabang->id, 'level' => 'odp', 'parent' => $this->switchPort->id]))->assertOk()->json();
        $this->assertTrue($odp['disabled']);

        $this->actingAs($fop)->getJson(route('fop-tasks.infrastructure-scope-options', ['pop_id' => $this->cabang->id, 'level' => 'device', 'parent' => $this->miniLain->id]))
            ->assertStatus(422);
    }

    #[Test]
    public function opsi_slot_fop_memuat_slot_milik_olt_terpilih(): void
    {
        $fop = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        $this->giveAllPopScope($fop);
        $slotKedua = app(InfrastructurePositionService::class)->createSlot($this->oltA, 2);

        $this->actingAs($fop)->getJson(route('fop-tasks.infrastructure-scope-options', [
            'pop_id' => $this->cabang->id,
            'level' => 'slot',
            'parent' => $this->oltA->id,
        ]))->assertOk()
            ->assertJsonPath('options.0.id', $this->slotA1->id)
            ->assertJsonPath('options.0.label', 'Slot 1')
            ->assertJsonPath('options.1.id', $slotKedua->id)
            ->assertJsonPath('options.1.label', 'Slot 2')
            ->assertJsonCount(2, 'options');
    }

    #[Test]
    public function opsi_pon_dan_odp_fop_mengikuti_slot_dan_pon_terpilih(): void
    {
        $fop = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        $this->giveAllPopScope($fop);
        $ponKedua = app(InfrastructurePositionService::class)->createPort($this->slotA1, 2);
        $odpKedua = $this->node('odp', $this->miniA1, 'ODP-B');
        $odpKedua->update(['parent_id' => $this->ponA11->id]);

        $pons = $this->actingAs($fop)->getJson(route('fop-tasks.infrastructure-scope-options', [
            'pop_id' => $this->cabang->id,
            'level' => 'port',
            'parent' => $this->slotA1->id,
        ]))->assertOk()->json('options');

        $this->assertSame([$this->ponA11->id, $ponKedua->id], array_column($pons, 'id'));
        $this->assertSame(['PON 1', 'PON 2'], array_column($pons, 'label'));

        $odps = $this->actingAs($fop)->getJson(route('fop-tasks.infrastructure-scope-options', [
            'pop_id' => $this->cabang->id,
            'level' => 'odp',
            'parent' => $pons[0]['id'],
        ]))->assertOk()->json('options');

        $this->assertSame([$this->odpA->id, $odpKedua->id], array_column($odps, 'id'));
        $this->assertSame(['ODP-A', 'ODP-B'], array_column($odps, 'label'));
    }

    #[Test]
    public function modal_task_fop_menyediakan_dropdown_akar_scope_dari_server(): void
    {
        $this->withoutVite();
        $fop = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        $this->giveAllPopScope($fop);

        $this->actingAs($fop)->get(route('fop-tasks.index'))
            ->assertOk()
            ->assertSee('data-scope-options-url="'.route('fop-tasks.infrastructure-scope-options').'"', false)
            ->assertSee('Lokasi kerja (akar scope)');
    }

    #[Test]
    public function halaman_laporan_memakai_dropdown_bertingkat_dari_server(): void
    {
        $this->withoutVite();
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);

        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $task))
            ->assertOk()
            ->assertSee('data-location-options-url="'.route('tasks.infrastructure.location-options', $task).'"', false)
            ->assertSee('Dikosongkan')
            ->assertSee('C1-M1');
    }

    #[Test]
    public function assets_at_counts_200_serials_with_bounded_queries_and_never_loads_outside_serials(): void
    {
        $category = ItemCategory::firstOrCreate(['code' => 'olt_module'], ['name' => 'OLT', 'default_unit' => 'pcs']);
        $item = Item::create(['code' => 'PERF-ASSET', 'name' => 'Aset', 'unit' => 'pcs', 'item_category_id' => $category->id,
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset']);
        $direct = InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'DIRECT', 'status' => 'deployed', 'current_mini_pop_id' => $this->miniA1->id]);
        $rows = [];
        for ($i = 0; $i < 200; $i++) {
            $rows[] = ['item_id' => $item->id, 'serial_number' => 'INSIDE-'.$i, 'status' => 'deployed', 'current_infrastructure_slot_id' => $this->slotA1->id, 'current_mini_pop_id' => null];
            $rows[] = ['item_id' => $item->id, 'serial_number' => 'OUTSIDE-'.$i, 'status' => 'deployed', 'current_mini_pop_id' => $this->miniLain->id, 'current_infrastructure_slot_id' => null];
        }
        InventorySerial::insert($rows);
        $fopTask = FopTask::where('task_id', $this->task()->id)->sole();
        $loaded = [];
        InventorySerial::retrieved(function (InventorySerial $serial) use (&$loaded): void {
            $loaded[] = $serial->serial_number;
        });

        DB::enableQueryLog();
        $result = app(InfrastructureLocationOptionsService::class)
            ->assetsAt($fopTask, $this->technician, 'mini_pop', $this->miniA1->id);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame([$direct->id], array_column($result['direct'], 'id'));
        $this->assertSame(200, $result['descendant_count']);
        $this->assertSame([], array_values(array_filter($loaded, fn (string $sn): bool => str_starts_with($sn, 'OUTSIDE-'))));
        $this->assertLessThanOrEqual(40, $queries, 'Asset query count must not grow per deployed serial; actual: '.$queries);
    }

    #[Test]
    public function assets_at_mengikuti_induk_pon_dan_kembali_ke_anchor_setelah_dilepas(): void
    {
        $category = ItemCategory::firstOrCreate(['code' => 'olt_module'], ['name' => 'OLT', 'default_unit' => 'pcs']);
        $item = Item::create(['code' => 'PON-ASSET', 'name' => 'Aset', 'unit' => 'pcs', 'item_category_id' => $category->id,
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset']);
        InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'ON-ODP', 'status' => 'deployed', 'current_network_node_id' => $this->odpA->id]);
        $fopTask = FopTask::where('task_id', $this->task()->id)->sole();
        $service = app(InfrastructureLocationOptionsService::class);
        $this->assertSame(1, $service->assetsAt($fopTask, $this->technician, 'port', $this->ponA11->id)['descendant_count']);
        $this->assertSame(0, $service->assetsAt($fopTask, $this->technician, 'port', $this->switchPort->id)['descendant_count']);

        // Melepas ODP dari PON = induknya dikosongkan (anchor POP-nya yang berlaku lagi).
        $this->odpA->update(['parent_id' => null]);

        $this->assertSame(0, $service->assetsAt($fopTask, $this->technician, 'port', $this->ponA11->id)['descendant_count']);
        $this->assertSame(['ON-ODP'], array_column($service->assetsAt($fopTask, $this->technician, 'node', $this->odpA->id)['direct'], 'serial_number'));
    }

    #[Test]
    public function assets_at_keeps_slot_and_port_direct_and_uses_canonical_pon_instead_of_stale_anchor(): void
    {
        $category = ItemCategory::firstOrCreate(['code' => 'olt_module'], ['name' => 'OLT', 'default_unit' => 'pcs']);
        $item = Item::create(['code' => 'EXACT-ASSET', 'name' => 'Aset', 'unit' => 'pcs', 'item_category_id' => $category->id,
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset']);
        foreach (['AT-SLOT' => ['current_infrastructure_slot_id' => $this->slotA1->id],
            'AT-PON' => ['current_infrastructure_port_id' => $this->ponA11->id],
            'AT-ODP' => ['current_network_node_id' => $this->odpA->id]] as $sn => $position) {
            InventorySerial::create(['item_id' => $item->id, 'serial_number' => $sn, 'status' => 'deployed', ...$position]);
        }
        $this->odpA->update(['anchor_pop_id' => $this->miniA2->id]);
        $fopTask = FopTask::where('task_id', $this->task()->id)->sole();
        $service = app(InfrastructureLocationOptionsService::class);

        $slot = $service->assetsAt($fopTask, $this->technician, 'slot', $this->slotA1->id);
        $this->assertSame(['AT-SLOT'], array_column($slot['direct'], 'serial_number'));
        $this->assertSame(2, $slot['descendant_count']);
        $port = $service->assetsAt($fopTask, $this->technician, 'port', $this->ponA11->id);
        $this->assertSame(['AT-PON'], array_column($port['direct'], 'serial_number'));
        $this->assertSame(1, $port['descendant_count']);
        $this->assertSame(3, $service->assetsAt($fopTask, $this->technician, 'mini_pop', $this->miniA1->id)['descendant_count']);
        $this->assertSame(0, $service->assetsAt($fopTask, $this->technician, 'mini_pop', $this->miniA2->id)['descendant_count']);

        // Melepas ODP dari PON = induknya dikosongkan (anchor POP-nya yang berlaku lagi).
        $this->odpA->update(['parent_id' => null]);

        $this->assertSame(2, $service->assetsAt($fopTask, $this->technician, 'mini_pop', $this->miniA1->id)['descendant_count']);
        $this->assertSame(1, $service->assetsAt($fopTask, $this->technician, 'mini_pop', $this->miniA2->id)['descendant_count']);
    }
}
