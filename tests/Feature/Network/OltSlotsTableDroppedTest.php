<?php

namespace Tests\Feature\Network;

use App\Models\NetworkNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 14: Slot OLT kini baris `network_nodes`, jadi tabel `olt_slots` (dan
 * tabel posisi lama) harus hilang setelah semua migration jalan dari nol.
 * Kalau ada yang menghidupkan lagi tabelnya, dua sumber kebenaran Slot muncul.
 *
 * Final review I3: drop `olt_slots` berisi data wajib lewat saklar
 * konfirmasi yang sama dengan migrasi retarget (2026_10_24_000002).
 */
class OltSlotsTableDroppedTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_25_000001_drop_olt_slots_table.php';

    private function migration(): object
    {
        return require base_path(self::MIGRATION);
    }

    /**
     * Bangun ulang tabel lama lewat `down()` migrasi itu sendiri (struktur
     * sama persis), lalu isi baris.
     */
    private function recreateWithRows(int $rows): void
    {
        $this->migration()->down();
        for ($number = 1; $number <= $rows; $number++) {
            DB::table('olt_slots')->insert(['chassis_node_id' => NetworkNode::factory()->create()->id, 'number' => $number]);
        }
    }

    #[Test]
    public function tabel_slot_dan_port_lama_tidak_ada_setelah_migrate_dari_nol(): void
    {
        foreach (['olt_slots', 'infrastructure_slots', 'infrastructure_ports', 'infrastructure_legacy_mappings'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Tabel {$table} seharusnya sudah dibuang.");
        }
        $this->assertTrue(Schema::hasTable('network_nodes'));
    }

    #[Test]
    public function olt_slots_berisi_ditolak_tanpa_konfirmasi_di_luar_testing(): void
    {
        $this->recreateWithRows(2);
        $this->app['env'] = 'production';
        config(['infrastructure.confirm_dummy_reset' => false]);

        $refusal = null;
        try {
            $this->migration()->up();
        } catch (RuntimeException $exception) {
            $refusal = $exception;
        }

        $this->assertNotNull($refusal, 'Drop olt_slots berisi harus ditolak tanpa konfirmasi.');
        $this->assertStringContainsString('2 baris', $refusal->getMessage());
        $this->assertStringContainsString('INFRA_CONFIRM_DUMMY_RESET=true', $refusal->getMessage());
        $this->assertTrue(Schema::hasTable('olt_slots'));
        $this->assertSame(2, DB::table('olt_slots')->count());
    }

    #[Test]
    public function olt_slots_berisi_dibuang_dengan_konfirmasi_boolean_true(): void
    {
        $this->recreateWithRows(2);
        $this->app['env'] = 'production';

        // Ditangkap ke variabel, bukan fail() di dalam try: exception asersi
        // PHPUnit turunan RuntimeException dan akan tertelan catch.
        config(['infrastructure.confirm_dummy_reset' => 'true']);
        $refused = false;
        try {
            $this->migration()->up();
        } catch (RuntimeException) {
            $refused = true;
        }
        $this->assertTrue($refused, 'String "true" bukan konfirmasi.');
        $this->assertTrue(Schema::hasTable('olt_slots'));

        config(['infrastructure.confirm_dummy_reset' => true]);
        $this->migration()->up();
        $this->assertFalse(Schema::hasTable('olt_slots'));
    }

    #[Test]
    public function olt_slots_kosong_dibuang_tanpa_konfirmasi(): void
    {
        $this->recreateWithRows(0);
        $this->app['env'] = 'production';
        config(['infrastructure.confirm_dummy_reset' => false]);

        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('olt_slots'));
    }
}
