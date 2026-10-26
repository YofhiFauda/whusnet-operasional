<?php

namespace Tests\Feature\Network;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructurePositionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi pasca-merge ADHOC-213 (umpan balik user 2026-10-26): klik OLT di
 * Master Node cuma menampilkan dua tautan — panel "Manajemen Perangkat"
 * (kartu Slot/PON inline + form Tambah PON) hilang karena dicabut demi fix
 * O(N²) (final review C2). Panel dikembalikan LAZY: dirender server untuk
 * SATU perangkat lewat `network.nodes.device-panel`, diambil inspector saat
 * perangkat dipilih. Tes ini menjaga endpoint (isi, scope, tipe, izin) dan
 * kaitnya di index (URL dari server, partial tidak dirender saat load).
 */
class MasterNodeDevicePanelLazyTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabangA;

    private Pop $cabangB;

    private NetworkNode $oltA;

    private NetworkNode $oltB;

    private NetworkNode $ponA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class,
            PermissionSeeder::class, NetworkNodeTypeSeeder::class, RolePermissionSeeder::class]);

        $this->cabangA = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Satu']);
        $this->cabangB = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Dua']);
        $positions = app(InfrastructurePositionService::class);
        $this->oltA = $this->device('olt_chassis', $this->cabangA, 'OLT-SATU');
        $this->oltB = $this->device('olt_chassis', $this->cabangB, 'OLT-DUA');
        $this->ponA = $positions->createPort($positions->createSlot($this->oltA, 2), 3, ['capacity' => 64]);
        $positions->createPort($positions->createSlot($this->oltB, 5), 7);
    }

    private function device(string $typeCode, Pop $pop, string $code): NetworkNode
    {
        return NetworkNode::factory()->create(['node_type_id' => NetworkNodeType::where('code', $typeCode)->value('id'),
            'anchor_pop_id' => $pop->id, 'parent_id' => null, 'code' => $code, 'name' => $code]);
    }

    private function cabangUser(Pop $cabang, string $role = 'fop'): User
    {
        $user = User::factory()->create(['role_id' => Role::where('code', $role)->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $user->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    #[Test]
    public function panel_olt_dalam_scope_berisi_slot_dan_pon_milik_olt_itu_saja(): void
    {
        $user = $this->cabangUser($this->cabangA);
        $slotA = $this->ponA->parent;

        $html = $this->actingAs($user)->get(route('network.nodes.device-panel', $this->oltA))
            ->assertOk()
            ->assertSee('id="device-components-'.$this->oltA->id.'"', false)
            ->assertSee('data-component-slot="'.$slotA->id.'"', false)
            ->assertSee('data-component-port="'.$this->ponA->id.'"', false)
            ->assertSee('Slot 2')
            ->assertSee('PON 3')
            // Form di dalam panel tetap form server biasa: target dari route(), token CSRF ada.
            ->assertSee('action="'.route('network.infrastructure-slots.ports.store', $slotA).'"', false)
            ->assertSee('action="'.route('network.nodes.capacity', $this->oltA).'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('Tambah PON')
            ->getContent();

        // Potongan HTML, bukan halaman penuh (disisipkan ke inspector).
        $this->assertStringNotContainsString('<html', $html);
        // Panel OLT lain tidak ikut.
        $this->assertStringNotContainsString('OLT-DUA', $html);
        $this->assertStringNotContainsString('Slot 5', $html);
    }

    #[Test]
    public function panel_switch_berisi_slot_dan_port_switch(): void
    {
        $positions = app(InfrastructurePositionService::class);
        $mini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabangA->id]);
        $switch = $this->device('switch_mini_pop', $mini, 'SW-SATU');
        $port = $positions->createPort($positions->createSlot($switch, 1), 4);

        $this->loginAsAdmin();
        $this->get(route('network.nodes.device-panel', $switch))
            ->assertOk()
            ->assertSee('data-component-port="'.$port->id.'"', false)
            ->assertSee('Setiap Slot memuat Port Switch.')
            ->assertDontSee('Kapasitas PON (pelanggan aktif)');
    }

    #[Test]
    public function panel_olt_cabang_lain_403(): void
    {
        $this->actingAs($this->cabangUser($this->cabangA))
            ->get(route('network.nodes.device-panel', $this->oltB))
            ->assertForbidden();
    }

    #[Test]
    public function bukan_perangkat_404(): void
    {
        $this->actingAs($this->cabangUser($this->cabangA))
            ->get(route('network.nodes.device-panel', $this->ponA))
            ->assertNotFound();
    }

    #[Test]
    public function tanpa_izin_network_nodes_view_403(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('code', 'helpdesk')->firstOrFail()->id, 'status' => 'active']);

        $this->actingAs($user)->get(route('network.nodes.device-panel', $this->oltA))->assertForbidden();
    }

    #[Test]
    public function index_merender_kait_panel_lazy_dengan_url_server_tanpa_merender_partial(): void
    {
        $user = $this->cabangUser($this->cabangA);

        $html = $this->actingAs($user)->get(route('network.nodes.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-device-panel-url="'.route('network.nodes.device-panel', $this->oltA).'"', $html);
        $this->assertStringContainsString('Manajemen Node — OLT', $html);
        $this->assertStringContainsString(route('network.nodes.generate-structure.create', $this->oltA), $html);
        // Partial TIDAK dirender saat load (itu yang membuat index O(N²)).
        $this->assertStringNotContainsString('data-component-slot=', $html);
        $this->assertStringNotContainsString('id="device-components-', $html);
        // OLT cabang lain tidak punya kait.
        $this->assertStringNotContainsString(route('network.nodes.device-panel', $this->oltB), $html);
        // Klien tidak merakit path panel dari id.
        $this->assertStringNotContainsString("'/device-panel'", $html);
        $this->assertStringNotContainsString('+ \'/device-panel', $html);
    }

    #[Test]
    public function index_memilih_ulang_node_dari_query_string_setelah_redirect(): void
    {
        $other = $this->device('olt_chassis', $this->cabangA, 'OLT-AAA');
        $user = $this->cabangUser($this->cabangA);

        // Tanpa parameter: node pertama terurut kode.
        $this->actingAs($user)->get(route('network.nodes.index'))->assertOk()
            ->assertViewHas('selectedNodeId', $other->id);
        // Dengan ?node= dalam scope: node itu yang terpilih.
        $this->get(route('network.nodes.index', ['node' => $this->oltA->id]))->assertOk()
            ->assertViewHas('selectedNodeId', $this->oltA->id);
        // ?node= di luar scope diabaikan (tidak membocorkan, tidak error).
        $this->get(route('network.nodes.index', ['node' => $this->oltB->id]))->assertOk()
            ->assertViewHas('selectedNodeId', $other->id);
    }
}
