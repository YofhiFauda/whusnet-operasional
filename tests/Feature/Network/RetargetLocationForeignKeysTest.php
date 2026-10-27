<?php

namespace Tests\Feature\Network;

use App\Models\Customer;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\User;
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
 * tabel posisi lama dibuang. Migrasi destruktif — guard konfirmasi, NULL
 * kolom lama, penyelamatan lokasi ke perangkat induk (final review I2), dan
 * D2 (ODP berpelanggan tidak boleh ikut terhapus) masing-masing diuji di sini.
 *
 * RefreshDatabase sudah menjalankan migrasi ini sekali (lolos guard karena
 * env `testing`) sehingga tabel lama hilang. Tes yang butuh keadaan "lama"
 * membangunnya ulang: tabel slot/port minimal, FK ke-14 kolom lokasi
 * dipasang balik ke tabel lama, kolom diisi id lama (9xxx — sengaja tidak
 * ada di `network_nodes`), lalu `up()` migrasi asli dijalankan.
 */
class RetargetLocationForeignKeysTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_24_000002_retarget_location_foreign_keys_to_network_nodes.php';

    private const SLOT_IDS = [9001, 9002];

    private const PORT_IDS = [9101, 9102, 9103];

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
     * Kolom lokasi yang di-NULL-kan (koneksi dihapus utuh, bukan di-NULL).
     *
     * @return array<string, list<string>>
     */
    private function nullableLocationColumns(): array
    {
        return collect($this->migration()::LOCATION_FOREIGN_KEYS)
            ->except('infrastructure_connections')
            ->map(fn (array $columns): array => array_keys($columns))
            ->all();
    }

    private function typeId(string $code): int
    {
        return (int) NetworkNodeType::where('code', $code)->value('id');
    }

    /**
     * Bentuk minimal tabel lama — cukup untuk dirujuk FK, dihitung, dihapus, dan di-drop.
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

    /**
     * Pasang balik FK ke-14 kolom lokasi ke tabel lama (keadaan sebelum
     * Task 5). Dikerjakan SEBELUM baris yang merujuk disisipkan.
     */
    private function restoreLegacyForeignKeys(): void
    {
        // Artefak tes, bukan skema nyata: RefreshDatabase membungkus tes dalam
        // transaksi, dan di dalam transaksi sqlite mengabaikan PRAGMA
        // foreign_keys=OFF — rebuild fop_tasks (drop table) lalu ditolak
        // karena baris aksi laporan merujuknya. Migrasi sendiri berjalan di
        // luar transaksi ($withinTransaction = false) sehingga tidak kena.
        // FK lokasi tetap ditegakkan di tes ini — justru itu yang menangkap
        // urutan retarget-sebelum-NULL.
        Schema::table('task_infrastructure_actions', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['fop_task_id']);
        });

        foreach ($this->nullableLocationColumns() as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropForeign([$column]);
                }
            });
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->foreign($column)->references('id')
                        ->on(str_contains($column, '_slot_') ? 'infrastructure_slots' : 'infrastructure_ports')
                        ->restrictOnDelete();
                }
            });
        }
    }

    private function seedLegacyRows(NetworkNode $chassis): void
    {
        foreach (self::SLOT_IDS as $i => $id) {
            DB::table('infrastructure_slots')->insert(['id' => $id, 'network_node_id' => $chassis->id, 'number' => $i + 1]);
        }
        foreach (self::PORT_IDS as $i => $id) {
            DB::table('infrastructure_ports')->insert(['id' => $id, 'infrastructure_slot_id' => self::SLOT_IDS[0], 'number' => $i + 1]);
        }
        // FK koneksi tetap ke network_nodes (tidak dibangun balik), jadi sumber = id node.
        DB::table('infrastructure_connections')->insert([
            'source_infrastructure_port_id' => $chassis->id,
            'verification_status' => 'belum_terverifikasi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('infrastructure_legacy_mappings')->insert(['legacy_type' => 'olt_slot', 'legacy_id' => 1, 'status' => 'mapped']);
    }

    /**
     * Satu baris per tabel lokasi dengan SEMUA kolom slot/port terisi id lama,
     * plus dua saldo: satu yatim (hanya Slot) dan satu yang juga punya node.
     *
     * @return array<string, int>
     */
    private function seedLocationRows(NetworkNode $chassis): array
    {
        [$slot, $slot2] = self::SLOT_IDS;
        [$port, $port2] = self::PORT_IDS;

        $categoryId = DB::table('item_categories')->insertGetId(['code' => 'retarget', 'name' => 'Retarget', 'default_unit' => 'pcs']);
        $itemId = DB::table('items')->insertGetId(['code' => 'RT-1', 'name' => 'Aset', 'unit' => 'pcs', 'item_category_id' => $categoryId,
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset']);
        $pop = Pop::factory()->create();
        $user = User::factory()->create();
        $taskId = DB::table('tasks')->insertGetId(['task_number' => 'TASK-RT-1', 'pop_id' => $pop->id, 'task_type' => 'INFR REQ', 'title' => 'Retarget']);

        return [
            'serial' => DB::table('inventory_serials')->insertGetId(['item_id' => $itemId, 'serial_number' => 'SN-RT', 'status' => 'deployed',
                'current_infrastructure_slot_id' => $slot, 'current_infrastructure_port_id' => $port]),
            'transaction' => DB::table('inventory_transactions')->insertGetId(['type' => 'deploy', 'item_id' => $itemId, 'qty' => 1,
                'from_infrastructure_slot_id' => $slot, 'to_infrastructure_slot_id' => $slot2,
                'from_infrastructure_port_id' => $port, 'to_infrastructure_port_id' => $port2]),
            'balance_orphan' => DB::table('infrastructure_balances')->insertGetId(['item_id' => $itemId, 'infrastructure_slot_id' => $slot, 'qty' => 3]),
            'balance_located' => DB::table('infrastructure_balances')->insertGetId(['item_id' => $itemId, 'network_node_id' => $chassis->id,
                'infrastructure_port_id' => $port, 'qty' => 2]),
            'fop_task' => $fopTaskId = DB::table('fop_tasks')->insertGetId(['task_number' => 'TFOP-RT-1', 'category' => 'INFR REQ', 'tugas' => 'Retarget',
                'scope_infrastructure_slot_id' => $slot, 'scope_infrastructure_port_id' => $port]),
            'action' => DB::table('task_infrastructure_actions')->insertGetId(['task_id' => $taskId, 'fop_task_id' => $fopTaskId,
                'action_key' => 'rt-1', 'action_type' => 'install', 'mutation_hash' => 'h', 'mutation' => '{}',
                'performed_by' => $user->id, 'performed_at' => now(),
                'work_infrastructure_slot_id' => $slot, 'work_infrastructure_port_id' => $port,
                'end_from_infrastructure_port_id' => $port, 'end_to_infrastructure_port_id' => $port2]),
        ];
    }

    /**
     * @return array{chassis: NetworkNode, rows: array<string, int>}
     */
    private function buildLegacyState(): array
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $chassis = NetworkNode::factory()->create(['node_type_id' => $this->typeId('olt_chassis')]);

        $this->recreateLegacyTables();
        $this->restoreLegacyForeignKeys();
        $this->seedLegacyRows($chassis);

        return ['chassis' => $chassis, 'rows' => $this->seedLocationRows($chassis)];
    }

    private function assertLocationForeignKeysTargetNetworkNodes(): void
    {
        foreach ($this->migration()::LOCATION_FOREIGN_KEYS as $table => $columns) {
            $keys = collect(Schema::getForeignKeys($table));
            foreach (array_keys($columns) as $column) {
                $matches = $keys->filter(fn (array $key): bool => $key['columns'] === [$column]);
                $this->assertCount(1, $matches, "{$table}.{$column} harus punya tepat satu FK.");
                $this->assertSame('network_nodes', $matches->first()['foreign_table'], "{$table}.{$column} harus menunjuk network_nodes.");
                $this->assertSame(['id'], $matches->first()['foreign_columns']);
                $this->assertSame('restrict', strtolower((string) $matches->first()['on_delete']), "{$table}.{$column} harus restrictOnDelete.");
            }
        }
    }

    private function assertLegacyTablesDropped(): void
    {
        $this->assertFalse(Schema::hasTable('infrastructure_slots'));
        $this->assertFalse(Schema::hasTable('infrastructure_ports'));
        $this->assertFalse(Schema::hasTable('infrastructure_legacy_mappings'));
    }

    private function captureRefusal(): ?RuntimeException
    {
        // Ditangkap ke variabel, bukan fail() di dalam try: exception
        // asersi PHPUnit sendiri turunan RuntimeException dan akan tertelan catch.
        try {
            $this->runMigrationUp();
        } catch (RuntimeException $e) {
            return $e;
        }

        return null;
    }

    #[Test]
    public function migrasi_menolak_tanpa_konfirmasi_di_luar_testing(): void
    {
        $state = $this->buildLegacyState();

        $this->app['env'] = 'production';
        config(['infrastructure.confirm_dummy_reset' => false]);

        $refusal = $this->captureRefusal();

        $this->assertNotNull($refusal, 'Migrasi harus menolak tanpa konfirmasi di luar env testing.');
        foreach (['2 slot', '3 port', '1 koneksi', '1 mapping', 'INFRA_CONFIRM_DUMMY_RESET=true', 'infrastructure.confirm_dummy_reset'] as $expected) {
            $this->assertStringContainsString($expected, $refusal->getMessage());
        }

        // Penolakan terjadi SEBELUM data disentuh.
        $this->assertSame(2, DB::table('infrastructure_slots')->count());
        $this->assertSame(3, DB::table('infrastructure_ports')->count());
        $this->assertSame(1, DB::table('infrastructure_connections')->count());
        $this->assertSame(1, DB::table('infrastructure_legacy_mappings')->count());
        $this->assertSame(self::SLOT_IDS[0], (int) DB::table('inventory_serials')->where('id', $state['rows']['serial'])->value('current_infrastructure_slot_id'));
        $this->assertSame(2, DB::table('infrastructure_balances')->count());
    }

    #[Test]
    public function pesan_penolakan_menghitung_baris_terdampak_dan_saldo_yang_dihapus(): void
    {
        $this->buildLegacyState();

        $this->app['env'] = 'production';
        config(['infrastructure.confirm_dummy_reset' => false]);

        $message = $this->captureRefusal()?->getMessage() ?? '';

        // Satu baris per tabel di-NULL-kan; lokasi yang punya perangkat induk
        // DIPINDAHKAN ke perangkat itu (serial 1, transaksi 2 sisi, saldo
        // yatim 1), sehingga tidak ada saldo yang dihapus.
        foreach ([
            'inventory_serials 1 baris',
            'inventory_transactions 1 baris',
            'infrastructure_balances 2 baris',
            'fop_tasks 1 baris',
            'task_infrastructure_actions 1 baris',
            'MEMINDAHKAN lokasi Slot/Port ke perangkat induknya pada: inventory_serials 1 lokasi, inventory_transactions 2 lokasi, infrastructure_balances 1 lokasi',
            'TIDAK menemukan perangkat induk (lokasi dikosongkan) pada: (tidak ada)',
            'MENGHAPUS 0 saldo infrastructure_balances',
        ] as $expected) {
            $this->assertStringContainsString($expected, $message);
        }
    }

    #[Test]
    public function db_kosong_lolos_tanpa_konfirmasi_di_luar_testing(): void
    {
        // Deploy baru / DB tanpa data posisi lama: tidak ada yang dibuang,
        // jadi tidak butuh saklar.
        $this->recreateLegacyTables();
        $this->app['env'] = 'production';
        config(['infrastructure.confirm_dummy_reset' => false]);

        $this->assertNull($this->captureRefusal(), 'DB tanpa baris lama tidak boleh ditolak.');
        $this->assertLegacyTablesDropped();
        $this->assertLocationForeignKeysTargetNetworkNodes();
    }

    #[Test]
    public function konfirmasi_eksplisit_meloloskan_guard_di_luar_testing(): void
    {
        $this->buildLegacyState();
        $this->app['env'] = 'production';
        config(['infrastructure.confirm_dummy_reset' => true]);

        $this->runMigrationUp();

        $this->assertLegacyTablesDropped();
        $this->assertLocationForeignKeysTargetNetworkNodes();
    }

    #[Test]
    public function konfirmasi_harus_boolean_true_bukan_sekadar_truthy(): void
    {
        $this->app['env'] = 'production';
        config(['infrastructure.confirm_dummy_reset' => 'true']);

        $this->expectException(RuntimeException::class);
        $this->migration()->assertConfirmed([
            'legacy' => ['slots' => 1, 'ports' => 0, 'connections' => 0, 'mappings' => 0],
            'affected' => [],
            'repointed' => [],
            'unresolved' => [],
            'balances_deleted' => 0,
        ]);
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

        // Daftar uji ditulis tangan = daftar konstanta migrasi (tidak ada kolom yang lolos dari tes).
        $this->assertSame($expected, collect($this->migration()::LOCATION_FOREIGN_KEYS)->map(fn (array $columns): array => array_keys($columns))->all());
        $this->assertLocationForeignKeysTargetNetworkNodes();

        // Nama FK eksplisit ujung sambungan dipertahankan di driver yang menyimpan nama.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $names = collect(Schema::getForeignKeys('task_infrastructure_actions'))->pluck('name');
            $this->assertContains('tia_end_from_port_foreign', $names);
            $this->assertContains('tia_end_to_port_foreign', $names);
        }
    }

    #[Test]
    public function retarget_data_lama_mengosongkan_seluruh_kolom_lokasi(): void
    {
        $this->buildLegacyState();
        $serialIndexes = collect(Schema::getIndexes('inventory_serials'))->pluck('name')->sort()->values()->all();

        $this->runMigrationUp();

        $checked = 0;
        foreach ($this->nullableLocationColumns() as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(DB::table($table)->whereNotNull($column)->doesntExist(), "{$table}.{$column} harus NULL.");
                $checked++;
            }
        }
        $this->assertSame(14, $checked);

        $this->assertLocationForeignKeysTargetNetworkNodes();
        $this->assertLegacyTablesDropped();
        $this->assertSame(0, DB::table('infrastructure_connections')->count());
        // Rebuild tabel sqlite tidak boleh menghilangkan index lain.
        $this->assertSame($serialIndexes, collect(Schema::getIndexes('inventory_serials'))->pluck('name')->sort()->values()->all());
        // Baris tidak dihapus — hanya kolomnya yang di-NULL-kan.
        $this->assertSame(1, DB::table('inventory_transactions')->count());
        $this->assertSame(1, DB::table('fop_tasks')->count());
        $this->assertSame(1, DB::table('task_infrastructure_actions')->count());
        $this->assertSame('deployed', DB::table('inventory_serials')->value('status'));
    }

    /**
     * Final review ADHOC-213 (I2): saldo yang satu-satunya lokasinya Slot/Port
     * dulu DIHAPUS walau perangkat induknya masih tercatat di
     * `infrastructure_slots.network_node_id`. Kini qty-nya pindah ke perangkat
     * induk — digabung ke saldo perangkat yang sudah ada untuk barang & lot
     * yang sama, supaya tidak ada dua baris saldo untuk satu lokasi.
     */
    #[Test]
    public function saldo_yang_hanya_berlokasi_slot_port_pindah_ke_perangkat_induk(): void
    {
        $state = $this->buildLegacyState();

        $this->runMigrationUp();

        $this->assertFalse(DB::table('infrastructure_balances')->where('id', $state['rows']['balance_orphan'])->exists(),
            'Baris saldo Slot lama digabung ke saldo perangkat, bukan dibiarkan jadi baris kedua.');
        $located = DB::table('infrastructure_balances')->where('id', $state['rows']['balance_located'])->first();
        $this->assertNotNull($located, 'Saldo yang masih punya lokasi node harus tetap ada.');
        $this->assertSame($state['chassis']->id, (int) $located->network_node_id);
        $this->assertNull($located->infrastructure_port_id);
        $this->assertEquals(5, $located->qty, 'qty 3 dari Slot lama + qty 2 di perangkat — tidak ada qty yang hilang.');
        $this->assertSame(1, DB::table('infrastructure_balances')->count());
    }

    #[Test]
    public function saldo_slot_tanpa_saldo_perangkat_dipindah_utuh_ke_perangkat_induk(): void
    {
        $state = $this->buildLegacyState();
        DB::table('infrastructure_balances')->where('id', $state['rows']['balance_located'])->delete();

        $this->runMigrationUp();

        $moved = DB::table('infrastructure_balances')->where('id', $state['rows']['balance_orphan'])->first();
        $this->assertNotNull($moved);
        $this->assertSame($state['chassis']->id, (int) $moved->network_node_id);
        $this->assertNull($moved->infrastructure_slot_id);
        $this->assertEquals(3, $moved->qty);
    }

    /**
     * I2: serial DEPLOYED di Slot/Port lama dulu kehilangan lokasi total
     * (tidak terlacak di mana pun). Kini tetap terlacak di perangkat induk.
     */
    #[Test]
    public function serial_terpasang_dan_ledger_tetap_berlokasi_di_perangkat_induk(): void
    {
        $state = $this->buildLegacyState();

        $this->runMigrationUp();

        $serial = DB::table('inventory_serials')->where('id', $state['rows']['serial'])->first();
        $this->assertSame('deployed', $serial->status);
        $this->assertSame($state['chassis']->id, (int) $serial->current_network_node_id);
        $this->assertNull($serial->current_infrastructure_slot_id);
        $this->assertNull($serial->current_infrastructure_port_id);

        $transaction = DB::table('inventory_transactions')->where('id', $state['rows']['transaction'])->first();
        $this->assertSame($state['chassis']->id, (int) $transaction->from_network_node_id);
        $this->assertSame($state['chassis']->id, (int) $transaction->to_network_node_id);
    }

    #[Test]
    public function serial_yang_sudah_punya_lokasi_lain_tidak_ditimpa(): void
    {
        $state = $this->buildLegacyState();
        $other = NetworkNode::factory()->create(['node_type_id' => $this->typeId('olt_chassis')]);
        DB::table('inventory_serials')->where('id', $state['rows']['serial'])->update(['current_network_node_id' => $other->id]);

        $this->runMigrationUp();

        $this->assertSame($other->id, (int) DB::table('inventory_serials')->where('id', $state['rows']['serial'])->value('current_network_node_id'));
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
        $this->assertLegacyTablesDropped();

        // Dipertahankan (spec §3): koneksi non-pohon dan port fisik ODP.
        $this->assertTrue(Schema::hasTable('infrastructure_connections'));
        $this->assertTrue(Schema::hasTable('network_node_ports'));
        $this->assertTrue(Schema::hasTable('network_port_assignments'));
    }

    #[Test]
    public function node_odp_berpelanggan_tidak_dihapus(): void
    {
        $state = $this->buildLegacyState();

        $odp = NetworkNode::factory()->create(['node_type_id' => $this->typeId('odp')]);
        $portId = DB::table('network_node_ports')->insertGetId([
            'network_node_id' => $odp->id, 'number' => 1, 'is_used' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $customer = Customer::factory()->create();
        $assignmentId = DB::table('network_port_assignments')->insertGetId([
            'network_node_port_id' => $portId, 'customer_id' => $customer->id, 'active_key' => 1,
            'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $nodesBefore = DB::table('network_nodes')->count();
        $nodePortsBefore = DB::table('network_node_ports')->count();
        $assignmentsBefore = DB::table('network_port_assignments')->count();

        $this->runMigrationUp();

        // D2: tidak satu pun baris network_nodes / port ODP / assignment terhapus.
        $this->assertSame($nodesBefore, DB::table('network_nodes')->count());
        $this->assertSame($nodePortsBefore, DB::table('network_node_ports')->count());
        $this->assertSame($assignmentsBefore, DB::table('network_port_assignments')->count());
        $this->assertTrue(DB::table('network_nodes')->where('id', $odp->id)->exists());
        $this->assertTrue(DB::table('network_nodes')->where('id', $state['chassis']->id)->exists());
        $this->assertSame(1, (int) DB::table('network_port_assignments')->where('id', $assignmentId)->value('active_key'));
        $this->assertSame($customer->id, (int) DB::table('network_port_assignments')->where('id', $assignmentId)->value('customer_id'));
        $this->assertLegacyTablesDropped();
    }
}
