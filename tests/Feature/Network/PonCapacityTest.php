<?php

namespace Tests\Feature\Network;

use App\Enums\InfrastructurePortKind;
use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\InfrastructurePort;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureConnectionService;
use App\Services\InfrastructurePositionService;
use App\Services\NetworkPortAssignmentService;
use App\Services\PonCapacityService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kapasitas PON manual bersatuan pelanggan aktif (keputusan user 21-10-2026,
 * §16.3). Pemakaian = pelanggan dengan assignment port ODP aktif pada ODP
 * yang uplink-nya ke PON ini aktif DAN terverifikasi.
 */
class PonCapacityTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $miniPop;

    private User $owner;

    private InfrastructurePort $pon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        // Feature `network_nodes` dibuat NetworkNodeTypeSeeder — wajib sebelum
        // RolePermissionSeeder supaya izin network_nodes.* ikut ter-grant.
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id]);
        $this->pon = $this->makePon(capacity: 10, threshold: 80);
    }

    private function makePon(?int $capacity, ?int $threshold): InfrastructurePort
    {
        $positions = app(InfrastructurePositionService::class);
        $olt = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->firstOrFail()->id,
            'anchor_pop_id' => $this->miniPop->id,
        ]);

        return $positions->createPort($positions->createSlot($olt, 1), 1, InfrastructurePortKind::PON, [
            'capacity' => $capacity, 'red_zone_threshold' => $threshold,
        ]);
    }

    /**
     * ODP tersambung ke PON + `$customers` pelanggan aktif pada port-portnya.
     *
     * @return list<Customer>
     */
    private function odpWithCustomers(int $customers, bool $verified = true, ?InfrastructurePort $pon = null): array
    {
        $odp = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->firstOrFail()->id,
            'anchor_pop_id' => $this->miniPop->id,
        ]);
        app(InfrastructureConnectionService::class)->connect($pon ?? $this->pon, $this->owner, targetNode: $odp, verified: $verified);

        $created = [];
        for ($number = 1; $number <= $customers; $number++) {
            $port = NetworkNodePort::create(['network_node_id' => $odp->id, 'number' => $number]);
            $customer = Customer::factory()->create(['pop_id' => $this->cabang->id]);
            app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $this->owner);
            $created[] = $customer;
        }

        return $created;
    }

    private function capacity(): PonCapacityService
    {
        return app(PonCapacityService::class);
    }

    #[Test]
    public function tanpa_pemakaian_bukan_red_zone(): void
    {
        $summary = $this->capacity()->summary($this->pon);

        $this->assertSame(0, $summary['used']);
        $this->assertSame(10, $summary['capacity']);
        $this->assertSame('pelanggan aktif', $summary['unit_label']);
        $this->assertFalse($summary['red_zone']);
        $this->assertFalse($summary['is_full']);
    }

    #[Test]
    public function tepat_di_ambang_menjadi_red_zone_dan_di_bawahnya_tidak(): void
    {
        $this->odpWithCustomers(7);
        $this->assertFalse($this->capacity()->summary($this->pon)['red_zone']);

        $this->odpWithCustomers(1);
        $summary = $this->capacity()->summary($this->pon);
        $this->assertSame(8, $summary['used']);
        $this->assertTrue($summary['red_zone']);
    }

    #[Test]
    public function di_atas_ambang_dan_penuh(): void
    {
        $this->odpWithCustomers(10);

        $summary = $this->capacity()->summary($this->pon);
        $this->assertTrue($summary['red_zone']);
        $this->assertTrue($summary['is_full']);
    }

    #[Test]
    public function kapasitas_atau_ambang_belum_terdata_tidak_mengklaim_red_zone(): void
    {
        $noCapacity = $this->makePon(capacity: null, threshold: 80);
        $noThreshold = $this->makePon(capacity: 10, threshold: null);
        $this->odpWithCustomers(9, pon: $noThreshold);

        $this->assertNull($this->capacity()->summary($noCapacity)['red_zone']);
        $this->assertNull($this->capacity()->summary($noThreshold)['red_zone']);
        $this->assertSame(9, $this->capacity()->summary($noThreshold)['used']);
    }

    #[Test]
    public function banyak_ont_satu_pelanggan_dihitung_satu(): void
    {
        [$customer] = $this->odpWithCustomers(1);
        $category = ItemCategory::create(['code' => 'modem_ont', 'name' => 'Modem', 'default_unit' => 'pcs']);
        $item = Item::create(['code' => 'ONT-X', 'name' => 'ONT', 'unit' => 'pcs', 'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        foreach (['ONT-1', 'ONT-2'] as $serial) {
            InventorySerial::create(['item_id' => $item->id, 'serial_number' => $serial, 'status' => SerialStatus::INSTALLED->value, 'customer_id' => $customer->id]);
        }

        $this->assertSame(1, $this->capacity()->summary($this->pon)['used']);
    }

    #[Test]
    public function koneksi_belum_terverifikasi_tidak_dihitung_tetapi_dilaporkan_terpisah(): void
    {
        $this->odpWithCustomers(2, verified: false);

        $summary = $this->capacity()->summary($this->pon);
        $this->assertSame(0, $summary['used']);
        $this->assertSame(2, $summary['unverified']);
    }

    #[Test]
    public function pengurangan_kapasitas_di_bawah_pemakaian_ditolak_dan_perubahan_diaudit(): void
    {
        $this->odpWithCustomers(3);

        try {
            $this->capacity()->updateCapacity($this->pon, 2, 80, $this->owner);
            $this->fail('Kapasitas di bawah pemakaian harus ditolak.');
        } catch (ValidationException) {
        }
        $this->assertSame(10, $this->pon->fresh()->capacity);

        $this->capacity()->updateCapacity($this->pon, 3, 90, $this->owner);

        $this->assertSame(3, $this->pon->fresh()->capacity);
        $this->assertSame(90, $this->pon->fresh()->red_zone_threshold);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => InfrastructurePort::class, 'auditable_id' => $this->pon->id, 'action' => 'capacity_change', 'user_id' => $this->owner->id,
        ]);
    }

    #[Test]
    public function kapasitas_dan_ambang_divalidasi(): void
    {
        foreach ([[0, 80], [10, 0], [10, 101]] as [$capacity, $threshold]) {
            try {
                $this->capacity()->updateCapacity($this->pon, $capacity, $threshold, $this->owner);
                $this->fail("Nilai {$capacity}/{$threshold} harus ditolak.");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function port_switch_tidak_punya_kapasitas_pelanggan(): void
    {
        $positions = app(InfrastructurePositionService::class);
        $switch = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'switch_mini_pop')->firstOrFail()->id,
            'anchor_pop_id' => $this->miniPop->id,
        ]);
        $port = $positions->createPort($positions->ensureLogicalSlot($switch), 1, InfrastructurePortKind::SWITCH);

        $this->expectException(ValidationException::class);
        $this->capacity()->updateCapacity($port, 10, 80, $this->owner);
    }

    #[Test]
    public function endpoint_mengubah_kapasitas_dengan_izin_dan_scope(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('network.infrastructure-ports.capacity', $this->pon), ['capacity' => 16, 'red_zone_threshold' => 75])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(16, $this->pon->fresh()->capacity);
    }

    #[Test]
    public function endpoint_menolak_aktor_tanpa_izin(): void
    {
        $sales = User::factory()->create(['role_id' => Role::where('code', 'sales')->firstOrFail()->id]);

        $this->actingAs($sales)
            ->patch(route('network.infrastructure-ports.capacity', $this->pon), ['capacity' => 16, 'red_zone_threshold' => 75])
            ->assertForbidden();
        $this->assertSame(10, $this->pon->fresh()->capacity);
    }

    #[Test]
    public function endpoint_menolak_aktor_cabang_lain(): void
    {
        $otherCabang = Pop::factory()->create(['type' => 'cabang']);
        $admin = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $admin->id, 'role_id' => $admin->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $otherCabang->id]);
        app(EffectiveAccessService::class)->clearCache($admin);
        $this->assertTrue($admin->hasPermission('network_nodes.update'), 'Fixture: admin harus punya izin update node.');

        $this->actingAs($admin)
            ->patch(route('network.infrastructure-ports.capacity', $this->pon), ['capacity' => 16, 'red_zone_threshold' => 75])
            ->assertForbidden();
    }
}
