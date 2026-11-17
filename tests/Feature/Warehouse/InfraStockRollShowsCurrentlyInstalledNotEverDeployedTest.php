<?php

namespace Tests\Feature\Warehouse;

use App\Enums\RollStatus;
use App\Enums\ScopeType;
use App\Models\FopTask;
use App\Models\InventoryRoll;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\InfrastructureLocationResolver;
use App\Services\InfrastructureMaterialService;
use App\Services\InventoryService;
use App\Support\InfrastructureLocation;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi: tab Roll di "Barang Terpasang di Infrastruktur" dulu cuma
 * men-SUM baris DEPLOY — kabel yang sudah dicabut (RETRIEVE_INFRA) atau
 * dikoreksi jumlah/lokasinya (ADJUSTMENT `infra_record_correction`) tetap
 * dihitung, jadi angkanya "meter yang pernah dipasang", bukan "meter yang
 * terpasang sekarang".
 */
class InfraStockRollShowsCurrentlyInstalledNotEverDeployedTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private NetworkNode $odc;

    private NetworkNode $odp;

    private User $technician;

    private FopTask $fopTask;

    private InventoryRoll $roll;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(NetworkNodeTypeSeeder::class);

        $this->cabang = Pop::factory()->create(['name' => 'Cabang Jetis', 'type' => 'cabang']);
        $odcType = NetworkNodeType::where('code', 'odc')->first() ?? NetworkNodeType::firstOrFail();
        $this->odc = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabang->id, 'node_type_id' => $odcType->id, 'name' => 'ODC Jetis 01', 'code' => 'ODC-JETIS-01']);
        $this->odp = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabang->id, 'node_type_id' => $odcType->id, 'name' => 'ODP Jetis 07', 'code' => 'ODP-JETIS-07']);

        $this->technician = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $task = Task::create([
            'task_number' => 'TASK-ROLLNET-0001', 'pop_id' => $this->cabang->id, 'task_type' => 'INFR REQ', 'title' => 'Tarik kabel',
            'status' => 'in_progress', 'created_by' => $this->technician->id, 'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);
        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-ROLLNET-0001', 'task_date' => now(), 'category' => 'INFR REQ', 'tugas' => 'Tarik kabel',
            'pop_id' => $this->cabang->id, 'issue' => 'Uji', 'status' => 'draft', 'priority' => 'Medium', 'task_id' => $task->id,
        ]);

        $category = ItemCategory::create(['code' => 'kabel_rollnet_uji', 'name' => 'Kabel', 'default_unit' => 'meter']);
        $item = Item::create(['code' => 'FDR-ROLLNET', 'name' => 'Feeder Rollnet', 'unit' => 'meter', 'item_category_id' => $category->id,
            'tracking_type' => 'roll', 'ownership_mode' => 'infrastructure_asset', 'meter_per_roll' => 100]);
        $this->roll = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-ROLLNET-1', 'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->cabang->id,
            'length_total' => 100, 'length_remaining' => 100, 'unit_price_snapshot' => 1500,
        ]);
    }

    private function adminGudang(): User
    {
        $role = Role::where('code', 'admin_gudang')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        $user->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP->value]);

        return $user;
    }

    private function deploy(float $meters, NetworkNode $node): InventoryTransaction
    {
        return app(InventoryService::class)->deployRoll($this->roll, $meters, $node, [$this->technician], $this->fopTask, $this->technician);
    }

    /** @return list<array<string, mixed>> */
    private function rollRows(): array
    {
        return $this->actingAs($this->adminGudang())
            ->get(route('warehouse.infra_stock.index', ['tab' => 'roll']))
            ->assertOk()
            ->viewData('rows')
            ->all();
    }

    #[Test]
    public function kabel_yang_dicabut_sebagian_mengurangi_meter_terpasang(): void
    {
        $source = $this->deploy(40, $this->odc);
        app(InfrastructureMaterialService::class)->retrieve($source, 15, $this->fopTask, $this->technician, 'ODC dibongkar sebagian', 'rollnet-retrieve-1');

        $rows = $this->rollRows();

        $this->assertCount(1, $rows);
        $this->assertEquals(25, $rows[0]['total_qty']);
        $this->assertStringContainsString($this->odc->code, $rows[0]['breadcrumb']);
    }

    #[Test]
    public function kabel_yang_dicabut_habis_hilang_dari_tab_roll(): void
    {
        $source = $this->deploy(40, $this->odc);
        app(InfrastructureMaterialService::class)->retrieve($source, 40, $this->fopTask, $this->technician, 'ODC dibongkar', 'rollnet-retrieve-2');

        $this->assertSame([], $this->rollRows());
    }

    #[Test]
    public function koreksi_jumlah_kabel_menampilkan_meter_hasil_koreksi(): void
    {
        $source = $this->deploy(40, $this->odc);
        app(InfrastructureMaterialService::class)->correct($source, app(InfrastructureLocationResolver::class)->forNode($this->odc), 30, $this->fopTask, $this->technician, 'Salah ukur', 'rollnet-correct-1');

        $rows = $this->rollRows();

        $this->assertCount(1, $rows);
        $this->assertEquals(30, $rows[0]['total_qty']);
    }

    #[Test]
    public function koreksi_lokasi_kabel_memindah_meter_ke_lokasi_baru(): void
    {
        $source = $this->deploy(40, $this->odc);
        app(InfrastructureMaterialService::class)->correct($source, app(InfrastructureLocationResolver::class)->forNode($this->odp), 40, $this->fopTask, $this->technician, 'Salah titik', 'rollnet-correct-2');

        $rows = $this->rollRows();

        $this->assertCount(1, $rows);
        $this->assertEquals(40, $rows[0]['total_qty']);
        $this->assertStringContainsString($this->odp->code, $rows[0]['breadcrumb']);
        $this->assertStringNotContainsString($this->odc->code, $rows[0]['breadcrumb']);
    }

    #[Test]
    public function kabel_dicabut_di_pop_cabang_ikut_berkurang(): void
    {
        // Lokasi POP: DEPLOY mengisi `to_infrastructure_pop_id`, sedangkan
        // cabut cuma mengisi `from_pop_id` (positionOnly) — keduanya harus
        // jatuh ke baris lokasi yang sama.
        $pop = app(InfrastructureLocationResolver::class)->resolve(InfrastructureLocation::POP, $this->cabang->id);
        $source = app(InventoryService::class)->consumeInfrastructureRoll($this->roll, 20, $pop, [$this->technician], $this->fopTask, $this->technician);
        app(InfrastructureMaterialService::class)->retrieve($source, 5, $this->fopTask, $this->technician, 'Rapikan POP', 'rollnet-retrieve-pop');

        $rows = $this->rollRows();

        $this->assertCount(1, $rows);
        $this->assertEquals(15, $rows[0]['total_qty']);
        $this->assertNotSame('-', $rows[0]['deployed_at']);
    }
}
