<?php

namespace Tests\Feature\Network;

use App\Enums\InfrastructureOccupancy;
use App\Enums\InfrastructureRecordStatus;
use App\Models\Customer;
use App\Models\InfrastructureConnection;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InfrastructureConnectionService;
use App\Services\NetworkNodeService;
use App\Services\NetworkPortAssignmentService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Koneksi fisik (§3, §4.3) sejak Slot/PON/Port jadi node: sambungan hanya
 * untuk Port Switch. ODP -> PON lewat `parent_id`, bukan sambungan.
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

    private function pon(?Pop $miniPop = null): NetworkNode
    {
        $slot = NetworkNode::factory()->slot($this->node('olt_chassis', $miniPop ?? $this->miniPop), 1)->create();

        return NetworkNode::factory()->pon($slot, 1)->create();
    }

    private function switchPort(?Pop $anchor = null, int $number = 1): NetworkNode
    {
        $slot = NetworkNode::factory()->slot($this->node('switch_mini_pop', $anchor ?? $this->miniPop), 1)->create();

        return NetworkNode::factory()->switchPort($slot, $number)->create();
    }

    private function connections(): InfrastructureConnectionService
    {
        return app(InfrastructureConnectionService::class);
    }

    #[Test]
    public function pon_tidak_bisa_jadi_sumber_sambungan(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ODP tersambung ke PON lewat induknya, bukan sambungan.');
        $this->connections()->connect($this->pon(), $this->actor, targetLabel: 'ODP A');
    }

    #[Test]
    public function uplink_odp_dibaca_dari_induknya_bukan_dari_sambungan(): void
    {
        $pon = $this->pon();
        $odp = NetworkNode::factory()->child($pon)->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->firstOrFail()->id,
        ]);

        $this->assertSame($pon->id, $odp->uplinkPon()?->id);
        $this->assertSame(0, InfrastructureConnection::query()->count());
    }

    #[Test]
    public function koneksi_belum_terverifikasi_tidak_dihitung_sebagai_terverifikasi(): void
    {
        $port = $this->switchPort();
        $connection = $this->connections()->connect($port, $this->actor, targetLabel: 'Radio PTP');

        $this->assertSame(InfrastructureOccupancy::TERPAKAI_BELUM_TERVERIFIKASI, $this->connections()->occupancyOf($port));
        $this->assertSame(0, InfrastructureConnection::query()->active()->verified()->count());

        $this->connections()->verify($connection, $this->actor);

        $this->assertSame(InfrastructureOccupancy::TERPAKAI_TERVERIFIKASI, $this->connections()->occupancyOf($port->fresh()));
        $this->assertSame($this->actor->id, $connection->fresh()->verified_by);
    }

    #[Test]
    public function occupancy_belum_didata_kosong_dan_rusak_dibedakan(): void
    {
        $port = $this->switchPort();
        $this->assertSame(InfrastructureOccupancy::BELUM_DIDATA, $this->connections()->occupancyOf($port));

        $port->update(['record_status' => InfrastructureRecordStatus::TERVERIFIKASI]);
        $this->assertSame(InfrastructureOccupancy::KOSONG_TERVERIFIKASI, $this->connections()->occupancyOf($port));

        $port->update(['record_status' => InfrastructureRecordStatus::RUSAK_NONAKTIF]);
        $this->assertSame(InfrastructureOccupancy::RUSAK_NONAKTIF, $this->connections()->occupancyOf($port));
    }

    #[Test]
    public function occupancy_port_tujuan_ikut_terpakai(): void
    {
        $source = $this->switchPort(number: 1);
        $target = $this->switchPort(number: 2);
        $this->connections()->connect($source, $this->actor, targetPort: $target, verified: true);

        $this->assertSame(InfrastructureOccupancy::TERPAKAI_TERVERIFIKASI, $this->connections()->occupancyOf($target));
    }

    #[Test]
    public function port_rusak_tidak_bisa_disambung(): void
    {
        $port = $this->switchPort();
        $port->update(['record_status' => InfrastructureRecordStatus::RUSAK_NONAKTIF]);

        $this->expectException(InvalidArgumentException::class);
        $this->connections()->connect($port, $this->actor, targetLabel: 'Uplink');
    }

    #[Test]
    public function lintas_cabang_ditolak(): void
    {
        $cabangB = Pop::factory()->create(['type' => 'cabang']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cabang berbeda');
        $this->connections()->connect($this->switchPort(), $this->actor, targetPort: $this->switchPort($cabangB));
    }

    #[Test]
    public function tujuan_odp_ditolak_dan_parent_tidak_berubah(): void
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
    public function port_switch_satu_sambungan_aktif(): void
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
    public function release_menyimpan_histori_dan_membebaskan_port(): void
    {
        $port = $this->switchPort();
        $first = $this->connections()->connect($port, $this->actor, targetLabel: 'Uplink A', verified: true);

        $this->connections()->release($first, $this->actor, 'Pindah uplink');
        $second = $this->connections()->connect($port, $this->actor, targetLabel: 'Uplink B', verified: true);

        $first->refresh();
        $this->assertNull($first->active_key);
        $this->assertNotNull($first->released_at);
        $this->assertSame('Pindah uplink', $first->release_note);
        $this->assertSame(1, $second->active_key);
        $this->assertSame(2, InfrastructureConnection::query()->where('source_infrastructure_port_id', $port->id)->count());
    }

    #[Test]
    public function pelepasan_wajib_alasan(): void
    {
        $connection = $this->connections()->connect($this->switchPort(), $this->actor, targetLabel: 'Uplink');

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
    public function switch_dengan_port_tersambung_tidak_boleh_pindah_lintas_cabang(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $port = $this->switchPort();
        $this->connections()->connect($port, $this->actor, targetLabel: 'Uplink');
        $otherMiniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => Pop::factory()->create(['type' => 'cabang'])->id]);
        $switch = $port->parent->parent;

        $this->expectException(InvalidArgumentException::class);
        app(NetworkNodeService::class)->move($switch, null, $otherMiniPop, $owner);
    }

    #[Test]
    public function koneksi_dan_pelepasan_tercatat_audit(): void
    {
        $connection = $this->connections()->connect($this->switchPort(), $this->actor, targetLabel: 'Uplink');
        $this->connections()->release($connection, $this->actor, 'Uji');

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => InfrastructureConnection::class, 'auditable_id' => $connection->id, 'action' => 'connect']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => InfrastructureConnection::class, 'auditable_id' => $connection->id, 'action' => 'release']);
    }
}
