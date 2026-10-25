<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Services\NetworkPortAssignmentService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Setelah tabel `infrastructure_slots`/`infrastructure_ports` dibuang (Task 14),
 * kartu "Lokasi Jaringan" di Detail Pelanggan membaca OLT → Slot → PON → ODP
 * dari pohon `network_nodes` (parent_id). Tes ini gagal kalau halaman kembali
 * memanggil relasi legacy (`sourcePort.slot.device`) yang sudah tidak ada.
 */
class CustomerShowLokasiJaringanDariPohonNodeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function detail_pelanggan_menampilkan_olt_slot_pon_dan_odp_dari_pohon_node(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $owner = $this->loginAsAdmin();
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $mini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);

        $olt = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->firstOrFail()->id,
            'anchor_pop_id' => $mini->id,
            'code' => 'OLT-POHON-01',
        ]);
        $slot = NetworkNode::factory()->slot($olt, 2)->create();
        $pon = NetworkNode::factory()->pon($slot, 3)->create();
        $odp = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->firstOrFail()->id,
            'parent_id' => $pon->id,
            'anchor_pop_id' => null,
            'code' => 'ODP-POHON-01',
        ]);
        $port = NetworkNodePort::create(['network_node_id' => $odp->id, 'number' => 4]);
        $customer = Customer::factory()->create(['pop_id' => $cabang->id]);
        app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $owner);

        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('OLT-POHON-01')
            ->assertSee('Slot 2')
            ->assertSee('PON 3')
            ->assertSee('ODP-POHON-01')
            ->assertDontSee('OLT/Slot/PON belum terhubung');
    }
}
