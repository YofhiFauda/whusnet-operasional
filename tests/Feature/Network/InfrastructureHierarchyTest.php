<?php

namespace Tests\Feature\Network;

use App\Enums\InfrastructurePortKind;
use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
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
use App\Services\InfrastructureHierarchyService;
use App\Services\InfrastructureMapService;
use App\Services\InfrastructurePositionService;
use App\Services\NetworkPortAssignmentService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proyeksi hierarki tunggal (§1, §5, Task 10): Server → POP → Mini POP →
 * OLT → Slot → PON → ODP → ONT dan Mini POP → Switch → Slot → Port. Satu
 * sumber untuk Master Node, Peta, dan detail posisi; berscope POP aktor.
 */
class InfrastructureHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pusat;

    private Pop $cabangA;

    private Pop $cabangB;

    private Pop $miniA;

    private Pop $miniB;

    private NetworkNode $server;

    private NetworkNode $olt1;

    private NetworkNode $olt2;

    private NetworkNode $switch;

    private InfrastructureSlot $slotKosong;

    private InfrastructurePort $pon;

    private InfrastructurePort $switchPort;

    private NetworkNode $odpVerified;

    private NetworkNode $odpUnverified;

    private NetworkNode $odpLain;

    private Customer $customerDuaOnt;

    private Customer $customerTanpaSn;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        $this->pusat = Pop::factory()->create(['type' => 'pusat', 'name' => 'WHUSNET']);
        $this->cabangA = Pop::factory()->create(['type' => 'cabang', 'name' => 'Jetis', 'parent_id' => $this->pusat->id]);
        $this->cabangB = Pop::factory()->create(['type' => 'cabang', 'name' => 'Sandya', 'parent_id' => $this->pusat->id]);
        $this->miniA = Pop::factory()->create(['type' => 'mini_pop', 'name' => 'C1', 'parent_id' => $this->cabangA->id]);
        $this->miniB = Pop::factory()->create(['type' => 'mini_pop', 'name' => 'D1', 'parent_id' => $this->cabangB->id]);

        $positions = app(InfrastructurePositionService::class);
        $connections = app(InfrastructureConnectionService::class);
        $this->server = $this->node('server', $this->pusat, 'SRV-UTAMA');
        $this->olt1 = $this->node('olt_chassis', $this->miniA, 'OLT-1');
        $this->olt2 = $this->node('olt_chassis', $this->miniA, 'OLT-2');
        $this->switch = $this->node('switch_mini_pop', $this->miniA, 'SW-1');
        $slot = $positions->createSlot($this->olt1, 1);
        $this->slotKosong = $positions->createSlot($this->olt1, 2);
        $this->pon = $positions->createPort($slot, 1, InfrastructurePortKind::PON, ['capacity' => 10, 'red_zone_threshold' => 80]);
        $this->switchPort = $positions->createPort($positions->ensureLogicalSlot($this->switch), 1, InfrastructurePortKind::SWITCH);
        $connections->connect($this->switchPort, $this->owner, targetLabel: 'Radio PTP');
        $this->odpVerified = $this->node('odp', $this->miniA, 'ODP-1');
        $this->odpUnverified = $this->node('odp', $this->miniA, 'ODP-2');
        $connections->connect($this->pon, $this->owner, targetNode: $this->odpVerified, verified: true);
        $connections->connect($this->pon, $this->owner, targetNode: $this->odpUnverified);
        $this->odpLain = $this->node('odp', $this->miniB, 'ODP-SANDYA');

        $this->customerDuaOnt = $this->customerOn($this->odpVerified, 1, 'Pelanggan Dua ONT');
        $this->customerTanpaSn = $this->customerOn($this->odpVerified, 2, 'Pelanggan Tanpa SN');
        $item = Item::create(['code' => 'ONT-X', 'name' => 'ONT X', 'unit' => 'pcs', 'item_category_id' => ItemCategory::where('code', 'modem_ont')->value('id'),
            'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        foreach (['ONT-SN-1', 'ONT-SN-2'] as $sn) {
            InventorySerial::create(['item_id' => $item->id, 'serial_number' => $sn, 'status' => SerialStatus::INSTALLED->value, 'customer_id' => $this->customerDuaOnt->id]);
        }
    }

    private function node(string $typeCode, Pop $anchor, string $code): NetworkNode
    {
        return NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', $typeCode)->firstOrFail()->id,
            'anchor_pop_id' => $anchor->id, 'code' => $code, 'name' => $code,
        ]);
    }

    private function customerOn(NetworkNode $odp, int $portNumber, string $name): Customer
    {
        $port = NetworkNodePort::create(['network_node_id' => $odp->id, 'number' => $portNumber]);
        $customer = Customer::factory()->create(['pop_id' => $this->cabangA->id, 'full_name' => $name]);
        app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $this->owner);

        return $customer;
    }

    /**
     * @return array<string, mixed>
     */
    private function projection(?User $user = null): array
    {
        return app(InfrastructureHierarchyService::class)->build($user ?? $this->owner);
    }

    private function cabangUser(Pop $cabang, string $role = 'sales'): User
    {
        $user = User::factory()->create(['role_id' => Role::where('code', $role)->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $user->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    #[Test]
    public function server_tampil_di_atas_pop_pusat_tanpa_siklus(): void
    {
        $p = $this->projection();

        $this->assertContains('node:'.$this->server->id, $p['roots']);
        $this->assertSame('node:'.$this->server->id, $p['items']['pop:'.$this->pusat->id]['parent']);
        $this->assertNull($p['items']['node:'.$this->server->id]['parent']);
    }

    #[Test]
    public function jalur_olt_lengkap_sampai_ont_dan_switch_berhenti_di_port(): void
    {
        $p = $this->projection();

        $this->assertSame('pop:'.$this->cabangA->id, $p['items']['pop:'.$this->miniA->id]['parent']);
        // Banyak OLT/Switch per Mini POP; ODP ber-uplink PON pindah ke bawah
        // PON (bukan anak langsung Mini POP lagi).
        $this->assertEqualsCanonicalizing(
            ['node:'.$this->olt1->id, 'node:'.$this->olt2->id, 'node:'.$this->switch->id],
            $p['children']['pop:'.$this->miniA->id],
        );
        $this->assertSame('slot:'.$this->pon->infrastructure_slot_id, $p['items']['port:'.$this->pon->id]['parent']);
        $this->assertSame('port:'.$this->pon->id, $p['items']['node:'.$this->odpVerified->id]['parent']);
        $this->assertSame('connection', $p['items']['node:'.$this->odpVerified->id]['relation']);
        $this->assertSame('node:'.$this->odpVerified->id, $p['items']['customer:'.$this->customerDuaOnt->id]['parent']);
        $this->assertSame([], $p['children']['port:'.$this->switchPort->id] ?? []);
        $this->assertSame('Radio PTP', $p['items']['port:'.$this->switchPort->id]['connection_label']);
        // Breadcrumb operasional mulai dari Cabang (sama dengan InfrastructureLocation).
        $this->assertSame('Jetis → C1 → OLT-1 → Slot 1 → PON 1 → ODP-1', $p['items']['node:'.$this->odpVerified->id]['breadcrumb']);
    }

    #[Test]
    public function slot_kosong_dan_koneksi_belum_terverifikasi_ditandai(): void
    {
        $p = $this->projection();

        $this->assertArrayHasKey('slot:'.$this->slotKosong->id, $p['items']);
        $this->assertSame('Belum didata', $p['items']['slot:'.$this->slotKosong->id]['status_label']);
        $this->assertFalse($p['items']['node:'.$this->odpUnverified->id]['verified']);
        $this->assertTrue($p['items']['node:'.$this->odpVerified->id]['verified']);
    }

    #[Test]
    public function kapasitas_bersatuan_dan_pon_banyak_odp(): void
    {
        $p = $this->projection();
        $pon = $p['items']['port:'.$this->pon->id];

        $this->assertCount(2, $p['children']['port:'.$this->pon->id]);
        $this->assertSame(2, $pon['capacity']['used']);
        $this->assertSame(10, $pon['capacity']['capacity']);
        $this->assertSame('pelanggan aktif', $pon['capacity']['unit_label']);
        $this->assertSame('port pelanggan', $p['items']['node:'.$this->odpVerified->id]['capacity']['unit_label']);
        $this->assertSame(2, $p['items']['node:'.$this->odpVerified->id]['capacity']['used']);
    }

    #[Test]
    public function olt_menampilkan_kapasitas_slot_chassis_terisi_kartu(): void
    {
        // Keputusan user 21-10-2026: Slot = lubang kartu fisik di chassis,
        // tampil sebagai baris pohon; OLT cukup menampilkan angka slot terisi.
        $item = Item::create(['code' => 'KARTU-KAP', 'name' => 'Kartu GPON', 'unit' => 'pcs', 'item_category_id' => ItemCategory::where('code', 'olt_module')->value('id'),
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset', 'infrastructure_location_kind' => 'slot']);
        InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'SN-KAP-1', 'status' => SerialStatus::DEPLOYED->value, 'current_infrastructure_slot_id' => $this->pon->infrastructure_slot_id]);
        $this->olt1->update(['capacity' => 16]);

        $capacity = $this->projection()['items']['node:'.$this->olt1->id]['capacity'];

        $this->assertSame(1, $capacity['used']);
        $this->assertSame(16, $capacity['capacity']);
        $this->assertSame('slot terisi kartu', $capacity['unit_label']);
        $this->assertNull($this->projection()['items']['node:'.$this->switch->id]['capacity']['capacity']);
    }

    #[Test]
    public function semua_ont_satu_pelanggan_terlihat_dan_tanpa_sn_diberi_label(): void
    {
        $p = $this->projection();

        $this->assertSame(['ONT-SN-1', 'ONT-SN-2'], array_column($p['items']['customer:'.$this->customerDuaOnt->id]['onts'], 'serial_number'));
        $this->assertSame([], $p['items']['customer:'.$this->customerTanpaSn->id]['onts']);
        $this->assertSame('ONT belum tercatat', $p['items']['customer:'.$this->customerTanpaSn->id]['ont_label']);
    }

    #[Test]
    public function slot_port_dan_ont_tidak_punya_koordinat_sendiri(): void
    {
        $p = $this->projection();

        foreach (['slot:'.$this->slotKosong->id, 'port:'.$this->pon->id, 'customer:'.$this->customerDuaOnt->id] as $key) {
            $this->assertFalse($p['items'][$key]['has_pin'], $key);
        }
        $this->assertTrue($p['items']['pop:'.$this->miniA->id]['has_pin']);
    }

    #[Test]
    public function aktor_satu_cabang_tidak_melihat_cabang_lain_dan_sn_ditutup_tanpa_izin_sensitif(): void
    {
        $user = $this->cabangUser($this->cabangA);
        $this->assertFalse($user->hasPermission('customers.detail.devices.view_sensitive'), 'Fixture: aktor tanpa izin SN sensitif.');
        $p = $this->projection($user);

        $this->assertArrayNotHasKey('pop:'.$this->cabangB->id, $p['items']);
        $this->assertArrayNotHasKey('node:'.$this->odpLain->id, $p['items']);
        $this->assertArrayNotHasKey('node:'.$this->server->id, $p['items']);
        $this->assertSame([], $p['items']['customer:'.$this->customerDuaOnt->id]['onts']);
        $this->assertTrue($p['items']['customer:'.$this->customerDuaOnt->id]['onts_hidden']);
    }

    #[Test]
    public function metrik_rekonsiliasi_per_cabang(): void
    {
        $metrics = app(InfrastructureHierarchyService::class)->metricsByCabang($this->owner);

        $this->assertSame(['odp' => 2, 'customers' => 2, 'used_ports' => 2, 'assets' => 0], $metrics[$this->cabangA->id]);
        $this->assertSame(['odp' => 1, 'customers' => 0, 'used_ports' => 0, 'assets' => 0], $metrics[$this->cabangB->id]);
    }

    #[Test]
    public function master_node_merender_proyeksi_hierarki(): void
    {
        $this->withoutVite();

        $this->actingAs($this->owner)->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee('data-hierarchy-key="port:'.$this->pon->id.'"', false)
            ->assertSee('data-hierarchy-key="slot:'.$this->slotKosong->id.'"', false)
            ->assertSee('data-hierarchy-key="customer:'.$this->customerTanpaSn->id.'"', false)
            ->assertSee('ONT belum tercatat');
    }

    #[Test]
    public function master_node_cabang_lain_tidak_bocor(): void
    {
        $this->withoutVite();

        $this->actingAs($this->cabangUser($this->cabangA, 'fop'))->get(route('network.nodes.index'))
            ->assertOk()
            ->assertDontSee('ODP-SANDYA')
            ->assertDontSee('data-hierarchy-key="pop:'.$this->cabangB->id.'"', false);
    }

    #[Test]
    public function detail_port_menampilkan_breadcrumb_kapasitas_dan_koneksi_tanpa_mutasi(): void
    {
        $this->withoutVite();

        $this->actingAs($this->owner)->get(route('network.infrastructure-ports.show', $this->pon))
            ->assertOk()
            ->assertSee('Jetis → C1 → OLT-1 → Slot 1 → PON 1')
            ->assertSee('pelanggan aktif')
            ->assertSee('ODP-1')
            ->assertSee('belum terverifikasi');
    }

    #[Test]
    public function aksi_tambah_kontekstual_perangkat_slot_pon_odp(): void
    {
        $this->withoutVite();
        $this->actingAs($this->owner)->get(route('network.nodes.show', $this->olt2))->assertOk()->assertSee('Slot perangkat');

        $this->actingAs($this->owner)->post(route('network.nodes.infrastructure-slots.store', $this->olt2), ['number' => 3])
            ->assertRedirect(route('network.nodes.show', $this->olt2));
        $slot = InfrastructureSlot::where('network_node_id', $this->olt2->id)->where('number', 3)->sole();

        $this->actingAs($this->owner)->post(route('network.infrastructure-slots.ports.store', $slot), ['number' => 1, 'capacity' => 64, 'red_zone_threshold' => 85])
            ->assertRedirect(route('network.infrastructure-slots.show', $slot));
        $pon = InfrastructurePort::where('infrastructure_slot_id', $slot->id)->sole();
        $this->assertSame(64, $pon->capacity);

        $odp = $this->node('odp', $this->miniA, 'ODP-BARU');
        $this->actingAs($this->owner)->post(route('network.infrastructure-ports.connections.store', $pon), ['target_network_node_id' => $odp->id, 'verified' => 1])
            ->assertRedirect(route('network.infrastructure-ports.show', $pon));
        $this->assertSame($pon->id, app(InfrastructureConnectionService::class)->activeUplinkFor($odp)?->source_infrastructure_port_id);

        $this->actingAs($this->owner)->post(route('network.infrastructure-ports.connections.store', $this->pon), ['target_network_node_id' => $odp->id])
            ->assertSessionHasErrors('target_network_node_id');

        $this->actingAs($this->owner)->post(route('network.infrastructure-slots.ports.store', $slot), ['number' => 1])
            ->assertSessionHasErrors('number');
    }

    #[Test]
    public function aksi_tambah_slot_cabang_lain_ditolak(): void
    {
        $this->actingAs($this->cabangUser($this->cabangB, 'fop'))
            ->post(route('network.nodes.infrastructure-slots.store', $this->olt2), ['number' => 5])
            ->assertForbidden();
    }

    #[Test]
    public function detail_slot_dan_port_cabang_lain_ditolak(): void
    {
        $user = $this->cabangUser($this->cabangB, 'fop');

        $this->actingAs($user)->get(route('network.infrastructure-ports.show', $this->pon))->assertForbidden();
        $this->actingAs($user)->get(route('network.infrastructure-slots.show', $this->slotKosong))->assertForbidden();
    }

    #[Test]
    public function peta_memakai_proyeksi_yang_sama(): void
    {
        $this->withoutVite();
        $map = app(InfrastructureMapService::class)->build($this->owner, null, 'path');

        // Keputusan user 21-10-2026: Slot dan PON instance kanvas sendiri
        // (ID dinamespace negatif), rantai lengkap OLT→Slot→PON→ODP.
        $ponCanvasId = -3_000_000_000 - $this->pon->id;
        $slotCanvasId = -2_000_000_000 - $this->pon->infrastructure_slot_id;
        $slotKosongCanvasId = -2_000_000_000 - $this->slotKosong->id;
        $this->assertSame($ponCanvasId, $map['topologyDetails'][$this->odpVerified->id]['parent_id']);
        $this->assertSame('connection', $map['topologyDetails'][$this->odpVerified->id]['relation']);
        $this->assertFalse($map['topologyDetails'][$this->odpUnverified->id]['verified']);
        $this->assertSame($this->server->id, $map['topologyDetails'][-$this->pusat->id]['parent_id']);
        $this->assertSame('PON 1', $map['topologyDetails'][$this->odpVerified->id]['via_port']);
        $this->assertSame(['Slot 1', 'Slot 2'], array_column($map['topologyDetails'][$this->olt1->id]['slots'], 'label'));
        $this->assertSame($this->olt1->id, $map['topologyDetails'][$slotCanvasId]['parent_id']);
        $this->assertSame($slotCanvasId, $map['topologyDetails'][$ponCanvasId]['parent_id']);
        $this->assertSame($this->olt1->id, $map['topologyDetails'][$slotKosongCanvasId]['parent_id']);
    }

    #[Test]
    public function detail_pelanggan_menampilkan_lokasi_jaringan_tepat(): void
    {
        $response = $this->actingAs($this->owner)->get(route('customers.show', $this->customerDuaOnt));

        $response->assertOk()
            ->assertSee($this->olt1->code)
            ->assertSee($this->pon->displayName())
            ->assertSee($this->odpVerified->code)
            ->assertSee('Port 1')
            ->assertDontSee('Uplink PON');
    }

    #[Test]
    public function detail_pelanggan_tanpa_uplink_pon_tandai_belum_terhubung(): void
    {
        $customer = $this->customerOn($this->odpUnverified, 1, 'Pelanggan ODP Belum Verifikasi');

        $response = $this->actingAs($this->owner)->get(route('customers.show', $customer));

        $response->assertOk()
            ->assertSee($this->odpUnverified->code)
            ->assertSee('Uplink PON→ODP belum terverifikasi');
    }

    #[Test]
    public function inventaris_peta_menunjukkan_aset_pada_posisi_tepat(): void
    {
        $item = Item::create(['code' => 'KARTU', 'name' => 'Kartu GPON', 'unit' => 'pcs', 'item_category_id' => ItemCategory::where('code', 'olt_module')->value('id'),
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset', 'infrastructure_location_kind' => 'slot']);
        InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'SN-KARTU-1', 'status' => SerialStatus::DEPLOYED->value, 'current_infrastructure_slot_id' => $this->pon->infrastructure_slot_id]);
        InventorySerial::create(['item_id' => $item->id, 'serial_number' => 'SN-RAK-1', 'status' => SerialStatus::DEPLOYED->value, 'current_mini_pop_id' => $this->miniA->id]);

        $map = app(InfrastructureMapService::class)->build($this->owner, null, 'path');

        // Aset di Slot kini tampil di kartu Slot itu sendiri (bukan diringkas
        // ke panel perangkat) — Slot instance kanvas sendiri sejak keputusan
        // user 21-10-2026.
        $slotCanvasId = -2_000_000_000 - $this->pon->infrastructure_slot_id;
        $oltAsset = collect($map['topologyDetails'][$slotCanvasId]['assets'])->firstWhere('reference', 'SN-KARTU-1');
        $this->assertSame('Jetis → C1 → OLT-1 → Slot 1', $oltAsset['position']);
        $this->assertSame('SN-RAK-1', collect($map['topologyDetails'][-$this->miniA->id]['assets'])->first()['reference']);
        $this->assertSame(2, app(InfrastructureHierarchyService::class)->metricsByCabang($this->owner)[$this->cabangA->id]['assets']);
    }

    #[Test]
    public function inspector_olt_memuat_komponen_slot_dan_pon_bersama_kapasitas(): void
    {
        $this->withoutVite();
        $slot = $this->pon->slot;
        $slot->update(['capacity' => 16]);
        $this->olt1->update(['capacity' => 8]);

        $this->actingAs($this->owner)->get(route('network.nodes.index'))->assertOk()
            ->assertSee('id="device-components-'.$this->olt1->id.'"', false)
            ->assertSee('data-component-slot="'.$slot->id.'"', false)
            ->assertSee('data-component-port="'.$this->pon->id.'"', false)
            ->assertSee('Kapasitas perangkat (slot)')
            ->assertSee('PON per slot')
            ->assertSee('pelanggan aktif');
        $this->get(route('network.nodes.show', $this->olt1))
            ->assertOk()->assertDontSee('OLT · Slot dan Kartu');
    }

    #[Test]
    public function kapasitas_belum_didata_tetap_terlihat_dan_filter_mempertahankan_leluhur(): void
    {
        $this->withoutVite();
        $this->actingAs($this->owner)->get(route('network.nodes.index'))->assertOk()
            ->assertSee('kapasitas belum didata')
            ->assertSee('matchesHierarchyFilter', false);
    }

    #[Test]
    public function pembuatan_switch_tidak_membuat_port_odp_legacy(): void
    {
        $this->actingAs($this->owner)->post(route('network.nodes.store'), [
            'node_type_id' => NetworkNodeType::where('code', 'switch_mini_pop')->value('id'),
            'anchor_pop_id' => $this->miniA->id, 'code' => 'SW-KANONIS', 'name' => 'Switch baru', 'capacity' => 2,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $switch = NetworkNode::where('code', 'SW-KANONIS')->sole();
        $this->assertSame(2, $switch->capacity);
        $this->assertSame(0, $switch->ports()->count());
    }

    #[Test]
    public function perubahan_kapasitas_olt_dan_slot_diaudit_dan_tidak_boleh_di_bawah_posisi_tercatat(): void
    {
        $this->actingAs($this->owner)->patch('/network/nodes/'.$this->olt1->id.'/capacity', ['capacity' => 8])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(8, $this->olt1->fresh()->capacity);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => NetworkNode::class, 'auditable_id' => $this->olt1->id, 'action' => 'capacity_change']);
        $this->patch('/network/nodes/'.$this->olt1->id.'/capacity', ['capacity' => 1])->assertSessionHasErrors('capacity');
        $this->assertSame(8, $this->olt1->fresh()->capacity);

        $slot = $this->pon->slot;
        $this->patch('/network/infrastructure-slots/'.$slot->id.'/capacity', ['capacity' => 16])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(16, $slot->fresh()->capacity);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => InfrastructureSlot::class, 'auditable_id' => $slot->id, 'action' => 'capacity_change']);
        app(InfrastructurePositionService::class)->createPort($slot, 3, InfrastructurePortKind::PON);
        $this->patch('/network/infrastructure-slots/'.$slot->id.'/capacity', ['capacity' => 2])->assertSessionHasErrors('capacity');
        $this->assertSame(16, $slot->fresh()->capacity);
    }

    #[Test]
    public function kapasitas_membatasi_nomor_slot_dan_port_baru(): void
    {
        $this->olt2->update(['capacity' => 2]);
        $this->actingAs($this->owner)->post(route('network.nodes.infrastructure-slots.store', $this->olt2), ['number' => 3])
            ->assertSessionHasErrors('number');
        $slot = app(InfrastructurePositionService::class)->createSlot($this->olt2, 1, ['capacity' => 2]);
        $this->post(route('network.infrastructure-slots.ports.store', $slot), ['number' => 3])
            ->assertSessionHasErrors('number');
        $this->assertSame(0, $slot->ports()->count());
    }

    #[Test]
    public function perubahan_kapasitas_cabang_lain_dan_tanpa_permission_ditolak(): void
    {
        $user = $this->cabangUser($this->cabangB, 'fop');
        $this->actingAs($user)->patch('/network/nodes/'.$this->olt1->id.'/capacity', ['capacity' => 8])->assertForbidden();
        $this->patch('/network/infrastructure-slots/'.$this->pon->infrastructure_slot_id.'/capacity', ['capacity' => 16])->assertForbidden();
        $this->actingAs($this->cabangUser($this->cabangA, 'sales'))
            ->patch('/network/nodes/'.$this->olt1->id.'/capacity', ['capacity' => 8])->assertForbidden();
    }

    #[Test]
    public function pohon_operasional_tidak_menjadikan_pon_legacy_sejajar_slot(): void
    {
        $legacy = $this->node('pon_port', $this->miniA, 'PON-LEGACY');
        $legacy->update(['parent_id' => $this->olt1->id, 'anchor_pop_id' => null]);
        $this->withoutVite();
        $this->actingAs($this->owner)->get(route('network.nodes.index'))->assertOk()
            ->assertDontSee('data-hierarchy-key="node:'.$legacy->id.'"', false)
            ->assertSee('data-legacy-node="'.$legacy->id.'"', false)
            ->assertSee('Menunggu pemetaan legacy');
    }

    #[Test]
    public function switch_tidak_boleh_menerima_port_legacy_lewat_request_lama(): void
    {
        $this->actingAs($this->owner)->post(route('network.nodes.ports.add', $this->switch), ['additional_capacity' => 4])
            ->assertUnprocessable();
        $this->assertSame(0, $this->switch->ports()->count());
    }

    #[Test]
    public function target_switch_luar_scope_tidak_bocor_di_pohon(): void
    {
        $sibling = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabangA->id]);
        $outside = $this->node('core_router', $sibling, 'RAHASIA-MINI-LAIN');
        app(InfrastructureConnectionService::class)->release(
            InfrastructureConnection::where('source_infrastructure_port_id', $this->switchPort->id)->sole(),
            $this->owner, 'Ubah tujuan',
        );
        app(InfrastructureConnectionService::class)->connect($this->switchPort, $this->owner, targetNode: $outside);
        $p = $this->projection($this->cabangUser($this->miniA));
        $this->assertSame('Tujuan di luar scope', $p['items']['port:'.$this->switchPort->id]['connection_label']);
    }

    #[Test]
    public function peta_merender_pohon_kanonis_dan_komponen_per_perangkat(): void
    {
        $this->withoutVite();
        $this->actingAs($this->owner)->get(route('network.map.index', ['tab' => 'pohon']))->assertOk()
            ->assertSee('data-map-hierarchy-key="slot:'.$this->pon->infrastructure_slot_id.'"', false)
            ->assertSee('data-map-hierarchy-key="port:'.$this->pon->id.'"', false)
            ->assertSee('data-map-hierarchy-key="customer:'.$this->customerTanpaSn->id.'"', false)
            ->assertSee('Pemakaian / Kapasitas')
            ->assertSee('currentSelectedNode.slots', false)
            ->assertDontSee('slots[currentSelectedNode.anchor_pop_id]', false);
        $map = app(InfrastructureMapService::class)->build($this->owner, null, 'path');
        $this->assertArrayHasKey('hierarchy', $map);
        $this->assertArrayHasKey('capacity', $map['topologyDetails'][$this->olt1->id]);
        $this->assertArrayHasKey('capacity', $map['topologyDetails'][$this->olt1->id]['slots'][0]);
        $this->assertArrayHasKey(-1000000000 - $this->customerTanpaSn->id, $map['topologyDetails']);
    }
}
