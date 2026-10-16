<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\User;
use App\Services\NetworkPortAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gejala yang dijaga: di data produksi hierarki POP-nya tiga tingkat —
 * WHUSNET (pusat) > Jetis/Sandya (cabang) > C1/D1 (mini_pop), dipasang
 * `app:import-legacy-sql --pusat-code`. `Pop::resolveCabangId()` dulu naik
 * sampai akar pohon, jadi SEMUA cabang terbaca sebagai Pusat: Jetis dan
 * Sandya dianggap "cabang yang sama", seluruh guard lintas-cabang (port ODP,
 * node tujuan, deploy SN) diam-diam lolos. Test lama gak nangkep karena
 * factory selalu bikin Cabang tanpa induk.
 */
class CabangResolutionUnderPusatTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pusat;

    private Pop $jetis;

    private Pop $c1;

    private Pop $sandya;

    private Pop $d1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pusat = Pop::factory()->create(['type' => 'pusat', 'name' => 'WHUSNET']);
        $this->jetis = Pop::factory()->create(['type' => 'cabang', 'name' => 'Jetis', 'parent_id' => $this->pusat->id]);
        $this->c1 = Pop::factory()->create(['type' => 'mini_pop', 'name' => 'C1', 'parent_id' => $this->jetis->id]);
        $this->sandya = Pop::factory()->create(['type' => 'cabang', 'name' => 'Sandya', 'parent_id' => $this->pusat->id]);
        $this->d1 = Pop::factory()->create(['type' => 'mini_pop', 'name' => 'D1', 'parent_id' => $this->sandya->id]);
    }

    #[Test]
    public function resolve_cabang_berhenti_di_cabang_bukan_naik_ke_pusat(): void
    {
        $this->assertSame($this->jetis->id, Pop::resolveCabangId($this->c1->id));
        $this->assertSame($this->jetis->id, Pop::resolveCabangId($this->jetis->id));
        $this->assertSame($this->sandya->id, Pop::resolveCabangId($this->d1->id));
        $this->assertSame($this->sandya->id, Pop::resolveCabangId($this->sandya->id));
        $this->assertSame($this->pusat->id, Pop::resolveCabangId($this->pusat->id));
        $this->assertNull(Pop::resolveCabangId(null));
    }

    #[Test]
    public function cabang_tanpa_induk_tetap_terbaca_dirinya_sendiri(): void
    {
        $orphan = Pop::factory()->create(['type' => 'cabang']);
        $orphanMini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $orphan->id]);

        $this->assertSame($orphan->id, Pop::resolveCabangId($orphan->id));
        $this->assertSame($orphan->id, Pop::resolveCabangId($orphanMini->id));
    }

    #[Test]
    public function node_di_cabang_dan_mini_pop_sama_sama_terbaca_cabangnya(): void
    {
        $atCabang = NetworkNode::factory()->create(['anchor_pop_id' => $this->jetis->id]);
        $atMini = NetworkNode::factory()->create(['anchor_pop_id' => $this->c1->id]);
        $child = NetworkNode::factory()->create(['anchor_pop_id' => null, 'parent_id' => $atMini->id]);
        $atPusat = NetworkNode::factory()->create(['anchor_pop_id' => $this->pusat->id]);

        $this->assertSame($this->jetis->id, $atCabang->resolvePop()?->id);
        $this->assertSame($this->jetis->id, $atMini->resolvePop()?->id);
        $this->assertSame($this->jetis->id, $child->resolvePop()?->id);
        $this->assertSame($this->pusat->id, $atPusat->resolvePop()?->id);
    }

    #[Test]
    public function pelanggan_sandya_ditolak_di_odp_jetis(): void
    {
        $odpType = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);
        $odp = NetworkNode::factory()->create(['anchor_pop_id' => $this->c1->id, 'node_type_id' => $odpType->id]);
        $port = $odp->ports()->create(['number' => 1]);
        $sandyaCustomer = Customer::factory()->create(['pop_id' => $this->d1->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pelanggan dan ODP berada di cabang berbeda.');

        app(NetworkPortAssignmentService::class)->assignCustomer($port, $sandyaCustomer, User::factory()->create());
    }

    #[Test]
    public function pelanggan_mini_pop_jetis_diterima_di_odp_cabang_jetis(): void
    {
        $odpType = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);
        $odp = NetworkNode::factory()->create(['anchor_pop_id' => $this->jetis->id, 'node_type_id' => $odpType->id]);
        $port = $odp->ports()->create(['number' => 1]);
        $customer = Customer::factory()->create(['pop_id' => $this->c1->id]);

        $assignment = app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, User::factory()->create());

        $this->assertSame($customer->id, $assignment->customer_id);
    }

    #[Test]
    public function node_tujuan_di_cabang_lain_ditolak(): void
    {
        $source = NetworkNode::factory()->create(['anchor_pop_id' => $this->c1->id]);
        $port = $source->ports()->create(['number' => 1]);
        $target = NetworkNode::factory()->create(['anchor_pop_id' => $this->d1->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Node tujuan berada di cabang berbeda.');

        app(NetworkPortAssignmentService::class)->assignNode($port, $target, User::factory()->create());
    }
}
