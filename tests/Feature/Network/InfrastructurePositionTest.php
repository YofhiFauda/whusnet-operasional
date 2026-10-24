<?php

namespace Tests\Feature\Network;

use App\Enums\InfrastructureCapacityUnit;
use App\Enums\InfrastructureRecordStatus;
use App\Enums\InfrastructureSlotKind;
use App\Models\AuditLog;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\User;
use App\Services\InfrastructurePositionService;
use App\Services\NetworkNodeService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Model posisi kanonis §2/§16.2 (ADHOC-213): Slot milik OLT/Switch, PON milik
 * Slot OLT, Port Switch milik Slot Switch — ketiganya baris `network_nodes`.
 * Posisi berdiri sendiri — Slot boleh kosong.
 */
class InfrastructurePositionTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $miniPop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id]);
    }

    private function device(string $typeCode, ?Pop $anchor = null, string $code = ''): NetworkNode
    {
        $type = NetworkNodeType::where('code', $typeCode)->firstOrFail();

        return NetworkNode::factory()->create([
            'node_type_id' => $type->id,
            'anchor_pop_id' => ($anchor ?? $this->miniPop)->id,
            'code' => $code !== '' ? $code : strtoupper($typeCode).'-'.fake()->unique()->numerify('###'),
        ]);
    }

    private function service(): InfrastructurePositionService
    {
        return app(InfrastructurePositionService::class);
    }

    private function typeCodeOf(NetworkNode $node): string
    {
        return NetworkNodeType::whereKey($node->node_type_id)->value('code');
    }

    #[Test]
    public function tipe_switch_mini_pop_tersedia_dengan_port_dan_aggregation_switch_sudah_dihapus(): void
    {
        $switch = NetworkNodeType::where('code', 'switch_mini_pop')->firstOrFail();
        $this->assertTrue($switch->has_ports);
        $this->assertTrue($switch->is_active);

        // Dulu hanya dinonaktifkan; migration 2026_10_23_000003 menghapusnya permanen.
        $this->assertFalse(NetworkNodeType::where('code', 'aggregation_switch')->exists());
    }

    #[Test]
    public function satu_mini_pop_boleh_punya_banyak_olt_dan_switch(): void
    {
        $nodes = app(NetworkNodeService::class);
        foreach (['olt_chassis', 'olt_chassis', 'switch_mini_pop', 'switch_mini_pop'] as $code) {
            $nodes->assertTypePlacement(NetworkNodeType::where('code', $code)->firstOrFail(), null, $this->miniPop);
        }

        $this->expectException(InvalidArgumentException::class);
        $nodes->assertTypePlacement(NetworkNodeType::where('code', 'switch_mini_pop')->firstOrFail(), null, $this->cabang);
    }

    #[Test]
    public function slot_hanya_di_olt_atau_switch(): void
    {
        $slotOlt = $this->service()->createSlot($this->device('olt_chassis'), 1);
        $slotSwitch = $this->service()->createSlot($this->device('switch_mini_pop'), 1);

        $this->assertSame('olt_slot', $this->typeCodeOf($slotOlt));
        $this->assertSame('switch_slot', $this->typeCodeOf($slotSwitch));
        $this->assertSame(InfrastructureSlotKind::OLT, $slotOlt->fresh()->slotKind());
        $this->assertNull($slotOlt->anchor_pop_id);
        $this->assertSame('active', $slotOlt->status);
        $this->assertSame(InfrastructureRecordStatus::BELUM_DIDATA, $slotOlt->fresh()->record_status);
        $this->assertSame(InfrastructureCapacityUnit::PON, $slotOlt->fresh()->capacity_unit);
        $this->assertSame(InfrastructureCapacityUnit::PORT, $slotSwitch->fresh()->capacity_unit);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->createSlot($this->device('odp'), 1);
    }

    #[Test]
    public function pon_hanya_di_slot_olt_dan_port_switch_hanya_di_slot_switch(): void
    {
        $slotOlt = $this->service()->createSlot($this->device('olt_chassis'), 1);
        $slotSwitch = $this->service()->createSlot($this->device('switch_mini_pop'), 1);

        $pon = $this->service()->createPort($slotOlt, 2);
        $port = $this->service()->createPort($slotSwitch, 4);

        $this->assertSame('olt_pon', $this->typeCodeOf($pon));
        $this->assertSame('PON 2', $pon->name);
        $this->assertSame(InfrastructureCapacityUnit::PELANGGAN_AKTIF, $pon->fresh()->capacity_unit);
        $this->assertSame('switch_port', $this->typeCodeOf($port));
        $this->assertSame('Port 4', $port->name);
        $this->assertNull($port->fresh()->capacity_unit);

        // Induk bukan Slot (mis. OLT langsung) ditolak: jenis port tak bisa diturunkan.
        $this->expectException(InvalidArgumentException::class);
        $this->service()->createPort($this->device('olt_chassis'), 1);
    }

    #[Test]
    public function aturan_penempatan_tipe_dijaga_lewat_tabel_aturan_bukan_duplikat(): void
    {
        $slotSwitch = $this->service()->createSlot($this->device('switch_mini_pop'), 1);
        $ponType = NetworkNodeType::where('code', 'olt_pon')->firstOrFail();

        $this->expectException(InvalidArgumentException::class);
        app(NetworkNodeService::class)->assertTypePlacement($ponType, $slotSwitch, null);
    }

    #[Test]
    public function nomor_ganda_per_induk_ditolak_tapi_nomor_sama_di_induk_lain_lolos(): void
    {
        $oltA = $this->device('olt_chassis');
        $oltB = $this->device('olt_chassis');

        $slotA = $this->service()->createSlot($oltA, 1);
        $slotB = $this->service()->createSlot($oltB, 1);
        $this->assertSame($oltB->id, $slotB->parent_id);
        $this->assertNotSame($slotA->id, $slotB->id);

        $this->service()->createPort($slotA, 1);
        $this->service()->createPort($slotB, 1);

        try {
            $this->service()->createSlot($oltA, 1);
            $this->fail('Slot ganda harus ditolak.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->service()->createPort($slotA, 1);
    }

    #[Test]
    public function kode_slot_dan_pon_diturunkan_dari_kode_induk(): void
    {
        $olt = $this->device('olt_chassis', null, 'OLT-A');
        $slot = $this->service()->createSlot($olt, 3);
        $pon = $this->service()->createPort($slot, 5);

        $this->assertSame('OLT-A-S3', $slot->code);
        $this->assertSame('OLT-A-S3-P5', $pon->code);
        $this->assertSame($slot->id, $pon->parent_id);
        $this->assertSame('Slot 3', $slot->name);
    }

    #[Test]
    public function kode_turunan_lebih_dari_120_karakter_ditolak_ramah(): void
    {
        $olt = $this->device('olt_chassis', null, str_repeat('A', 119));

        try {
            $this->service()->createSlot($olt, 1);
            $this->fail('Kode > 120 karakter harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Kode turunan melebihi 120 karakter.', $e->getMessage());
        }

        $this->assertSame(0, $olt->children()->count());
    }

    #[Test]
    public function slot_melebihi_kapasitas_perangkat_ditolak(): void
    {
        $olt = $this->device('olt_chassis');
        $olt->forceFill(['capacity' => 4])->saveQuietly();

        $this->service()->createSlot($olt, 4);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->createSlot($olt, 5);
    }

    #[Test]
    public function port_melebihi_kapasitas_slot_ditolak(): void
    {
        $slot = $this->service()->createSlot($this->device('olt_chassis'), 1, ['capacity' => 8]);

        $this->service()->createPort($slot, 8);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->createPort($slot, 9);
    }

    #[Test]
    public function slot_kosong_tanpa_pon_sah(): void
    {
        $slot = $this->service()->createSlot($this->device('olt_chassis'), 3);

        $this->assertDatabaseHas('network_nodes', ['id' => $slot->id, 'number' => 3]);
        $this->assertSame(0, $slot->positionPorts()->count());
    }

    #[Test]
    public function slot_logis_hanya_untuk_switch_dan_idempoten(): void
    {
        $switch = $this->device('switch_mini_pop');

        $slot = $this->service()->ensureLogicalSlot($switch);
        $again = $this->service()->ensureLogicalSlot($switch);

        $this->assertSame($slot->id, $again->id);
        $this->assertSame(1, $slot->number);
        $this->assertTrue($slot->is_logical);
        $this->assertSame(InfrastructurePositionService::LOGICAL_SLOT_LABEL, $slot->name);
        $this->assertSame(InfrastructureCapacityUnit::PORT, $slot->fresh()->capacity_unit);
        $this->assertSame(1, $switch->children()->count());

        $port = $this->service()->createPort($slot, 4);
        $this->assertNull($port->fresh()->capacity_unit);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->ensureLogicalSlot($this->device('olt_chassis'));
    }

    #[Test]
    public function slot_logis_eksplisit_di_olt_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->createSlot($this->device('olt_chassis'), 1, ['is_logical' => true]);
    }

    #[Test]
    public function red_zone_dan_kapasitas_pelanggan_hanya_untuk_pon(): void
    {
        $slotOlt = $this->service()->createSlot($this->device('olt_chassis'), 1);
        $slotSwitch = $this->service()->createSlot($this->device('switch_mini_pop'), 1);

        $pon = $this->service()->createPort($slotOlt, 1, ['capacity' => 128, 'red_zone_threshold' => 80]);
        $this->assertSame(128, $pon->fresh()->capacity);
        $this->assertSame(80, $pon->fresh()->red_zone_threshold);

        foreach ([
            [$slotOlt, 2, ['red_zone_threshold' => 0]],
            [$slotOlt, 3, ['red_zone_threshold' => 101]],
            [$slotOlt, 4, ['capacity' => 0]],
            [$slotSwitch, 1, ['red_zone_threshold' => 80]],
            [$slotSwitch, 2, ['capacity' => 10]],
        ] as [$slot, $number, $attrs]) {
            try {
                $this->service()->createPort($slot, $number, $attrs);
                $this->fail('Atribut '.json_encode($attrs).' harus ditolak.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function kapasitas_perangkat_dan_slot_diaudit_dan_tidak_boleh_di_bawah_posisi_tertinggi(): void
    {
        $actor = User::factory()->create();
        $olt = $this->device('olt_chassis');
        $slot = $this->service()->createSlot($olt, 6);
        $this->service()->createPort($slot, 3);

        $updated = $this->service()->updateDeviceCapacity($olt, 8, $actor);
        $this->assertSame(8, $updated->capacity);
        $slotUpdated = $this->service()->updateSlotCapacity($slot, 16, $actor);
        $this->assertSame(16, $slotUpdated->capacity);

        $this->assertSame(2, AuditLog::where('module', 'Kapasitas Infrastruktur')
            ->where('action', 'capacity_change')
            ->where('auditable_type', NetworkNode::class)->count());

        try {
            $this->service()->updateDeviceCapacity($olt, 5, $actor);
            $this->fail('Kapasitas di bawah slot tertinggi harus ditolak.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(ValidationException::class);
        $this->service()->updateSlotCapacity($slot, 2, $actor);
    }
}
