<?php

namespace Tests\Feature;

use App\Enums\CustodyStatus;
use App\Enums\FopTaskPriority;
use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InfrastructureBalance;
use App\Models\InfrastructureSlot;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\NetworkPortAssignment;
use App\Models\OltSlot;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\UserRoleScopeTarget;
use App\Services\InfrastructureLegacyBackfillService;
use App\Services\InfrastructureMaterialService;
use App\Services\InventoryService;
use App\Services\TaskService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan O-REQ/INFR REQ — seksi "Pasang/Ganti Infrastruktur": teknisi
 * memasang SN/qty/roll aset infrastruktur netap di titik jaringan
 * (`NetworkNode`), atau mencabut SN lama dari titik itu balik ke custody.
 * Node wajib di cabang task DAN di scope POP aktor — penolakan harus muncul
 * sebagai error form (redirect back), bukan 403 mentah.
 */
class TaskInfrastructureDeployReportTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Pop $popLain;

    protected Customer $customer;

    protected User $technician;

    protected Role $teknisiRole;

    protected NetworkNode $node;

    protected ItemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);

        $this->pop = Pop::create(['code' => 'INF-DPL', 'pop_code' => 'IFD', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Infra Deploy', 'type' => 'cabang', 'status' => 'active']);
        $this->popLain = Pop::create(['code' => 'INF-LAIN', 'pop_code' => 'IFL', 'registration_prefix' => 'E', 'cid_prefix' => 'F', 'name' => 'Cabang Lain', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-INFRA-DPL-001',
            'full_name' => 'Infra Deploy Customer',
            'primary_phone' => '0812340099',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $this->teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $this->teknisiRole->id]);
        $this->technician->roleScopes()->create(['role_id' => $this->teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $this->node = NetworkNode::factory()->create(['anchor_pop_id' => $this->pop->id, 'name' => 'ODC Uji Laporan']);

        $this->category = ItemCategory::create(['code' => 'infra_report_test', 'name' => 'Infra Report Test', 'default_unit' => 'pcs']);
    }

    private function makeTask(TaskType $type, bool $withCustomer = true, bool $withAnchor = true): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-INFRA-'.uniqid(),
            'customer_id' => $withCustomer ? $this->customer->id : null,
            'pop_id' => $this->pop->id,
            'task_type' => $type->value,
            'title' => 'Pasang Infrastruktur',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        if ($withAnchor) {
            FopTask::create([
                'task_number' => 'TFOP-INFRA-'.uniqid(),
                'task_date' => now(),
                'category' => $type->value,
                'tugas' => 'Uji Pasang Infrastruktur',
                'pop_id' => $this->pop->id,
                'customer_id' => $withCustomer ? $this->customer->id : null,
                'task_id' => $task->id,
                'issue' => 'Uji pasang infrastruktur',
                'status' => TaskStatus::DRAFT->value,
                'priority' => FopTaskPriority::MEDIUM->value,
            ]);
        }

        return $task;
    }

    private function makeItem(string $code, string $trackingType, string $unit = 'pcs'): Item
    {
        return Item::create([
            'code' => $code,
            'name' => "Item {$code}",
            'unit' => $unit,
            'item_category_id' => $this->category->id,
            'tracking_type' => $trackingType,
            'ownership_mode' => 'infrastructure_asset',
        ]);
    }

    /**
     * Kabel tidak punya saldo terpasang (rancangan hierarki §16.4 #2) — lokasi
     * kerja aktif dibaca dari ledger: koreksi positif terakhir atau transaksi
     * asal.
     */
    private function cableWorkNodeId(InventoryTransaction $source): ?int
    {
        $positive = InventoryTransaction::where('source_inventory_transaction_id', $source->id)
            ->where('reason', 'infra_record_correction')->where('qty', '>', 0)->latest('id')->first();

        return $positive?->to_network_node_id ?? $source->to_network_node_id;
    }

    private function issuedSerial(Item $item, string $serialNumber): InventorySerial
    {
        return InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => $serialNumber,
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);
    }

    #[Test]
    public function form_laporan_menampilkan_barisan_aksi_dan_mencegah_item_duplikat_antar_seksi(): void
    {
        $task = $this->makeTask(TaskType::INFR, false);
        $item = $this->makeItem('DOUBLE-STOCK', 'quantity');

        $this->withoutVite();
        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $task))
            ->assertOk()->assertSee('Pilih tindakan')->assertSee('Satu tindakan = satu lokasi');

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Dua seksi untuk item sama.',
            'materials' => [['item_id' => $item->id, 'qty' => 1]],
            'infra_actions' => [[
                'action_key' => 'd83bd300-9bc1-4845-8b11-c03771579000',
                'action_type' => 'install', 'network_node_id' => $this->node->id,
                'item_id' => $item->id, 'qty' => 1,
            ]],
        ])->assertRedirect()->assertSessionHasErrors('infra_actions');

        $this->assertDatabaseCount('task_infrastructure_actions', 0);
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->refresh()->status);
    }

    #[Test]
    public function aksi_pada_node_mini_pop_dalam_cabang_task_diterima(): void
    {
        $miniPop = Pop::create([
            'code' => 'INF-MINI', 'pop_code' => 'IFM', 'registration_prefix' => 'M',
            'cid_prefix' => 'M', 'name' => 'Mini POP Infra', 'type' => 'mini_pop',
            'parent_id' => $this->pop->id, 'status' => 'active',
        ]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $miniPop->id]);
        $task = $this->makeTask(TaskType::INFR, false);

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Periksa node mini POP.',
            'infra_actions' => [[
                'action_key' => 'd83bd300-9bc1-4845-8b11-c03771579444',
                'action_type' => 'inspect', 'network_node_id' => $node->id,
                'result_note' => 'Kondisi normal.',
            ]],
        ])->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('task_infrastructure_actions', [
            'task_id' => $task->id, 'network_node_id' => $node->id, 'action_type' => 'inspect',
        ]);
    }

    #[Test]
    public function baris_kedua_tidak_valid_membatalkan_deploy_baris_pertama(): void
    {
        $task = $this->makeTask(TaskType::INFR, false);
        $serial = $this->issuedSerial($this->makeItem('ATOMIC-ACTION', 'serialized'), 'ATOMIC-01');

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Pasang lalu periksa.',
            'infra_actions' => [
                ['action_key' => 'd83bd300-9bc1-4845-8b11-c03771579555', 'action_type' => 'install',
                    'network_node_id' => $this->node->id, 'serial_id' => $serial->id],
                ['action_key' => 'd83bd300-9bc1-4845-8b11-c03771579666', 'action_type' => 'inspect',
                    'network_node_id' => $this->node->id, 'serial_id' => $serial->id],
            ],
        ])->assertRedirect()->assertSessionHasErrors('infra_actions');

        $this->assertDatabaseCount('task_infrastructure_actions', 0);
        $this->assertSame(0, InventoryTransaction::where('type', InventoryTransactionType::DEPLOY)->count());
        $this->assertSame(SerialStatus::ISSUED, $serial->refresh()->status);
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->refresh()->status);
    }

    #[Test]
    public function kartu_olt_wajib_memilih_slot_pada_chassis_induk(): void
    {
        $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
        $cardType = NetworkNodeType::where('code', 'olt_card')->firstOrFail();
        $chassis = NetworkNode::factory()->create(['anchor_pop_id' => $this->pop->id, 'node_type_id' => $chassisType->id]);
        $card = NetworkNode::factory()->child($chassis)->create(['node_type_id' => $cardType->id]);
        $slot = OltSlot::create(['chassis_node_id' => $chassis->id, 'number' => 1]);
        app(InfrastructureLegacyBackfillService::class)->syncOltSlot($slot);
        $category = ItemCategory::where('code', 'olt_module')->firstOrFail();
        $item = $this->makeItem('OLT-CARD-REPORT', 'serialized');
        $item->update(['item_category_id' => $category->id, 'infrastructure_node_type_id' => $cardType->id]);
        $serial = $this->issuedSerial($item, 'OLT-CARD-001');
        $task = $this->makeTask(TaskType::INFR, false);
        $row = [
            'action_key' => 'd83bd300-9bc1-4845-8b11-c03771579999',
            'action_type' => 'install', 'network_node_id' => $card->id, 'serial_id' => $serial->id,
        ];

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Pasang kartu OLT.', 'infra_actions' => [$row],
        ])->assertRedirect()->assertSessionHasErrors('infra_actions');

        $this->assertSame(SerialStatus::ISSUED, $serial->refresh()->status);
        $this->assertNull($slot->refresh()->card_node_id);

        $qtyItem = $this->makeItem('OLT-CARD-QTY', 'quantity');
        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Coba pakai barang qty sebagai kartu.',
            'infra_actions' => [[
                'action_key' => 'd83bd300-9bc1-4845-8b11-c03771579998',
                'action_type' => 'install', 'network_node_id' => $card->id,
                'item_id' => $qtyItem->id, 'qty' => 1, 'slot_id' => $slot->id,
            ]],
        ])->assertRedirect()->assertSessionHasErrors('infra_actions');
        $this->assertNull($slot->refresh()->card_node_id);

        $row['slot_id'] = $slot->id;
        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Pasang kartu OLT.', 'infra_actions' => [$row],
        ])->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertSame($card->id, $slot->refresh()->card_node_id);
        $this->assertSame($card->id, $serial->refresh()->current_network_node_id);
        $canonical = InfrastructureSlot::where('legacy_olt_slot_id', $slot->id)->sole();
        $this->assertDatabaseHas('infrastructure_legacy_mappings', ['legacy_type' => 'olt_card_node', 'legacy_id' => $card->id, 'new_id' => $canonical->id]);
    }

    #[Test]
    public function sambung_dan_lepas_kabel_mencatat_serta_membebaskan_kedua_port(): void
    {
        $task = $this->makeTask(TaskType::INFR, false);
        $child = NetworkNode::factory()->child($this->node)->create();
        $sourcePort = NetworkNodePort::create(['network_node_id' => $this->node->id, 'number' => 1, 'is_used' => false]);
        $targetPort = NetworkNodePort::create(['network_node_id' => $child->id, 'number' => 1, 'is_used' => false]);
        $wrongPort = NetworkNodePort::create(['network_node_id' => $this->node->id, 'number' => 2, 'is_used' => false]);

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Port tujuan salah.',
            'infra_actions' => [[
                'action_key' => 'd83bd300-9bc1-4845-8b11-c03771579776',
                'action_type' => 'port_connect', 'network_node_id' => $this->node->id,
                'port_id' => $sourcePort->id, 'target_node_id' => $child->id,
                'target_port_id' => $wrongPort->id,
            ]],
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('network_port_assignments', 0);
        $this->assertFalse($sourcePort->refresh()->is_used);

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Sambung kabel distribusi.',
            'infra_actions' => [[
                'action_key' => 'd83bd300-9bc1-4845-8b11-c03771579777',
                'action_type' => 'port_connect', 'network_node_id' => $this->node->id,
                'port_id' => $sourcePort->id, 'target_node_id' => $child->id,
                'target_port_id' => $targetPort->id,
            ]],
        ])->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('network_port_assignments', [
            'network_node_port_id' => $sourcePort->id, 'target_port_id' => $targetPort->id,
            'target_node_id' => $child->id, 'active_key' => 1,
        ]);
        $this->assertTrue($sourcePort->refresh()->is_used);
        $this->assertTrue($targetPort->refresh()->is_used);

        $this->withoutVite();
        $this->actingAs($this->technician)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Ringkasan pekerjaan')
            ->assertSee('Penyambungan port')
            ->assertSee('Port asal: 1')
            ->assertSee($child->name)
            ->assertDontSee('node #');

        $task->refresh()->update(['status' => TaskStatus::IN_PROGRESS->value]);
        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Lepas kabel distribusi.',
            'infra_actions' => [[
                'action_key' => 'd83bd300-9bc1-4845-8b11-c03771579888',
                'action_type' => 'port_release', 'network_node_id' => $this->node->id,
                'port_id' => $sourcePort->id,
            ]],
        ])->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertNull(NetworkPortAssignment::firstOrFail()->active_key);
        $this->assertFalse($sourcePort->refresh()->is_used);
        $this->assertFalse($targetPort->refresh()->is_used);
    }

    #[Test]
    public function beberapa_aksi_berbeda_node_tercatat_dan_kirim_ulang_tidak_memotong_stok_lagi(): void
    {
        $task = $this->makeTask(TaskType::INFR, false);
        $other = NetworkNode::factory()->create(['anchor_pop_id' => $this->pop->id]);
        $item = $this->makeItem('MULTI-ACTION', 'serialized');
        $first = $this->issuedSerial($item, 'MULTI-001');
        $second = $this->issuedSerial($item, 'MULTI-002');
        $actions = [
            ['action_key' => 'd83bd300-9bc1-4845-8b11-c03771579111', 'action_type' => 'install', 'network_node_id' => $this->node->id, 'serial_id' => $first->id],
            ['action_key' => 'd83bd300-9bc1-4845-8b11-c03771579222', 'action_type' => 'install', 'network_node_id' => $other->id, 'serial_id' => $second->id],
        ];

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Dua node diperbaiki.', 'infra_actions' => $actions,
        ])->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->withoutVite();
        $this->actingAs($this->technician)->get(route('tasks.show', $task))
            ->assertOk()->assertSee('Rincian tindakan')->assertSee('Pemasangan barang jaringan')->assertSee('MULTI-001');

        $this->assertDatabaseCount('task_infrastructure_actions', 2);
        $this->assertDatabaseCount('task_infrastructure_action_transactions', 2);
        $this->assertSame(2, InventoryTransaction::where('type', InventoryTransactionType::DEPLOY)->count());

        $task->refresh()->update(['status' => TaskStatus::IN_PROGRESS->value]);
        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Bukti diperbaiki.', 'infra_actions' => $actions,
        ])->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('task_infrastructure_actions', 2);
        $this->assertSame(2, InventoryTransaction::where('type', InventoryTransactionType::DEPLOY)->count());
    }

    #[Test]
    public function kunci_aksi_lama_dengan_mutasi_berbeda_ditolak(): void
    {
        $task = $this->makeTask(TaskType::INFR, false);
        $serial = $this->issuedSerial($this->makeItem('ACTION-CONFLICT', 'serialized'), 'CONFLICT-01');
        $key = 'd83bd300-9bc1-4845-8b11-c03771579333';
        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Pasang.', 'infra_actions' => [[
                'action_key' => $key, 'action_type' => 'install', 'network_node_id' => $this->node->id, 'serial_id' => $serial->id,
            ]],
        ])->assertRedirect()->assertSessionHas('success');
        $task->refresh()->update(['status' => TaskStatus::IN_PROGRESS->value]);

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Ubah menjadi inspeksi.', 'infra_actions' => [[
                'action_key' => $key, 'action_type' => 'inspect', 'network_node_id' => $this->node->id,
            ]],
        ])->assertStatus(302)->assertSessionHasErrors('infra_actions');

        $this->assertSame(1, InventoryTransaction::where('type', InventoryTransactionType::DEPLOY)->count());
    }

    #[Test]
    public function infr_req_pasang_sn_infrastruktur_ke_node_jadi_deployed(): void
    {
        $task = $this->makeTask(TaskType::INFR);
        $serial = $this->issuedSerial($this->makeItem('INF-SPLITTER', 'serialized'), 'SN-INF-001');

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Pasang splitter baru di ODC.',
            'infra_target_network_node_id' => $this->node->id,
            'infra_selected_serial_id' => $serial->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        $serial->refresh();
        $this->assertSame(SerialStatus::DEPLOYED, $serial->status);
        $this->assertSame($this->node->id, (int) $serial->current_network_node_id);
        $this->assertSame(TaskStatus::SELESAI, $task->refresh()->status);
    }

    #[Test]
    public function node_di_cabang_lain_ditolak_sebagai_error_form(): void
    {
        $task = $this->makeTask(TaskType::INFR);
        $serial = $this->issuedSerial($this->makeItem('INF-SPLITTER-X', 'serialized'), 'SN-INF-002');
        $nodeLain = NetworkNode::factory()->create(['anchor_pop_id' => $this->popLain->id]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Coba pasang di node cabang lain.',
            'infra_target_network_node_id' => $nodeLain->id,
            'infra_selected_serial_id' => $serial->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('infra_target_network_node_id');
        $this->assertSame(SerialStatus::ISSUED, $serial->refresh()->status);
        $this->assertSame(0, InventoryTransaction::count());
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->refresh()->status);
    }

    #[Test]
    public function node_di_luar_scope_pop_aktor_ditolak_sebagai_error_form_bukan_403(): void
    {
        // Teknisi scope-nya cuma Cabang Lain, tapi tetap anggota tim task di
        // Cabang Infra Deploy — node di cabang task lolos cek "cabang task",
        // harus tetap ditolak lewat assertNetworkNodeInScope().
        $technician = User::factory()->create(['role_id' => $this->teknisiRole->id]);
        $scope = $technician->roleScopes()->create(['role_id' => $this->teknisiRole->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->popLain->id]);

        $task = $this->makeTask(TaskType::INFR);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'member']);
        $serial = $this->issuedSerial($this->makeItem('INF-SPLITTER-S', 'serialized'), 'SN-INF-003');

        $response = $this->actingAs($technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Coba pasang di luar scope.',
            'infra_target_network_node_id' => $this->node->id,
            'infra_selected_serial_id' => $serial->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('infra_target_network_node_id');
        $this->assertSame(SerialStatus::ISSUED, $serial->refresh()->status);
    }

    #[Test]
    public function field_infra_terisi_tanpa_node_wajib_pilih_node(): void
    {
        $task = $this->makeTask(TaskType::INFR);
        $serial = $this->issuedSerial($this->makeItem('INF-SPLITTER-N', 'serialized'), 'SN-INF-004');

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Lupa pilih node.',
            'infra_selected_serial_id' => $serial->id,
        ]);

        $response->assertSessionHasErrors('infra_target_network_node_id');
        $this->assertSame(SerialStatus::ISSUED, $serial->refresh()->status);
    }

    #[Test]
    public function oreq_pasang_barang_qty_menambah_infrastructure_balance(): void
    {
        $task = $this->makeTask(TaskType::OREQ);
        $closure = $this->makeItem('INF-CLOSURE', 'quantity');
        TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
            'item_id' => $closure->id,
            'qty_remaining' => 10,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Pasang closure di ODC.',
            'infra_target_network_node_id' => $this->node->id,
            'infra_quantity_item_id' => $closure->id,
            'infra_quantity_qty' => 3,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        $balance = InfrastructureBalance::where('item_id', $closure->id)->where('network_node_id', $this->node->id)->sole();
        $this->assertEquals(3, $balance->qty);
        $this->assertSame('', $balance->lot_no);
    }

    #[Test]
    public function oreq_potong_roll_infrastruktur_hanya_mengurangi_sisa_roll_tanpa_saldo_terpasang(): void
    {
        $task = $this->makeTask(TaskType::OREQ);
        $kabel = $this->makeItem('INF-FEEDER', 'roll', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ROLL-INF-001',
            'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
            'length_total' => 100,
            'length_remaining' => 100,
        ]);

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Tarik feeder ke ODC.',
            'infra_target_network_node_id' => $this->node->id,
            'infra_selected_roll_id' => $roll->id,
            'infra_roll_meters_used' => 40,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        $this->assertEquals(60, $roll->refresh()->length_remaining);
        $this->assertSame(0, InfrastructureBalance::count());
        $deploy = InventoryTransaction::where('roll_id', $roll->id)->sole();
        $this->assertEquals(40, $deploy->qty);
        $this->assertSame($this->node->id, $deploy->to_network_node_id);
    }

    #[Test]
    public function ganti_aset_cabut_sn_lama_dan_pasang_sn_baru_dalam_satu_laporan(): void
    {
        $task = $this->makeTask(TaskType::INFR);
        $splitter = $this->makeItem('INF-SPLITTER-G', 'serialized');

        $snLama = InventorySerial::create([
            'item_id' => $splitter->id,
            'serial_number' => 'SN-INF-LAMA',
            'status' => SerialStatus::DEPLOYED->value,
            'current_network_node_id' => $this->node->id,
        ]);
        $snBaru = $this->issuedSerial($splitter, 'SN-INF-BARU');

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Splitter rusak, diganti.',
            'infra_target_network_node_id' => $this->node->id,
            'infra_retrieve_old_serial_id' => $snLama->id,
            'infra_selected_serial_id' => $snBaru->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        $snLama->refresh();
        $this->assertSame(SerialStatus::ISSUED, $snLama->status);
        $this->assertSame($this->technician->id, (int) $snLama->current_technician_id);
        $this->assertNull($snLama->current_network_node_id);

        $snBaru->refresh();
        $this->assertSame(SerialStatus::DEPLOYED, $snBaru->status);
        $this->assertSame($this->node->id, (int) $snBaru->current_network_node_id);

        $this->assertSame(1, InventoryTransaction::where('type', InventoryTransactionType::RETRIEVE_INFRA->value)->count());
        $this->assertSame(1, InventoryTransaction::where('type', InventoryTransactionType::DEPLOY->value)->count());
    }

    #[Test]
    public function cabut_sn_dari_node_lain_dalam_cabang_yang_sama_ditolak_tanpa_mutasi(): void
    {
        $task = $this->makeTask(TaskType::INFR);
        $otherNode = NetworkNode::factory()->create(['anchor_pop_id' => $this->pop->id]);
        $serial = InventorySerial::create([
            'item_id' => $this->makeItem('INF-SN-NODE-GUARD', 'serialized')->id,
            'serial_number' => 'SN-NODE-GUARD', 'status' => SerialStatus::DEPLOYED,
            'current_network_node_id' => $otherNode->id,
        ]);

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Cabut perangkat di node salah.',
            'infra_target_network_node_id' => $this->node->id,
            'infra_retrieve_old_serial_id' => $serial->id,
        ])->assertSessionHasErrors('infra_retrieve_old_serial_id');

        $this->assertSame($otherNode->id, $serial->refresh()->current_network_node_id);
        $this->assertSame(SerialStatus::DEPLOYED, $serial->status);
        $this->assertSame(0, InventoryTransaction::where('type', InventoryTransactionType::RETRIEVE_INFRA->value)->count());
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->refresh()->status);
    }

    #[Test]
    public function infr_req_tanpa_pelanggan_tetap_memasang_aset(): void
    {
        // INFR REQ lazimnya pekerjaan jaringan murni, tanpa pelanggan —
        // deploy tidak boleh diam-diam dilewati cuma karena customer_id null.
        $task = $this->makeTask(TaskType::INFR, withCustomer: false);
        $serial = $this->issuedSerial($this->makeItem('INF-SPLITTER-NC', 'serialized'), 'SN-INF-NC');

        $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Pasang splitter tanpa pelanggan.',
            'infra_target_network_node_id' => $this->node->id,
            'infra_selected_serial_id' => $serial->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');
        $this->assertSame(SerialStatus::DEPLOYED, $serial->refresh()->status);
    }

    #[Test]
    public function infr_req_tanpa_pelanggan_dan_tanpa_anchor_membuat_satu_fop_task_sebelum_deploy(): void
    {
        $task = $this->makeTask(TaskType::INFR, withCustomer: false, withAnchor: false);
        $serial = $this->issuedSerial($this->makeItem('INF-NO-ANCHOR', 'serialized'), 'SN-INF-NO-ANCHOR');

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Pasang splitter tanpa pelanggan dan anchor.',
            'infra_target_network_node_id' => $this->node->id,
            'infra_selected_serial_id' => $serial->id,
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $anchor = FopTask::where('task_id', $task->id)->sole();
        $this->assertNull($anchor->customer_id);
        $this->assertSame($task->task_number, $anchor->task?->task_number);
        $this->assertSame($anchor->id, InventoryTransaction::where('type', InventoryTransactionType::DEPLOY->value)->sole()->fop_task_id);
        $this->assertSame(SerialStatus::DEPLOYED, $serial->refresh()->status);
    }

    #[Test]
    public function task_infr_baru_tanpa_pelanggan_langsung_memiliki_anchor(): void
    {
        $task = app(TaskService::class)->create([
            'task_type' => TaskType::INFR->value,
            'pop_id' => $this->pop->id,
            'title' => 'Periksa jalur feeder',
            'team_member_ids' => [$this->technician->id],
            'scheduled_at' => now(),
        ], $this->technician);

        $anchor = FopTask::where('task_id', $task->id)->sole();
        $this->assertNull($anchor->customer_id);
        $this->assertSame($task->pop_id, $anchor->pop_id);
        $this->assertSame($task->task_type, $anchor->category);
    }

    #[Test]
    public function task_hanya_boleh_memiliki_satu_fop_task_anchor_di_database(): void
    {
        $task = $this->makeTask(TaskType::INFR, withCustomer: false);

        $this->expectException(QueryException::class);

        FopTask::create([
            'task_number' => 'TFOP-DUP-'.uniqid(),
            'task_id' => $task->id,
            'task_date' => now(),
            'category' => TaskType::INFR,
            'tugas' => 'Duplikat anchor',
            'pop_id' => $task->pop_id,
            'status' => TaskStatus::DRAFT,
            'priority' => FopTaskPriority::MEDIUM,
        ]);
    }

    #[Test]
    public function koreksi_node_roll_memindahkan_saldo_tanpa_cabut_fisik_atau_memotong_roll_lagi(): void
    {
        $task = $this->makeTask(TaskType::INFR, withCustomer: false);
        $anchor = FopTask::where('task_id', $task->id)->sole();
        $item = $this->makeItem('INF-ROLL-CORRECT', 'roll', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-CORRECT-01',
            'status' => RollStatus::ISSUED, 'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id, 'length_total' => 100, 'length_remaining' => 100,
        ]);
        $original = app(InventoryService::class)->deployRoll($roll, 40, $this->node, [$this->technician], $anchor, $this->technician);
        $actualNode = NetworkNode::factory()->create(['anchor_pop_id' => $this->pop->id]);

        $service = app(InfrastructureMaterialService::class);
        $service->correct($original, $actualNode, 40, $anchor, $this->technician, 'Node laporan salah', 'fix-roll-node-01');
        $service->correct($original, $actualNode, 40, $anchor, $this->technician, 'Node laporan salah', 'fix-roll-node-01');

        $this->assertSame(0, InfrastructureBalance::count());
        $this->assertSame($actualNode->id, $this->cableWorkNodeId($original));
        $this->assertEquals(60, $roll->refresh()->length_remaining);
        $this->assertSame(0, InventoryTransaction::where('type', InventoryTransactionType::RETRIEVE_INFRA->value)->count());
        $this->assertSame(2, InventoryTransaction::where('type', InventoryTransactionType::ADJUSTMENT->value)->count());
    }

    #[Test]
    public function cabut_fisik_roll_mengurangi_saldo_tanpa_menambah_roll_asal_dan_menahan_hasil_cabut(): void
    {
        $task = $this->makeTask(TaskType::INFR, withCustomer: false);
        $anchor = FopTask::where('task_id', $task->id)->sole();
        $item = $this->makeItem('INF-ROLL-RETRIEVE', 'roll', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-RETRIEVE-01',
            'status' => RollStatus::ISSUED, 'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id, 'length_total' => 100, 'length_remaining' => 100,
        ]);
        $original = app(InventoryService::class)->deployRoll($roll, 40, $this->node, [$this->technician], $anchor, $this->technician);

        $service = app(InfrastructureMaterialService::class);
        $recovery = $service->retrieve($original, 10, $anchor, $this->technician, 'Cabut feeder rusak', 'retrieve-roll-01');
        $sameRecovery = $service->retrieve($original, 10, $anchor, $this->technician, 'Cabut feeder rusak', 'retrieve-roll-01');

        $this->assertSame($recovery->id, $sameRecovery->id);
        $this->assertSame(0, InfrastructureBalance::count());
        $this->assertEquals(60, $roll->refresh()->length_remaining);
        $this->assertSame('pending_inspection', $recovery->status);
        $this->assertSame(1, InventoryTransaction::where('type', InventoryTransactionType::RETRIEVE_INFRA->value)->count());

        $this->expectException(\InvalidArgumentException::class);
        $service->retrieve($original, 31, $anchor, $this->technician, 'Melebihi sisa', 'retrieve-roll-02');
    }

    #[Test]
    public function koreksi_berulang_dan_cabut_memakai_node_aktif_serta_menolak_kunci_aksi_yang_berubah(): void
    {
        $task = $this->makeTask(TaskType::INFR, withCustomer: false);
        $anchor = FopTask::where('task_id', $task->id)->sole();
        $item = $this->makeItem('INF-ROLL-REPEAT', 'roll', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-REPEAT-01',
            'status' => RollStatus::ISSUED, 'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id, 'length_total' => 100, 'length_remaining' => 100,
        ]);
        $original = app(InventoryService::class)->deployRoll($roll, 40, $this->node, [$this->technician], $anchor, $this->technician);
        $secondNode = NetworkNode::factory()->create(['anchor_pop_id' => $this->pop->id]);
        $thirdNode = NetworkNode::factory()->create(['anchor_pop_id' => $this->pop->id]);
        $service = app(InfrastructureMaterialService::class);

        $service->correct($original, $secondNode, 40, $anchor, $this->technician, 'Salah node pertama', 'repeat-correct-1');
        $service->retrieve($original, 10, $anchor, $this->technician, 'Cabut sepuluh meter', 'repeat-retrieve-1');
        $service->correct($original, $thirdNode, 30, $anchor, $this->technician, 'Salah node kedua', 'repeat-correct-2');

        $this->assertSame(0, InfrastructureBalance::count());
        $this->assertSame($thirdNode->id, $this->cableWorkNodeId($original));
        $this->assertEquals(30, InventoryTransaction::where('source_inventory_transaction_id', $original->id)
            ->where('reason', 'infra_record_correction')->where('qty', '>', 0)->latest('id')->value('qty'));
        $this->assertEquals(60, $roll->refresh()->length_remaining);

        try {
            $service->correct($original, $thirdNode, 25, $anchor, $this->technician, 'Payload berubah', 'repeat-correct-2');
            $this->fail('Kunci aksi yang berubah seharusnya ditolak.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('Kunci aksi', $exception->getMessage());
        }

        $this->assertSame($thirdNode->id, $this->cableWorkNodeId($original));
        $this->assertSame(4, InventoryTransaction::where('type', InventoryTransactionType::ADJUSTMENT->value)->count());
        $this->assertSame(1, InventoryTransaction::where('type', InventoryTransactionType::RETRIEVE_INFRA->value)->count());
    }

    #[Test]
    public function koreksi_jumlah_qty_memulihkan_custody_asal_tepat_sekali(): void
    {
        $task = $this->makeTask(TaskType::OREQ, withCustomer: false);
        $anchor = FopTask::where('task_id', $task->id)->sole();
        $item = $this->makeItem('INF-QTY-CORRECT', 'quantity');
        $custody = TechnicianCustody::create([
            'technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->pop->id,
            'item_id' => $item->id, 'lot_no' => 'LOT-CORRECT', 'qty_remaining' => 10,
            'status' => CustodyStatus::ISSUED, 'issued_at' => now(),
        ]);
        $deployed = app(InventoryService::class)->deployQuantity([$this->technician], $item, 4, $this->node, $anchor, $this->technician);
        $source = InventoryTransaction::findOrFail($deployed[0]['transaction_id']);
        $service = app(InfrastructureMaterialService::class);

        $service->correct($source, $this->node, 2, $anchor, $this->technician, 'Jumlah terpasang sebenarnya dua', 'correct-qty-once');
        $service->correct($source, $this->node, 2, $anchor, $this->technician, 'Jumlah terpasang sebenarnya dua', 'correct-qty-once');

        $this->assertEquals(8, $custody->refresh()->qty_remaining);
        $this->assertEquals(2, InfrastructureBalance::where('item_id', $item->id)->where('network_node_id', $this->node->id)->value('qty'));
        $this->assertSame(2, InventoryTransaction::where('type', InventoryTransactionType::ADJUSTMENT->value)->count());
        $this->assertSame(0, InventoryTransaction::where('type', InventoryTransactionType::RETRIEVE_INFRA->value)->count());
    }

    #[Test]
    public function koreksi_roll_salah_memulihkan_roll_lama_dan_mencatat_deploy_dari_roll_benar(): void
    {
        $task = $this->makeTask(TaskType::INFR, withCustomer: false);
        $anchor = FopTask::where('task_id', $task->id)->sole();
        $item = $this->makeItem('INF-ROLL-IDENTITY', 'roll', 'meter');
        $wrongRoll = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-WRONG-01', 'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->pop->id,
            'length_total' => 100, 'length_remaining' => 100,
        ]);
        $rightRoll = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-RIGHT-01', 'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->pop->id,
            'length_total' => 100, 'length_remaining' => 100,
        ]);
        $source = app(InventoryService::class)->deployRoll($wrongRoll, 40, $this->node, [$this->technician], $anchor, $this->technician);
        $actualNode = NetworkNode::factory()->create(['anchor_pop_id' => $this->pop->id]);
        $service = app(InfrastructureMaterialService::class);

        $replacement = $service->correctRoll($source, $rightRoll, $actualNode, 40, $anchor, $this->technician, 'Kode roll pada laporan salah', 'correct-roll-id-01');
        $same = $service->correctRoll($source, $rightRoll, $actualNode, 40, $anchor, $this->technician, 'Kode roll pada laporan salah', 'correct-roll-id-01');

        $this->assertSame($replacement->id, $same->id);
        $this->assertSame($source->id, $replacement->source_inventory_transaction_id);
        $this->assertEquals(100, $wrongRoll->refresh()->length_remaining);
        $this->assertEquals(60, $rightRoll->refresh()->length_remaining);
        $this->assertSame(0, InfrastructureBalance::count());
        $this->assertSame($actualNode->id, $replacement->to_network_node_id);
        $this->assertSame($rightRoll->id, $replacement->roll_id);
        $this->assertSame(0, InventoryTransaction::where('type', InventoryTransactionType::RETRIEVE_INFRA->value)->count());
    }

    #[Test]
    public function hasil_cabut_roll_layak_pakai_menjadi_potongan_baru_setelah_diperiksa_gudang(): void
    {
        $task = $this->makeTask(TaskType::INFR, withCustomer: false);
        $anchor = FopTask::where('task_id', $task->id)->sole();
        $item = $this->makeItem('INF-ROLL-INSPECT', 'roll', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-INSPECT-01',
            'status' => RollStatus::ISSUED, 'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id, 'length_total' => 100, 'length_remaining' => 100,
        ]);
        $original = app(InventoryService::class)->deployRoll($roll, 40, $this->node, [$this->technician], $anchor, $this->technician);
        $service = app(InfrastructureMaterialService::class);
        $recovery = $service->retrieve($original, 10, $anchor, $this->technician, 'Cabut kabel lama', 'inspect-retrieve-01');
        $inspector = User::factory()->create(['role_id' => Role::where('name', 'Owner')->firstOrFail()->id]);
        $inspector->roleScopes()->create(['role_id' => $inspector->role_id, 'scope_type' => ScopeType::ALL_POP]);

        $checked = $service->inspect($recovery, 'reusable', $inspector, 'Potongan kabel utuh');
        $same = $service->inspect($recovery, 'reusable', $inspector, 'Potongan kabel utuh');

        $this->assertSame($checked->id, $same->id);
        $this->assertSame('reusable', $checked->status);
        $this->assertNotNull($checked->recovered_roll_id);
        $this->assertEquals(10, InventoryRoll::findOrFail($checked->recovered_roll_id)->length_remaining);
        $this->assertEquals(60, $roll->refresh()->length_remaining);
        $this->assertSame(2, InventoryRoll::where('item_id', $item->id)->count());
    }

    #[Test]
    public function gudang_memeriksa_hasil_cabut_qty_dan_membuat_custody_baru_tanpa_mengisi_custody_asal(): void
    {
        $task = $this->makeTask(TaskType::OREQ, withCustomer: false);
        $anchor = FopTask::where('task_id', $task->id)->sole();
        $item = $this->makeItem('INF-QTY-INSPECT', 'quantity');
        $originalCustody = TechnicianCustody::create([
            'technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->pop->id,
            'item_id' => $item->id, 'lot_no' => 'LOT-INSPECT', 'qty_remaining' => 10,
            'status' => CustodyStatus::ISSUED, 'issued_at' => now(),
        ]);
        $deployed = app(InventoryService::class)->deployQuantity([$this->technician], $item, 4, $this->node, $anchor, $this->technician);
        $source = InventoryTransaction::findOrFail($deployed[0]['transaction_id']);
        $recovery = app(InfrastructureMaterialService::class)->retrieve($source, 2, $anchor, $this->technician, 'Cabut konektor', 'inspect-qty-01');
        $inspector = User::factory()->create(['role_id' => Role::where('name', 'Owner')->firstOrFail()->id]);
        $inspector->roleScopes()->create(['role_id' => $inspector->role_id, 'scope_type' => ScopeType::ALL_POP]);

        $this->withoutVite();
        $this->actingAs($inspector)->get(route('warehouse.infrastructure-recoveries.index'))->assertOk()->assertSee($task->task_number);
        $this->actingAs($inspector)->get(route('warehouse.infrastructure-recoveries.show', $recovery))->assertOk()->assertSee('Kondisi fisik');
        $this->actingAs($inspector)->post(route('warehouse.infrastructure-recoveries.store', $recovery), [
            'outcome' => 'reusable', 'verified_qty' => 1, 'inspection_note' => 'Jumlah berbeda',
        ])->assertSessionHas('error');
        $this->assertSame('pending_inspection', $recovery->refresh()->status);
        $this->assertEquals(6, $originalCustody->refresh()->qty_remaining);

        $this->actingAs($inspector)->post(route('warehouse.infrastructure-recoveries.store', $recovery), [
            'outcome' => 'reusable', 'verified_qty' => 2, 'inspection_note' => 'Konektor utuh',
        ])->assertSessionHas('success');

        $this->assertSame('reusable', $recovery->refresh()->status);
        $this->assertNotNull($recovery->recovered_custody_id);
        $this->assertEquals(2, $recovery->verified_qty);
        $this->assertEquals(2, TechnicianCustody::findOrFail($recovery->recovered_custody_id)->qty_remaining);
        $this->assertEquals(6, $originalCustody->refresh()->qty_remaining);
        $this->assertEquals(2, InfrastructureBalance::where('item_id', $item->id)->where('network_node_id', $this->node->id)->value('qty'));
    }

    #[Test]
    public function laporan_infr_dapat_mencabut_meter_roll_fisik_tanpa_memulihkan_roll_asal(): void
    {
        $task = $this->makeTask(TaskType::INFR, withCustomer: false);
        $anchor = FopTask::where('task_id', $task->id)->sole();
        $item = $this->makeItem('INF-ROLL-REPORT-RETRIEVE', 'roll', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-REPORT-RETRIEVE',
            'status' => RollStatus::ISSUED, 'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id, 'length_total' => 100, 'length_remaining' => 100,
        ]);
        $source = app(InventoryService::class)->deployRoll($roll, 40, $this->node, [$this->technician], $anchor, $this->technician);

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Cabut sepuluh meter feeder.',
            'infra_material_action' => 'retrieve',
            'infra_material_source_transaction_id' => $source->id,
            'infra_material_qty' => 10,
            'infra_material_reason' => 'Feeder lama rusak',
        ])->assertSessionHas('success')->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(0, InfrastructureBalance::count());
        $this->assertEquals(60, $roll->refresh()->length_remaining);
        $this->assertDatabaseHas('infrastructure_recoveries', ['source_transaction_id' => $source->id, 'qty' => 10, 'status' => 'pending_inspection']);
    }

    #[Test]
    public function laporan_infr_dapat_mengoreksi_kode_roll_yang_salah(): void
    {
        $task = $this->makeTask(TaskType::INFR, withCustomer: false);
        $anchor = FopTask::where('task_id', $task->id)->sole();
        $item = $this->makeItem('INF-ROLL-REPORT-IDENTITY', 'roll', 'meter');
        $wrong = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-REPORT-WRONG', 'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->pop->id,
            'length_total' => 100, 'length_remaining' => 100,
        ]);
        $right = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-REPORT-RIGHT', 'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->pop->id,
            'length_total' => 100, 'length_remaining' => 100,
        ]);
        $source = app(InventoryService::class)->deployRoll($wrong, 40, $this->node, [$this->technician], $anchor, $this->technician);

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Kode roll salah.', 'infra_material_action' => 'correct',
            'infra_material_source_transaction_id' => $source->id, 'infra_material_correct_roll_id' => $right->id,
            'infra_material_qty' => 40, 'infra_material_reason' => 'Yang dipakai roll kanan',
            'infra_target_network_node_id' => $this->node->id,
        ])->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertEquals(100, $wrong->refresh()->length_remaining);
        $this->assertEquals(60, $right->refresh()->length_remaining);
        $this->assertSame(0, InfrastructureBalance::count());
        $this->assertSame($this->node->id, InventoryTransaction::where('roll_id', $right->id)->sole()->to_network_node_id);
    }

    #[Test]
    public function laporan_infr_dapat_mengoreksi_node_roll_tanpa_mencatat_cabut_fisik(): void
    {
        $task = $this->makeTask(TaskType::INFR, withCustomer: false);
        $anchor = FopTask::where('task_id', $task->id)->sole();
        $item = $this->makeItem('INF-ROLL-REPORT-CORRECT', 'roll', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-REPORT-CORRECT',
            'status' => RollStatus::ISSUED, 'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id, 'length_total' => 100, 'length_remaining' => 100,
        ]);
        $source = app(InventoryService::class)->deployRoll($roll, 40, $this->node, [$this->technician], $anchor, $this->technician);
        $actualNode = NetworkNode::factory()->create(['anchor_pop_id' => $this->pop->id]);

        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Koreksi lokasi feeder.',
            'infra_material_action' => 'correct',
            'infra_material_source_transaction_id' => $source->id,
            'infra_material_qty' => 40,
            'infra_material_reason' => 'Laporan sebelumnya salah node',
            'infra_target_network_node_id' => $actualNode->id,
        ])->assertSessionHas('success')->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(0, InfrastructureBalance::count());
        $this->assertSame($actualNode->id, $this->cableWorkNodeId($source));
        $this->assertSame(0, InventoryTransaction::where('type', InventoryTransactionType::RETRIEVE_INFRA->value)->count());
    }

    #[Test]
    public function laporan_infrastruktur_memakai_layar_khusus_dengan_pilihan_tindakan_yang_mudah_dipahami(): void
    {
        $this->withoutVite();
        $task = $this->makeTask(TaskType::INFR, false);

        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $task))
            ->assertOk()
            ->assertViewIs('tasks.infrastructure-report')
            ->assertSee('Apa yang dikerjakan?')
            ->assertSee('Pilih tindakan')
            ->assertSee('Tinjau laporan')
            ->assertDontSee('Modem/Perangkat Aktif')
            ->assertDontSee('Kategori C-REQ')
            ->assertDontSee('Pekerjaan pada pelanggan');

        $customerTask = $this->makeTask(TaskType::OREQ);
        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $customerTask))
            ->assertOk()
            ->assertSee('Pekerjaan pada pelanggan')
            ->assertSee('modem_ownership_choice', false)
            ->assertSee('selected_inventory_roll_id', false);
    }

    #[Test]
    public function form_laporan_menampilkan_seksi_infrastruktur_hanya_untuk_oreq_infr(): void
    {
        $this->withoutVite();

        $infr = $this->makeTask(TaskType::INFR);
        $item = $this->makeItem('INF-FORM-RECOVERY', 'quantity');
        InventoryTransaction::create([
            'type' => InventoryTransactionType::DEPLOY,
            'reference_number' => 'DPL-FORM-RECOVERY',
            'item_id' => $item->id,
            'qty' => 2,
            'to_network_node_id' => $this->node->id,
            'to_pop_id' => $this->pop->id,
            'created_by' => $this->technician->id,
        ]);
        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $infr))
            ->assertOk()
            ->assertViewIs('tasks.infrastructure-report')
            ->assertSee('Tindakan di jaringan')
            ->assertSee('infra_target_network_node_id', false)
            ->assertSee('infra_material_action', false)
            ->assertSee('infra_material_source_transaction_id', false);

        $mtn = $this->makeTask(TaskType::MAINTENANCE);
        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $mtn))
            ->assertOk()
            ->assertDontSee('Pasang/Ganti Infrastruktur')
            ->assertDontSee('infra_material_action', false);
    }

    #[Test]
    public function picker_lokasi_laporan_menampilkan_parent_dan_child_secara_berjenjang(): void
    {
        $this->withoutVite();
        $task = $this->makeTask(TaskType::INFR);
        $child = NetworkNode::factory()->child($this->node)->create(['name' => 'ODP Turunan']);

        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $task))
            ->assertOk()
            ->assertViewHas('infraNodeOptions', fn ($nodes) => $nodes->pluck('id')->contains($this->node->id) && $nodes->pluck('id')->contains($child->id))
            ->assertSee('Lokasi yang dikerjakan')
            ->assertSee('ODP Turunan');
    }

    #[Test]
    public function picker_material_dan_roll_pelanggan_tidak_menampilkan_aset_infrastruktur(): void
    {
        $this->withoutVite();

        $task = $this->makeTask(TaskType::OREQ);

        $infraClosure = $this->makeItem('INF-CLOSURE-P', 'quantity');
        $infraClosure->update(['equipment_class_override' => 'pasif']);
        $konektor = Item::create([
            'code' => 'CUST-KONEKTOR-P', 'name' => 'Konektor Pelanggan', 'unit' => 'pcs',
            'item_category_id' => $this->category->id, 'tracking_type' => 'quantity',
            'ownership_mode' => 'installable', 'equipment_class_override' => 'pasif',
        ]);
        foreach ([$infraClosure, $konektor] as $item) {
            TechnicianCustody::create([
                'technician_id' => $this->technician->id,
                'issued_from_pop_id' => $this->pop->id,
                'item_id' => $item->id,
                'qty_remaining' => 5,
                'status' => 'issued',
                'issued_at' => now(),
            ]);
        }

        $infraRoll = InventoryRoll::create([
            'item_id' => $this->makeItem('INF-FEEDER-P', 'roll', 'meter')->id,
            'roll_code' => 'ROLL-INF-P01', 'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->pop->id,
            'length_total' => 100, 'length_remaining' => 100,
        ]);
        $dropcore = Item::create([
            'code' => 'CUST-DC-P', 'name' => 'Dropcore Pelanggan', 'unit' => 'meter',
            'item_category_id' => $this->category->id, 'tracking_type' => 'roll', 'ownership_mode' => 'installable',
        ]);
        $customerRoll = InventoryRoll::create([
            'item_id' => $dropcore->id,
            'roll_code' => 'ROLL-CUST-P01', 'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->pop->id,
            'length_total' => 100, 'length_remaining' => 100,
        ]);

        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $task))
            ->assertOk()
            ->assertViewHas('eligiblePassiveCustody', fn ($rows) => $rows->pluck('item_id')->all() === [$konektor->id])
            ->assertViewHas('eligibleRolls', fn ($rolls) => $rolls->pluck('id')->all() === [$customerRoll->id])
            ->assertViewHas('eligibleInfraCustody', fn ($rows) => $rows->pluck('item_id')->contains($infraClosure->id))
            ->assertViewHas('eligibleInfraRolls', fn ($rolls) => $rolls->pluck('id')->contains($infraRoll->id));

        // Roll infrastruktur dikirim lewat field roll PELANGGAN → ditolak
        // Rule::in sebagai error form, roll gak terpotong.
        $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), [
            'kendala_teknis' => 'Coba potong feeder ke pelanggan.',
            'selected_inventory_roll_id' => $infraRoll->id,
            'roll_meters_used' => 10,
        ])->assertSessionHasErrors('selected_inventory_roll_id');

        $this->assertEquals(100, $infraRoll->refresh()->length_remaining);
    }

    #[Test]
    public function kartu_olt_hanya_menerima_satu_aset_modul_dari_gudang(): void
    {
        $miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->pop->id]);
        $cardType = NetworkNodeType::firstOrCreate(['code' => 'olt_card'], ['name' => 'Kartu OLT', 'has_ports' => true]);
        $card = NetworkNode::factory()->create(['anchor_pop_id' => $miniPop->id, 'node_type_id' => $cardType->id]);
        $task = $this->makeTask(TaskType::INFR);
        $wrong = $this->issuedSerial($this->makeItem('WRONG-CARD', 'serialized'), 'WRONG-CARD-SN');
        $service = app(InventoryService::class);

        try {
            $service->deploySerial($wrong, $card, $task->fopTask, [$this->technician], $this->technician);
            $this->fail('Kartu OLT tidak boleh berisi jenis barang yang bukan modul OLT.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(SerialStatus::ISSUED, $wrong->fresh()->status);
        }

        $chassisType = NetworkNodeType::firstOrCreate(['code' => 'olt_chassis'], ['name' => 'Chassis OLT', 'has_ports' => false]);
        $chassisItem = Item::create([
            'code' => 'OLT-TEST-CHASSIS', 'name' => 'Chassis OLT Uji', 'unit' => 'pcs',
            'item_category_id' => ItemCategory::where('code', 'olt_module')->firstOrFail()->id,
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset',
            'infrastructure_node_type_id' => $chassisType->id,
        ]);
        $wrongSku = $this->issuedSerial($chassisItem, 'CHASSIS-SN-1');
        try {
            $service->deploySerial($wrongSku, $card, $task->fopTask, [$this->technician], $this->technician);
            $this->fail('SKU chassis tidak boleh dipasang sebagai kartu OLT.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(SerialStatus::ISSUED, $wrongSku->fresh()->status);
        }
        $item = Item::create([
            'code' => 'OLT-TEST-CARD', 'name' => 'Modul OLT Uji', 'unit' => 'pcs',
            'item_category_id' => ItemCategory::where('code', 'olt_module')->firstOrFail()->id,
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset',
            'infrastructure_node_type_id' => $cardType->id,
        ]);
        $first = $this->issuedSerial($item, 'CARD-SN-1');
        $second = $this->issuedSerial($item, 'CARD-SN-2');
        $service->deploySerial($first, $card, $task->fopTask, [$this->technician], $this->technician);
        $this->assertSame($card->id, $first->fresh()->current_network_node_id);
        $this->expectException(\InvalidArgumentException::class);
        $service->deploySerial($second, $card, $task->fopTask, [$this->technician], $this->technician);
    }

    #[Test]
    public function service_deploy_serial_menolak_node_di_cabang_lain_setelah_lock(): void
    {
        $task = $this->makeTask(TaskType::INFR);
        $serial = $this->issuedSerial($this->makeItem('INF-BRANCH-GUARD', 'serialized'), 'SN-BRANCH-GUARD');
        $foreignNode = NetworkNode::factory()->create(['anchor_pop_id' => $this->popLain->id]);

        try {
            app(InventoryService::class)->deploySerial(
                $serial, $foreignNode, $task->fopTask, [$this->technician], $this->technician
            );
            $this->fail('Node cabang lain harus ditolak pada level service.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(SerialStatus::ISSUED, $serial->fresh()->status);
        }
    }
}
