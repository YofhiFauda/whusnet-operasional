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
 * Regresi final review ADHOC-213 (C1): setelah Slot/PON jadi node lazy, modal
 * "Tambah Titik" memfilter `kind === 'port'` yang sudah tidak ada sehingga
 * pilihan "PON induk" SELALU kosong, dan halaman Detail PON kehilangan form
 * "Hubungkan ODP" — tidak ada satu pun jalur UI untuk membuat ODP. Tes ini
 * memastikan kedua jalur ada lagi, ter-scope POP, dan targetnya dari server.
 */
class OdpCreationPathFromUiTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabangA;

    private Pop $cabangB;

    private NetworkNode $oltA;

    private NetworkNode $oltB;

    private NetworkNode $ponA;

    private NetworkNode $ponB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class,
            PermissionSeeder::class, NetworkNodeTypeSeeder::class, RolePermissionSeeder::class]);

        $this->cabangA = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Satu']);
        $this->cabangB = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Dua']);
        $positions = app(InfrastructurePositionService::class);
        $this->oltA = $this->olt($this->cabangA, 'OLT-SATU');
        $this->oltB = $this->olt($this->cabangB, 'OLT-DUA');
        $this->ponA = $positions->createPort($positions->createSlot($this->oltA, 2), 3);
        $this->ponB = $positions->createPort($positions->createSlot($this->oltB, 1), 1);
    }

    private function olt(Pop $pop, string $code): NetworkNode
    {
        return NetworkNode::factory()->create(['node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'anchor_pop_id' => $pop->id, 'parent_id' => null, 'code' => $code, 'name' => $code]);
    }

    private function cabangUser(Pop $cabang): User
    {
        $user = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $user->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    private function owner(): User
    {
        return User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
    }

    #[Test]
    public function modal_master_node_membawa_url_pilihan_pon_hanya_untuk_olt_dalam_scope(): void
    {
        $user = $this->cabangUser($this->cabangA);

        // URL dirender server (route()) di payload item perangkat — Alpine
        // tinggal memilih, tidak merakit path. OLT cabang lain tidak ada.
        $response = $this->actingAs($user)->get(route('network.nodes.index'))->assertOk()
            ->assertDontSee('OLT-DUA');
        // Payload x-data di-encode @js (garis miring jadi "\/" / "\\/");
        // normalkan dulu supaya yang dibandingkan URL-nya, bukan escaping-nya.
        $html = str_replace(['\\\\/', '\\/'], '/', $response->getContent());
        // Template URL "__ID__" yang dulu di-replace di klien sudah tidak ada.
        $this->assertStringNotContainsString('/__ID__/infrastructure-slots', $html);
        $this->assertStringNotContainsString('infrastructure-slots/__ID__/ports', $html);
        $this->assertStringContainsString(route('network.nodes.pon-options', $this->oltA), $html);
        $this->assertStringContainsString(route('network.nodes.infrastructure-slots.store', $this->oltA), $html);
        $this->assertStringNotContainsString(route('network.nodes.pon-options', $this->oltB), $html);
    }

    #[Test]
    public function pilihan_pon_berlabel_jalur_dan_terscope(): void
    {
        $user = $this->cabangUser($this->cabangA);

        $options = $this->actingAs($user)->getJson(route('network.nodes.pon-options', $this->oltA))
            ->assertOk()->json('options');
        $this->assertSame([['id' => $this->ponA->id, 'label' => 'OLT-SATU / Slot 2 / PON 3']], $options);

        // PON cabang lain tidak bisa diminta sama sekali (403), bukan cuma disaring.
        $this->actingAs($user)->getJson(route('network.nodes.pon-options', $this->oltB))->assertForbidden();
        // Bukan OLT → 404 (PON hanya milik OLT).
        $this->actingAs($user)->getJson(route('network.nodes.pon-options', $this->ponA))->assertNotFound();
    }

    #[Test]
    public function slot_untuk_form_tambah_pon_membawa_target_post_dari_server(): void
    {
        $children = $this->actingAs($this->owner())->getJson(route('network.nodes.children', $this->oltA))
            ->assertOk()->json('children');

        $this->assertCount(1, $children);
        $this->assertSame(route('network.infrastructure-slots.ports.store', $children[0]['id']), $children[0]['store_port_url']);
        $this->assertSame(route('network.nodes.children', $children[0]['id']), $children[0]['children_url']);
    }

    #[Test]
    public function halaman_pon_menampilkan_form_tambah_odp_dengan_target_server(): void
    {
        $this->actingAs($this->owner())->get(route('network.infrastructure-ports.show', $this->ponA))->assertOk()
            ->assertSee('data-add-odp-form', false)
            ->assertSee('action="'.route('network.nodes.store').'"', false)
            ->assertSee('name="parent_id" value="'.$this->ponA->id.'"', false);
    }

    #[Test]
    public function tambah_odp_dari_halaman_pon_membuat_anak_pon_lalu_ke_detail_odp(): void
    {
        $response = $this->actingAs($this->owner())
            ->from(route('network.infrastructure-ports.show', $this->ponA))
            ->post(route('network.nodes.store'), [
                'parent_id' => $this->ponA->id,
                'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
                'code' => 'odp-dari-pon',
                'name' => 'ODP Dari PON',
                'capacity' => 8,
            ]);

        $odp = NetworkNode::where('code', 'ODP-DARI-PON')->firstOrFail();
        $response->assertRedirect(route('network.nodes.show', $odp));
        $this->assertSame($this->ponA->id, $odp->parent_id);
        $this->assertNull($odp->anchor_pop_id);
        $this->assertSame(8, $odp->ports()->count());

        $this->get(route('network.infrastructure-ports.show', $this->ponA))->assertSee('ODP-DARI-PON');
    }

    #[Test]
    public function tambah_odp_tanpa_pon_ditolak(): void
    {
        $response = $this->actingAs($this->owner())
            ->from(route('network.nodes.index'))
            ->post(route('network.nodes.store'), [
                'anchor_pop_id' => $this->cabangA->id,
                'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
                'code' => 'ODP-TANPA-PON',
                'name' => 'ODP Tanpa PON',
                'capacity' => 8,
            ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertFalse(NetworkNode::where('code', 'ODP-TANPA-PON')->exists());
    }

    #[Test]
    public function tambah_odp_ke_pon_cabang_lain_ditolak(): void
    {
        $user = $this->cabangUser($this->cabangA);

        $this->actingAs($user)->post(route('network.nodes.store'), [
            'parent_id' => $this->ponB->id,
            'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'code' => 'ODP-SUSUP',
            'name' => 'ODP Susup',
            'capacity' => 8,
        ])->assertForbidden();
        $this->assertFalse(NetworkNode::where('code', 'ODP-SUSUP')->exists());
    }
}
