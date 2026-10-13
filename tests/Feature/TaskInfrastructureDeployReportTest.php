<?php

namespace Tests\Feature;

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
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\UserRoleScopeTarget;
use App\Services\InventoryService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
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

    private function makeTask(TaskType $type, bool $withCustomer = true): Task
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
    public function oreq_potong_roll_infrastruktur_menambah_infrastructure_balance_per_roll(): void
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
        $balance = InfrastructureBalance::where('item_id', $kabel->id)->where('network_node_id', $this->node->id)->sole();
        $this->assertEquals(40, $balance->qty);
        $this->assertSame('ROLL-INF-001', $balance->lot_no);
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
    public function form_laporan_menampilkan_seksi_infrastruktur_hanya_untuk_oreq_infr(): void
    {
        $this->withoutVite();

        $infr = $this->makeTask(TaskType::INFR);
        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $infr))
            ->assertOk()
            ->assertSee('Pasang/Ganti Infrastruktur')
            ->assertSee('infra_target_network_node_id', false);

        $mtn = $this->makeTask(TaskType::MAINTENANCE);
        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $mtn))
            ->assertOk()
            ->assertDontSee('Pasang/Ganti Infrastruktur');
    }

    #[Test]
    public function picker_lokasi_laporan_menampilkan_parent_dan_child_secara_berjenjang(): void
    {
        $this->withoutVite();
        $task = $this->makeTask(TaskType::INFR);
        $child = NetworkNode::factory()->child($this->node)->create(['name' => 'ODP Turunan']);

        $this->actingAs($this->technician)->get(route('tasks.maintenance.report', $task))
            ->assertOk()
            ->assertSee('data-infra-node-tree', false)
            ->assertSee('data-infra-node-id="'.$this->node->id.'"', false)
            ->assertSee('data-infra-parent-id="'.$this->node->id.'"', false)
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
