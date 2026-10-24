<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
     * D2 (mengikat): TIDAK menghapus `network_nodes` (ODP lama memegang
     * pelanggan nyata), `network_node_ports`, maupun
     * `network_port_assignments`.
     *
     * Setiap langkah idempoten (cek FK yang sudah benar, dropIfExists) —
     * di MySQL DDL auto-commit, jadi kegagalan di tengah harus bisa
     * dilanjutkan dengan menjalankan ulang migrate, bukan perbaikan manual.
     */
    public function up(): void
    {
        $this->assertConfirmed($this->rowCounts());

        $this->clearLegacyData();
        $this->retargetForeignKeys();

        foreach (self::DROPPED_TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Tidak dapat dibalik: data posisi lama sudah dibersihkan.');
    }

    /**
     * @return array{slots: int, ports: int, connections: int, mappings: int}
     */
    public function rowCounts(): array
    {
        $counts = [];
        foreach (self::WIPED_TABLES as $key => $table) {
            $counts[$key] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
        }

        return [
            'slots' => $counts['slots'],
            'ports' => $counts['ports'],
            'connections' => $counts['connections'],
            'mappings' => $counts['mappings'],
        ];
    }

    /**
     * Env `testing` lolos supaya RefreshDatabase bisa migrate. Di luar itu
     * operator wajib menyalakan saklar eksplisit — `=== true`, bukan
     * truthy, supaya string "false"/"0" dari env mentah tidak lolos.
     * Dibaca dari config(), bukan env(): env() mengembalikan null saat
     * config di-cache sehingga saklar yang sudah dinyalakan tampak mati.
     *
     * @param  array{slots: int, ports: int, connections: int, mappings: int}  $counts
     */
    public function assertConfirmed(array $counts): void
    {
        if (app()->environment('testing') || config('infrastructure.confirm_dummy_reset') === true) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Migrasi retarget FK lokasi ke network_nodes DITOLAK di env "%s": langkah ini akan MENGHAPUS %d slot, %d port, %d koneksi, dan %d mapping legacy, '
            .'lalu men-drop tabel infrastructure_slots/infrastructure_ports/infrastructure_legacy_mappings. '
            .'Jalankan hanya bila database target terbukti dummy: set INFRA_CONFIRM_DUMMY_RESET=true (config infrastructure.confirm_dummy_reset; '
            .'jalankan php artisan config:clear bila config di-cache), lalu ulangi migrate.',
            app()->environment(),
            $counts['slots'],
            $counts['ports'],
            $counts['connections'],
            $counts['mappings'],
        ));
    }

    /**
     * Langkah (a)+(b): NULL-kan kolom lokasi lalu kosongkan tabel lama.
     * Hanya DML, satu transaksi — kalau gagal, tidak ada kolom yang
     * setengah dibersihkan.
     */
    private function clearLegacyData(): void
    {
        DB::transaction(function (): void {
            foreach (self::LOCATION_FOREIGN_KEYS as $table => $columns) {
                // Baris koneksi dihapus utuh di bawah; source_* NOT NULL
                // sehingga memang tidak bisa di-NULL-kan.
                if ($table === 'infrastructure_connections') {
                    continue;
                }

                $columnNames = array_keys($columns);
                DB::table($table)
                    ->where(function ($query) use ($columnNames): void {
                        foreach ($columnNames as $column) {
                            $query->orWhereNotNull($column);
                        }
                    })
                    ->update(array_fill_keys($columnNames, null));
            }

            foreach (self::WIPED_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        });
    }

    /**
     * Langkah (c): drop FK lama lalu pasang FK ke `network_nodes`.
     *
     * FK yang ada dicari lewat getForeignKeys(), bukan ditebak namanya:
     * identitas drop = array kolom di sqlite (driver itu tidak bisa drop FK
     * per nama — pola 2026_10_21_150332) dan nama ASLI di MySQL. Kolom yang
     * FK-nya sudah ke `network_nodes` dilewati supaya migrate ulang setelah
     * gagal di tengah aman. Drop dan add dipisah dua Schema::table: di sqlite
     * keduanya memicu rebuild tabel, dan memisahkannya menghindari dua
     * definisi FK untuk kolom yang sama dalam satu rebuild.
     */
    private function retargetForeignKeys(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        foreach (self::LOCATION_FOREIGN_KEYS as $table => $columns) {
            $existing = collect(Schema::getForeignKeys($table));
            $pending = [];

            foreach ($columns as $column => $name) {
                $current = $existing->first(fn (array $key): bool => $key['columns'] === [$column]);
                if ($current !== null && $current['foreign_table'] === 'network_nodes') {
                    continue;
                }

                $pending[$column] = [
                    'name' => $name,
                    'drop' => $current === null ? null : ($isSqlite ? [$column] : $current['name']),
                ];
            }

            if ($pending === []) {
                continue;
            }

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
};
