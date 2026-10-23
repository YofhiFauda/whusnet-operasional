<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\InfrastructureLocationKind;
use App\Enums\InfrastructurePortKind;
use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskInfrastructureAction;
use App\Models\User;
use App\Models\UserRoleScopeTarget;
use App\Services\FopTaskInfrastructureScopeService;
use App\Services\InfrastructureConnectionService;
use App\Services\InfrastructureHierarchyService;
use App\Services\InfrastructureLocationOptionsService;
use App\Services\InfrastructureLocationResolver;
use App\Services\InfrastructurePositionService;
use App\Services\InventoryService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lokasi kerja bertingkat per baris laporan O-REQ/INFR-REQ (§10.1–10.4,
 * Task 8). Dikosongkan (`none`) = null eksplisit dan seluruh anak null;
 * Belum dipilih (kosong) BUKAN izin memasang di induk. Semua lokasi dicek
 * server: izin aktor ∩ Cabang task ∩ subtree akar scope.
 */
class TaskInfrastructureHierarchicalLocationTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $miniA1;

    private Pop $miniA2;

    private NetworkNode $oltA;

    private NetworkNode $oltB;

    private NetworkNode $switchB;

    private InfrastructureSlot $slotA1;

    private InfrastructureSlot $slotA2;

    private InfrastructurePort $ponA11;

    private InfrastructurePort $switchPort;

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
        $this->seed(ItemCategorySeeder::class);

        $this->cabang = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang C1']);
        $this->miniA1 = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id, 'name' => 'C1-M1']);
        $this->miniA2 = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id, 'name' => 'C1-M2']);

        $teknisi = Role::where('code', 'teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisi->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisi->id, 'scope_type' => ScopeType::ALL_POP]);

        $positions = app(InfrastructurePositionService::class);
        $this->oltA = $this->node('olt_chassis', $this->miniA1, 'OLT-A');
        $this->oltB = $this->node('olt_chassis', $this->miniA1, 'OLT-B');
        $this->switchB = $this->node('switch_mini_pop', $this->miniA1, 'SW-B');
        $this->slotA1 = $positions->createSlot($this->oltA, 1);
        $this->slotA2 = $positions->createSlot($this->oltA, 2);
        $this->ponA11 = $positions->createPort($this->slotA1, 1, InfrastructurePortKind::PON);
        $this->switchPort = $positions->createPort($positions->ensureLogicalSlot($this->switchB), 4, InfrastructurePortKind::SWITCH);
        $this->odpA = $this->node('odp', $this->miniA1, 'ODP-A');
        app(InfrastructureConnectionService::class)->connect($this->ponA11, $this->technician, targetNode: $this->odpA, verified: true);
    }

    private function node(string $typeCode, Pop $anchor, string $code): NetworkNode
    {
        return NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', $typeCode)->firstOrFail()->id,
            'anchor_pop_id' => $anchor->id, 'code' => $code, 'name' => $code,
        ]);
    }

    /**
     * @param  array<string, int|null>  $scope  input dropdown akar scope Task FOP
     */
    private function task(array $scope = [], TaskType $type = TaskType::INFR): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-HIER-'.uniqid(), 'pop_id' => $this->cabang->id, 'task_type' => $type->value,
            'title' => 'Uji lokasi', 'status' => TaskStatus::IN_PROGRESS->value, 'started_at' => now(),
            'created_by' => $this->technician->id, 'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-HIER-'.uniqid(), 'task_date' => now(), 'category' => $type->value, 'tugas' => 'Uji',
            'pop_id' => $this->cabang->id, 'task_id' => $task->id, 'issue' => 'Uji',
            'status' => TaskStatus::DRAFT->value, 'priority' => FopTaskPriority::MEDIUM->value,
        ]);
        $scopes = app(FopTaskInfrastructureScopeService::class);
        $scopes->applyScope($fopTask, $scopes->resolveRequestedRoot($this->cabang, $scope), User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]));

        return $task;
    }

    private function item(string $code, InfrastructureLocationKind $kind, string $category = 'olt_module', string $tracking = 'serialized'): Item
    {
        return Item::create([
            'code' => $code, 'name' => "Item {$code}", 'unit' => $tracking === 'roll' ? 'meter' : 'pcs',
            'item_category_id' => ItemCategory::firstOrCreate(['code' => $category], ['name' => $category, 'default_unit' => 'pcs'])->id,
            'tracking_type' => $tracking, 'ownership_mode' => 'infrastructure_asset',
            'infrastructure_location_kind' => $tracking === 'serialized' ? $kind->value : null,
            'meter_per_roll' => $tracking === 'roll' ? 100 : null,
        ]);
    }

    private function serial(Item $item, string $number, ?User $holder = null): InventorySerial
    {
        return InventorySerial::create([
            'item_id' => $item->id, 'serial_number' => $number, 'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => ($holder ?? $this->technician)->id, 'issued_from_pop_id' => $this->cabang->id,
        ]);
    }

    private function roll(): InventoryRoll
    {
        return InventoryRoll::create([
            'item_id' => $this->item('KBL-'.uniqid(), InfrastructureLocationKind::NODE, 'kabel_feeder_uji', 'roll')->id,
            'roll_code' => 'ROLL-'.uniqid(), 'status' => RollStatus::ISSUED, 'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabang->id, 'length_total' => 100, 'length_remaining' => 100, 'unit_price_snapshot' => 1200,
        ]);
    }

    /**
     * @param  array<string, mixed>  $location
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function row(string $type, array $location, array $fields = []): array
    {
        return ['action_key' => (string) Str::uuid(), 'action_type' => $type, ...$location, ...$fields];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function submit(Task $task, array $rows): TestResponse
    {
        return $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Laporan uji lokasi.', 'infra_actions' => $rows,
        ]);
    }

    private function assertRejected(TestResponse $response, Task $task): void
    {
        $response->assertRedirect();
        $session = $response->baseResponse->getSession();
        $this->assertTrue(
            $session->has('errors') || $session->has('error'),
            'Laporan harus ditolak dengan pesan error.'
        );
        $this->assertSame(0, TaskInfrastructureAction::count());
        $this->assertSame(0, InventoryTransaction::count());
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->refresh()->status);
    }

    /**
     * @return array<string, int|string>
     */
    private function miniPath(): array
    {
        return ['loc_pop' => $this->cabang->id, 'loc_mini_pop' => $this->miniA1->id];
    }

    #[Test]
    public function mini_pop_dengan_olt_dikosongkan_memasang_aset_langsung_di_mini_pop(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $serial = $this->serial($this->item('RACK', InfrastructureLocationKind::MINI_POP), 'SN-123');

        $this->submit($task, [$this->row('install', [...$this->miniPath(), 'loc_device' => 'none'], ['serial_id' => $serial->id])])
            ->assertSessionHasNoErrors()->assertSessionMissing('error');

        $serial->refresh();
        $this->assertSame(SerialStatus::DEPLOYED, $serial->status);
        $this->assertSame($this->miniA1->id, $serial->current_mini_pop_id);
        $action = TaskInfrastructureAction::sole();
        $this->assertSame($this->miniA1->id, $action->work_pop_id);
        $this->assertNull($action->network_node_id);
        $this->assertSame('mini_pop:'.$this->miniA1->id, $action->work_location_snapshot['key']);
        $this->assertSame('mini_pop:'.$this->miniA1->id, $action->scope_snapshot['key']);
    }

    #[Test]
    public function aset_berserial_bisa_dipasang_dan_dilacak_langsung_di_pop_cabang(): void
    {
        $task = $this->task();
        $serial = $this->serial($this->item('RACK-CABANG', InfrastructureLocationKind::POP), 'SN-RACK-CABANG');

        $this->submit($task, [$this->row('install', ['loc_pop' => $this->cabang->id, 'loc_mini_pop' => 'none'], ['serial_id' => $serial->id])])
            ->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame($this->cabang->id, $serial->fresh()->current_infrastructure_pop_id);
        $this->assertNull($serial->fresh()->current_pop_id);
        $this->assertSame('pop:'.$this->cabang->id, app(InventoryService::class)->currentLocationOf($serial->fresh())->key());
        $this->assertSame($this->cabang->id, InventoryTransaction::sole()->to_infrastructure_pop_id);
        $fopTask = FopTask::where('task_id', $task->id)->sole();
        $assets = app(InfrastructureLocationOptionsService::class)
            ->assetsAt($fopTask, $this->technician, 'pop', $this->cabang->id);
        $this->assertSame('SN-RACK-CABANG', $assets['direct'][0]['serial_number']);
        $projection = app(InfrastructureHierarchyService::class)->build($this->technician);
        $this->assertSame('SN-RACK-CABANG', $projection['items']['pop:'.$this->cabang->id]['direct_assets'][0]['reference']);

        app(InventoryService::class)->retrieveSerialFromLocation(
            $serial, $fopTask, $this->technician,
            expectedLocation: app(InfrastructureLocationResolver::class)->forPop($this->cabang)
        );
        $this->assertNull($serial->fresh()->current_infrastructure_pop_id);
        $this->assertSame($this->cabang->id, InventoryTransaction::where('type', InventoryTransactionType::RETRIEVE_INFRA->value)->sole()->from_infrastructure_pop_id);
    }

    #[Test]
    public function aset_pop_cabang_tidak_boleh_dipasang_di_pop_pusat(): void
    {
        $pusat = Pop::factory()->create(['type' => 'pusat']);
        $item = $this->item('RACK-PUSAT-DITOLAK', InfrastructureLocationKind::POP);

        $this->expectException(\InvalidArgumentException::class);
        app(InventoryService::class)->assertSerialItemFitsLocation(
            $item,
            app(InfrastructureLocationResolver::class)->forPop($pusat)
        );
    }

    #[Test]
    public function olt_dengan_slot_dikosongkan_memasang_aset_di_olt(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $item = $this->item('OLT-ASET', InfrastructureLocationKind::NODE);
        $item->update(['infrastructure_node_type_id' => $this->oltA->node_type_id]);
        $serial = $this->serial($item, 'SN-OLT');

        $this->submit($task, [$this->row('install', [...$this->miniPath(), 'loc_device' => $this->oltA->id, 'loc_slot' => 'none'], ['serial_id' => $serial->id])])
            ->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame($this->oltA->id, $serial->fresh()->current_network_node_id);
        $this->assertSame($this->oltA->id, TaskInfrastructureAction::sole()->network_node_id);
    }

    #[Test]
    public function slot_dengan_pon_dikosongkan_memasang_kartu_di_slot(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->oltA->id]);
        $serial = $this->serial($this->item('KARTU', InfrastructureLocationKind::SLOT), 'SN-KARTU');

        $this->submit($task, [$this->row('install', [...$this->miniPath(), 'loc_device' => $this->oltA->id, 'loc_slot' => $this->slotA1->id, 'loc_port' => 'none'], ['serial_id' => $serial->id])])
            ->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame($this->slotA1->id, $serial->fresh()->current_infrastructure_slot_id);
        $this->assertSame($this->slotA1->id, TaskInfrastructureAction::sole()->work_infrastructure_slot_id);
    }

    #[Test]
    public function kabel_di_pop_saja_mengurangi_sisa_roll_dan_mencatat_koordinat(): void
    {
        $task = $this->task();
        $roll = $this->roll();

        $this->submit($task, [$this->row('install', ['loc_pop' => $this->cabang->id, 'loc_mini_pop' => 'none'], [
            'roll_id' => $roll->id, 'meters' => 35, 'work_latitude' => -7.8712345, 'work_longitude' => 111.4612345,
        ])])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertEquals(65, $roll->refresh()->length_remaining);
        $action = TaskInfrastructureAction::sole();
        $this->assertSame($this->cabang->id, $action->work_pop_id);
        $this->assertEquals(-7.8712345, (float) $action->work_latitude);
        $tx = InventoryTransaction::sole();
        $this->assertSame($this->cabang->id, $tx->to_pop_id);
        $this->assertNull($tx->to_mini_pop_id);
    }

    #[Test]
    public function kabel_mencatat_ujung_sambungan_terpisah_dari_lokasi_kerja(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $roll = $this->roll();

        $this->submit($task, [$this->row('install', [...$this->miniPath(), 'loc_device' => 'none'], [
            'roll_id' => $roll->id, 'meters' => 10,
            'end_from' => 'port:'.$this->ponA11->id, 'end_to' => 'node:'.$this->odpA->id,
        ])])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $action = TaskInfrastructureAction::sole();
        $this->assertSame($this->miniA1->id, $action->work_pop_id);
        $this->assertSame($this->ponA11->id, $action->end_from_infrastructure_port_id);
        $this->assertSame($this->odpA->id, $action->end_to_network_node_id);
    }

    #[Test]
    public function ujung_sambungan_di_luar_scope_ditolak(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $outside = $this->node('odp', $this->miniA2, 'ODP-M2');

        $this->assertRejected($this->submit($task, [$this->row('install', [...$this->miniPath(), 'loc_device' => 'none'], [
            'roll_id' => $this->roll()->id, 'meters' => 10, 'end_to' => 'node:'.$outside->id,
        ])]), $task);
    }

    #[Test]
    public function akar_scope_null_ditolak(): void
    {
        $task = $this->task();

        $this->assertRejected($this->submit($task, [$this->row('install', ['loc_pop' => 'none'], [
            'roll_id' => $this->roll()->id, 'meters' => 10,
        ])]), $task);
    }

    #[Test]
    public function akar_scope_mini_pop_tidak_boleh_dikosongkan(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);

        $this->assertRejected($this->submit($task, [$this->row('install', ['loc_pop' => $this->cabang->id, 'loc_mini_pop' => 'none'], [
            'roll_id' => $this->roll()->id, 'meters' => 10,
        ])]), $task);
    }

    #[Test]
    public function anak_yang_dipaksa_setelah_dikosongkan_ditolak(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $serial = $this->serial($this->item('KARTU-P', InfrastructureLocationKind::SLOT), 'SN-PAKSA');

        $this->assertRejected($this->submit($task, [$this->row('install', [...$this->miniPath(), 'loc_device' => 'none', 'loc_slot' => $this->slotA1->id], ['serial_id' => $serial->id])]), $task);
        $this->assertSame(SerialStatus::ISSUED, $serial->fresh()->status);
    }

    #[Test]
    public function belum_dipilih_bukan_izin_memasang_di_induk(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $serial = $this->serial($this->item('RACK-B', InfrastructureLocationKind::MINI_POP), 'SN-BELUM');

        $this->assertRejected($this->submit($task, [$this->row('install', $this->miniPath(), ['serial_id' => $serial->id])]), $task);
    }

    #[Test]
    public function odp_di_bawah_switch_ditolak(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);

        $this->assertRejected($this->submit($task, [$this->row('inspect', [...$this->miniPath(),
            'loc_device' => $this->switchB->id, 'loc_slot' => $this->switchPort->infrastructure_slot_id,
            'loc_port' => $this->switchPort->id, 'loc_odp' => $this->odpA->id,
        ])]), $task);
    }

    #[Test]
    public function jalur_switch_berhenti_di_port(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);

        $this->submit($task, [$this->row('inspect', [...$this->miniPath(),
            'loc_device' => $this->switchB->id, 'loc_slot' => $this->switchPort->infrastructure_slot_id, 'loc_port' => $this->switchPort->id,
        ], ['result_note' => 'Link normal'])])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame($this->switchPort->id, TaskInfrastructureAction::sole()->work_infrastructure_port_id);
    }

    #[Test]
    public function olt_saudara_dalam_task_olt_a_ditolak(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id, 'scope_device_id' => $this->oltA->id]);

        $this->assertRejected($this->submit($task, [$this->row('inspect', [...$this->miniPath(), 'loc_device' => $this->oltB->id, 'loc_slot' => 'none'])]), $task);
        $this->assertRejected($this->submit($task, [$this->row('inspect', [...$this->miniPath(), 'loc_device' => 'none'])]), $task);
    }

    #[Test]
    public function id_global_sah_tetapi_bukan_anak_induk_terpilih_ditolak(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);

        $this->assertRejected($this->submit($task, [$this->row('inspect', [...$this->miniPath(),
            'loc_device' => $this->oltB->id, 'loc_slot' => $this->slotA1->id, 'loc_port' => 'none',
        ])]), $task);
    }

    #[Test]
    public function dua_baris_sah_pada_turunan_berbeda_tercatat_masing_masing(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $rack = $this->serial($this->item('RACK-2', InfrastructureLocationKind::MINI_POP), 'SN-RACK-2');
        $card = $this->serial($this->item('KARTU-2', InfrastructureLocationKind::SLOT), 'SN-KARTU-2');

        $this->submit($task, [
            $this->row('install', [...$this->miniPath(), 'loc_device' => 'none'], ['serial_id' => $rack->id]),
            $this->row('install', [...$this->miniPath(), 'loc_device' => $this->oltA->id, 'loc_slot' => $this->slotA2->id, 'loc_port' => 'none'], ['serial_id' => $card->id]),
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame($this->miniA1->id, $rack->fresh()->current_mini_pop_id);
        $this->assertSame($this->slotA2->id, $card->fresh()->current_infrastructure_slot_id);
        $this->assertSame(2, TaskInfrastructureAction::count());
    }

    #[Test]
    public function satu_baris_gagal_membatalkan_seluruh_laporan(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $rack = $this->serial($this->item('RACK-3', InfrastructureLocationKind::MINI_POP), 'SN-RACK-3');

        $this->assertRejected($this->submit($task, [
            $this->row('install', [...$this->miniPath(), 'loc_device' => 'none'], ['serial_id' => $rack->id]),
            $this->row('inspect', ['loc_pop' => $this->cabang->id, 'loc_mini_pop' => $this->miniA2->id, 'loc_device' => 'none']),
        ]), $task);
        $this->assertSame(SerialStatus::ISSUED, $rack->fresh()->status);
    }

    #[Test]
    public function sn_lama_milik_slot_saudara_tidak_bisa_dicabut(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $card = $this->serial($this->item('KARTU-4', InfrastructureLocationKind::SLOT), 'SN-SLOT2');
        $fopTask = FopTask::where('task_id', $task->id)->sole();
        app(InventoryService::class)->deploySerialAt($card, app(InfrastructureLocationResolver::class)->forSlot($this->slotA2), $fopTask, [$this->technician], $this->technician);
        InventoryTransaction::query()->delete();

        $this->assertRejected($this->submit($task, [$this->row('retrieve', [...$this->miniPath(), 'loc_device' => $this->oltA->id, 'loc_slot' => $this->slotA1->id, 'loc_port' => 'none'], ['old_serial_id' => $card->id])]), $task);
        $this->assertSame($this->slotA2->id, $card->fresh()->current_infrastructure_slot_id);
    }

    #[Test]
    public function sn_baru_di_luar_custody_tim_ditolak(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $serial = $this->serial($this->item('RACK-5', InfrastructureLocationKind::MINI_POP), 'SN-ORANG-LAIN', User::factory()->create());

        $this->assertRejected($this->submit($task, [$this->row('install', [...$this->miniPath(), 'loc_device' => 'none'], ['serial_id' => $serial->id])]), $task);
    }

    #[Test]
    public function item_tidak_kompatibel_dengan_lokasi_ditolak(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $card = $this->serial($this->item('KARTU-6', InfrastructureLocationKind::SLOT), 'SN-KARTU-6');

        $this->assertRejected($this->submit($task, [$this->row('install', [...$this->miniPath(), 'loc_device' => 'none'], ['serial_id' => $card->id])]), $task);
        $this->assertSame(SerialStatus::ISSUED, $card->fresh()->status);
    }

    #[Test]
    public function kirim_ulang_kunci_sama_tidak_memotong_roll_lagi(): void
    {
        $task = $this->task();
        $roll = $this->roll();
        $row = $this->row('install', ['loc_pop' => $this->cabang->id, 'loc_mini_pop' => 'none'], ['roll_id' => $roll->id, 'meters' => 20]);

        $this->submit($task, [$row])->assertSessionHasNoErrors();
        $task->refresh()->update(['status' => TaskStatus::IN_PROGRESS->value]);
        $this->submit($task, [$row])->assertSessionHasNoErrors();

        $this->assertEquals(80, $roll->refresh()->length_remaining);
        $this->assertSame(1, InventoryTransaction::where('type', InventoryTransactionType::DEPLOY->value)->count());
    }

    #[Test]
    public function koreksi_material_ke_lokasi_di_luar_scope_ditolak(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $fopTask = FopTask::where('task_id', $task->id)->sole();
        $source = app(InventoryService::class)->consumeInfrastructureRoll(
            $this->roll(), 30, app(InfrastructureLocationResolver::class)->forPop($this->miniA1), [$this->technician], $fopTask, $this->technician
        );
        $outside = $this->node('odp', $this->miniA2, 'ODP-LUAR');

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Koreksi lokasi.',
            'infra_material_action' => 'correct',
            'infra_material_source_transaction_id' => $source->id,
            'infra_material_qty' => 30,
            'infra_material_reason' => 'Salah lokasi',
            'infra_target_network_node_id' => $outside->id,
        ])->assertSessionHasErrors('infra_target_network_node_id');

        $this->assertSame(0, InventoryTransaction::where('type', InventoryTransactionType::ADJUSTMENT->value)->count());
    }

    #[Test]
    public function oreq_inspeksi_tanpa_lokasi_tetap_sah(): void
    {
        $task = $this->task([], TaskType::OREQ);

        $this->submit($task, [$this->row('inspect', [], ['result_note' => 'Cek dokumen kantor'])])
            ->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertNull(TaskInfrastructureAction::sole()->work_location_snapshot);
    }

    #[Test]
    public function aktor_tanpa_scope_cabang_ditolak(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);
        $teknisi = Role::where('code', 'teknisi')->firstOrFail();
        $outsider = User::factory()->create(['role_id' => $teknisi->id]);
        $scope = $outsider->roleScopes()->create(['role_id' => $teknisi->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => Pop::factory()->create(['type' => 'cabang'])->id]);
        $task->teamMembers()->create(['user_id' => $outsider->id, 'role_in_task' => 'member']);

        $response = $this->actingAs($outsider)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Di luar scope.',
            'infra_actions' => [$this->row('inspect', [...$this->miniPath(), 'loc_device' => 'none'])],
        ]);

        $this->assertRejected($response, $task);
    }

    #[Test]
    public function location_parser_menolak_kode_ujung_tidak_dikenal(): void
    {
        $task = $this->task(['scope_mini_pop_id' => $this->miniA1->id]);

        $this->assertRejected($this->submit($task, [$this->row('install', [...$this->miniPath(), 'loc_device' => 'none'], [
            'roll_id' => $this->roll()->id, 'meters' => 1, 'end_from' => 'customer:1',
        ])]), $task);
    }
}
