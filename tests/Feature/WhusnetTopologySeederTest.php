<?php

namespace Tests\Feature;

use App\Enums\InfrastructurePortKind;
use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\NetworkPortAssignment;
use App\Models\Pop;
use App\Models\User;
use Database\Seeders\NetworkInfrastructureExampleSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\WhusnetTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Seeder topologi struktur baru (rancangan hierarki §16.2, §16.4 #5):
 * Server → POP → Mini POP → OLT → Slot 1 → PON 1 → ODP dan
 * Mini POP → Switch → Slot 1 (logis) → Port 1, semuanya baris `network_nodes`
 * (Slot/PON anak lewat parent_id). Tidak lagi membuat
 * aggregation_switch, backbone, feeder, atau node kartu OLT, dan
 * tidak pernah memetakan pelanggan ke port ODP berdasarkan teks label
 * (§4.3 — referensi harus ID terverifikasi).
 */
class WhusnetTopologySeederTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pusat;

    private Pop $jetis;

    private Pop $sandya;

    private Pop $c1;

    private Pop $d1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pusat = Pop::factory()->create(['name' => 'WHUSNET', 'type' => 'pusat', 'parent_id' => null]);
        $this->jetis = Pop::factory()->create(['name' => 'Jetis', 'type' => 'cabang', 'parent_id' => null]);
        $this->sandya = Pop::factory()->create(['name' => 'Sandya', 'type' => 'cabang', 'parent_id' => null]);
        $this->c1 = Pop::factory()->create(['code' => 'C1', 'name' => 'C1', 'type' => 'mini_pop', 'parent_id' => $this->jetis->id]);
        $this->d1 = Pop::factory()->create(['code' => 'D1', 'name' => 'D1', 'type' => 'mini_pop', 'parent_id' => $this->sandya->id]);
        User::factory()->create();
    }

    #[Test]
    public function seeder_contoh_lama_ditolak_sebelum_menulis_node_legacy(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Seeder contoh infrastruktur lama sudah dinonaktifkan');
        $this->seed(NetworkInfrastructureExampleSeeder::class);
    }

    #[Test]
    public function seeder_menolak_cabang_tanpa_mini_pop_daripada_membuat_lokasi_fiktif(): void
    {
        $this->d1->delete();

        $this->expectException(RuntimeException::class);
        $this->seed(WhusnetTopologySeeder::class);
    }

    #[Test]
    public function seeder_menolak_membuat_akun_operator_fiktif(): void
    {
        User::query()->delete();

        $this->expectException(RuntimeException::class);
        $this->seed(WhusnetTopologySeeder::class);
    }

    #[Test]
    public function membentuk_struktur_baru_olt_dan_switch_di_mini_pop(): void
    {
        $this->seed(WhusnetTopologySeeder::class);
        $this->seed(WhusnetTopologySeeder::class);

        $this->assertSame($this->pusat->id, $this->jetis->fresh()->parent_id);

        foreach (['JETIS' => $this->c1, 'SANDYA' => $this->d1] as $suffix => $mini) {
            $olt = NetworkNode::where('code', "OLT-{$suffix}-01")->firstOrFail();
            $switch = NetworkNode::where('code', "SW-{$suffix}-01")->firstOrFail();
            $odp = NetworkNode::where('code', "ODP-{$suffix}-01")->firstOrFail();
            $this->assertSame($mini->id, $olt->anchor_pop_id);
            $this->assertSame('switch_mini_pop', $switch->nodeType->code);
            $this->assertSame($mini->id, $switch->anchor_pop_id);

            $switchSlot = $switch->slots()->sole();
            $this->assertSame(1, $switchSlot->number);
            $this->assertTrue($switchSlot->is_logical);
            $this->assertSame('switch_slot', $switchSlot->nodeType->code);
            $this->assertSame(1, $switchSlot->positionPorts()->count());
            $this->assertSame(InfrastructurePortKind::SWITCH, $switchSlot->positionPorts()->sole()->portKind());

            // Idempoten: jalan dua kali tetap satu Slot, satu PON, satu ODP.
            $oltSlot = $olt->slots()->sole();
            $this->assertSame(1, $oltSlot->number);
            $this->assertTrue($oltSlot->isSlot());
            $pon = $oltSlot->positionPorts()->sole();
            $this->assertTrue($pon->isPon());
            $this->assertSame(1, $pon->number);
            // ODP menggantung langsung ke PON lewat parent_id, bukan koneksi.
            $this->assertSame($pon->id, $odp->parent_id);
            $this->assertSame(1, $pon->children()->count());
            $this->assertSame($mini->parent_id, $odp->resolvePop()?->id, 'POP ODP diwariskan dari rantai OLT → Mini POP (Cabang pemilik).');
        }
    }

    #[Test]
    public function tidak_membuat_representasi_lama_dan_tipe_lama_tetap_terbaca(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        // aggregation_switch/olt_card/pon_port dihapus dari seeder master
        // 2026-10-23 (ADHOC-212) — tes ini tetap membuktikan baris lama
        // (kalau ada, mis. dari impor/migrasi susulan) tidak diam-diam
        // tersentuh/ditambah seeder baru, jadi tipenya diprovisioning
        // sendiri (bukan gantung baris seeder permanen yang sudah dihapus).
        $legacyTypeId = NetworkNodeType::firstOrCreate(['code' => 'aggregation_switch'], ['name' => 'aggregation_switch', 'has_ports' => false])->id;
        $legacy = NetworkNode::create([
            'code' => 'AGG-LAMA', 'name' => 'Agregasi lama', 'anchor_pop_id' => $this->jetis->id, 'status' => 'active',
            'node_type_id' => $legacyTypeId,
        ]);

        $this->seed(WhusnetTopologySeeder::class);

        foreach (['aggregation_switch', 'olt_card', 'pon_port', 'feeder', 'backbone'] as $code) {
            $this->assertSame($code === 'aggregation_switch' ? 1 : 0,
                NetworkNode::whereHas('nodeType', fn ($query) => $query->where('code', $code))->count(), $code);
        }
        $this->assertSame('aggregation_switch', $legacy->fresh()->nodeType->code);
        $this->assertSame($this->jetis->id, $legacy->fresh()->anchor_pop_id);
    }

    #[Test]
    public function tidak_memetakan_pelanggan_ke_port_odp_berdasarkan_teks(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->jetis->id]);
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'odp_number' => 'ODP-JETIS-01', 'odp_port' => '1']);

        $this->seed(WhusnetTopologySeeder::class);

        $this->assertSame(0, NetworkPortAssignment::count());
    }

    #[Test]
    public function node_legacy_yang_sudah_ada_tidak_dipindah(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $odp = NetworkNode::create([
            'code' => 'ODP-JETIS-01', 'name' => 'ODP lama', 'anchor_pop_id' => $this->jetis->id, 'status' => 'active',
            'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
        ]);

        $this->seed(WhusnetTopologySeeder::class);

        $this->assertSame($this->jetis->id, $odp->fresh()->anchor_pop_id);
        $this->assertNull($odp->fresh()->parent_id, 'ODP lama tidak dipindah ke PON contoh.');
        $this->assertSame(1, NetworkNode::where('code', 'ODP-JETIS-01')->count());
    }
}
