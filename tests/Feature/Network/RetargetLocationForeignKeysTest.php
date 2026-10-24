<?php

namespace Tests\Feature\Network;

use App\Models\Customer;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 5 ADHOC-213: FK lokasi "slot"/"port" diarahkan ke `network_nodes`,
 * tabel posisi lama dibuang. Migrasi destruktif — guard konfirmasi dan D2
 * (ODP berpelanggan tidak boleh ikut terhapus) masing-masing diuji di sini.
 *
 * RefreshDatabase sudah menjalankan migrasi ini sekali (lolos guard karena
 * env `testing`). Tabel lama sudah hilang, jadi tes guard & D2 membuat
 * ulang tabel lama berbentuk minimal lalu memanggil `up()` migrasi asli.
 */
class RetargetLocationForeignKeysTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_24_000002_retarget_location_foreign_keys_to_network_nodes.php';

    /**
     * Kelas anonim migrasi tetap bisa diuji: `require` mengembalikan
     * instance-nya, jadi tes memanggil `up()` / method publik yang sama
     * persis dengan yang dijalankan Migrator — tanpa salinan logika di
     * kelas helper terpisah.
     */
    private function migration(): object
    {
        return require base_path(self::MIGRATION);
    }

    private function runMigrationUp(): void
    {
        $this->migration()->up();
    }

    /**
     * Bentuk minimal tabel lama — cukup untuk dihitung, dihapus, dan di-drop.
     */
    private function recreateLegacyTables(): void
    {
        Schema::create('infrastructure_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_node_id')->constrained('network_nodes')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
        });
        Schema::create('infrastructure_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('infrastructure_slot_id')->constrained('infrastructure_slots')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
        });
        Schema::create('infrastructure_legacy_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_type', 50);
            $table->unsignedBigInteger('legacy_id');
            $table->string('status', 20);
        });
    }

    private function typeId(string $code): int
    {
        return (int) NetworkNodeType::where('code', $code)->value('id');
    }

    /**
     * @return array{chassis: NetworkNode, slots: list<int>, ports: list<int>}
     */
    private function seedLegacyRows(): array
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $chassis = NetworkNode::factory()->create(['node_type_id' => $this->typeId('olt_chassis')]);

        $slots = [
            DB::table('infrastructure_slots')->insertGetId(['network_node_id' => $chassis->id, 'number' => 1]),
            DB::table('infrastructure_slots')->insertGetId(['network_node_id' => $chassis->id, 'number' => 2]),
        ];
        $ports = [
            DB::table('infrastructure_ports')->insertGetId(['infrastructure_slot_id' => $slots[0], 'number' => 1]),
            DB::table('infrastructure_ports')->insertGetId(['infrastructure_slot_id' => $slots[0], 'number' => 2]),
            DB::table('infrastructure_ports')->insertGetId(['infrastructure_slot_id' => $slots[1], 'number' => 1]),
        ];
        // FK koneksi sudah menunjuk network_nodes, jadi sumber = id node.
        DB::table('infrastructure_connections')->insert([
            'source_infrastructure_port_id' => $chassis->id,
            'verification_status' => 'belum_terverifikasi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('infrastructure_legacy_mappings')->insert(['legacy_type' => 'olt_slot', 'legacy_id' => 1, 'status' => 'mapped']);

        return ['chassis' => $chassis, 'slots' => $slots, 'ports' => $ports];
    }

    #[Test]
    public function migrasi_menolak_tanpa_konfirmasi_di_luar_testing(): void
    {
        $this->recreateLegacyTables();
        $this->seedLegacyRows();

        $this->app['env'] = 'production';
        config(['infrastructure.confirm_dummy_reset' => false]);

        // Ditangkap ke variabel, bukan fail() di dalam try: exception
        // asersi PHPUnit sendiri turunan RuntimeException dan akan tertelan catch.
        $refusal = null;
        try {
            $this->runMigrationUp();
        } catch (RuntimeException $e) {
            $refusal = $e;
        }

        $this->assertNotNull($refusal, 'Migrasi harus menolak tanpa konfirmasi di luar env testing.');
        $this->assertStringContainsString('2 slot', $refusal->getMessage());
        $this->assertStringContainsString('3 port', $refusal->getMessage());
        $this->assertStringContainsString('1 koneksi', $refusal->getMessage());
        $this->assertStringContainsString('1 mapping', $refusal->getMessage());
        $this->assertStringContainsString('INFRA_CONFIRM_DUMMY_RESET=true', $refusal->getMessage());
        $this->assertStringContainsString('infrastructure.confirm_dummy_reset', $refusal->getMessage());

        // Penolakan terjadi SEBELUM data disentuh.
        $this->assertTrue(Schema::hasTable('infrastructure_slots'));
        $this->assertSame(2, DB::table('infrastructure_slots')->count());
        $this->assertSame(3, DB::table('infrastructure_ports')->count());
        $this->assertSame(1, DB::table('infrastructure_connections')->count());
        $this->assertSame(1, DB::table('infrastructure_legacy_mappings')->count());
    }

    #[Test]
    public function konfirmasi_eksplisit_meloloskan_guard_di_luar_testing(): void
    {
        $this->app['env'] = 'production';
        $retirement = $this->migration();

        config(['infrastructure.confirm_dummy_reset' => true]);
        $retirement->assertConfirmed(['slots' => 1, 'ports' => 1, 'connections' => 0, 'mappings' => 0]);

        // Bukan sekadar truthy: string "true" dari env mentah tanpa cast ditolak.
        config(['infrastructure.confirm_dummy_reset' => 'true']);
        $this->expectException(RuntimeException::class);
        $retirement->assertConfirmed(['slots' => 1, 'ports' => 1, 'connections' => 0, 'mappings' => 0]);
    }

    #[Test]
    public function fk_lokasi_menunjuk_network_nodes(): void
    {
        $expected = [
            'inventory_serials' => ['current_infrastructure_slot_id', 'current_infrastructure_port_id'],
            'inventory_transactions' => ['from_infrastructure_slot_id', 'to_infrastructure_slot_id', 'from_infrastructure_port_id', 'to_infrastructure_port_id'],
            'infrastructure_balances' => ['infrastructure_slot_id', 'infrastructure_port_id'],
            'task_infrastructure_actions' => ['work_infrastructure_slot_id', 'work_infrastructure_port_id', 'end_from_infrastructure_port_id', 'end_to_infrastructure_port_id'],
            'fop_tasks' => ['scope_infrastructure_slot_id', 'scope_infrastructure_port_id'],
            'infrastructure_connections' => ['source_infrastructure_port_id', 'target_infrastructure_port_id'],
        ];

        $checked = 0;
        foreach ($expected as $table => $columns) {
            $keys = collect(Schema::getForeignKeys($table));
            foreach ($columns as $column) {
                $matches = $keys->filter(fn (array $key): bool => $key['columns'] === [$column]);
                $this->assertCount(1, $matches, "{$table}.{$column} harus punya tepat satu FK.");
                $this->assertSame('network_nodes', $matches->first()['foreign_table'], "{$table}.{$column} harus menunjuk network_nodes.");
                $this->assertSame(['id'], $matches->first()['foreign_columns']);
                $this->assertSame('restrict', strtolower((string) $matches->first()['on_delete']), "{$table}.{$column} harus restrictOnDelete.");
                $checked++;
            }
        }

        // Daftar uji = daftar yang diretarget migrasi (tidak ada kolom yang lolos dari tes).
        $this->assertSame($checked, collect($this->migration()::LOCATION_FOREIGN_KEYS)->map(fn (array $columns): int => count($columns))->sum());

        // Nama FK eksplisit ujung sambungan dipertahankan di driver yang menyimpan nama.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $names = collect(Schema::getForeignKeys('task_infrastructure_actions'))->pluck('name');
            $this->assertContains('tia_end_from_port_foreign', $names);
            $this->assertContains('tia_end_to_port_foreign', $names);
        }
    }

    #[Test]
    public function rollback_ditolak(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak dapat dibalik: data posisi lama sudah dibersihkan.');

        $this->migration()->down();
    }

    #[Test]
    public function tabel_lama_hilang(): void
    {
        $this->assertFalse(Schema::hasTable('infrastructure_slots'));
        $this->assertFalse(Schema::hasTable('infrastructure_ports'));
        $this->assertFalse(Schema::hasTable('infrastructure_legacy_mappings'));

        // Dipertahankan (spec §3): koneksi non-pohon dan port fisik ODP.
        $this->assertTrue(Schema::hasTable('infrastructure_connections'));
        $this->assertTrue(Schema::hasTable('network_node_ports'));
        $this->assertTrue(Schema::hasTable('network_port_assignments'));
    }

    #[Test]
    public function node_odp_berpelanggan_tidak_dihapus(): void
    {
        $this->recreateLegacyTables();
        $legacy = $this->seedLegacyRows();

        $odp = NetworkNode::factory()->create(['node_type_id' => $this->typeId('odp')]);
        $portId = DB::table('network_node_ports')->insertGetId([
            'network_node_id' => $odp->id, 'number' => 1, 'is_used' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $customer = Customer::factory()->create();
        $assignmentId = DB::table('network_port_assignments')->insertGetId([
            'network_node_port_id' => $portId, 'customer_id' => $customer->id, 'active_key' => 1,
            'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Kolom lokasi yang berisi id posisi lama harus di-NULL-kan (id slot
        // lama tidak bermakna sebagai id node). Nilai = id chassis supaya FK
        // ke network_nodes yang sudah diretarget tetap terpenuhi saat insert.
        $categoryId = DB::table('item_categories')->insertGetId(['code' => 'retarget', 'name' => 'Retarget', 'default_unit' => 'pcs']);
        $itemId = DB::table('items')->insertGetId(['code' => 'RT-1', 'name' => 'Aset', 'unit' => 'pcs', 'item_category_id' => $categoryId,
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset']);
        $serialId = DB::table('inventory_serials')->insertGetId(['item_id' => $itemId, 'serial_number' => 'SN-RT', 'status' => 'deployed',
            'current_infrastructure_port_id' => $legacy['chassis']->id]);
        $balanceId = DB::table('infrastructure_balances')->insertGetId(['item_id' => $itemId, 'infrastructure_slot_id' => $legacy['chassis']->id, 'qty' => 1]);

        $nodesBefore = DB::table('network_nodes')->count();

        $this->runMigrationUp();

        // D2: tidak satu pun baris network_nodes / port ODP / assignment terhapus.
        $this->assertSame($nodesBefore, DB::table('network_nodes')->count());
        $this->assertTrue(DB::table('network_nodes')->where('id', $odp->id)->exists());
        $this->assertTrue(DB::table('network_nodes')->where('id', $legacy['chassis']->id)->exists());
        $this->assertTrue(DB::table('network_node_ports')->where('id', $portId)->exists());
        $this->assertSame(1, (int) DB::table('network_port_assignments')->where('id', $assignmentId)->value('active_key'));
        $this->assertSame($customer->id, (int) DB::table('network_port_assignments')->where('id', $assignmentId)->value('customer_id'));

        // Data posisi lama bersih, tabel lama hilang.
        $this->assertSame(0, DB::table('infrastructure_connections')->count());
        $this->assertFalse(Schema::hasTable('infrastructure_slots'));
        $this->assertFalse(Schema::hasTable('infrastructure_ports'));
        $this->assertFalse(Schema::hasTable('infrastructure_legacy_mappings'));
        $this->assertNull(DB::table('inventory_serials')->where('id', $serialId)->value('current_infrastructure_port_id'));
        $this->assertNull(DB::table('infrastructure_balances')->where('id', $balanceId)->value('infrastructure_slot_id'));
    }
}
