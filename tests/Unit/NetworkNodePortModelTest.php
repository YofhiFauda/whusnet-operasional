<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NetworkNodePortModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_counts_used_ports_and_orders_ports_by_number_on_the_relation(): void
    {
        $node = NetworkNode::factory()->create();

        // Sengaja tidak urut dan campur is_used, supaya orderBy('number') di
        // relasi benar-benar terbukti, bukan kebetulan urutan insert.
        $node->ports()->create(['number' => 3, 'is_used' => true]);
        $node->ports()->create(['number' => 1, 'is_used' => false]);
        $node->ports()->create(['number' => 2, 'is_used' => true]);

        $this->assertSame(2, $node->ports()->where('is_used', true)->count());
        $this->assertSame([1, 2, 3], $node->ports->pluck('number')->values()->all());
    }

    #[Test]
    public function it_rejects_duplicate_port_number_within_the_same_node(): void
    {
        $node = NetworkNode::factory()->create();
        $node->ports()->create(['number' => 1, 'is_used' => false]);

        $this->expectException(QueryException::class);

        $node->ports()->create(['number' => 1, 'is_used' => false]);
    }

    /**
     * Regresi vs `OdpPort` lama (punya RecordsAuditLogs created+updated):
     * port baru & toggle is_used wajib meninggalkan jejak audit.
     */
    #[Test]
    public function it_writes_audit_log_on_create_and_on_is_used_update(): void
    {
        $node = NetworkNode::factory()->create();
        $port = $node->ports()->create(['number' => 1, 'is_used' => false]);

        $created = AuditLog::where('auditable_type', NetworkNodePort::class)->where('auditable_id', $port->id)->where('action', 'create')->sole();
        $this->assertSame('Port Node Jaringan', $created->module);

        $port->update(['is_used' => true]);

        $updated = AuditLog::where('auditable_type', NetworkNodePort::class)->where('auditable_id', $port->id)->where('action', 'update')->sole();
        $this->assertFalse((bool) $updated->old_values['is_used']);
        $this->assertTrue((bool) $updated->new_values['is_used']);
    }
}
