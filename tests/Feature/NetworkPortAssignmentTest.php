<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\NetworkPortAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class NetworkPortAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_port_odp_hanya_mempunyai_satu_pelanggan_aktif_dan_dilepas_secara_eksplisit(): void
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $odp = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $pop->id, 'node_type_id' => $odp->id]);
        $port = $node->ports()->create(['number' => 1]);
        $first = Customer::factory()->create(['pop_id' => $pop->id]);
        $second = Customer::factory()->create(['pop_id' => $pop->id]);
        $actor = User::factory()->create();
        $service = app(NetworkPortAssignmentService::class);

        $assignment = $service->assignCustomer($port, $first, $actor);
        $this->assertSame($first->id, $assignment->customer_id);
        $this->assertTrue($port->fresh()->is_used);
        $this->assertSame($assignment->id, $service->assignCustomer($port, $first, $actor)->id);
        $this->assertSame(1, $port->assignments()->count());

        try {
            $service->assignCustomer($port, $second, $actor);
            $this->fail('Port yang dipakai pelanggan lain harus ditolak.');
        } catch (InvalidArgumentException) {
            $this->assertSame(1, $port->assignments()->whereNotNull('active_key')->count());
        }

        $service->release($port, $actor, 'Kabel drop dicabut pada kunjungan lapangan');
        $this->assertFalse($port->fresh()->is_used);
        $this->assertSame(0, $port->assignments()->whereNotNull('active_key')->count());
        $this->assertNotNull($assignment->fresh()->released_at);
    }

    public function test_rekonsiliasi_port_lama_membuat_catatan_manual_tanpa_mengubah_status(): void
    {
        $node = NetworkNode::factory()->create();
        $used = $node->ports()->create(['number' => 1, 'is_used' => true]);
        $free = $node->ports()->create(['number' => 2, 'is_used' => false]);

        $migration = require database_path('migrations/2026_10_13_111823_reconcile_legacy_network_port_usage.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, $used->assignments()->where('active_key', 1)->count());
        $this->assertSame('Terpakai (data lama; perlu verifikasi)', $used->assignments()->firstOrFail()->manual_label);
        $this->assertSame(0, $free->assignments()->count());
        $this->assertTrue($used->fresh()->is_used);
    }

    public function test_pelanggan_cabang_lain_tidak_boleh_ditempatkan_di_odp(): void
    {
        $node = NetworkNode::factory()->create(['anchor_pop_id' => Pop::factory()->create(['type' => 'cabang'])->id]);
        $port = $node->ports()->create(['number' => 1]);
        $foreign = Customer::factory()->create(['pop_id' => Pop::factory()->create(['type' => 'cabang'])->id]);

        $this->expectException(InvalidArgumentException::class);
        app(NetworkPortAssignmentService::class)->assignCustomer($port, $foreign, User::factory()->create());
    }

    public function test_detail_node_menampilkan_penghuni_port_dan_status_verifikasi(): void
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $type = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $pop->id, 'node_type_id' => $type->id]);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Pelanggan Port Satu']);
        $actor = User::factory()->create();
        $firstPort = $node->ports()->create(['number' => 1]);
        $secondPort = $node->ports()->create(['number' => 2]);
        app(NetworkPortAssignmentService::class)->assignCustomer($firstPort, $customer, $actor);
        app(NetworkPortAssignmentService::class)->assignManual($secondPort, 'Data lama perlu verifikasi', $actor);

        $owner = User::factory()->create(['role_id' => Role::create(['code' => 'owner', 'name' => 'Owner'])->id]);
        $this->actingAs($owner)->get(route('network.nodes.show', $node))
            ->assertOk()
            ->assertSee('Pelanggan Port Satu')
            ->assertSee('Data lama perlu verifikasi')
            ->assertSee('Belum terverifikasi');
    }

    public function test_rekonsiliasi_pelanggan_lama_hanya_mencocokkan_port_yang_unik_dalam_cabang(): void
    {
        $branch = Pop::factory()->create(['type' => 'cabang']);
        $type = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $branch->id, 'node_type_id' => $type->id, 'code' => 'ODP-LEGACY-01']);
        $matchedPort = $node->ports()->create(['number' => 1, 'is_used' => true]);
        $ambiguousPort = $node->ports()->create(['number' => 2, 'is_used' => true]);
        $matchedCustomer = Customer::factory()->create(['pop_id' => $branch->id]);
        $firstAmbiguous = Customer::factory()->create(['pop_id' => $branch->id]);
        $secondAmbiguous = Customer::factory()->create(['pop_id' => $branch->id]);

        foreach ([[$matchedCustomer, '1'], [$firstAmbiguous, '2'], [$secondAmbiguous, '2']] as [$customer, $number]) {
            CustomerTechnicalDetail::create([
                'customer_id' => $customer->id,
                'odp_number' => 'ODP-LEGACY-01',
                'odp_port' => $number,
            ]);
        }
        $placeholder = require database_path('migrations/2026_10_13_111823_reconcile_legacy_network_port_usage.php');
        $placeholder->up();
        $migration = require database_path('migrations/2026_10_13_142233_reconcile_legacy_customer_odp_ports.php');
        $migration->up();
        $migration->up();

        $this->assertSame($matchedCustomer->id, $matchedPort->assignments()->firstOrFail()->customer_id);
        $this->assertNull($matchedPort->assignments()->firstOrFail()->manual_label);
        $this->assertSame(1, $matchedPort->assignments()->count());
        $this->assertNull($ambiguousPort->assignments()->firstOrFail()->customer_id);
        $this->assertSame('Terpakai (data lama; perlu verifikasi)', $ambiguousPort->assignments()->firstOrFail()->manual_label);
    }

    public function test_rekonsiliasi_pelanggan_lama_tidak_meledak_lazy_loading_saat_odp_lebih_dari_satu(): void
    {
        // Regresi: migration dulu memanggil NetworkNode::resolvePop() per node
        // dari koleksi >1 baris → LazyLoadingViolationException di `migrate`
        // data nyata. Test lama cuma 1 ODP sehingga guard tidak pernah aktif.
        $branch = Pop::factory()->create(['type' => 'cabang']);
        $type = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);
        $root = NetworkNode::factory()->create(['anchor_pop_id' => $branch->id, 'node_type_id' => $type->id, 'code' => 'ODP-ROOT']);
        $child = NetworkNode::factory()->create(['anchor_pop_id' => null, 'parent_id' => $root->id, 'node_type_id' => $type->id, 'code' => 'ODP-CHILD']);
        $childPort = $child->ports()->create(['number' => 3]);
        $customer = Customer::factory()->create(['pop_id' => $branch->id]);
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'odp_number' => 'ODP-CHILD', 'odp_port' => '3']);

        $migration = require database_path('migrations/2026_10_13_142233_reconcile_legacy_customer_odp_ports.php');
        $migration->up();

        $this->assertSame($customer->id, $childPort->assignments()->firstOrFail()->customer_id);
        $this->assertTrue($childPort->fresh()->is_used);
    }

    public function test_port_pelanggan_hanya_dilepas_setelah_penghapusan_fisik_dikonfirmasi(): void
    {
        $branch = Pop::factory()->create(['type' => 'cabang']);
        $type = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $branch->id, 'node_type_id' => $type->id]);
        $port = $node->ports()->create(['number' => 1]);
        $customer = Customer::factory()->create(['pop_id' => $branch->id]);
        $owner = User::factory()->create(['role_id' => Role::create(['code' => 'owner', 'name' => 'Owner'])->id]);
        app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $owner);

        $this->actingAs($owner)->put(route('network.node-ports.update', $port), ['is_used' => 0])
            ->assertSessionHasErrors(['physical_removal_confirmed', 'release_note']);
        $this->assertTrue($port->fresh()->is_used);

        $this->actingAs($owner)->put(route('network.node-ports.update', $port), [
            'is_used' => 0,
            'physical_removal_confirmed' => 1,
            'release_note' => 'Kabel drop telah dicabut dari port ODP',
        ])->assertRedirect(route('network.nodes.show', $node));
        $this->assertFalse($port->fresh()->is_used);
        $this->assertSame('Kabel drop telah dicabut dari port ODP', $port->assignments()->firstOrFail()->release_note);
    }

    public function test_port_dapat_dihubungkan_ke_anak_langsung_satu_kali(): void
    {
        $branch = Pop::factory()->create(['type' => 'cabang']);
        $odcType = NetworkNodeType::create(['code' => 'odc', 'name' => 'ODC', 'has_ports' => true]);
        $odpType = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);
        $odc = NetworkNode::factory()->create(['anchor_pop_id' => $branch->id, 'node_type_id' => $odcType->id]);
        $odp = NetworkNode::factory()->create(['parent_id' => $odc->id, 'anchor_pop_id' => null, 'node_type_id' => $odpType->id]);
        $unrelated = NetworkNode::factory()->create(['anchor_pop_id' => $branch->id, 'node_type_id' => $odpType->id]);
        $first = $odc->ports()->create(['number' => 1]);
        $second = $odc->ports()->create(['number' => 2]);
        $owner = User::factory()->create(['role_id' => Role::create(['code' => 'owner', 'name' => 'Owner'])->id]);

        $this->actingAs($owner)->post(route('network.node-ports.assign-node', $first), ['target_node_id' => $odp->id])
            ->assertRedirect(route('network.nodes.show', $odc));
        $this->assertSame($odp->id, $first->assignments()->firstOrFail()->target_node_id);
        $this->actingAs($owner)->post(route('network.node-ports.assign-node', $second), ['target_node_id' => $odp->id])
            ->assertSessionHasErrors('target_node_id');
        $this->actingAs($owner)->post(route('network.node-ports.assign-node', $second), ['target_node_id' => $unrelated->id])
            ->assertSessionHasErrors('target_node_id');
    }
}
