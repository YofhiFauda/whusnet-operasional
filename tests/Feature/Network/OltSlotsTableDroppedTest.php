<?php

namespace Tests\Feature\Network;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Task 14: Slot OLT kini baris `network_nodes`, jadi tabel `olt_slots` (dan
 * tabel posisi lama) harus hilang setelah semua migration jalan dari nol.
 * Kalau ada yang menghidupkan lagi tabelnya, dua sumber kebenaran Slot muncul.
 */
class OltSlotsTableDroppedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tabel_slot_dan_port_lama_tidak_ada_setelah_migrate_dari_nol(): void
    {
        foreach (['olt_slots', 'infrastructure_slots', 'infrastructure_ports', 'infrastructure_legacy_mappings'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Tabel {$table} seharusnya sudah dibuang.");
        }
        $this->assertTrue(Schema::hasTable('network_nodes'));
    }
}
