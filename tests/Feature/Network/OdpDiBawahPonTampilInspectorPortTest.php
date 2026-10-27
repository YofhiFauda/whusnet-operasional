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
 * Umpan balik user 2026-10-26 (putaran UI 3 ADHOC-213, screenshot image.png /
 * "image copy.png"): ODP di bawah PON yang diklik dari pohon lazy (OLT → Slot
 * → PON → ODP) atau dari panel PON tidak menampilkan inspector ODP (grid Port
 * 1..n Terpakai/Tersedia, Hubungkan, Tambah Port, modal port, tab Lokasi &
 * Relokasi Uplink) — hanya tautan Detail. Akar: ODP anak PON sengaja tidak
 * dimuat ke `$nodes` (fix C2), jadi tidak punya blok inspector.
 *
 * Perbaikan: `?node=<ODP>` memuat TEPAT SATU ODP itu ke blok inspector ODP
 * yang sama. Tes ini menjaga: inspector & penanda modal tampil, ODP/posisi
 * lain tetap tidak dimuat, query tambahan konstan, scope POP, URL pemilihan
 * dirender server (pohon & panel PON), relokasi ke PON (bukan non-PON), dan
 * PRG kembali ke ODP yang sama setelah aksi di inspector.
 */
class OdpDiBawahPonTampilInspectorPortTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabangA;

    private Pop $cabangB;

    private User $owner;

    private InfrastructurePositionService $positions;

    /** @var array<string, int> */
    private array $lastQueries = [];

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

    private function olt(Pop $pop, string $code): NetworkNode
    {
        return NetworkNode::create(['node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'anchor_pop_id' => $pop->id, 'parent_id' => null, 'code' => $code, 'name' => $code, 'status' => 'active']);
    }

    private function odp(NetworkNode $pon, string $code, int $ports, int $customers = 0, ?Pop $customerPop = null): NetworkNode
    {
        $odp = NetworkNode::create(['node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'parent_id' => $pon->id, 'anchor_pop_id' => null, 'code' => $code, 'name' => "Nama {$code}", 'status' => 'active']);
        for ($number = 1; $number <= $ports; $number++) {
            $port = NetworkNodePort::create(['network_node_id' => $odp->id, 'number' => $number, 'is_used' => false]);
            if ($number <= $customers) {
                $customer = Customer::factory()->create(['pop_id' => ($customerPop ?? $this->cabangA)->id, 'full_name' => "Pelanggan {$code}-{$number}"]);
                app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $this->owner);
            }
        }

        return $odp;
    }

    /**
     * @return array{olt: NetworkNode, slot: NetworkNode, pon: NetworkNode, odp: NetworkNode, sibling: NetworkNode}
     */
    private function structure(): array
    {
        $olt = $this->olt($this->cabangA, 'OLT-SATU');
        $slot = $this->positions->createSlot($olt, 1, ['capacity' => 16]);
        $pon = $this->positions->createPort($slot, 2, ['capacity' => 64]);
        $odp = $this->odp($pon, 'ODP-SATU-A', 4, 1);
        $sibling = $this->odp($pon, 'ODP-SATU-B', 2);

        return compact('olt', 'slot', 'pon', 'odp', 'sibling');
    }

    private function cabangUser(Pop $cabang): User
    {
        $user = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $user->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    /**
     * @param  array<string, int>  $query
     */
    private function indexQueryCount(array $query = []): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->owner)->get(route('network.nodes.index', $query))->assertOk();
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        $this->lastQueries = collect($log)->countBy('query')->sortDesc()->take(5)->all();

        return count($log);
    }

    #[Test]
    public function node_odp_di_bawah_pon_merender_inspector_port_odp_lengkap(): void
    {
        ['olt' => $olt, 'slot' => $slot, 'pon' => $pon, 'odp' => $odp, 'sibling' => $sibling] = $this->structure();
        $ports = $odp->ports()->orderBy('number')->get();

        $response = $this->actingAs($this->cabangUser($this->cabangA))
            ->get(route('network.nodes.index', ['node' => $odp->id]))->assertOk();
        $html = $response->getContent();

        // `$nodes` (sumber pohon/penghitung) TETAP tanpa ODP anak PON; hanya
        // daftar inspector yang ketambahan tepat ODP terpilih.
        $this->assertSame([$olt->id], $response->viewData('nodes')->pluck('id')->all());
        $this->assertSame([$olt->id, $odp->id], $response->viewData('inspectorNodes')->pluck('id')->all());
        $this->assertSame($odp->id, $response->viewData('selectedNodeId'));
        $this->assertNull($response->viewData('selectedPosition'));
        $this->assertSame([$olt->id, $slot->id, $pon->id], $response->viewData('treeOpenIds'));

        // Inspector ODP: header, okupansi, tab, grid port.
        $this->assertStringContainsString('id="inspector-node-'.$odp->id.'"', $html);
        $this->assertStringContainsString('Terminal Distribusi Port', $html);
        $this->assertStringContainsString('Manajemen Port &amp; Sambungan', $html);
        $this->assertStringContainsString('Lokasi &amp; Koordinat', $html);
        $this->assertStringContainsString('Relokasi Uplink', $html);
        $this->assertStringContainsString('Okupansi Port', $html);
        $this->assertMatchesRegularExpression('/>1<\/strong> \/ <strong[^>]*>4<\/strong> port/', $html);
        $this->assertStringContainsString('3 port tersedia', $html);
        // Breadcrumb jalur OLT / Slot / PON, dan POP hasil resolusi induk.
        $this->assertStringContainsString('Slot 1', $html);
        $this->assertStringContainsString('PON 2', $html);
        $this->assertStringContainsString('<strong class="text-text-main font-semibold">Cabang Satu</strong>', $html);
        // Tiap port punya kartu + target modal yang dirender server.
        foreach ($ports as $port) {
            $this->assertStringContainsString('data-odp-port="'.$port->id.'"', $html);
            $this->assertStringContainsString('data-port-update-url="'.route('network.node-ports.update', $port).'"', $html);
            $this->assertStringContainsString('data-port-assign-customer-url="'.route('network.node-ports.assign-customer', $port).'"', $html);
            $this->assertStringContainsString('data-port-assign-node-url="'.route('network.node-ports.assign-node', $port).'"', $html);
        }
        $this->assertStringContainsString('Pelanggan ODP-SATU-A-1', $html);
        $this->assertStringContainsString('Hubungkan', $html);
        $this->assertStringContainsString('action="'.route('network.nodes.ports.add', $odp).'"', $html);
        // Modal port: tidak lagi merakit URL di klien; Lepas membawa isian wajib.
        $this->assertStringNotContainsString("'/network/node-ports/'", $html);
        $this->assertStringContainsString('data-port-release-form', $html);
        $this->assertStringContainsString('name="physical_removal_confirmed"', $html);
        $this->assertStringContainsString('name="release_note"', $html);
        // Relokasi ODP: field PON bercari (server), bukan select semua OLT/PON.
        $this->assertStringContainsString('data-odp-relocate data-pon-search-url="'.route('network.nodes.pon-search').'"', $html);
        $this->assertStringContainsString('action="'.route('network.nodes.move', $odp).'"', $html);

        // ODP saudara & posisi tetap tidak dimuat.
        $this->assertStringNotContainsString('inspector-node-'.$sibling->id.'"', $html);
        $this->assertStringNotContainsString('ODP-SATU-B', $html);
        $this->assertStringNotContainsString('inspector-node-'.$pon->id.'"', $html);
        $this->assertStringNotContainsString('inspector-node-'.$slot->id.'"', $html);
        $this->assertSame(1, substr_count($html, 'id="inspector-node-'.$odp->id.'"'));
    }

    #[Test]
    public function memilih_odp_di_bawah_pon_hanya_menambah_query_konstan(): void
    {
        // Kecil: 1 OLT / 1 Slot / 1 PON / 1 ODP 2 port.
        $small = $this->olt($this->cabangA, 'OLT-KECIL');
        $smallPon = $this->positions->createPort($this->positions->createSlot($small, 1), 1);
        $smallOdp = $this->odp($smallPon, 'ODP-KECIL', 2, 1);
        $this->indexQueryCount(); // pemanasan cache izin/scope
        $smallDelta = $this->indexQueryCount(['node' => $smallOdp->id]) - $this->indexQueryCount();

        // Besar: 16 Slot × 16 PON + 30 ODP (ODP terpilih 16 port, 5 pelanggan).
        $big = $this->olt($this->cabangA, 'OLT-BESAR');
        $this->positions->generateStructure($big, 16, 16, 64, $this->owner);
        $pons = NetworkNode::query()->where('code', 'like', 'OLT-BESAR-%')
            ->whereHas('nodeType', fn ($query) => $query->where('code', 'olt_pon'))->get();
        $this->assertCount(256, $pons);
        $odpTypeId = NetworkNodeType::where('code', 'odp')->value('id');
        $pons->take(29)->each(fn (NetworkNode $pon, int $i) => NetworkNode::create([
            'node_type_id' => $odpTypeId, 'parent_id' => $pon->id, 'anchor_pop_id' => null,
            'code' => "ODP-BESAR-{$i}", 'name' => "ODP Besar {$i}", 'status' => 'active',
        ]));
        $bigOdp = $this->odp($pons->last(), 'ODP-BESAR-PILIH', 16, 5);

        $withoutSelection = $this->indexQueryCount();
        $withSelection = $this->indexQueryCount(['node' => $bigOdp->id]);
        $bigDelta = $withSelection - $withoutSelection;

        $this->assertLessThanOrEqual($smallDelta + 2, $bigDelta,
            "Memilih ODP menambah {$bigDelta} query di struktur 16x16+30 ODP vs {$smallDelta} di struktur kecil. Teratas: ".json_encode($this->lastQueries));
        $this->assertLessThanOrEqual(25, $bigDelta, "Tambahan query memilih ODP {$bigDelta}, mestinya konstan kecil.");

        $html = $this->actingAs($this->owner)->get(route('network.nodes.index', ['node' => $bigOdp->id]))->assertOk()->getContent();
        $this->assertStringContainsString('id="inspector-node-'.$bigOdp->id.'"', $html);
        $this->assertSame(16, substr_count($html, 'data-odp-port="'));
        $this->assertStringNotContainsString('ODP-BESAR-0"', $html);
        $this->assertLessThan(600_000, strlen($html));
    }

    #[Test]
    public function node_odp_cabang_lain_tidak_dirender_dan_tidak_bocor(): void
    {
        $this->structure();
        $oltB = $this->olt($this->cabangB, 'OLT-DUA');
        $ponB = $this->positions->createPort($this->positions->createSlot($oltB, 1), 1);
        $odpB = $this->odp($ponB, 'ODP-RAHASIA-B', 2, 1, $this->cabangB);

        $response = $this->actingAs($this->cabangUser($this->cabangA))
            ->get(route('network.nodes.index', ['node' => $odpB->id]))->assertOk();
        $html = $response->getContent();

        $this->assertNotContains($odpB->id, $response->viewData('inspectorNodes')->pluck('id')->all());
        $this->assertNotSame($odpB->id, $response->viewData('selectedNodeId'));
        $this->assertSame([], $response->viewData('treeOpenIds'));
        $this->assertStringNotContainsString('inspector-node-'.$odpB->id.'"', $html);
        $this->assertStringNotContainsString('ODP-RAHASIA-B', $html);
        $this->assertStringNotContainsString('OLT-DUA', $html);
    }

    #[Test]
    public function pohon_dan_panel_pon_membawa_url_pilih_odp_dari_server(): void
    {
        ['slot' => $slot, 'pon' => $pon, 'odp' => $odp, 'sibling' => $sibling] = $this->structure();
        $this->actingAs($this->owner);

        $rows = collect($this->getJson(route('network.nodes.children', $pon))->assertOk()->json('children'));
        $this->assertSame(route('network.nodes.index', ['node' => $odp->id]), $rows->firstWhere('id', $odp->id)['select_url']);
        $this->assertSame(route('network.nodes.index', ['node' => $sibling->id]), $rows->firstWhere('id', $sibling->id)['select_url']);
        // Slot/PON tetap dipilih lewat panel, bukan select_url.
        $ponRow = collect($this->getJson(route('network.nodes.children', $slot))->json('children'))->firstWhere('id', $pon->id);
        $this->assertNull($ponRow['select_url']);

        // Template pohon lazy memakai select_url (tidak merakit path dari id).
        $index = $this->get(route('network.nodes.index'))->assertOk()->getContent();
        $this->assertStringContainsString(':href="child.select_url"', $index);
        $this->assertStringContainsString('openOdp(child)', $index);
        $this->assertStringContainsString('treeOpenIds.includes(child.id)', $index);

        // Panel PON di Master Node: tiap ODP punya "Kelola Port" ke index?node=.
        $panel = $this->get(route('network.nodes.panel', $pon))->assertOk()->getContent();
        foreach ([$odp, $sibling] as $row) {
            $this->assertStringContainsString('href="'.e(route('network.nodes.index', ['node' => $row->id])).'" data-select-odp="'.$row->id.'"', $panel);
        }
    }

    #[Test]
    public function inspector_odp_dapat_dimuat_tanpa_refresh_dan_tetap_terbatas_pop(): void
    {
        ['pon' => $pon, 'odp' => $odp] = $this->structure();
        $oltB = $this->olt($this->cabangB, 'OLT-TERBATAS');
        $ponB = $this->positions->createPort($this->positions->createSlot($oltB, 1), 1);
        $odpB = $this->odp($ponB, 'ODP-TERBATAS', 2);
        $this->actingAs($this->cabangUser($this->cabangA));

        $children = collect($this->getJson(route('network.nodes.children', $pon))->assertOk()->json('children'));
        $this->assertSame(route('network.nodes.inspector', $odp), $children->firstWhere('id', $odp->id)['inspector_url']);

        $this->get(route('network.nodes.inspector', $odp))
            ->assertOk()
            ->assertSee('id="inspector-node-'.$odp->id.'"', false)
            ->assertSee('data-odp-port=', false)
            ->assertDontSee('inspector-node-'.$odpB->id, false);

        $index = $this->get(route('network.nodes.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Penghuni Port Aktif', $index);
        $this->assertStringContainsString('fetch(child.inspector_url', $index);
        $this->assertStringNotContainsString('window.location.assign(child.select_url)', $index);

        $this->get(route('network.nodes.inspector', $odpB))->assertNotFound();
    }

    #[Test]
    public function node_pon_membuka_cabang_leluhur_di_pohon(): void
    {
        ['olt' => $olt, 'slot' => $slot, 'pon' => $pon] = $this->structure();

        $this->actingAs($this->owner)->get(route('network.nodes.index', ['node' => $pon->id]))->assertOk()
            ->assertViewHas('treeOpenIds', [$olt->id, $slot->id]);
    }

    #[Test]
    public function relokasi_uplink_odp_ke_pon_lain_dan_menolak_induk_non_pon(): void
    {
        ['olt' => $olt, 'slot' => $slot, 'pon' => $pon, 'odp' => $odp] = $this->structure();
        $targetPon = $this->positions->createPort($slot, 5);
        $user = $this->cabangUser($this->cabangA);
        $this->actingAs($user);

        // PON tujuan bisa ditemukan lewat pencarian yang dipakai field relokasi.
        $options = collect($this->getJson(route('network.nodes.pon-search', ['q' => 'OLT-SATU']))->assertOk()->json('options'));
        $this->assertTrue($options->contains(fn (array $option): bool => $option['id'] === $targetPon->id && $option['label'] === 'OLT-SATU / Slot 1 / PON 5'));

        $this->patch(route('network.nodes.move', $odp), ['parent_id' => $targetPon->id, 'master_node' => $odp->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('network.nodes.index', ['node' => $odp->id]));
        $this->assertSame($targetPon->id, $odp->fresh()->parent_id);

        // Setelah relokasi, ?node= masih merender inspector ODP di PON barunya.
        $this->get(route('network.nodes.index', ['node' => $odp->id]))->assertOk()
            ->assertSee('id="inspector-node-'.$odp->id.'"', false)
            ->assertSee('PON 5');

        foreach ([$slot, $olt] as $notPon) {
            $this->from(route('network.nodes.index', ['node' => $odp->id]))
                ->patch(route('network.nodes.move', $odp), ['parent_id' => $notPon->id, 'master_node' => $odp->id])
                ->assertSessionHasErrors('parent_id');
            $this->assertSame($targetPon->id, $odp->fresh()->parent_id);
        }
    }

    #[Test]
    public function aksi_port_dari_inspector_kembali_ke_odp_yang_sama(): void
    {
        ['odp' => $odp] = $this->structure();
        $this->actingAs($this->cabangUser($this->cabangA));
        $back = route('network.nodes.index', ['node' => $odp->id]);

        // Tambah Port.
        $this->post(route('network.nodes.ports.add', $odp), ['additional_capacity' => 2, 'master_node' => $odp->id])
            ->assertRedirect($back);
        $this->assertSame(6, $odp->ports()->count());

        // Tandai Manual.
        $free = $odp->ports()->where('is_used', false)->orderBy('number')->firstOrFail();
        $this->put(route('network.node-ports.update', $free), ['is_used' => 1, 'master_node' => $odp->id])
            ->assertRedirect($back);
        $this->assertTrue($free->fresh()->is_used);

        // Lepas / Putus Port dengan isian form modal (konfirmasi fisik + catatan).
        $used = $odp->ports()->where('number', 1)->firstOrFail();
        $this->from($back)->put(route('network.node-ports.update', $used), [
            'is_used' => 0, 'physical_removal_confirmed' => 1, 'release_note' => 'Pelepasan port kabel fisik',
        ])->assertSessionHasNoErrors()->assertRedirect($back);
        $this->assertFalse($used->fresh()->is_used);
    }
}
