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
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pencarian PON untuk modal "Tambah ODP" (umpan balik user 2026-10-26: satu
 * field PON bercari, bukan OLT → PON dua langkah). Satu deploy bisa punya
 * ribuan PON, jadi pencarian di server, dibatasi 20 hasil, ter-scope POP
 * lewat definisi yang sama dengan `authorizeNodeScope()` (`inPops`).
 */
class PonSearchTypeaheadTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabangA;

    private Pop $cabangB;

    private NetworkNode $oltA;

    private NetworkNode $oltB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class,
            PermissionSeeder::class, NetworkNodeTypeSeeder::class, RolePermissionSeeder::class]);

        $this->cabangA = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Satu']);
        $this->cabangB = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Dua']);
        $this->oltA = $this->olt($this->cabangA, 'OLT-SATU', 'Siman Utara');
        $this->oltB = $this->olt($this->cabangB, 'OLT-DUA', 'Jetis Barat');
    }

    private function olt(Pop $pop, string $code, string $name): NetworkNode
    {
        return NetworkNode::create(['node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'anchor_pop_id' => $pop->id, 'parent_id' => null, 'code' => $code, 'name' => $name, 'status' => 'active']);
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
     * @return array<int, array{id: int, label: string}>
     */
    private function search(User $user, ?string $q = null): array
    {
        return $this->actingAs($user)
            ->getJson(route('network.nodes.pon-search', $q === null ? [] : ['q' => $q]))
            ->assertOk()
            ->json('options');
    }

    #[Test]
    public function hasil_berlabel_jalur_dan_hanya_pon_dalam_scope(): void
    {
        $positions = app(InfrastructurePositionService::class);
        $ponA = $positions->createPort($positions->createSlot($this->oltA, 2), 3);
        $ponB = $positions->createPort($positions->createSlot($this->oltB, 1), 1);

        $options = $this->search($this->cabangUser($this->cabangA));

        $this->assertSame([['id' => $ponA->id, 'label' => 'OLT-SATU / Slot 2 / PON 3']], $options);
        $this->assertNotContains($ponB->id, array_column($options, 'id'));
        // Cari eksplisit kode OLT cabang lain pun tetap kosong.
        $this->assertSame([], $this->search($this->cabangUser($this->cabangA), 'OLT-DUA'));
    }

    #[Test]
    public function filter_q_cocok_ke_kode_pon_turunan_serta_kode_dan_nama_olt(): void
    {
        $positions = app(InfrastructurePositionService::class);
        $slotA = $positions->createSlot($this->oltA, 1);
        $pon1 = $positions->createPort($slotA, 1);
        $pon2 = $positions->createPort($slotA, 2);
        $ponB = $positions->createPort($positions->createSlot($this->oltB, 1), 2);
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        // Kode turunan `{OLT}-S{n}-P{m}`.
        $this->assertSame([$pon2->id], array_column($this->search($owner, 'OLT-SATU-S1-P2'), 'id'));
        // Nama OLT (bukan bagian kode).
        $this->assertSame([$ponB->id], array_column($this->search($owner, 'jetis'), 'id'));
        // Kode OLT → semua PON-nya, urut Slot lalu PON.
        $this->assertSame([$pon1->id, $pon2->id], array_column($this->search($owner, 'olt-satu'), 'id'));
        // Tanpa q → semua dalam scope (owner = semua POP), urut kode OLT.
        $this->assertSame([$ponB->id, $pon1->id, $pon2->id], array_column($this->search($owner), 'id'));
        // Karakter wildcard LIKE diperlakukan literal.
        $this->assertSame([], $this->search($owner, '%'));
    }

    #[Test]
    public function hasil_dibatasi_20_dan_jumlah_query_tidak_tumbuh_dengan_jumlah_pon(): void
    {
        $positions = app(InfrastructurePositionService::class);
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $user = $this->cabangUser($this->cabangA);
        $positions->generateStructure($this->oltA, 1, 2, null, $owner);
        $this->search($user);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->search($user);
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();

        $positions->generateStructure($this->oltA, 4, 16, null, $owner);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $options = $this->search($user);
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(20, $options);
        $this->assertSame('OLT-SATU / Slot 1 / PON 1', $options[0]['label']);
        $this->assertSame($small, $large, 'Jumlah query pencarian PON tidak boleh bergantung pada jumlah PON (N+1).');
    }

    #[Test]
    public function scope_kosong_tidak_mengembalikan_apa_pun(): void
    {
        $positions = app(InfrastructurePositionService::class);
        $positions->createPort($positions->createSlot($this->oltA, 1), 1);
        $user = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        $this->assertSame([], $this->search($user));
    }

    #[Test]
    public function tanpa_izin_network_nodes_view_403(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('code', 'helpdesk')->firstOrFail()->id, 'status' => 'active']);

        $this->actingAs($user)->getJson(route('network.nodes.pon-search'))->assertForbidden();
    }
}
