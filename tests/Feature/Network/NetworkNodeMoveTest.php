<?php

namespace Tests\Feature\Network;

use App\Enums\ScopeType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\InfrastructureBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\NetworkNodeService;
use App\Services\NetworkPortAssignmentService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class NetworkNodeMoveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    #[Test]
    public function move_mengubah_anchor_dan_resolvepop_turunan_ikut_berubah(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $miniPopA = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabangA->id]);
        $miniPopB = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabangB->id]);

        $odc1 = NetworkNode::factory()->create([
            'parent_id' => null,
            'anchor_pop_id' => $miniPopA->id,
        ]);

        $odp = NetworkNode::factory()->child($odc1)->create();

        $actor = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        (new NetworkNodeService)->move($odc1, null, $miniPopB, $actor);

        $odc1->refresh();
        $odp->refresh();

        $this->assertSame($miniPopB->id, $odc1->anchor_pop_id);
        $this->assertNull($odc1->parent_id);

        // ODP tidak disentuh sama sekali oleh move — hanya resolvePop()-nya
        // (dihitung lewat parent chain) yang berubah secara transitif.
        $this->assertSame($odc1->id, $odp->parent_id);
        $this->assertNull($odp->anchor_pop_id);
        $this->assertSame($cabangB->id, $odp->resolvePop()?->id);
    }

    #[Test]
    public function move_antar_parent_dalam_satu_olt_hanya_mengubah_parent_id(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $olt = NetworkNode::factory()->create([
            'parent_id' => null,
            'anchor_pop_id' => $cabang->id,
        ]);

        $odc1 = NetworkNode::factory()->child($olt)->create();
        $odc2 = NetworkNode::factory()->child($olt)->create();
        $odp = NetworkNode::factory()->child($odc1)->create();

        $actor = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        (new NetworkNodeService)->move($odp, $odc2, null, $actor);

        $odp->refresh();

        $this->assertSame($odc2->id, $odp->parent_id);
        $this->assertNull($odp->anchor_pop_id);
    }

    #[Test]
    public function move_ke_turunan_sendiri_dilempar_exception_dan_tidak_mengubah_apapun(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);

        $odc1 = NetworkNode::factory()->create([
            'parent_id' => null,
            'anchor_pop_id' => $cabang->id,
        ]);
        $odp = NetworkNode::factory()->child($odc1)->create();

        $actor = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        $originalParentId = $odc1->parent_id;
        $originalAnchorPopId = $odc1->anchor_pop_id;

        $this->expectException(InvalidArgumentException::class);

        try {
            (new NetworkNodeService)->move($odc1, $odp, null, $actor);
        } finally {
            $odc1->refresh();
            $this->assertSame($originalParentId, $odc1->parent_id);
            $this->assertSame($originalAnchorPopId, $odc1->anchor_pop_id);
        }
    }

    #[Test]
    public function assert_placement_menolak_parent_dan_anchor_sama_sama_terisi(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $node = NetworkNode::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        NetworkNodeService::assertPlacement($node, $cabang);
    }

    #[Test]
    public function assert_placement_menolak_parent_dan_anchor_sama_sama_kosong(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NetworkNodeService::assertPlacement(null, null);
    }

    #[Test]
    public function assert_placement_lolos_saat_salah_satu_terisi(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $node = NetworkNode::factory()->create();

        try {
            NetworkNodeService::assertPlacement($node, null);
            NetworkNodeService::assertPlacement(null, $cabang);
        } catch (InvalidArgumentException $e) {
            $this->fail('assertPlacement() seharusnya tidak melempar exception saat tepat satu terisi: '.$e->getMessage());
        }

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function move_mencatat_audit_log_dengan_aktor_node_dan_cabang_asal_tujuan(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $miniPopA = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabangA->id]);
        $miniPopB = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabangB->id]);

        $odc1 = NetworkNode::factory()->create([
            'parent_id' => null,
            'anchor_pop_id' => $miniPopA->id,
        ]);

        $actor = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        (new NetworkNodeService)->move($odc1, null, $miniPopB, $actor);

        $log = AuditLog::where('auditable_type', NetworkNode::class)
            ->where('auditable_id', $odc1->id)
            ->where('action', 'move')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame($cabangA->id, $log->old_values['pop_id']);
        $this->assertSame($cabangB->id, $log->new_values['pop_id']);
    }

    #[Test]
    public function move_dalam_cabang_yang_sama_pop_id_audit_tidak_berubah(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $olt = NetworkNode::factory()->create([
            'parent_id' => null,
            'anchor_pop_id' => $cabang->id,
        ]);

        $odc1 = NetworkNode::factory()->child($olt)->create();
        $odc2 = NetworkNode::factory()->child($olt)->create();
        $odp = NetworkNode::factory()->child($odc1)->create();

        $actor = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        (new NetworkNodeService)->move($odp, $odc2, null, $actor);

        $log = AuditLog::where('auditable_type', NetworkNode::class)
            ->where('auditable_id', $odp->id)
            ->where('action', 'move')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($log->old_values['pop_id'], $log->new_values['pop_id']);
        $this->assertNotSame($log->old_values['parent_id'], $log->new_values['parent_id']);
    }

    #[Test]
    public function move_lintas_cabang_ditolak_jika_aktor_hanya_punya_scope_asal(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabangA->id, 'parent_id' => null]);
        $role = Role::where('code', 'admin')->firstOrFail();
        $actor = User::factory()->create(['role_id' => $role->id]);
        $scope = UserRoleScope::create([
            'user_id' => $actor->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabangA->id]);

        try {
            (new NetworkNodeService)->move($node, null, $cabangB, $actor);
            $this->fail('Pindah ke cabang di luar scope harus ditolak.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame($cabangA->id, $node->fresh()->anchor_pop_id);
        }
    }

    #[Test]
    public function move_ditolak_jika_aktor_hanya_punya_scope_tujuan(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabangA->id, 'parent_id' => null]);
        $role = Role::where('code', 'admin')->firstOrFail();
        $actor = User::factory()->create(['role_id' => $role->id]);
        $scope = UserRoleScope::create([
            'user_id' => $actor->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabangB->id]);

        try {
            (new NetworkNodeService)->move($node, null, $cabangB, $actor);
            $this->fail('Pindah dari cabang di luar scope harus ditolak.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame($cabangA->id, $node->fresh()->anchor_pop_id);
        }
    }

    #[Test]
    public function move_lintas_cabang_ditolak_saat_subtree_masih_punya_port_pelanggan_aktif(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $odpType = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);
        $node = NetworkNode::factory()->create(['anchor_pop_id' => $cabangA->id, 'node_type_id' => $odpType->id]);
        $port = $node->ports()->create(['number' => 1]);
        $customer = Customer::factory()->create(['pop_id' => $cabangA->id]);
        $actor = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $actor);

        try {
            app(NetworkNodeService::class)->move($node, null, $cabangB, $actor);
            $this->fail('Subtree dengan penempatan pelanggan aktif tidak boleh pindah cabang.');
        } catch (InvalidArgumentException) {
            $this->assertSame($cabangA->id, $node->fresh()->anchor_pop_id);
            $this->assertSame($customer->id, $port->assignments()->where('active_key', 1)->firstOrFail()->customer_id);
        }
    }

    /**
     * Slot/PON/Port kini baris `network_nodes` biasa (Task 2) — aset Gudang
     * yang "diparkir" di sana memakai kolom historis
     * `infrastructure_slot_id`/`infrastructure_port_id` yang isinya id node
     * tersebut, bukan lagi baris tabel `infrastructure_slots`/
     * `infrastructure_ports` (sudah dihapus Task 5/6). Dulu tes ini membuat
     * baris di tabel itu langsung — sekarang dikonversi memakai
     * `NetworkNode::factory()->slot()/pon()` supaya tidak lagi menyentuh
     * tabel yang sudah tidak ada ("no such table").
     */
    #[Test]
    public function pindah_lintas_cabang_ditolak_bila_ada_aset_di_slot(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $olt = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->firstOrFail()->id,
            'anchor_pop_id' => $cabangA->id,
        ]);
        $slot = NetworkNode::factory()->slot($olt, 1)->create();
        $pon = NetworkNode::factory()->pon($slot, 1)->create();
        $category = ItemCategory::firstOrCreate(['code' => 'move-test'], ['name' => 'Move', 'default_unit' => 'pcs']);
        $item = Item::create(['code' => 'MOVE-QTY', 'name' => 'Qty', 'unit' => 'pcs', 'item_category_id' => $category->id,
            'tracking_type' => 'quantity', 'ownership_mode' => 'infrastructure_asset']);
        $actor = User::factory()->create(['role_id' => Role::where('code', 'owner')->sole()->id]);

        foreach (['infrastructure_slot_id' => $slot->id, 'infrastructure_port_id' => $pon->id] as $column => $id) {
            $balance = InfrastructureBalance::create(['item_id' => $item->id, $column => $id, 'qty' => 1]);
            try {
                app(NetworkNodeService::class)->move($olt, null, $cabangB, $actor);
                $this->fail('Positive stock on descendant '.$column.' must block branch relocation.');
            } catch (InvalidArgumentException) {
                $this->assertSame($cabangA->id, $olt->fresh()->anchor_pop_id);
                $this->assertSame('1.00', $balance->fresh()->qty);
                $this->assertDatabaseMissing('audit_logs', ['auditable_type' => NetworkNode::class, 'auditable_id' => $olt->id, 'action' => 'move']);
            }
            $balance->delete();
        }
    }

    /**
     * D5: Slot/PON/Port tidak punya identitas sendiri — kode & nomornya
     * diturunkan dari induk saat dibuat (`InfrastructurePositionService`).
     * Memindah salah satunya sendirian akan membuatnya menempel ke induk
     * yang tidak pernah menerbitkan kode itu. `move()` wajib menolak lebih
     * dulu, sebelum aturan induk-per-tipe/scope dicek sama sekali.
     */
    #[Test]
    public function slot_pon_port_tidak_bisa_dipindah(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $miniPop = Pop::factory()->create(['type' => 'mini_pop']);
        $olt = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->firstOrFail()->id,
            'anchor_pop_id' => $miniPop->id,
        ]);
        $slot = NetworkNode::factory()->slot($olt, 1)->create();
        $pon = NetworkNode::factory()->pon($slot, 1)->create();

        $otherOlt = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->firstOrFail()->id,
            'anchor_pop_id' => $miniPop->id,
        ]);
        $otherSlot = NetworkNode::factory()->slot($otherOlt, 1)->create();

        $actor = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        foreach ([$slot, $pon] as $position) {
            try {
                app(NetworkNodeService::class)->move($position, $otherSlot, null, $actor);
                $this->fail('Slot/PON tidak boleh dipindah sendirian.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('Slot/PON/Port tidak dapat dipindah — identitasnya mengikuti induk.', $e->getMessage());
            }
        }
    }

    /**
     * Memindah OLT (induk Slot/PON) TETAP boleh — yang tidak boleh dipindah
     * SENDIRIAN adalah posisi (Slot/PON/Port) di bawahnya. Subtree ikut
     * pindah transitif lewat `parent_id`, bukan disentuh satu-satu. Dua
     * Mini POP di sini berada di Cabang yang sama, jadi `resolvePop()` PON
     * tetap menunjuk Cabang yang sama setelah pindah — murni memverifikasi
     * rantai `parent` tetap benar setelah `anchor_pop_id` OLT berubah.
     */
    #[Test]
    public function pindah_olt_ikut_memindahkan_seluruh_subtree(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $miniPopA = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);
        $miniPopB = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);

        $olt = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->firstOrFail()->id,
            'anchor_pop_id' => $miniPopA->id,
        ]);
        $slot = NetworkNode::factory()->slot($olt, 1)->create();
        $pon = NetworkNode::factory()->pon($slot, 1)->create();

        $actor = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        app(NetworkNodeService::class)->move($olt, null, $miniPopB, $actor);

        $olt->refresh();
        $this->assertSame($miniPopB->id, $olt->anchor_pop_id);
        // Slot/PON sendiri tidak disentuh — parent_id-nya tetap merujuk ke
        // OLT yang sama, resolvePop() yang menghitung ulang rantai induk.
        $this->assertSame($slot->id, $pon->fresh()->parent_id);
        $this->assertSame($cabang->id, $pon->fresh()->resolvePop()?->id);
    }

    /**
     * D2: database produksi bukan dummy murni (ODP berpelanggan ada).
     * Migrasi reset dummy TIDAK menghapus ODP lama yang masih anchor POP
     * langsung — operator memasangnya ke PON lewat `move()` biasa, bukan
     * lewat migrasi data. `assertTypePlacement()` meloloskannya karena
     * parent baru (PON) cocok dengan rule `odp→olt_pon`.
     */
    #[Test]
    public function odp_lama_anchor_pop_bisa_dipasang_ke_pon(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $odpType = NetworkNodeType::where('code', 'odp')->firstOrFail();
        // Jalur create() SENGAJA tidak menegakkan aturan tipe (lihat
        // komentar XOR di NetworkNode) — baris ini mensimulasikan ODP lama
        // yang ditinggalkan migrasi reset dummy D2 (anchor POP langsung,
        // dibuat sebelum aturan odp->olt_pon ada).
        $odpLama = NetworkNode::factory()->create([
            'anchor_pop_id' => $cabang->id,
            'node_type_id' => $odpType->id,
            'parent_id' => null,
        ]);

        $olt = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->firstOrFail()->id,
            'anchor_pop_id' => $cabang->id,
        ]);
        $slot = NetworkNode::factory()->slot($olt, 1)->create();
        $pon = NetworkNode::factory()->pon($slot, 1)->create();

        $actor = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);

        app(NetworkNodeService::class)->move($odpLama, $pon, null, $actor);

        $odpLama->refresh();
        $this->assertSame($pon->id, $odpLama->parent_id);
        $this->assertNull($odpLama->anchor_pop_id);
    }

    /**
     * ODP dengan port pelanggan aktif tidak boleh pindah PON lintas Cabang
     * — sama persis dengan kasus "subtree masih punya port pelanggan aktif"
     * di atas, tapi topologi kanonis baru (ODP di bawah PON, bukan anchor
     * POP langsung).
     */
    #[Test]
    public function odp_antar_pon_lintas_cabang_ditolak(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $cabangA = Pop::factory()->create(['type' => 'cabang']);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $odpType = NetworkNodeType::where('code', 'odp')->firstOrFail();
        $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();

        $oltA = NetworkNode::factory()->create(['node_type_id' => $chassisType->id, 'anchor_pop_id' => $cabangA->id]);
        $slotA = NetworkNode::factory()->slot($oltA, 1)->create();
        $ponA = NetworkNode::factory()->pon($slotA, 1)->create();
        $odp = NetworkNode::factory()->create(['node_type_id' => $odpType->id, 'parent_id' => $ponA->id, 'anchor_pop_id' => null]);
        $port = $odp->ports()->create(['number' => 1]);
        $customer = Customer::factory()->create(['pop_id' => $cabangA->id]);
        $actor = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        app(NetworkPortAssignmentService::class)->assignCustomer($port, $customer, $actor);

        $oltB = NetworkNode::factory()->create(['node_type_id' => $chassisType->id, 'anchor_pop_id' => $cabangB->id]);
        $slotB = NetworkNode::factory()->slot($oltB, 1)->create();
        $ponB = NetworkNode::factory()->pon($slotB, 1)->create();

        try {
            app(NetworkNodeService::class)->move($odp, $ponB, null, $actor);
            $this->fail('ODP dengan pelanggan aktif tidak boleh pindah PON lintas cabang.');
        } catch (InvalidArgumentException) {
            $this->assertSame($ponA->id, $odp->fresh()->parent_id);
            $this->assertSame($customer->id, $port->assignments()->where('active_key', 1)->firstOrFail()->customer_id);
        }
    }
}
