<?php

namespace Tests\Feature\Network;

use App\Models\AuditLog;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\User;
use App\Services\NetworkNodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NetworkNodeMoveTest extends TestCase
{
    use RefreshDatabase;

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

        $actor = User::factory()->create();

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

        $actor = User::factory()->create();

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

        $actor = User::factory()->create();

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

        $actor = User::factory()->create();

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

        $actor = User::factory()->create();

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
    public function snapshot_inventory_transactions_belum_bisa_diuji_di_task_ini(): void
    {
        $this->markTestIncomplete(
            'Kolom to_network_node_id/from_network_node_id di inventory_transactions '.
            'baru dibuat di Task 8 — placeholder ini mendokumentasikan requirement '.
            '"move() tidak menyentuh baris inventory_transactions" sampai tabelnya ada.'
        );
    }
}
