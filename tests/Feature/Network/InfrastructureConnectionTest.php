<?php

namespace Tests\Feature\Network;

use App\Enums\InfrastructureOccupancy;
use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureRecordStatus;
use App\Models\Customer;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructurePort;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InfrastructureConnectionService;
use App\Services\InfrastructurePositionService;
use App\Services\NetworkNodeService;
use App\Services\NetworkPortAssignmentService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Koneksi fisik (§3, §4.3): terpisah dari parent, memakai FK posisi, punya
 * status verifikasi. Satu PON banyak ODP; satu ODP satu uplink aktif.
 */
class InfrastructureConnectionTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $miniPop;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id]);
        $this->actor = User::factory()->create();
    }

    private function node(string $typeCode, Pop $anchor): NetworkNode
    {
        return NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', $typeCode)->firstOrFail()->id,
            'anchor_pop_id' => $anchor->id,
        ]);
    }

    private function pon(?Pop $miniPop = null): InfrastructurePort
    {
        $positions = app(InfrastructurePositionService::class);
        $slot = $positions->createSlot($this->node('olt_chassis', $miniPop ?? $this->miniPop), 1);

        return $positions->createPort($slot, 1, InfrastructurePortKind::PON);
    }

    private function switchPort(): InfrastructurePort
    {
        $positions = app(InfrastructurePositionService::class);
        $slot = $positions->ensureLogicalSlot($this->node('switch_mini_pop', $this->miniPop));

        return $positions->createPort($slot, 1, InfrastructurePortKind::SWITCH);
    }

    private function connections(): InfrastructureConnectionService
    {
        return app(InfrastructureConnectionService::class);
    }

    #[Test]
    public function satu_pon_boleh_melayani_banyak_odp(): void
    {
        $pon = $this->pon();
        $odpA = $this->node('odp', $this->miniPop);
        $odpB = $this->node('odp', $this->miniPop);

        $this->connections()->connect($pon, $this->actor, targetNode: $odpA, verified: true);
        $this->connections()->connect($pon, $this->actor, targetNode: $odpB, verified: true);

        $this->assertSame(2, InfrastructureConnection::query()->active()->where('source_infrastructure_port_id', $pon->id)->count());
        $this->assertSame($pon->id, $this->connections()->activeUplinkFor($odpA)?->source_infrastructure_port_id);
    }

    #[Test]
    public function odp_tidak_boleh_punya_dua_uplink_aktif(): void
    {
        $odp = $this->node('odp', $this->miniPop);
        $this->connections()->connect($this->pon(), $this->actor, targetNode: $odp, verified: true);

        $this->expectException(InvalidArgumentException::class);
        $this->connections()->connect($this->pon(), $this->actor, targetNode: $odp);
    }

    #[Test]
    public function koneksi_belum_terverifikasi_tidak_dihitung_sebagai_terverifikasi(): void
    {
        $pon = $this->pon();
        $connection = $this->connections()->connect($pon, $this->actor, targetNode: $this->node('odp', $this->miniPop));

        $this->assertSame(InfrastructureOccupancy::TERPAKAI_BELUM_TERVERIFIKASI, $this->connections()->occupancyOf($pon));
        $this->assertSame(0, InfrastructureConnection::query()->active()->verified()->count());

        $this->connections()->verify($connection, $this->actor);

        $this->assertSame(InfrastructureOccupancy::TERPAKAI_TERVERIFIKASI, $this->connections()->occupancyOf($pon->fresh()));
        $this->assertSame($this->actor->id, $connection->fresh()->verified_by);
    }

    #[Test]
    public function status_belum_didata_kosong_dan_rusak_dibedakan(): void
    {
        $pon = $this->pon();
        $this->assertSame(InfrastructureOccupancy::BELUM_DIDATA, $this->connections()->occupancyOf($pon));

        $pon->update(['status' => InfrastructureRecordStatus::TERVERIFIKASI]);
        $this->assertSame(InfrastructureOccupancy::KOSONG_TERVERIFIKASI, $this->connections()->occupancyOf($pon));

        $pon->update(['status' => InfrastructureRecordStatus::RUSAK_NONAKTIF]);
        $this->assertSame(InfrastructureOccupancy::RUSAK_NONAKTIF, $this->connections()->occupancyOf($pon));
    }

    #[Test]
    public function port_rusak_tidak_bisa_disambung(): void
    {
        $pon = $this->pon();
        $pon->update(['status' => InfrastructureRecordStatus::RUSAK_NONAKTIF]);

        $this->expectException(InvalidArgumentException::class);
        $this->connections()->connect($pon, $this->actor, targetNode: $this->node('odp', $this->miniPop));
    }

    #[Test]
    public function pon_ke_odp_cabang_lain_ditolak(): void
    {
        $cabangB = Pop::factory()->create(['type' => 'cabang']);
        $odpB = $this->node('odp', $cabangB);

        $this->expectException(InvalidArgumentException::class);
        $this->connections()->connect($this->pon(), $this->actor, targetNode: $odpB);
    }

    #[Test]
    public function pon_hanya_ke_odp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->connections()->connect($this->pon(), $this->actor, targetLabel: 'Rumah warga');
    }

    #[Test]
    public function switch_tidak_membuat_odp_anak_dan_tidak_menerima_odp(): void
    {
        $port = $this->switchPort();
        $odp = $this->node('odp', $this->miniPop);

        try {
            $this->connections()->connect($port, $this->actor, targetNode: $odp);
            $this->fail('Port Switch tidak boleh berujung ODP.');
        } catch (InvalidArgumentException) {
        }

        $connection = $this->connections()->connect($port, $this->actor, targetLabel: 'Radio PTP gedung A');

        $this->assertSame('Radio PTP gedung A', $connection->target_label);
        $this->assertNull($odp->fresh()->parent_id);
        $this->assertSame($this->miniPop->id, $odp->fresh()->anchor_pop_id);
    }

    #[Test]
    public function port_switch_hanya_satu_sambungan_aktif(): void
    {
        $port = $this->switchPort();
        $this->connections()->connect($port, $this->actor, targetLabel: 'Uplink A');

        $this->expectException(InvalidArgumentException::class);
        $this->connections()->connect($port, $this->actor, targetLabel: 'Uplink B');
    }

    #[Test]
    public function target_wajib_tepat_satu(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->connections()->connect($this->switchPort(), $this->actor);
    }

    #[Test]
    public function pelepasan_menjaga_histori_dan_membebaskan_odp(): void
    {
        $odp = $this->node('odp', $this->miniPop);
        $first = $this->connections()->connect($this->pon(), $this->actor, targetNode: $odp, verified: true);

        $this->connections()->release($first, $this->actor, 'Pindah PON');
        $second = $this->connections()->connect($this->pon(), $this->actor, targetNode: $odp, verified: true);

        $first->refresh();
        $this->assertNull($first->active_key);
        $this->assertNotNull($first->released_at);
        $this->assertSame('Pindah PON', $first->release_note);
        $this->assertSame($second->id, $this->connections()->activeUplinkFor($odp)?->id);
        $this->assertSame(2, InfrastructureConnection::query()->where('target_network_node_id', $odp->id)->count());
    }

    #[Test]
    public function pelepasan_wajib_alasan(): void
    {
        $connection = $this->connections()->connect($this->pon(), $this->actor, targetNode: $this->node('odp', $this->miniPop));

        $this->expectException(InvalidArgumentException::class);
        $this->connections()->release($connection, $this->actor, '  ');
    }

    #[Test]
    public function dua_pelanggan_pada_satu_port_odp_ditolak(): void
    {
        $odp = $this->node('odp', $this->miniPop);
        $port = NetworkNodePort::create(['network_node_id' => $odp->id, 'number' => 1]);
        $customerA = Customer::factory()->create(['pop_id' => $this->cabang->id]);
        $customerB = Customer::factory()->create(['pop_id' => $this->cabang->id]);
        $assignments = app(NetworkPortAssignmentService::class);

        $assignments->assignCustomer($port, $customerA, $this->actor);

        $this->expectException(InvalidArgumentException::class);
        $assignments->assignCustomer($port, $customerB, $this->actor);
    }

    #[Test]
    public function odp_dengan_uplink_aktif_tidak_boleh_pindah_lintas_cabang(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $odp = $this->node('odp', $this->miniPop);
        $this->connections()->connect($this->pon(), $this->actor, targetNode: $odp);
        $cabangB = Pop::factory()->create(['type' => 'cabang']);

        try {
            app(NetworkNodeService::class)->move($odp, null, $cabangB, $owner);
            $this->fail('ODP dengan uplink aktif tidak boleh dipindah lintas cabang.');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame($this->miniPop->id, $odp->fresh()->anchor_pop_id);
    }

    #[Test]
    public function olt_dengan_pon_tersambung_tidak_boleh_pindah_lintas_cabang(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pon = $this->pon();
        $this->connections()->connect($pon, $this->actor, targetNode: $this->node('odp', $this->miniPop));
        $otherMiniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => Pop::factory()->create(['type' => 'cabang'])->id]);
        $olt = $pon->slot->device;

        $this->expectException(InvalidArgumentException::class);
        app(NetworkNodeService::class)->move($olt, null, $otherMiniPop, $owner);
    }

    #[Test]
    public function koneksi_dan_pelepasan_tercatat_audit(): void
    {
        $connection = $this->connections()->connect($this->pon(), $this->actor, targetNode: $this->node('odp', $this->miniPop));
        $this->connections()->release($connection, $this->actor, 'Uji');

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => InfrastructureConnection::class, 'auditable_id' => $connection->id, 'action' => 'connect']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => InfrastructureConnection::class, 'auditable_id' => $connection->id, 'action' => 'release']);
    }
}
