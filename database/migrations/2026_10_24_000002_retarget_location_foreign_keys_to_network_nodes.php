<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Di sqlite, retarget FK = rebuild tabel (create temp → copy → drop →
     * rename). `PRAGMA foreign_keys = OFF` yang dipasang Laravel selama
     * rebuild DIABAIKAN sqlite di dalam transaksi, sehingga `drop table
     * fop_tasks` gagal begitu ada baris anak (mis. task_infrastructure_actions)
     * yang merujuknya. Di MySQL tidak berpengaruh (DDL auto-commit; data step
     * tetap punya DB::transaction sendiri).
     */
    public $withinTransaction = false;

    /**
     * Kolom lokasi "slot"/"port" yang diarahkan ke `network_nodes.id`.
     *
     * NAMA KOLOM SENGAJA TIDAK DIUBAH (spec §4, YAGNI): setelah migrasi ini
     * "slot" di nama kolom berarti node bertipe `olt_slot`/`switch_slot`, dan
     * "port" berarti node bertipe `olt_pon`/`switch_port`. Mengganti nama
     * memaksa logika "lokasi tepat" di service ditulis ulang — bukan cakupan
     * pekerjaan ini.
     *
     * Nilai = nama FK eksplisit (MySQL) atau null untuk nama bawaan Laravel
     * `{table}_{column}_foreign`. Dua ujung sambungan laporan memakai nama
     * pendek karena nama bawaannya melebihi batas 64 karakter MySQL (lihat
     * 2026_10_21_000005 & perbaikan 2026_10_21_150332).
     *
     * @var array<string, array<string, string|null>>
     */
    public const LOCATION_FOREIGN_KEYS = [
        'inventory_serials' => [
            'current_infrastructure_slot_id' => null,
            'current_infrastructure_port_id' => null,
        ],
        'inventory_transactions' => [
            'from_infrastructure_slot_id' => null,
            'to_infrastructure_slot_id' => null,
            'from_infrastructure_port_id' => null,
            'to_infrastructure_port_id' => null,
        ],
        'infrastructure_balances' => [
            'infrastructure_slot_id' => null,
            'infrastructure_port_id' => null,
        ],
        'task_infrastructure_actions' => [
            'work_infrastructure_slot_id' => null,
            'work_infrastructure_port_id' => null,
            'end_from_infrastructure_port_id' => 'tia_end_from_port_foreign',
            'end_to_infrastructure_port_id' => 'tia_end_to_port_foreign',
        ],
        'fop_tasks' => [
            'scope_infrastructure_slot_id' => null,
            'scope_infrastructure_port_id' => null,
        ],
        'infrastructure_connections' => [
            'source_infrastructure_port_id' => null,
            'target_infrastructure_port_id' => null,
        ],
    ];

    /**
     * Isi dihapus berurutan anak → induk: koneksi & mapping tidak dirujuk
     * siapa pun, port merujuk slot (restrict), slot paling akhir.
     */
    private const WIPED_TABLES = [
        'connections' => 'infrastructure_connections',
        'mappings' => 'infrastructure_legacy_mappings',
        'ports' => 'infrastructure_ports',
        'slots' => 'infrastructure_slots',
    ];

    /**
     * Tabel posisi lama yang dibuang (spec §3). `infrastructure_connections`
     * DIPERTAHANKAN untuk sambungan non-pohon — hanya isinya yang dikosongkan
     * karena seluruh barisnya menunjuk id port lama. Kolom
     * `legacy_olt_slot_id` (FK ke `olt_slots`) dan `legacy_network_node_id`
     * ikut hilang bersama tabelnya.
     */
    private const DROPPED_TABLES = [
        'infrastructure_ports',
        'infrastructure_slots',
        'infrastructure_legacy_mappings',
    ];

    /**
     * Slot/PON/Port Switch kini baris `network_nodes` (ADHOC-213 Task 5).
     *
     * DESTRUKTIF. Id di kolom lokasi lama adalah id baris
     * `infrastructure_slots`/`infrastructure_ports` — membiarkannya berarti
     * FK baru menunjuk node acak yang kebetulan ber-id sama, jadi kolom
     * slot/port di-NULL. Guard konfirmasi (spec §10) dijalankan SEBELUM satu
     * baris pun disentuh.
     *
     * Lokasi yang MASIH BISA DISELAMATKAN diselamatkan dulu (final review
     * ADHOC-213): selama migrasi, `infrastructure_slots.network_node_id` masih
     * menyebut PERANGKAT induk tiap Slot (dan Port lewat Slot-nya). Karena itu,
     * sebelum di-NULL:
     * - Serial di Slot/Port (termasuk DEPLOYED) dipindah ke
     *   `current_network_node_id` = perangkat induk — tetap terlacak "terpasang
     *   di OLT X", hanya kehilangan ketelitian Slot/Port.
     * - Sisi asal/tujuan ledger `inventory_transactions` di Slot/Port diisi
     *   `from_/to_network_node_id` = perangkat induk (ledger append-only tidak
     *   dihapus).
     * - Saldo `infrastructure_balances` yang satu-satunya lokasinya Slot/Port
     *   dipindah ke `network_node_id` = perangkat induk (digabung ke saldo
     *   perangkat yang sudah ada untuk barang & lot yang sama, supaya tetap
     *   satu baris per lokasi).
     * Hanya bila perangkat induk TIDAK bisa ditentukan (baris tabel lama
     * hilang) barulah kolom dibiarkan NULL / saldo dihapus — jumlahnya
     * dilaporkan di pesan guard dan log.
     * - `fop_tasks` & `task_infrastructure_actions`: akar scope / lokasi kerja
     *   Slot/Port tetap di-NULL; snapshot JSON (`scope_snapshot`,
     *   `work_location_snapshot`) tetap utuh sebagai jejak teks.
     * Jumlah tiap kelompok tercantum di pesan penolakan guard supaya operator
     * memutuskan dengan angka, bukan tebakan.
     *
     * D2 (mengikat): TIDAK menghapus `network_nodes` (ODP lama memegang
     * pelanggan nyata), `network_node_ports`, maupun
     * `network_port_assignments`.
     *
     * Hanya kolom yang FK-nya BELUM ke `network_nodes` yang di-NULL-kan dan
     * diretarget. Di MySQL DDL auto-commit; bila migrate gagal di tengah lalu
     * dijalankan ulang, kolom yang sudah diretarget (dan mungkin sudah diisi
     * id node baru) tidak ikut di-NULL-kan ulang.
     */
    public function up(): void
    {
        $legacy = $this->legacyColumns();
        $counts = $this->rowCounts($legacy);
        $this->assertConfirmed($counts);

        $this->clearLegacyData($legacy);
        $this->retargetForeignKeys($legacy);

        if ($counts['balances_deleted'] > 0 || array_sum($counts['unresolved']) > 0) {
            Log::warning('Retarget FK lokasi: sebagian lokasi Slot/Port lama tidak punya perangkat induk dan dikosongkan/dihapus.', $counts);
        }

        foreach (self::DROPPED_TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Tidak dapat dibalik: data posisi lama sudah dibersihkan.');
    }

    /**
     * Kolom lokasi yang FK-nya belum menunjuk `network_nodes` (masih ke
     * tabel lama, atau hilang sama sekali seperti kasus 2026_10_21_150332),
     * beserta identitas drop FK-nya.
     *
     * Identitas drop = array kolom di sqlite (driver itu tidak bisa drop FK
     * per nama) dan nama ASLI dari getForeignKeys() di MySQL — bukan nama
     * tebakan, supaya FK yang dulu dibuat dengan nama lain tetap terhapus.
     *
     * @return array<string, array<string, array{name: string|null, drop: string|list<string>|null}>>
     */
    public function legacyColumns(): array
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        $legacy = [];

        foreach (self::LOCATION_FOREIGN_KEYS as $table => $columns) {
            $existing = collect(Schema::getForeignKeys($table));
            foreach ($columns as $column => $name) {
                $current = $existing->first(fn (array $key): bool => $key['columns'] === [$column]);
                if ($current !== null && $current['foreign_table'] === 'network_nodes') {
                    continue;
                }

                $legacy[$table][$column] = [
                    'name' => $name,
                    'drop' => $current === null ? null : ($isSqlite ? [$column] : $current['name']),
                ];
            }
        }

        return $legacy;
    }

    /**
     * @param  array<string, array<string, mixed>>  $legacy  hasil legacyColumns()
     * @return array{legacy: array{slots: int, ports: int, connections: int, mappings: int}, affected: array<string, int>, repointed: array<string, int>, unresolved: array<string, int>, balances_deleted: int}
     */
    public function rowCounts(array $legacy): array
    {
        $counts = ['legacy' => [], 'affected' => [], 'repointed' => [], 'unresolved' => [], 'balances_deleted' => 0];
        foreach (self::WIPED_TABLES as $key => $table) {
            $counts['legacy'][$key] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
        }

        foreach ($this->nullableLegacyColumns($legacy) as $table => $columns) {
            $counts['affected'][$table] = $this->rowsWithAnyValue($table, $columns)->count();
        }

        foreach ($this->repointPlan($legacy) as $entry) {
            if ($entry['device_id'] !== null) {
                $counts['repointed'][$entry['table']] = ($counts['repointed'][$entry['table']] ?? 0) + 1;
            } elseif ($entry['table'] === 'infrastructure_balances') {
                $counts['balances_deleted']++;
            } else {
                $counts['unresolved'][$entry['table']] = ($counts['unresolved'][$entry['table']] ?? 0) + 1;
            }
        }

        if (isset($counts['affected']['infrastructure_balances'])) {
            $counts['affected']['infrastructure_balances'] -= $counts['balances_deleted'];
        }

        return $counts;
    }

    /**
     * Rencana penyelamatan lokasi: tiap lokasi Slot/Port lama yang TIDAK punya
     * lokasi lain di sisi yang sama, beserta perangkat induknya (null bila
     * tak dapat ditentukan). Satu fungsi untuk hitung guard DAN eksekusi,
     * supaya angka di pesan penolakan = yang benar-benar dikerjakan.
     *
     * Hanya kolom yang masih legacy (belum diretarget) yang dibaca: id di
     * kolom yang sudah menunjuk `network_nodes` (migrate ulang setelah gagal
     * di tengah) adalah id node, bukan id Slot/Port lama.
     *
     * @param  array<string, array<string, mixed>>  $legacy
     * @return list<array{table: string, id: int, set: string, device_id: ?int, row: object}>
     */
    private function repointPlan(array $legacy): array
    {
        $maps = $this->deviceMaps();
        $plan = [];

        // [tabel, kolom node tujuan, kolom slot, kolom port, kolom lokasi lain sisi itu]
        $sides = [
            ['inventory_serials', 'current_network_node_id', 'current_infrastructure_slot_id', 'current_infrastructure_port_id',
                ['current_mini_pop_id', 'current_infrastructure_pop_id']],
            ['inventory_transactions', 'from_network_node_id', 'from_infrastructure_slot_id', 'from_infrastructure_port_id',
                ['from_mini_pop_id', 'from_infrastructure_pop_id']],
            ['inventory_transactions', 'to_network_node_id', 'to_infrastructure_slot_id', 'to_infrastructure_port_id',
                ['to_mini_pop_id', 'to_infrastructure_pop_id']],
            ['infrastructure_balances', 'network_node_id', 'infrastructure_slot_id', 'infrastructure_port_id', ['pop_id']],
        ];

        foreach ($sides as [$table, $target, $slotColumn, $portColumn, $otherColumns]) {
            $pending = array_values(array_intersect([$slotColumn, $portColumn], array_keys($legacy[$table] ?? [])));
            if ($pending === []) {
                continue;
            }

            $query = $this->rowsWithAnyValue($table, $pending)->whereNull($target);
            foreach ($otherColumns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $query->whereNull($column);
                }
            }

            foreach ($query->orderBy('id')->get() as $row) {
                $slotId = in_array($slotColumn, $pending, true) ? $row->{$slotColumn} : null;
                $portId = in_array($portColumn, $pending, true) ? $row->{$portColumn} : null;
                $plan[] = [
                    'table' => $table,
                    'id' => (int) $row->id,
                    'set' => $target,
                    'device_id' => $this->deviceFor($maps, $slotId, $portId),
                    'row' => $row,
                ];
            }
        }

        return $plan;
    }

    /**
     * Perangkat induk tiap Slot/Port lama, dibaca dari tabel lama SEBELUM
     * di-drop (`infrastructure_slots.network_node_id`; Port lewat Slot-nya).
     *
     * @return array{slots: array<int, ?int>, ports: array<int, ?int>}
     */
    private function deviceMaps(): array
    {
        if (! Schema::hasTable('infrastructure_slots')) {
            return ['slots' => [], 'ports' => []];
        }

        $slots = DB::table('infrastructure_slots')->pluck('network_node_id', 'id')
            ->map(fn ($deviceId): ?int => $deviceId === null ? null : (int) $deviceId)->all();
        $ports = Schema::hasTable('infrastructure_ports')
            ? DB::table('infrastructure_ports')->pluck('infrastructure_slot_id', 'id')
                ->map(fn ($slotId): ?int => $slots[(int) $slotId] ?? null)->all()
            : [];

        return ['slots' => $slots, 'ports' => $ports];
    }

    /**
     * Port lebih spesifik dari Slot, tapi keduanya bermuara ke perangkat yang
     * sama; Port dicoba dulu, Slot sebagai cadangan.
     *
     * @param  array{slots: array<int, ?int>, ports: array<int, ?int>}  $maps
     */
    private function deviceFor(array $maps, mixed $slotId, mixed $portId): ?int
    {
        return ($portId !== null ? ($maps['ports'][(int) $portId] ?? null) : null)
            ?? ($slotId !== null ? ($maps['slots'][(int) $slotId] ?? null) : null);
    }

    /**
     * Terapkan rencana penyelamatan. Saldo digabung ke saldo perangkat yang
     * sudah ada (barang + lot sama) supaya tetap satu baris per lokasi —
     * `InventoryService` mencari saldo dengan `firstOrCreate` per lokasi dan
     * akan membaca hanya satu baris.
     *
     * @param  array<string, array<string, mixed>>  $legacy
     */
    private function repointToParentDevices(array $legacy): void
    {
        foreach ($this->repointPlan($legacy) as $entry) {
            if ($entry['device_id'] === null) {
                continue;
            }

            if ($entry['table'] !== 'infrastructure_balances') {
                DB::table($entry['table'])->where('id', $entry['id'])->update([$entry['set'] => $entry['device_id']]);

                continue;
            }

            $row = $entry['row'];
            $existing = DB::table('infrastructure_balances')
                ->where('id', '!=', $entry['id'])
                ->where('item_id', $row->item_id)
                ->where('lot_no', $row->lot_no)
                ->where('network_node_id', $entry['device_id'])
                ->whereNull('pop_id')
                ->orderBy('id')
                ->first();

            if ($existing !== null) {
                DB::table('infrastructure_balances')->where('id', $existing->id)->update(['qty' => DB::raw('qty + '.(float) $row->qty)]);
                DB::table('infrastructure_balances')->where('id', $entry['id'])->delete();
            } else {
                DB::table('infrastructure_balances')->where('id', $entry['id'])->update(['network_node_id' => $entry['device_id']]);
            }
        }
    }

    /**
     * Env `testing` lolos supaya RefreshDatabase bisa migrate. Tanpa baris
     * di keempat tabel lama tidak ada yang dibuang (FK ke tabel kosong tidak
     * mungkin berisi nilai), jadi DB kosong / deploy baru lolos tanpa saklar.
     * Selain itu operator wajib menyalakan saklar eksplisit — `=== true`,
     * bukan truthy, supaya string "false"/"0" dari env mentah tidak lolos.
     * Dibaca dari config(), bukan env(): env() mengembalikan null saat
     * config di-cache sehingga saklar yang sudah dinyalakan tampak mati.
     *
     * @param  array{legacy: array{slots: int, ports: int, connections: int, mappings: int}, affected: array<string, int>, repointed?: array<string, int>, unresolved?: array<string, int>, balances_deleted: int}  $counts
     */
    public function assertConfirmed(array $counts): void
    {
        if (app()->environment('testing')
            || array_sum($counts['legacy']) === 0
            || config('infrastructure.confirm_dummy_reset') === true) {
            return;
        }

        $describe = fn (array $rows, string $unit): string => collect($rows)
            ->map(fn (int $count, string $table): string => "{$table} {$count} {$unit}")
            ->implode(', ') ?: '(tidak ada)';

        throw new RuntimeException(sprintf(
            'Migrasi retarget FK lokasi ke network_nodes DITOLAK di env "%s": langkah ini akan MENGHAPUS %d slot, %d port, %d koneksi, dan %d mapping legacy; '
            .'MEMINDAHKAN lokasi Slot/Port ke perangkat induknya pada: %s; '
            .'TIDAK menemukan perangkat induk (lokasi dikosongkan) pada: %s; '
            .'MENGHAPUS %d saldo infrastructure_balances yang satu-satunya lokasinya slot/port tanpa perangkat induk; '
            .'dan me-NULL-kan kolom slot/port pada: %s. '
            .'Lalu men-drop tabel infrastructure_slots/infrastructure_ports/infrastructure_legacy_mappings. '
            .'Jalankan hanya bila database target terbukti dummy: set INFRA_CONFIRM_DUMMY_RESET=true (config infrastructure.confirm_dummy_reset; '
            .'jalankan php artisan config:clear bila config di-cache), lalu ulangi migrate.',
            app()->environment(),
            $counts['legacy']['slots'],
            $counts['legacy']['ports'],
            $counts['legacy']['connections'],
            $counts['legacy']['mappings'],
            $describe($counts['repointed'] ?? [], 'lokasi'),
            $describe($counts['unresolved'] ?? [], 'lokasi'),
            $counts['balances_deleted'],
            $describe($counts['affected'], 'baris'),
        ));
    }

    /**
     * Langkah (a)+(b): pindahkan lokasi ke perangkat induk, hapus saldo yatim
     * yang tersisa (tanpa perangkat induk), NULL-kan kolom lokasi lama, lalu
     * kosongkan tabel lama. Hanya DML, satu transaksi — kalau gagal, tidak
     * ada kolom yang setengah dibersihkan. HARUS sebelum retarget: FK baru
     * ke `network_nodes` akan ditolak selama kolom masih berisi id lama.
     *
     * @param  array<string, array<string, mixed>>  $legacy
     */
    private function clearLegacyData(array $legacy): void
    {
        DB::transaction(function () use ($legacy): void {
            // Selamatkan dulu ke perangkat induk; saldo yatim yang TERSISA
            // setelah ini hanyalah yang perangkatnya tak dapat ditentukan.
            $this->repointToParentDevices($legacy);
            $this->orphanBalances($legacy)->delete();

            foreach ($this->nullableLegacyColumns($legacy) as $table => $columns) {
                $this->rowsWithAnyValue($table, $columns)->update(array_fill_keys($columns, null));
            }

            foreach (self::WIPED_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        });
    }

    /**
     * Langkah (c): drop FK lama lalu pasang FK ke `network_nodes`. Drop dan
     * add dipisah dua Schema::table: di sqlite keduanya memicu rebuild tabel,
     * dan memisahkannya menghindari dua definisi FK untuk kolom yang sama
     * dalam satu rebuild.
     *
     * @param  array<string, array<string, array{name: string|null, drop: string|list<string>|null}>>  $legacy
     */
    private function retargetForeignKeys(array $legacy): void
    {
        foreach ($legacy as $table => $pending) {
            Schema::table($table, function (Blueprint $blueprint) use ($pending): void {
                foreach ($pending as $plan) {
                    if ($plan['drop'] !== null) {
                        $blueprint->dropForeign($plan['drop']);
                    }
                }
            });

            Schema::table($table, function (Blueprint $blueprint) use ($pending): void {
                foreach ($pending as $column => $plan) {
                    $blueprint->foreign($column, $plan['name'])->references('id')->on('network_nodes')->restrictOnDelete();
                }
            });
        }
    }

    /**
     * Kolom lama per tabel yang di-NULL-kan. Baris koneksi dihapus utuh
     * (source_* NOT NULL, dan tiap barisnya bermakna "dari port lama"),
     * jadi tabelnya dikecualikan di sini.
     *
     * @param  array<string, array<string, mixed>>  $legacy
     * @return array<string, list<string>>
     */
    private function nullableLegacyColumns(array $legacy): array
    {
        $columns = [];
        foreach ($legacy as $table => $plans) {
            if ($table !== 'infrastructure_connections') {
                $columns[$table] = array_keys($plans);
            }
        }

        return $columns;
    }

    /**
     * @param  list<string>  $columns
     */
    private function rowsWithAnyValue(string $table, array $columns): Builder
    {
        return DB::table($table)->where(function (Builder $query) use ($columns): void {
            foreach ($columns as $column) {
                $query->orWhereNotNull($column);
            }
        });
    }

    /**
     * Saldo yang lokasinya HANYA Slot/Port lama — setelah NULL tidak punya
     * lokasi sama sekali. Dipanggil SETELAH `repointToParentDevices()`, jadi
     * yang tersisa hanya saldo tanpa perangkat induk yang bisa ditentukan.
     *
     * @param  array<string, array<string, mixed>>  $legacy
     */
    private function orphanBalances(array $legacy): Builder
    {
        $columns = array_keys($legacy['infrastructure_balances'] ?? []);
        if ($columns === []) {
            return DB::table('infrastructure_balances')->whereRaw('1 = 0');
        }

        return $this->rowsWithAnyValue('infrastructure_balances', $columns)
            ->whereNull('network_node_id')
            ->whereNull('pop_id');
    }
};
