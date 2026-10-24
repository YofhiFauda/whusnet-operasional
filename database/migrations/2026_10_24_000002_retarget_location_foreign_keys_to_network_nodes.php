<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
     * `infrastructure_slots`/`infrastructure_ports` — memetakannya ke id node
     * tidak mungkin tanpa tabel lama, dan membiarkannya berarti FK baru
     * menunjuk node acak yang kebetulan ber-id sama. Karena itu kolom di-NULL
     * dulu, bukan diterjemahkan. Ini hanya sah bila database dummy, maka
     * guard konfirmasi (spec §10) dijalankan SEBELUM satu baris pun disentuh.
     *
     * Konsekuensi yang DITERIMA (putusan fix round 1):
     * - `infrastructure_balances` yang SATU-SATUNYA lokasinya Slot/Port
     *   (network_node_id & pop_id null) DIHAPUS — membiarkannya berarti saldo
     *   hantu tanpa lokasi, melanggar invarian "tepat satu lokasi per baris"
     *   (2026_10_21_000006). Saldo yang masih punya lokasi lain hanya
     *   di-NULL-kan kolom slot/port-nya.
     * - Ledger `inventory_transactions` TIDAK dihapus (ledger append-only):
     *   baris lama kehilangan asal/tujuan Slot/Port, sehingga jejak "dari/ke
     *   posisi mana" untuk mutasi itu hilang permanen.
     * - Serial (termasuk status DEPLOYED) di Slot/Port kehilangan lokasi
     *   tepatnya; statusnya tidak diubah. Rekonsiliasi Gudang akan
     *   menandainya sebagai terpasang-tanpa-lokasi.
     * - `fop_tasks` & `task_infrastructure_actions`: akar scope / lokasi kerja
     *   Slot/Port hilang; snapshot JSON (`scope_snapshot`,
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
        $this->assertConfirmed($this->rowCounts($legacy));

        $this->clearLegacyData($legacy);
        $this->retargetForeignKeys($legacy);

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
     * @return array{legacy: array{slots: int, ports: int, connections: int, mappings: int}, affected: array<string, int>, balances_deleted: int}
     */
    public function rowCounts(array $legacy): array
    {
        $counts = ['legacy' => [], 'affected' => [], 'balances_deleted' => 0];
        foreach (self::WIPED_TABLES as $key => $table) {
            $counts['legacy'][$key] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
        }

        foreach ($this->nullableLegacyColumns($legacy) as $table => $columns) {
            $counts['affected'][$table] = $this->rowsWithAnyValue($table, $columns)->count();
        }

        if (isset($counts['affected']['infrastructure_balances'])) {
            $counts['balances_deleted'] = $this->orphanBalances($legacy)->count();
            $counts['affected']['infrastructure_balances'] -= $counts['balances_deleted'];
        }

        return $counts;
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
     * @param  array{legacy: array{slots: int, ports: int, connections: int, mappings: int}, affected: array<string, int>, balances_deleted: int}  $counts
     */
    public function assertConfirmed(array $counts): void
    {
        if (app()->environment('testing')
            || array_sum($counts['legacy']) === 0
            || config('infrastructure.confirm_dummy_reset') === true) {
            return;
        }

        $affected = collect($counts['affected'])
            ->map(fn (int $rows, string $table): string => "{$table} {$rows} baris")
            ->implode(', ');

        throw new RuntimeException(sprintf(
            'Migrasi retarget FK lokasi ke network_nodes DITOLAK di env "%s": langkah ini akan MENGHAPUS %d slot, %d port, %d koneksi, dan %d mapping legacy; '
            .'MENGHAPUS %d saldo infrastructure_balances yang satu-satunya lokasinya slot/port; '
            .'dan me-NULL-kan kolom slot/port pada: %s. '
            .'Lalu men-drop tabel infrastructure_slots/infrastructure_ports/infrastructure_legacy_mappings. '
            .'Jalankan hanya bila database target terbukti dummy: set INFRA_CONFIRM_DUMMY_RESET=true (config infrastructure.confirm_dummy_reset; '
            .'jalankan php artisan config:clear bila config di-cache), lalu ulangi migrate.',
            app()->environment(),
            $counts['legacy']['slots'],
            $counts['legacy']['ports'],
            $counts['legacy']['connections'],
            $counts['legacy']['mappings'],
            $counts['balances_deleted'],
            $affected === '' ? '(tidak ada)' : $affected,
        ));
    }

    /**
     * Langkah (a)+(b): hapus saldo yatim, NULL-kan kolom lokasi lama, lalu
     * kosongkan tabel lama. Hanya DML, satu transaksi — kalau gagal, tidak
     * ada kolom yang setengah dibersihkan. HARUS sebelum retarget: FK baru
     * ke `network_nodes` akan ditolak selama kolom masih berisi id lama.
     *
     * @param  array<string, array<string, mixed>>  $legacy
     */
    private function clearLegacyData(array $legacy): void
    {
        DB::transaction(function () use ($legacy): void {
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
     * lokasi sama sekali.
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
