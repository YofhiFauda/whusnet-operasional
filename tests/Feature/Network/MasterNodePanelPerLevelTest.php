<?php

namespace Tests\Feature\Network;

use App\Models\Customer;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructurePositionService;
use App\Services\NetworkPortAssignmentService;
use App\Services\PonCapacityService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spesifikasi user 2026-10-26 (putaran UI 2 ADHOC-213): inspector Master Node
 * menampilkan panel "Manajemen Node — <tipe>" PER LEVEL, masing-masing HANYA
 * anak langsungnya + kapasitasnya — OLT → Slot, Slot → PON, PON → ODP. Gejala
 * lama: panel OLT menumpuk kartu PON di tiap Slot, dan Slot/PON di pohon tidak
 * bisa dipilih sama sekali (cuma tautan Detail).
 *
 * Tes ini menjaga: isi tiap level (tidak bocor ke level bawah / saudara),
 * matematika kapasitas (belum didata, penuh, Red Zone), scope/tipe/izin,
 * jumlah query konstan terhadap jumlah anak, `panel_url` server-rendered di
 * JSON pohon, dan pemilihan ulang Slot/PON lewat `?node=` setelah PRG.
 */
class MasterNodePanelPerLevelTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabangA;

    private Pop $cabangB;

    private User $owner;

    private InfrastructurePositionService $positions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class,
            PermissionSeeder::class, NetworkNodeTypeSeeder::class, RolePermissionSeeder::class]);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->cabangA = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Satu']);
        $this->cabangB = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Dua']);
        $this->positions = app(InfrastructurePositionService::class);
    }

    private function device(string $typeCode, Pop $pop, string $code, ?int $capacity = null): NetworkNode
    {
        return NetworkNode::create(['node_type_id' => NetworkNodeType::where('code', $typeCode)->value('id'),
            'anchor_pop_id' => $pop->id, 'parent_id' => null, 'code' => $code, 'name' => $code,
            'status' => 'active', 'capacity' => $capacity]);
    }

    private function odp(NetworkNode $pon, string $code, int $ports, int $customers = 0): NetworkNode
    {
        $odp = NetworkNode::create(['node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'parent_id' => $pon->id, 'anchor_pop_id' => null, 'code' => $code, 'name' => "Nama {$code}", 'status' => 'active']);
        for ($number = 1; $number <= $ports; $number++) {
            $port = NetworkNodePort::create(['network_node_id' => $odp->id, 'number' => $number, 'is_used' => false]);
            if ($number <= $customers) {
                $customer = Customer::factory()->create(['pop_id' => $this->cabangA->id]);
                app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $this->owner);
            }
        }

        return $odp;
    }

    private function cabangUser(Pop $cabang): User
    {
        $user = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $user->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    private function panelQueryCount(User $user, NetworkNode $node): int
    {
        $this->actingAs($user);
        // Permintaan pemanasan: permission & scope POP di-cache per user
        // (EffectiveAccessService) — permintaan pertama membayar ~6 query itu
        // sekali. Tanpa ini perbandingan kecil-vs-besar mengukur urutan, bukan N.
        $this->get(route('network.nodes.panel', $node))->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('network.nodes.panel', $node))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    #[Test]
    public function panel_slot_hanya_berisi_pon_slot_itu_dengan_kapasitas_pelanggan(): void
    {
        $olt = $this->device('olt_chassis', $this->cabangA, 'OLT-SATU');
        $slot = $this->positions->createSlot($olt, 1, ['capacity' => 16]);
        $sibling = $this->positions->createSlot($olt, 2);
        $pon = $this->positions->createPort($slot, 3, ['capacity' => 4, 'red_zone_threshold' => 50]);
        $siblingPon = $this->positions->createPort($sibling, 9, ['capacity' => 64]);
        $this->odp($pon, 'ODP-SATU-A', 4, 2);

        $html = $this->actingAs($this->cabangUser($this->cabangA))->get(route('network.nodes.panel', $slot))
            ->assertOk()
            ->assertSee('data-node-panel="slot"', false)
            ->assertSee('data-panel-port="'.$pon->id.'"', false)
            ->assertSee('OLT-SATU / Slot 1')
            ->assertSee('action="'.route('network.infrastructure-slots.ports.store', $slot).'"', false)
            ->assertSee('action="'.route('network.infrastructure-slots.capacity', $slot).'"', false)
            ->assertSee('name="master_node" value="'.$slot->id.'"', false)
            ->assertSee('Tambah PON')
            ->getContent();

        // Kapasitas PON: 2 pelanggan / 4 → sisa 2, ambang 50% → Red Zone.
        $this->assertMatchesRegularExpression('/data-capacity-used="2" data-capacity-total="4"/', $html);
        $this->assertStringContainsString('data-red-zone="1"', $html);
        // Slot: 1 PON terdata / kapasitas 16.
        $this->assertMatchesRegularExpression('/data-capacity-used="1" data-capacity-total="16"/', $html);
        // PON slot saudara & ODP (level bawah) tidak ikut.
        $this->assertStringNotContainsString('data-panel-port="'.$siblingPon->id.'"', $html);
        $this->assertStringNotContainsString('PON 9', $html);
        $this->assertStringNotContainsString('ODP-SATU-A', $html);
        $this->assertStringNotContainsString('data-panel-odp=', $html);
        $this->assertStringNotContainsString('<html', $html);
        // Tombol drill-down membawa panel_url PON dari server.
        $this->assertStringContainsString(e(json_encode(route('network.nodes.panel', $pon))), $html);
    }

    #[Test]
    public function panel_slot_switch_berisi_port_switch_tanpa_kapasitas_pelanggan(): void
    {
        $mini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabangA->id]);
        $switch = $this->device('switch_mini_pop', $mini, 'SW-SATU');
        $slot = $this->positions->createSlot($switch, 1);
        $port = $this->positions->createPort($slot, 4);

        $this->actingAs($this->owner)->get(route('network.nodes.panel', $slot))
            ->assertOk()
            ->assertSee('data-panel-port="'.$port->id.'"', false)
            ->assertSee('Port 4')
            ->assertSee('Tambah Port Switch')
            ->assertSee('Kapasitas belum didata')
            ->assertDontSee('Kapasitas PON (pelanggan aktif)')
            ->assertDontSee('Kelola ODP');
    }

    #[Test]
    public function panel_pon_hanya_berisi_odp_pon_itu_dengan_kapasitas_port_dan_total_pon(): void
    {
        $olt = $this->device('olt_chassis', $this->cabangA, 'OLT-SATU');
        $slot = $this->positions->createSlot($olt, 2);
        $pon = $this->positions->createPort($slot, 3, ['capacity' => 3, 'red_zone_threshold' => 90]);
        $otherPon = $this->positions->createPort($slot, 4, ['capacity' => 64]);
        $full = $this->odp($pon, 'ODP-PENUH', 2, 2);
        $empty = $this->odp($pon, 'ODP-KOSONG', 0);
        $foreign = $this->odp($otherPon, 'ODP-PON-LAIN', 8, 1);

        $html = $this->actingAs($this->cabangUser($this->cabangA))->get(route('network.nodes.panel', $pon))
            ->assertOk()
            ->assertSee('data-node-panel="pon"', false)
            ->assertSee('OLT-SATU / Slot 2 / PON 3')
            ->assertSee('data-panel-odp="'.$full->id.'"', false)
            ->assertSee('data-panel-odp="'.$empty->id.'"', false)
            ->assertSee('action="'.route('network.infrastructure-ports.capacity', $pon).'"', false)
            ->assertSee('action="'.route('network.nodes.store').'"', false)
            ->assertSee('name="parent_id" value="'.$pon->id.'"', false)
            ->assertSee('name="master_node" value="'.$pon->id.'"', false)
            ->assertSee('2 pelanggan aktif')
            ->getContent();

        // ODP penuh: 2/2 port, sisa 0, label Penuh.
        $this->assertMatchesRegularExpression('/data-capacity-used="2" data-capacity-total="2"/', $html);
        $this->assertStringContainsString('Penuh', $html);
        // ODP tanpa port: kapasitas belum didata, bukan "?"/NaN/0.
        $this->assertMatchesRegularExpression('/data-capacity-used="0" data-capacity-total=""/', $html);
        $this->assertStringContainsString('Kapasitas belum didata', $html);
        // Total PON: 2 pelanggan / 3 (sisa 1), ambang 90% → belum Red Zone.
        // (Blok kapasitas PON = div pertama setelah data-pon-total; ODP penuh
        // di bawahnya boleh Red Zone menurut ambang tipe ODP sendiri.)
        $this->assertMatchesRegularExpression('/data-pon-total>.*?data-capacity-used="2" data-capacity-total="3"/s', $html);
        $this->assertDoesNotMatchRegularExpression('/data-capacity-total="3">(?:(?!<\/div>).)*data-red-zone/s', $html);
        // ODP PON lain tidak ikut; tidak ada "?" atau NaN.
        $this->assertStringNotContainsString('data-panel-odp="'.$foreign->id.'"', $html);
        $this->assertStringNotContainsString('ODP-PON-LAIN', $html);
        $this->assertStringNotContainsString('NaN', $html);
        $this->assertDoesNotMatchRegularExpression('/>\s*\?\s*</', $html);
        // Form Tambah ODP tidak membawa penanda modal Tambah Titik.
        $this->assertStringNotContainsString('name="device_type"', $html);
    }

    #[Test]
    public function pon_tanpa_kapasitas_ditulis_belum_didata(): void
    {
        $olt = $this->device('olt_chassis', $this->cabangA, 'OLT-SATU');
        $pon = $this->positions->createPort($this->positions->createSlot($olt, 1), 1);
        $this->odp($pon, 'ODP-A', 4, 1);

        $html = $this->actingAs($this->owner)->get(route('network.nodes.panel', $pon))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-pon-total>.*?data-capacity-used="1" data-capacity-total=""/s', $html);
        $this->assertStringContainsString('Ambang Red Zone belum didata', $html);
        $this->assertStringNotContainsString('data-red-zone="1"', $html);
    }

    #[Test]
    public function jumlah_pelanggan_per_odp_sama_dengan_terpakai_pon(): void
    {
        $olt = $this->device('olt_chassis', $this->cabangA, 'OLT-SATU');
        $pon = $this->positions->createPort($this->positions->createSlot($olt, 1), 1, ['capacity' => 64]);
        $odps = collect([$this->odp($pon, 'ODP-A', 4, 3), $this->odp($pon, 'ODP-B', 8, 2), $this->odp($pon, 'ODP-C', 2)]);
        $capacity = app(PonCapacityService::class);

        $perOdp = $capacity->customersPerOdp($odps);

        $this->assertSame([$odps[0]->id => 3, $odps[1]->id => 2, $odps[2]->id => 0], $perOdp);
        $this->assertSame($capacity->summary($pon)['used'], array_sum($perOdp));
    }

    #[Test]
    public function panel_slot_dan_pon_cabang_lain_403(): void
    {
        $oltB = $this->device('olt_chassis', $this->cabangB, 'OLT-DUA');
        $slotB = $this->positions->createSlot($oltB, 1);
        $ponB = $this->positions->createPort($slotB, 1);
        $user = $this->cabangUser($this->cabangA);

        $this->actingAs($user)->get(route('network.nodes.panel', $slotB))->assertForbidden();
        $this->actingAs($user)->get(route('network.nodes.panel', $ponB))->assertForbidden();
    }

    #[Test]
    public function tipe_tanpa_panel_404(): void
    {
        $olt = $this->device('olt_chassis', $this->cabangA, 'OLT-SATU');
        $pon = $this->positions->createPort($this->positions->createSlot($olt, 1), 1);
        $odp = $this->odp($pon, 'ODP-A', 2);
        $mini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabangA->id]);
        $switchPort = $this->positions->createPort($this->positions->createSlot($this->device('switch_mini_pop', $mini, 'SW-1'), 1), 1);

        $this->actingAs($this->owner)->get(route('network.nodes.panel', $odp))->assertNotFound();
        $this->actingAs($this->owner)->get(route('network.nodes.panel', $switchPort))->assertNotFound();
    }

    #[Test]
    public function tanpa_izin_network_nodes_view_403(): void
    {
        $olt = $this->device('olt_chassis', $this->cabangA, 'OLT-SATU');
        $slot = $this->positions->createSlot($olt, 1);
        $user = User::factory()->create(['role_id' => Role::where('code', 'helpdesk')->firstOrFail()->id, 'status' => 'active']);

        $this->actingAs($user)->get(route('network.nodes.panel', $slot))->assertForbidden();
    }

    #[Test]
    public function jumlah_query_panel_konstan_terhadap_jumlah_anak(): void
    {
        // Mini POP dibuat SEBELUM permintaan pertama: scope POP user di-cache,
        // POP yang lahir sesudahnya tidak ikut sampai cache dibersihkan.
        $mini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabangA->id]);
        $user = $this->cabangUser($this->cabangA);

        // OLT: 1 Slot vs 16 Slot (berisi 16 PON tiap Slot).
        $small = $this->device('olt_chassis', $this->cabangA, 'OLT-KECIL', 16);
        $this->positions->generateStructure($small, 1, 1, 64, $this->owner);
        $large = $this->device('olt_chassis', $this->cabangA, 'OLT-BESAR', 16);
        $this->positions->generateStructure($large, 16, 16, 64, $this->owner);
        $this->assertSame($this->panelQueryCount($user, $small), $this->panelQueryCount($user, $large), 'Panel OLT: query naik mengikuti jumlah Slot.');

        // Slot: 1 PON vs 16 PON, masing-masing ber-ODP berpelanggan.
        $smallSlot = $small->slots()->first();
        $largeSlot = $large->slots()->first();
        foreach ([$smallSlot, $largeSlot] as $slot) {
            foreach ($slot->positionPorts()->get() as $pon) {
                $this->odp($pon, "ODP-{$pon->id}", 2, 1);
            }
        }
        $this->assertSame($this->panelQueryCount($user, $smallSlot), $this->panelQueryCount($user, $largeSlot), 'Panel Slot: query naik mengikuti jumlah PON.');

        // Switch Slot: 1 Port vs 24 Port (status sambungan massal, bukan per port).
        $sw = $this->device('switch_mini_pop', $mini, 'SW-Q');
        $swSmall = $this->positions->createSlot($sw, 1);
        $this->positions->createPort($swSmall, 1);
        $swLarge = $this->positions->createSlot($sw, 2);
        for ($i = 1; $i <= 24; $i++) {
            $this->positions->createPort($swLarge, $i);
        }
        $this->assertSame($this->panelQueryCount($user, $swSmall), $this->panelQueryCount($user, $swLarge), 'Panel Slot Switch: query naik mengikuti jumlah Port.');

        // PON: 1 ODP vs 30 ODP.
        $ponSmall = $smallSlot->positionPorts()->first();
        $ponLarge = $largeSlot->positionPorts()->first();
        for ($i = 0; $i < 29; $i++) {
            $this->odp($ponLarge, "ODP-BANYAK-{$i}", 2, $i % 2);
        }
        $this->assertSame(30, $ponLarge->children()->count());
        $this->assertSame($this->panelQueryCount($user, $ponSmall), $this->panelQueryCount($user, $ponLarge), 'Panel PON: query naik mengikuti jumlah ODP.');
    }

    #[Test]
    public function json_anak_pohon_membawa_panel_url_dan_jalur_dari_server(): void
    {
        $olt = $this->device('olt_chassis', $this->cabangA, 'OLT-SATU');
        $slot = $this->positions->createSlot($olt, 2, ['capacity' => 8]);
        $pon = $this->positions->createPort($slot, 5);
        $odp = $this->odp($pon, 'ODP-A', 2);
        $this->actingAs($this->owner);

        $slotRow = collect($this->getJson(route('network.nodes.children', $olt))->assertOk()->json('children'))->firstWhere('id', $slot->id);
        $this->assertSame(route('network.nodes.panel', $slot), $slotRow['panel_url']);
        $this->assertSame('OLT-SATU / Slot 2', $slotRow['path']);
        // Slot: terpakai = PON terdata, kapasitas = kapasitas Slot.
        $this->assertSame(1, $slotRow['used']);
        $this->assertSame(8, $slotRow['capacity']);

        $ponRow = collect($this->getJson(route('network.nodes.children', $slot))->json('children'))->firstWhere('id', $pon->id);
        $this->assertSame(route('network.nodes.panel', $pon), $ponRow['panel_url']);
        $this->assertSame('OLT-SATU / Slot 2 / PON 5', $ponRow['path']);

        // ODP: tidak punya panel posisi; dipilih lewat Master Node `?node=`
        // (inspector ODP, putaran UI 3) — `url` Detail tetap ada.
        $odpRow = collect($this->getJson(route('network.nodes.children', $pon))->json('children'))->firstWhere('id', $odp->id);
        $this->assertNull($odpRow['panel_url']);
        $this->assertSame(route('network.nodes.show', $odp), $odpRow['url']);
        $this->assertSame(route('network.nodes.index', ['node' => $odp->id]), $odpRow['select_url']);
        $this->assertNull($slotRow['select_url']);
        $this->assertNull($ponRow['select_url']);
    }

    #[Test]
    public function index_memilih_ulang_slot_atau_pon_dari_query_string_tanpa_memuat_posisi(): void
    {
        $olt = $this->device('olt_chassis', $this->cabangA, 'OLT-SATU');
        $slot = $this->positions->createSlot($olt, 2);
        $pon = $this->positions->createPort($slot, 5);
        $ponB = $this->positions->createPort($this->positions->createSlot($this->device('olt_chassis', $this->cabangB, 'OLT-DUA'), 1), 1);
        $user = $this->cabangUser($this->cabangA);

        $response = $this->actingAs($user)->get(route('network.nodes.index', ['node' => $pon->id]))->assertOk();
        $response->assertViewHas('selectedNodeId', $pon->id);
        $response->assertViewHas('selectedPosition', fn (?array $position): bool => $position !== null
            && $position['id'] === $pon->id
            && $position['type'] === 'olt_pon'
            && $position['label'] === 'PON 5'
            && $position['path'] === 'OLT-SATU / Slot 2 / PON 5'
            && $position['panel_url'] === route('network.nodes.panel', $pon)
            && $position['url'] === route('network.infrastructure-ports.show', $pon));
        // Posisi TIDAK masuk $nodes (fix C2) — hanya data inspector satu node.
        $this->assertSame([$olt->id], $response->viewData('nodes')->pluck('id')->all());
        $html = $response->getContent();
        $this->assertStringContainsString('data-position-inspector', $html);
        $this->assertStringNotContainsString('inspector-node-'.$pon->id.'"', $html);

        $this->get(route('network.nodes.index', ['node' => $slot->id]))->assertOk()
            ->assertViewHas('selectedPosition', fn (?array $position): bool => $position['id'] === $slot->id
                && $position['url'] === route('network.infrastructure-slots.show', $slot));

        // PON cabang lain diabaikan diam-diam (tidak bocor, tidak 403).
        $this->get(route('network.nodes.index', ['node' => $ponB->id]))->assertOk()
            ->assertViewHas('selectedPosition', null)
            ->assertViewHas('selectedNodeId', $olt->id);
    }

    #[Test]
    public function simpan_dari_panel_kembali_ke_master_node_dengan_node_terpilih(): void
    {
        $olt = $this->device('olt_chassis', $this->cabangA, 'OLT-SATU');
        $slot = $this->positions->createSlot($olt, 1);
        $pon = $this->positions->createPort($slot, 1);
        $this->actingAs($this->owner);

        $this->patch(route('network.infrastructure-ports.capacity', $pon), ['capacity' => 32, 'red_zone_threshold' => 80, 'master_node' => $pon->id])
            ->assertRedirect(route('network.nodes.index', ['node' => $pon->id]));
        $this->assertSame(32, $pon->fresh()->capacity);

        $this->post(route('network.nodes.store'), ['node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'parent_id' => $pon->id, 'code' => 'ODP-BARU', 'name' => 'ODP Baru', 'capacity' => 8, 'master_node' => $pon->id])
            ->assertRedirect(route('network.nodes.index', ['node' => $pon->id]));
        $this->assertSame($pon->id, NetworkNode::where('code', 'ODP-BARU')->value('parent_id'));

        $this->patch(route('network.infrastructure-slots.capacity', $slot), ['capacity' => 16, 'master_node' => $slot->id])
            ->assertRedirect(route('network.nodes.index', ['node' => $slot->id]));
        $this->post(route('network.infrastructure-slots.ports.store', $slot), ['number' => 2, 'master_node' => $slot->id])
            ->assertRedirect(route('network.nodes.index', ['node' => $slot->id]));
        $this->patch(route('network.nodes.capacity', $olt), ['capacity' => 4, 'master_node' => $olt->id])
            ->assertRedirect(route('network.nodes.index', ['node' => $olt->id]));
        $this->post(route('network.nodes.infrastructure-slots.store', $olt), ['number' => 3, 'master_node' => $olt->id])
            ->assertRedirect(route('network.nodes.index', ['node' => $olt->id]));

        // Tanpa `master_node` (form halaman Detail) tetap PRG ke Detail.
        $this->patch(route('network.infrastructure-ports.capacity', $pon), ['capacity' => 40, 'red_zone_threshold' => 80])
            ->assertRedirect(route('network.infrastructure-ports.show', $pon));
        // Nilai sampah tidak dipakai sebagai tujuan (bukan open redirect).
        $this->patch(route('network.infrastructure-ports.capacity', $pon), ['capacity' => 41, 'red_zone_threshold' => 80, 'master_node' => 'https://evil.test'])
            ->assertRedirect(route('network.infrastructure-ports.show', $pon));
    }
}
