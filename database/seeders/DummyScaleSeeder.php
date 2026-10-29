<?php

namespace Database\Seeders;

use App\Models\Pop;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Data dummy skala besar (default 50.000 pelanggan) buat uji performa/UX di
 * dev — jalankan MANUAL, JANGAN didaftarkan ke `DatabaseSeeder::run()`.
 *
 *   php artisan db:seed --class=DummyScaleSeeder
 *   DUMMY_CUSTOMERS=500 php artisan db:seed --class=DummyScaleSeeder   # uji kecil
 *   DUMMY_PURGE=1 php artisan db:seed --class=DummyScaleSeeder         # hapus SEMUA dummy
 *
 * ADDITIVE: tidak pernah menghapus/mengubah data non-dummy. Semua baris dummy
 * punya penanda yang dipakai purge — pelanggan `registered_by_name = DUMMY-SEED`,
 *
 * user `dummy.*@whusnet.test`, node `DMY-*`, SN/roll `DMY-*`, lot `DMY-LOT`,
 * transfer `DMYTRF-*`, ledger `notes LIKE [DUMMY]%`. Jangan ubah penanda itu
 * tanpa ubah `purge()` — baris yatim di tabel keuangan susah dibersihkan karena
 * FK induk → invoice/payment `restrictOnDelete`.
 *
 * SENGAJA lewat `DB::table()` (bukan Eloquent/Service): 50k pelanggan × ~15
 * tabel anak lewat observer/service = berjam-jam. Konsekuensinya observer
 * (audit log, guard pembayaran, CID, riwayat) TIDAK jalan, jadi invarian yang
 * biasanya dijaga observer dipenuhi manual di sini: CID = rumus
 * `Pop::generateComplexCid`, nominal pembayaran > 0, total invoice = (subtotal
 * - diskon) + PPN, SUM(invoice_items) = subtotal, nomor dokumen sesuai
 * `NumberSequenceService` (counter `number_sequences` disinkronkan di akhir).
 * ID diambil eksplisit dari MAX(id)+1 supaya tabel anak bisa di-bulk-insert
 * tanpa baca balik lastInsertId.
 */
class DummyScaleSeeder extends Seeder
{
    private const MARK = 'DUMMY-SEED';

    private const CHUNK = 1000;

    private const ACTIVATION_FEE = 150000;

    private const ODP_PORTS = 16;

    private const ODP_PER_PON = 8;

    private const PON_PER_SLOT = 16;

    private const SLOT_PER_CHASSIS = 4;

    private const CHASSIS_PER_MINI_POP = 3;

    /** @var array<string, int> */
    private array $nextId = [];

    /** @var array<string, int> */
    private array $docCounters = [];

    /** @var array<int, int> */
    private array $nextCode = [];

    /** @var array<string, mixed> */
    private array $ref = [];

    /** @var list<string> */
    private array $periods = [];

    public function run(): void
    {
        ini_set('memory_limit', '1G');
        DB::connection()->disableQueryLog();
        mt_srand(20261028);

        if (getenv('DUMMY_PURGE')) {
            $this->purge();

            return;
        }

        $total = (int) (getenv('DUMMY_CUSTOMERS') ?: 50000);

        if (DB::table('customers')->where('registered_by_name', self::MARK)->exists()) {
            $this->info('Data dummy sudah ada — skip. Jalankan DUMMY_PURGE=1 dulu kalau mau bikin ulang.');

            return;
        }

        if (! $this->loadReferences()) {
            return;
        }

        $this->info("Mulai: {$total} pelanggan dummy (additive, data lama tidak disentuh).");

        $this->seedStaff();
        $this->seedInfrastructure();
        $this->seedWarehouse();
        $this->seedCustomers($total);
        $this->syncSequences();

        // Tiket/FOP/task/permintaan stok menumpang pelanggan di atas; DUMMY_OPS=0 untuk melewatinya.
        if (getenv('DUMMY_OPS') !== '0') {
            $this->call(DummyOperationsSeeder::class);
        }

        $this->info('Selesai. Bersihkan dengan: DUMMY_PURGE=1 php artisan db:seed --class=DummyScaleSeeder');
    }

    // ───────────────────────── referensi & guard ─────────────────────────

    private function loadReferences(): bool
    {
        $cabang = Pop::query()->where('type', 'cabang')->where('status', 'active')->orderBy('id')->get();
        $pusat = Pop::query()->where('type', 'pusat')->first();
        // "Pelanggan Bisnis" = paket yang kategorinya punya role validator Biaya Instalasi
        // (lihat BusinessCustomerSeeder) — jadi paket reguler HARUS di luar kategori itu,
        // kalau tidak pelanggan rumahan ikut nongol di List Pelanggan Bisnis.
        $businessCategories = DB::table('package_categories')->whereNotNull('installation_fee_approval_role_id')->pluck('name');
        $allPackages = DB::table('internet_packages')->where('is_active', 1)->where('monthly_price', '>', 0)->get();
        $packages = $allPackages->reject(fn ($p) => $businessCategories->contains($p->category))->values();
        // Dedicated (jutaan/bulan) dikeluarkan: satu pelanggan dedicated menyeret angka omset dummy.
        $businessPackages = $allPackages->filter(fn ($p) => $businessCategories->contains($p->category) && (float) $p->monthly_price <= 1500000)->values();
        $roles = DB::table('roles')->pluck('id', 'code');
        $nodeTypes = DB::table('network_node_types')->pluck('id', 'code');
        $items = DB::table('items')->get()->keyBy('code');

        $needed = ['teknisi', 'kolektor', 'pic_gudang', 'sales', 'business_development'];
        $villages = DB::table('villages')
            ->join('districts', 'districts.id', '=', 'villages.district_id')
            ->where('districts.city_id', 1)
            ->get(['villages.id', 'villages.name', 'villages.district_id', 'districts.name as district_name']);

        if ($cabang->isEmpty() || ! $pusat || $packages->isEmpty() || $businessPackages->isEmpty() || $villages->isEmpty() || $items->isEmpty()
            || collect($needed)->contains(fn ($code) => ! $roles->has($code))
            || collect(['olt_chassis', 'olt_slot', 'olt_pon', 'odp', 'switch_mini_pop'])->contains(fn ($c) => ! $nodeTypes->has($c))) {
            $this->command?->error('Master dasar belum lengkap — jalankan php artisan db:seed (+ MasterPopSeeder) dulu.');

            return false;
        }

        // Cabang → Mini POP (anak) → distribusi. Pelanggan dummy dibagi rata antar cabang.
        $branches = [];
        foreach ($cabang as $pop) {
            $mini = Pop::query()->where('parent_id', $pop->id)->where('type', 'mini_pop')->orderBy('id')->first();
            if (! $mini) {
                continue;
            }
            $branches[] = [
                'pop' => $pop,
                'mini' => $mini,
                'distributions' => DB::table('distributions')->where('pop_id', $mini->id)->pluck('code', 'id')->all(),
            ];
        }

        if ($branches === []) {
            $this->command?->error('Tidak ada pasangan Cabang → Mini POP aktif.');

            return false;
        }

        $this->ref = compact('pusat', 'packages', 'businessPackages', 'roles', 'nodeTypes', 'items', 'villages', 'branches')
            + ['revenue' => $this->loadRevenue()];

        $now = Carbon::now('Asia/Jakarta');
        $this->periods = [
            $now->copy()->subMonthsNoOverflow(2)->format('Y-m'),
            $now->copy()->subMonthNoOverflow()->format('Y-m'),
            $now->format('Y-m'),
        ];

        return true;
    }

    /** @return array<string, array{id:int,name:string}> */
    private function loadRevenue(): array
    {
        $out = [];
        foreach (DB::table('revenue_categories')->get() as $c) {
            $out['cat:'.$c->code] = ['id' => $c->id, 'name' => $c->name];
        }
        foreach (DB::table('revenue_subcategories')->get() as $s) {
            $out['sub:'.$s->code] = ['id' => $s->id, 'name' => $s->name];
        }

        return $out;
    }

    // ───────────────────────── staf ─────────────────────────

    private function seedStaff(): void
    {
        $hash = Hash::make('password');
        $staff = [];
        $plan = [
            'teknisi' => [4, 3],
            'kolektor' => [3, 2],
            'pic_gudang' => [1, 1],
            'sales' => [2, 1],
            'business_development' => [1, 0], // satu BD lintas cabang cukup (index 0 = cabang pertama)
        ];

        foreach ($this->ref['branches'] as $b => $branch) {
            foreach ($plan as $role => $counts) {
                for ($n = 1; $n <= ($counts[$b] ?? 1); $n++) {
                    $email = sprintf('dummy.%s.%s%d@whusnet.test', $role, strtolower($branch['pop']->code), $n);
                    $user = DB::table('users')->where('email', $email)->first();
                    $id = $user->id ?? DB::table('users')->insertGetId([
                        'role_id' => $this->ref['roles'][$role],
                        'name' => sprintf('Dummy %s %s %d', ucfirst(str_replace('_', ' ', $role)), $branch['pop']->name, $n),
                        'email' => $email,
                        'email_verified_at' => now(),
                        'password' => $hash,
                        'phone' => '0812'.str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $scopeId = DB::table('user_role_scopes')->where(['user_id' => $id, 'role_id' => $this->ref['roles'][$role]])->value('id')
                        ?? DB::table('user_role_scopes')->insertGetId([
                            'user_id' => $id, 'role_id' => $this->ref['roles'][$role], 'scope_type' => 'selected_pop',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    foreach ([$branch['pop']->id, $branch['mini']->id] as $popId) {
                        DB::table('user_role_scope_targets')->updateOrInsert(['user_role_scope_id' => $scopeId, 'pop_id' => $popId]);
                    }

                    if ($role === 'pic_gudang') {
                        DB::table('warehouse_pop_pics')->updateOrInsert(
                            ['pop_id' => $branch['pop']->id, 'user_id' => $id],
                            ['created_at' => now(), 'updated_at' => now()],
                        );
                    }

                    $staff[$b][$role][] = $id;
                }
            }
        }

        // Master Agent (Skema 3) — kode `DMY-AGT-n` jadi penanda purge.
        $agents = [];
        foreach (range(1, 4) as $n) {
            $code = "DMY-AGT-{$n}";
            DB::table('agents')->updateOrInsert(['code' => $code], ['name' => "Agent Dummy {$n}", 'phone' => '0813'.str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT), 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $agents[$code] = DB::table('agents')->where('code', $code)->value('id');
        }

        $this->ref['agents'] = $agents;
        $this->ref['staff'] = $staff;
        $this->info('Staf dummy: '.collect($staff)->flatten()->count().' user (password: password).');
    }

    // ───────────────────────── infrastruktur ─────────────────────────

    /**
     * Pohon per Mini POP: OLT chassis → Slot → PON → ODP (aturan induk-anak ada di
     * `network_node_type_rules`), plus Switch Mini POP berport. Kapasitas ODP
     * 16 port × 8 ODP/PON = 128 pelanggan/PON, jadi 3 chassis × 4 slot × 16 PON
     * × 8 ODP × 16 port ≈ 24.5k port per Mini POP (cukup buat ~25k pelanggan).
     */
    private function seedInfrastructure(): void
    {
        $types = $this->ref['nodeTypes'];
        $nodeId = $this->takeId('network_nodes');
        $portId = $this->takeId('network_node_ports');
        $nodes = [];
        $ports = [];
        $odps = [];
        $infra = [];

        $node = function (array $attr) use (&$nodes, &$nodeId): int {
            $id = $nodeId++;
            $nodes[] = array_merge([
                'id' => $id, 'parent_id' => null, 'anchor_pop_id' => null, 'node_type_id' => null, 'code' => null,
                'number' => null, 'name' => null, 'capacity' => null, 'address' => null, 'village' => null,
                'district' => null, 'city' => 'Ponorogo', 'latitude' => null, 'longitude' => null, 'status' => 'active',
                'notes' => '[DUMMY]', 'created_at' => now(), 'updated_at' => now(), 'capacity_unit' => null,
                'is_logical' => 0, 'record_status' => 'terverifikasi', 'red_zone_threshold' => null,
            ], $attr);

            return $id;
        };

        $serverCode = 'DMY-SRV-01';
        $infra['server'] = $node(['anchor_pop_id' => $this->ref['pusat']->id, 'node_type_id' => $types['server'] ?? $types['olt_chassis'], 'code' => $serverCode, 'name' => 'Server Utama (Dummy)']);

        foreach ($this->ref['branches'] as $b => $branch) {
            $mini = $branch['mini'];
            $m = $mini->pop_code;
            $swId = $node(['anchor_pop_id' => $mini->id, 'node_type_id' => $types['switch_mini_pop'], 'code' => "DMY-{$m}-SW1", 'name' => "Switch {$m} (Dummy)", 'capacity' => 24, 'capacity_unit' => 'port']);
            for ($p = 1; $p <= 24; $p++) {
                $ports[] = ['id' => $portId++, 'network_node_id' => $swId, 'number' => $p, 'is_used' => $p <= 6 ? 1 : 0, 'created_at' => now(), 'updated_at' => now()];
            }
            $infra['switch'][$b] = $swId;

            $odpSeq = 0;
            for ($c = 1; $c <= self::CHASSIS_PER_MINI_POP; $c++) {
                $chassisCode = "DMY-{$m}-OLT{$c}";
                $chassis = $node(['anchor_pop_id' => $mini->id, 'node_type_id' => $types['olt_chassis'], 'code' => $chassisCode, 'name' => "OLT {$m}-{$c} (Dummy)"]);
                $infra['chassis'][$b][] = $chassis;

                for ($s = 1; $s <= self::SLOT_PER_CHASSIS; $s++) {
                    $slotCode = "{$chassisCode}-S{$s}";
                    $slot = $node(['parent_id' => $chassis, 'node_type_id' => $types['olt_slot'], 'code' => $slotCode, 'number' => $s, 'name' => "Slot {$s}", 'capacity_unit' => 'pon']);
                    $infra['slot'][$b][] = $slot;

                    for ($pn = 1; $pn <= self::PON_PER_SLOT; $pn++) {
                        $ponCode = "{$slotCode}-P{$pn}";
                        $pon = $node(['parent_id' => $slot, 'node_type_id' => $types['olt_pon'], 'code' => $ponCode, 'number' => $pn, 'name' => "PON {$pn}", 'capacity' => 128, 'capacity_unit' => 'pelanggan_aktif', 'red_zone_threshold' => 80]);

                        for ($o = 1; $o <= self::ODP_PER_PON; $o++) {
                            $odpSeq++;
                            $odpId = $node([
                                'parent_id' => $pon, 'node_type_id' => $types['odp'], 'code' => "{$ponCode}-ODP{$o}",
                                'number' => $o, 'name' => sprintf('ODP %s-%04d', $m, $odpSeq), 'capacity' => self::ODP_PORTS, 'capacity_unit' => 'port',
                                'red_zone_threshold' => 80,
                            ]);
                            $firstPort = $portId;
                            for ($p = 1; $p <= self::ODP_PORTS; $p++) {
                                $ports[] = ['id' => $portId++, 'network_node_id' => $odpId, 'number' => $p, 'is_used' => 0, 'created_at' => now(), 'updated_at' => now()];
                            }
                            $odps[$b][] = [
                                'id' => $odpId, 'code' => "{$ponCode}-ODP{$o}", 'first_port' => $firstPort,
                                'olt' => $chassisCode, 'slot' => $s, 'pon' => $pn, 'vlan' => 100 + $odpSeq % 900,
                            ];
                        }
                    }
                }
            }
        }

        // Node harus masuk berurutan (induk sebelum anak) — urutan push di atas sudah begitu.
        $this->bulk('network_nodes', $nodes);
        $this->bulk('network_node_ports', $ports);

        $this->ref['odps'] = $odps;
        $this->ref['infra'] = $infra;
        $this->ref['odpCursor'] = array_fill_keys(array_keys($odps), 0);
        $this->info('Infrastruktur: '.count($nodes).' node, '.count($ports).' port.');
    }

    // ───────────────────────── gudang ─────────────────────────

    private function seedWarehouse(): void
    {
        $items = $this->ref['items'];
        $pusat = $this->ref['pusat']->id;
        $gudang = [$pusat => 'pusat'];
        foreach ($this->ref['branches'] as $b => $branch) {
            $gudang[$branch['pop']->id] = $b;
        }

        $serialId = $this->takeId('inventory_serials');
        $rollId = $this->takeId('inventory_rolls');
        $txId = $this->takeId('inventory_transactions');
        $serials = [];
        $rolls = [];
        $tx = [];
        $balances = [];
        $custody = [];

        $newTx = function (array $o) use (&$txId) {
            return array_merge([
                'id' => $txId++, 'type' => 'receive', 'reference_number' => null, 'inventory_transfer_id' => null, 'item_id' => null,
                'lot_no' => null, 'serial_id' => null, 'roll_id' => null, 'qty' => 1, 'unit_price_snapshot' => null,
                'from_pop_id' => null, 'from_network_node_id' => null, 'to_pop_id' => null, 'to_network_node_id' => null,
                'from_technician_id' => null, 'to_technician_id' => null, 'fop_task_id' => null, 'reason' => null,
                'notes' => '[DUMMY]', 'evidence_file_path' => null, 'resulting_status' => null, 'created_by' => 1,
                'created_at' => now()->subDays(mt_rand(10, 180)), 'updated_at' => now(),
            ], $o);
        };

        $newSerial = function (array $o) use (&$serialId) {
            return array_merge([
                'id' => $serialId++, 'item_id' => null, 'serial_number' => null, 'mac_address' => null, 'status' => 'available',
                'condition' => 'new', 'condition_checked_at' => null, 'condition_checked_by' => null, 'ownership' => 'company',
                'current_pop_id' => null, 'current_network_node_id' => null, 'current_mini_pop_id' => null, 'current_technician_id' => null,
                'issued_from_pop_id' => null, 'customer_id' => null, 'fop_task_id' => null, 'installed_at' => null,
                'created_at' => now()->subDays(mt_rand(10, 180)), 'updated_at' => now(),
            ], $o);
        };

        $price = fn (string $code) => match (true) {
            str_starts_with($code, 'ONT') => 350000,
            str_starts_with($code, 'OLT') => 25000000,
            str_starts_with($code, 'SW') => 4500000,
            str_starts_with($code, 'RTR') => 1200000,
            str_starts_with($code, 'ODP'), str_starts_with($code, 'SPL') => 85000,
            default => 150000,
        };

        // 1) Serialized yang bisa dipasang/dipinjamkan: stok di tiap gudang, status campur.
        $stockItems = $items->filter(fn ($i) => $i->tracking_type === 'serialized' && $i->ownership_mode === 'installable' && $i->code !== 'MODEM-PELANGGAN-LAMA');
        foreach ($gudang as $popId => $key) {
            $isPusat = $key === 'pusat';
            foreach ($stockItems as $item) {
                $isOnt = str_starts_with($item->code, 'ONT');
                $count = $isOnt ? ($isPusat ? 500 : 600) : ($isPusat ? 60 : 40);
                $ref = sprintf('RCV-%s-%06d', now()->subDays(30)->format('Ymd'), $popId * 100 + $item->id);
                for ($n = 1; $n <= $count; $n++) {
                    $roll = mt_rand(1, 100);
                    $techs = $isPusat ? [] : ($this->ref['staff'][$key]['teknisi'] ?? []);
                    [$status, $cond, $tech] = match (true) {
                        $roll <= 70 => ['available', 'new', null],
                        $roll <= 80 => ['received', 'new', null],
                        $roll <= 90 && $techs !== [] => ['issued', 'new', $techs[array_rand($techs)]],
                        $roll <= 94 => ['damaged', 'used_damaged', null],
                        $roll <= 97 => ['quarantine', 'used_good', null],
                        default => ['returned', 'used_good', null],
                    };
                    $sn = sprintf('DMY-%s-%d-%05d', $item->code, $popId, $n);
                    $sid = $serialId;
                    $serials[] = $newSerial([
                        'item_id' => $item->id, 'serial_number' => $sn, 'mac_address' => $this->mac(), 'status' => $status,
                        'condition' => $cond, 'current_pop_id' => $tech ? null : $popId,
                        'current_technician_id' => $tech, 'issued_from_pop_id' => $tech ? $popId : null,
                    ]);
                    $tx[] = $newTx(['type' => 'receive', 'reference_number' => $ref, 'item_id' => $item->id, 'serial_id' => $sid, 'to_pop_id' => $popId, 'unit_price_snapshot' => $price($item->code), 'resulting_status' => 'received']);
                    if ($tech) {
                        $tx[] = $newTx(['type' => 'issue', 'reference_number' => sprintf('ISS-%s-%06d', now()->subDays(10)->format('Ymd'), $popId * 100 + $item->id), 'item_id' => $item->id, 'serial_id' => $sid, 'from_pop_id' => $popId, 'to_technician_id' => $tech, 'resulting_status' => 'issued']);
                    }
                }
                $this->flushWarehouse($serials, $tx);
            }
        }

        // 2) Roll kabel (dropcore & feeder).
        foreach ($gudang as $popId => $key) {
            foreach (['DC-1C' => [1000, 40], 'DC-2C' => [1000, 20], 'KBL-FEEDER-12C' => [2000, 8]] as $code => [$len, $cnt]) {
                $item = $items[$code] ?? null;
                if (! $item) {
                    continue;
                }
                $techs = $key === 'pusat' ? [] : ($this->ref['staff'][$key]['teknisi'] ?? []);
                for ($n = 1; $n <= $cnt; $n++) {
                    $roll = mt_rand(1, 100);
                    [$status, $remaining, $tech] = match (true) {
                        $roll <= 45 => ['available', $len, null],
                        $roll <= 65 && $techs !== [] => ['issued', $len, $techs[array_rand($techs)]],
                        $roll <= 80 && $techs !== [] => ['in_use', mt_rand(100, $len - 50), $techs[array_rand($techs)]],
                        $roll <= 95 => ['depleted', 0, null],
                        default => ['damaged', mt_rand(50, $len), null],
                    };
                    $rid = $rollId++;
                    $rolls[] = [
                        'id' => $rid, 'item_id' => $item->id, 'roll_code' => sprintf('DMY-%s-%d-%03d', $code, $popId, $n), 'vendor' => 'PT Dummy Fiber',
                        'length_total' => $len, 'length_remaining' => $remaining, 'unit_price_snapshot' => $code === 'KBL-FEEDER-12C' ? 8500 : 1800,
                        'status' => $status, 'current_pop_id' => $tech ? null : $popId, 'current_technician_id' => $tech,
                        'issued_from_pop_id' => $tech ? $popId : null, 'received_at' => now()->subDays(60), 'created_at' => now()->subDays(60), 'updated_at' => now(),
                    ];
                    $tx[] = $newTx(['type' => 'receive', 'reference_number' => sprintf('RCV-%s-%06d', now()->subDays(60)->format('Ymd'), $popId), 'item_id' => $item->id, 'roll_id' => $rid, 'qty' => $len, 'to_pop_id' => $popId, 'unit_price_snapshot' => $code === 'KBL-FEEDER-12C' ? 8500 : 1800]);
                }
            }
        }

        // 3) Barang quantity: saldo per gudang + custody teknisi + ledger masuk.
        foreach ($items->filter(fn ($i) => $i->tracking_type === 'quantity') as $item) {
            foreach ($gudang as $popId => $key) {
                $qty = $key === 'pusat' ? mt_rand(2000, 8000) : mt_rand(300, 2000);
                $balances[] = [
                    'pop_id' => $popId, 'item_id' => $item->id, 'lot_no' => 'DMY-LOT', 'qty' => $qty,
                    'minimum_stock' => 200, 'maximum_stock' => 10000, 'created_at' => now(), 'updated_at' => now(),
                ];
                $tx[] = $newTx(['type' => 'receive', 'reference_number' => sprintf('RCV-%s-%06d', now()->subDays(45)->format('Ymd'), $popId), 'item_id' => $item->id, 'lot_no' => 'DMY-LOT', 'qty' => $qty, 'to_pop_id' => $popId, 'unit_price_snapshot' => 5000]);
            }
            foreach ($this->ref['staff'] as $b => $roles) {
                foreach ($roles['teknisi'] ?? [] as $techId) {
                    $custody[] = [
                        'technician_id' => $techId, 'issued_from_pop_id' => $this->ref['branches'][$b]['pop']->id, 'item_id' => $item->id,
                        'lot_no' => 'DMY-LOT', 'qty_remaining' => mt_rand(5, 120), 'unit_price_snapshot' => 5000,
                        'status' => mt_rand(0, 1) ? 'issued' : 'partially_used', 'issued_at' => now()->subDays(mt_rand(1, 30)),
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
        }

        // 4) Transfer Pusat → Cabang.
        $transferId = $this->takeId('inventory_transfers');
        $transfers = [];
        foreach ($this->ref['branches'] as $b => $branch) {
            for ($n = 1; $n <= 8; $n++) {
                $tid = $transferId++;
                $status = $n === 8 ? 'in_transit' : ($n === 7 ? 'received_partial' : 'received');
                $created = now()->subDays(mt_rand(5, 120));
                $transfers[] = [
                    'id' => $tid, 'reference_number' => sprintf('DMYTRF-%d-%02d', $branch['pop']->id, $n), 'from_pop_id' => $pusat,
                    'to_pop_id' => $branch['pop']->id, 'status' => $status, 'created_by' => 1,
                    'received_by' => $status === 'in_transit' ? null : ($this->ref['staff'][$b]['pic_gudang'][0] ?? 1),
                    'received_at' => $status === 'in_transit' ? null : $created->copy()->addDays(2), 'created_at' => $created, 'updated_at' => now(),
                ];
                foreach ($items->filter(fn ($i) => $i->tracking_type === 'quantity')->random(4) as $item) {
                    $tx[] = $newTx(['type' => 'transfer', 'reference_number' => sprintf('TRF-%s-%06d', $created->format('Ymd'), $tid), 'inventory_transfer_id' => $tid, 'item_id' => $item->id, 'lot_no' => 'DMY-LOT', 'qty' => mt_rand(20, 200), 'from_pop_id' => $pusat, 'to_pop_id' => $branch['pop']->id, 'created_at' => $created]);
                }
            }
        }

        // Roll & transfer WAJIB masuk sebelum ledger yang merujuknya (FK roll_id / inventory_transfer_id).
        $this->bulk('inventory_rolls', $rolls);
        $this->bulk('inventory_balances', $balances);
        $this->bulk('technician_custody', $custody);
        $this->bulk('inventory_transfers', $transfers);
        $this->flushWarehouse($serials, $tx);

        // 5) Aset infrastruktur terpasang: OLT, modul GPON, switch, ODP + splitter (ledger DEPLOY).
        //    + varian QTY/ROLL (klem, sleeve splice, sisa dropcore) di `infrastructure_balances`
        //    (beda tabel dari `inventory_balances` gudang) — supaya tab "Barang Terpasang" di
        //    Peta Infrastruktur punya contoh ketiga `kind` (SN/QTY/ROLL), bukan cuma SN.
        $infraBalances = [];
        foreach ($this->ref['branches'] as $b => $branch) {
            $deploy = function (string $code, int $nodeId, string $sn) use ($items, &$serials, &$tx, $newSerial, $newTx, &$serialId, $branch, $price) {
                $item = $items[$code] ?? null;
                if (! $item) {
                    return;
                }
                $sid = $serialId;
                $serials[] = $newSerial([
                    'item_id' => $item->id, 'serial_number' => $sn, 'status' => 'deployed', 'condition' => 'new',
                    'current_network_node_id' => $nodeId, 'current_mini_pop_id' => $branch['mini']->id, 'installed_at' => now()->subDays(mt_rand(30, 300)),
                ]);
                $tx[] = $newTx(['type' => 'deploy', 'reference_number' => sprintf('DPL-%s-%06d', now()->subDays(100)->format('Ymd'), $sid), 'item_id' => $item->id, 'serial_id' => $sid, 'to_network_node_id' => $nodeId, 'unit_price_snapshot' => $price($code), 'resulting_status' => 'deployed']);
            };

            $deployQty = function (string $code, int $nodeId, int $qty, string $lot = '') use ($items, &$tx, &$infraBalances, $newTx, &$txId, $price) {
                $item = $items[$code] ?? null;
                if (! $item) {
                    return;
                }
                $refId = $txId;
                $infraBalances[] = ['item_id' => $item->id, 'lot_no' => $lot, 'network_node_id' => $nodeId, 'qty' => $qty, 'created_at' => now()->subDays(mt_rand(30, 300)), 'updated_at' => now()];
                $tx[] = $newTx(['type' => 'deploy', 'reference_number' => sprintf('DPL-%s-%06d', now()->subDays(90)->format('Ymd'), $refId), 'item_id' => $item->id, 'lot_no' => $lot ?: null, 'qty' => $qty, 'to_network_node_id' => $nodeId, 'unit_price_snapshot' => $price($code), 'resulting_status' => 'deployed']);
            };

            foreach ($this->ref['infra']['chassis'][$b] as $i => $nodeId) {
                $deploy('OLT-ZTE-C320', $nodeId, "DMY-OLT-{$b}-{$i}");
                $deployQty('AKS-KLEM', $nodeId, mt_rand(4, 12));
            }
            foreach ($this->ref['infra']['slot'][$b] as $i => $nodeId) {
                $deploy('OLT-MODULE-GPON-8P', $nodeId, "DMY-GPON-{$b}-{$i}");
            }
            $deploy('SW-TPLINK-24P', $this->ref['infra']['switch'][$b], "DMY-SW-{$b}-1");
            foreach ($this->ref['odps'][$b] as $i => $odp) {
                $deploy('ODP-16', $odp['id'], "DMY-ODPBOX-{$b}-{$i}");
                $deploy('SPL-1X16', $odp['id'], "DMY-SPL-{$b}-{$i}");
                $deployQty('SLV-SPLICE', $odp['id'], mt_rand(2, 8));
                // Lot = `roll_code` ASLI drum dropcore yang udah ada (seksi 2 di atas,
                // format sama: `DMY-{code}-{popId}-{urutan 3 digit}`) — bukan label
                // karangan, biar link Riwayat Gudang (by roll) beneran nyambung.
                $deployQty('DC-1C', $odp['id'], mt_rand(5, 30), sprintf('DMY-DC-1C-%d-%03d', $branch['pop']->id, ($i % 40) + 1));
                if (count($serials) >= 5000) {
                    $this->flushWarehouse($serials, $tx);
                }
                if (count($infraBalances) >= 2000) {
                    $this->bulk('infrastructure_balances', $infraBalances);
                    $infraBalances = [];
                }
            }
        }

        $this->bulk('infrastructure_balances', $infraBalances);
        $this->flushWarehouse($serials, $tx);

        $this->nextId['inventory_serials'] = $serialId;
        $this->nextId['inventory_transactions'] = $txId;
        $this->info('Gudang: stok serial/roll/quantity, custody teknisi, transfer, aset infrastruktur terpasang (SN+QTY+ROLL).');
    }

    /**
     * @param  list<array<string, mixed>>  $serials
     * @param  list<array<string, mixed>>  $tx
     */
    private function flushWarehouse(array &$serials, array &$tx): void
    {
        $this->bulk('inventory_serials', $serials);
        $this->bulk('inventory_transactions', $tx);
        $serials = [];
        $tx = [];
    }

    // ───────────────────────── pelanggan + billing ─────────────────────────

    private function seedCustomers(int $total): void
    {
        $ids = [];
        foreach (['persons', 'customers', 'customer_services', 'customer_addresses', 'customer_technical_details', 'customer_installations', 'customer_surveys', 'customer_devices', 'customer_status_logs', 'invoices', 'invoice_items', 'payments', 'network_port_assignments'] as $t) {
            $ids[$t] = $this->takeId($t);
        }
        $serialId = $this->nextId['inventory_serials'];
        $txId = $this->nextId['inventory_transactions'];
        $ontItems = collect(['ONT-ZTE-F609', 'ONT-ZTE-F660', 'ONT-HUAWEI-8245H', 'ONT-FIBERHOME-5506'])
            ->map(fn ($c) => $this->ref['items'][$c] ?? null)->filter()->values();

        foreach ($this->ref['branches'] as $branch) {
            $this->nextCode[$branch['pop']->id] = $this->nextCustomerNumber($branch['pop']->id);
        }

        $firstNames = ['Budi', 'Siti', 'Agus', 'Dewi', 'Rian', 'Sri', 'Eko', 'Indah', 'Hendra', 'Rina', 'Dedi', 'Yuni', 'Andi', 'Lilis', 'Taufik', 'Ratna', 'Bambang', 'Fitri', 'Joko', 'Wati', 'Slamet', 'Nur', 'Aris', 'Putri', 'Heri', 'Ani', 'Edi', 'Mega', 'Roni', 'Tuti'];
        $lastNames = ['Santoso', 'Aminah', 'Susanto', 'Lestari', 'Hidayat', 'Wahyuni', 'Prasetyo', 'Permata', 'Setiawan', 'Marlina', 'Kurniawan', 'Wijaya', 'Pratama', 'Suryani', 'Hermawan', 'Sari', 'Nugroho', 'Utami', 'Riyadi', 'Munandar', 'Cahyono', 'Handayani', 'Saputra', 'Rahayu', 'Purnomo', 'Widodo', 'Kusuma', 'Anggraini', 'Firmansyah', 'Maulana'];
        $streets = ['Jl. Raya Ponorogo', 'Jl. Merdeka', 'Jl. Sudirman', 'Jl. Pahlawan', 'Jl. Diponegoro', 'Jl. Gajah Mada', 'Jl. HOS Cokroaminoto', 'Jl. Ahmad Yani', 'Jl. Kyai Mojo', 'Jl. Soekarno Hatta'];
        $banks = ['BCA', 'BRI', 'BNI', 'Mandiri'];

        $villages = $this->ref['villages'];
        $villageCount = $villages->count();
        $packages = $this->ref['packages'];
        $businessPackages = $this->ref['businessPackages'];
        $agentIds = array_values($this->ref['agents']);
        $lastReferrer = null;
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $periodStart = Carbon::parse($this->periods[0].'-01', 'Asia/Jakarta');
        $revenue = $this->ref['revenue'];
        $branchCount = count($this->ref['branches']);

        $stats = ['active' => 0, 'invoices' => 0, 'payments' => 0];

        for ($offset = 0; $offset < $total; $offset += self::CHUNK) {
            $rows = array_fill_keys(['persons', 'customers', 'customer_services', 'customer_addresses', 'customer_technical_details', 'customer_installations', 'customer_surveys', 'customer_devices', 'customer_status_logs', 'invoices', 'invoice_items', 'payments', 'network_port_assignments', 'inventory_serials', 'inventory_transactions', 'ports_used', 'customer_acquisitions'], []);

            for ($g = $offset + 1; $g <= min($offset + self::CHUNK, $total); $g++) {
                $b = $g % $branchCount;
                $branch = $this->ref['branches'][$b];
                $pop = $branch['pop'];
                $mini = $branch['mini'];

                $bucket = $g % 100;
                $status = match (true) {
                    $bucket < 80 => 'active',
                    $bucket < 83 => 'suspended',
                    $bucket < 89 => 'terminated',
                    $bucket < 92 => 'rejected',
                    $bucket < 94 => 'waiting_survey',
                    $bucket < 96 => 'waiting_installation',
                    $bucket < 98 => 'registered',
                    default => 'active',
                };
                $billed = in_array($status, ['active', 'suspended'], true);
                $hasService = ! in_array($status, ['registered'], true);
                $installed = in_array($status, ['active', 'suspended', 'terminated'], true);

                $village = $villages[($g * 7) % $villageCount];
                // ±2,5% pelanggan aktif = Pelanggan Bisnis (paket kategori Bisnis, tanpa diskon, ada Biaya Instalasi).
                $isBusiness = $billed && $g % 40 === 0;
                $package = $isBusiness ? $businessPackages[$g % $businessPackages->count()] : $packages[$g % $packages->count()];

                // Asal pelanggan: 30% lewat Sales, 20% lewat Agent, ±4% referral pelanggan aktif sebelumnya (saling eksklusif).
                $salesList = $this->ref['staff'][$b]['sales'] ?? [];
                $salesUserId = ($isBusiness || $g % 10 < 3) && $salesList ? $salesList[$g % count($salesList)] : null;
                $agentId = ! $salesUserId && in_array($g % 10, [5, 6], true) ? $agentIds[$g % count($agentIds)] : null;
                $referrer = ! $salesUserId && ! $agentId && $g % 25 === 9 ? $lastReferrer : null;
                $name = $firstNames[$g % 30].' '.$lastNames[intdiv($g, 30) % 30].($g > 900 ? ' '.chr(65 + $g % 26) : '');
                $customerId = $ids['customers']++;
                $personId = $ids['persons']++;
                $codeNo = $this->nextCode[$pop->id]++;
                $code = $pop->registration_prefix.str_pad((string) $codeNo, 6, '0', STR_PAD_LEFT);
                $distId = $branch['distributions'] ? array_keys($branch['distributions'])[$g % count($branch['distributions'])] : null;
                $distCode = $distId ? $branch['distributions'][$distId] : '0';

                // Tanggal aktivasi: 90% sebelum periode pertama (tagihan BULANAN penuh), 10% di dalam jendela 3 bulan (kena AWAL).
                if ($installed) {
                    $activation = mt_rand(1, 100) <= 90
                        ? $periodStart->copy()->subDays(mt_rand(1, 650))
                        : $periodStart->copy()->addDays(mt_rand(0, (int) $periodStart->diffInDays($today) - 1));
                    if ($status === 'terminated') {
                        $activation = $periodStart->copy()->subDays(mt_rand(220, 700));
                    }
                } else {
                    $activation = null;
                }
                $registeredAt = ($activation ?? $today->copy()->subDays(mt_rand(1, 40)))->copy()->subDays($installed ? mt_rand(3, 10) : 0)->setTime(mt_rand(8, 16), mt_rand(0, 59));
                $terminatedAt = $status === 'terminated' ? $periodStart->copy()->subDays(mt_rand(10, 200)) : null;

                $discount = ! $isBusiness && $g % 5 === 0 ? 25000 : 0;
                $monthly = (float) $package->monthly_price;
                $lat = -7.8650000 + (($g % 400) * 0.0009) + mt_rand(0, 90) / 100000;
                $lng = 111.4620000 + (intdiv($g, 400) % 300) * 0.0007 + mt_rand(0, 90) / 100000;
                $address = $streets[$g % 10].' No. '.(1 + $g % 150).', RT '.str_pad((string) (1 + $g % 9), 2, '0', STR_PAD_LEFT).'/RW '.str_pad((string) (1 + $g % 6), 2, '0', STR_PAD_LEFT).', '.$village->name.', Kec. '.$village->district_name.', Kabupaten Ponorogo, Jawa Timur';

                // Port ODP untuk pelanggan yang terpasang (kecuali terminated: port sudah dilepas).
                $odp = null;
                $portNo = null;
                if ($billed) {
                    $cursor = $this->ref['odpCursor'][$b]++;
                    $odp = $this->ref['odps'][$b][intdiv($cursor, self::ODP_PORTS)] ?? null;
                    $portNo = $odp ? ($cursor % self::ODP_PORTS) + 1 : null;
                }
                $collectorId = $billed && $g % 5 < 2 ? ($this->ref['staff'][$b]['kolektor'][$g % count($this->ref['staff'][$b]['kolektor'])] ?? null) : null;
                $techId = $this->ref['staff'][$b]['teknisi'][$g % count($this->ref['staff'][$b]['teknisi'])] ?? null;

                $cid = null;
                if ($billed) {
                    $miniSegment = preg_replace('/[^A-Z0-9]/i', '', substr($mini->pop_code, strlen((string) $mini->cid_prefix ?: (string) $pop->cid_prefix))) ?: '0';
                    $cid = $pop->cid_prefix.$miniSegment.$distCode.$pop->registration_prefix.str_pad((string) $codeNo, 6, '0', STR_PAD_LEFT);
                }

                // Serial ONT: terpasang (active/suspended) atau sudah ditarik (terminated).
                $ontSerial = null;
                if ($installed && $ontItems->isNotEmpty()) {
                    $ontItem = $ontItems[$g % $ontItems->count()];
                    $ontSerial = sprintf('DMY-ONT-%07d', $g);
                    $sid = $serialId++;
                    $returned = $status === 'terminated';
                    $rows['inventory_serials'][] = [
                        'id' => $sid, 'item_id' => $ontItem->id, 'serial_number' => $ontSerial, 'mac_address' => $this->mac(), 'status' => $returned ? 'returned' : 'installed',
                        'condition' => $returned ? 'used_good' : 'new', 'condition_checked_at' => null, 'condition_checked_by' => null, 'ownership' => 'company',
                        'current_pop_id' => $returned ? $pop->id : null, 'current_network_node_id' => null, 'current_mini_pop_id' => null,
                        'current_technician_id' => null, 'issued_from_pop_id' => $pop->id, 'customer_id' => $returned ? null : $customerId,
                        'fop_task_id' => null, 'installed_at' => $returned ? null : $activation, 'created_at' => $activation, 'updated_at' => now(),
                    ];
                    $insDate = $activation->format('Ymd');
                    $this->docCounters["INS:{$insDate}"] = ($this->docCounters["INS:{$insDate}"] ?? 0) + 1;
                    $rows['inventory_transactions'][] = [
                        'id' => $txId++, 'type' => 'install', 'reference_number' => sprintf('INS-%s-%06d', $insDate, $this->docCounters["INS:{$insDate}"]),
                        'inventory_transfer_id' => null, 'item_id' => $ontItem->id, 'lot_no' => null, 'serial_id' => $sid, 'roll_id' => null, 'qty' => 1,
                        'unit_price_snapshot' => 350000, 'from_pop_id' => null, 'from_network_node_id' => null, 'to_pop_id' => null, 'to_network_node_id' => null,
                        'from_technician_id' => $techId, 'to_technician_id' => null, 'fop_task_id' => null, 'reason' => "Pemasangan pelanggan {$code}",
                        'notes' => '[DUMMY]', 'evidence_file_path' => null, 'resulting_status' => 'installed', 'created_by' => $techId ?? 1,
                        'created_at' => $activation->copy()->setTime(10, 0), 'updated_at' => now(),
                    ];
                }

                $rows['persons'][] = ['id' => $personId, 'uuid' => (string) Str::uuid(), 'legacy_key' => null, 'merged_into' => null, 'created_at' => $registeredAt, 'updated_at' => now()];

                $rows['customers'][] = [
                    'id' => $customerId, 'person_id' => $personId, 'customer_code' => $code, 'old_customer_id' => null, 'old_request_id' => null, 'cid' => $cid,
                    'full_name' => $name, 'identity_number' => '3502'.str_pad((string) mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT),
                    'gender' => $g % 2 ? 'Laki-laki' : 'Perempuan', 'customer_type' => $isBusiness ? 'BISNIS' : 'RUMAHAN',
                    'company_name' => $isBusiness ? 'CV Dummy Usaha '.$g : null, 'npwp' => $isBusiness ? sprintf('%015d', mt_rand(1, 999999999999999)) : null,
                    'primary_phone' => '628'.str_pad((string) mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT), 'alternative_phone' => null,
                    'email' => 'dummy'.$g.'@dummy.whusnet.test', 'registration_date' => $registeredAt, 'registered_by_name' => self::MARK,
                    'data_completeness_status' => $installed ? 'lengkap' : 'perlu_dilengkapi', 'pop_id' => $pop->id, 'collector_id' => $collectorId,
                    'distribution_id' => $distId, 'mini_pop_id' => $mini->id, 'status' => $status, 'pending_initial_invoice' => null,
                    'rejected_at' => $status === 'rejected' ? $registeredAt->copy()->addDays(2) : null, 'terminated_at' => $terminatedAt,
                    'termination_reason_id' => null, 'termination_note' => $terminatedAt ? 'Dummy: berhenti berlangganan' : null,
                    'address' => $address, 'latitude' => $lat, 'longitude' => $lng, 'foto_rumah' => null, 'city_id' => 1,
                    'district_id' => $village->district_id, 'village_id' => $village->id, 'internet_package_id' => $package->id,
                    'contract_period_months' => 12, 'discount_amount' => $discount, 'tax_percent' => 11, 'sales_code' => $salesUserId ? 'SLS-'.$salesUserId : null,
                    'agent_code' => $agentId ? array_search($agentId, $this->ref['agents'], true) : null,
                    'referral_customer_code' => $referrer['code'] ?? null, 'sales_user_id' => $salesUserId, 'agent_id' => $agentId, 'referral_customer_id' => $referrer['id'] ?? null, 'foto_kontrak' => null,
                    'ont_sn' => $ontSerial, 'odp_code' => $odp['code'] ?? null, 'olt_code' => $odp['olt'] ?? null, 'vlan_id' => isset($odp['vlan']) ? (string) $odp['vlan'] : null,
                    'created_at' => $registeredAt, 'updated_at' => now(), 'created_by' => 1, 'updated_by' => null,
                ];

                $rows['customer_addresses'][] = [
                    'customer_id' => $customerId, 'old_region_id' => null, 'old_branch_id' => null, 'full_address' => $address, 'province' => 'Jawa Timur',
                    'city' => 'Ponorogo', 'district' => $village->district_name, 'village' => $village->name, 'city_id' => 1, 'district_id' => $village->district_id,
                    'village_id' => $village->id, 'latitude' => $lat, 'longitude' => $lng, 'house_photo' => null, 'contract_photo' => null,
                    'created_at' => $registeredAt, 'updated_at' => now(),
                ];

                $afterDiscount = max(0, $monthly - $discount);
                $totalMonthly = round($afterDiscount * 1.11, 2);
                if ($hasService) {
                    $rows['customer_services'][] = [
                        'customer_id' => $customerId, 'internet_package_id' => $package->id, 'old_request_id' => null, 'old_cost_id' => null, 'request_status' => null,
                        'installation_status' => $installed ? 'Berhasil' : null, 'network_type' => 'KABEL', 'member_type' => null, 'reason' => null,
                        'package_name_snapshot' => $package->name, 'download_speed_snapshot' => (string) ($package->download_speed_mbps ?? ''), 'upload_speed_snapshot' => (string) ($package->upload_speed_mbps ?? ''),
                        'monthly_price' => $monthly, 'discount' => $discount, 'ppn' => 11, 'other_fee' => null, 'total_monthly_bill' => $totalMonthly,
                        'activation_date' => $activation?->toDateString(), 'due_date' => $activation?->copy()->addMonth()->toDateString(),
                        'billing_cycle' => 'monthly', 'service_status' => $status, 'billing_status' => $billed ? 'active' : 'inactive',
                        'created_at' => $registeredAt, 'updated_at' => now(), 'profile' => null, 'contract_type' => 'sewa', 'activation_time' => $installed ? '10:00:00' : null,
                        'activated_by_name' => $installed ? 'Dummy Admin' : null, 'admin_filter_at' => null, 'admin_filter_by_name' => null, 'activated_by_user_id' => null,
                    ];
                }

                if (in_array($status, ['active', 'suspended', 'terminated', 'waiting_installation'], true)) {
                    $rows['customer_surveys'][] = [
                        'customer_id' => $customerId, 'survey_status' => 'completed', 'survey_date' => $registeredAt->copy()->addDay()->toDateString(), 'start_time' => '09:00:00',
                        'end_time' => '10:00:00', 'technician_id' => $techId, 'surveyor_2_id' => null, 'surveyor_3_id' => null, 'required_tools' => 'Tang, Splicer, OPM',
                        'cable_estimation_meter' => mt_rand(40, 250), 'nearest_odp' => $odp['code'] ?? null, 'requested_installation_date' => null, 'survey_photo' => null,
                        'house_photo' => null, 'survey_note' => 'Dummy: lokasi terjangkau', 'difficulty_level' => null, 'created_at' => $registeredAt, 'updated_at' => now(),
                        'end_date' => null, 'duration_minutes' => 60, 'surveyors' => null, 'assigned_at' => $registeredAt, 'fop_id' => $code, 'started_at' => $registeredAt, 'completed_at' => $registeredAt,
                    ];
                } elseif ($status === 'waiting_survey') {
                    $rows['customer_surveys'][] = [
                        'customer_id' => $customerId, 'survey_status' => 'pending', 'survey_date' => null, 'start_time' => null, 'end_time' => null, 'technician_id' => null,
                        'surveyor_2_id' => null, 'surveyor_3_id' => null, 'required_tools' => null, 'cable_estimation_meter' => null, 'nearest_odp' => null,
                        'requested_installation_date' => null, 'survey_photo' => null, 'house_photo' => null, 'survey_note' => null, 'difficulty_level' => null,
                        'created_at' => $registeredAt, 'updated_at' => now(), 'end_date' => null, 'duration_minutes' => null, 'surveyors' => null, 'assigned_at' => null,
                        'fop_id' => $code, 'started_at' => null, 'completed_at' => null,
                    ];
                }

                if ($installed || $status === 'waiting_installation') {
                    $rows['customer_installations'][] = [
                        'customer_id' => $customerId, 'installation_status' => $installed ? 'completed' : 'scheduled', 'scheduled_date' => $activation?->toDateString(),
                        'scheduled_time' => $installed ? '10:00:00' : null, 'technician_id' => $installed ? $techId : null, 'technician_2_id' => null, 'technician_3_id' => null,
                        'finished_date' => $activation?->toDateString(), 'installation_photo' => null, 'contract_photo' => null, 'signature_photo' => null,
                        'installation_note' => $installed ? 'Dummy: terpasang normal' : null, 'selected_inventory_serial_id' => null, 'selected_inventory_roll_id' => null,
                        'roll_meters_used' => $installed ? mt_rand(30, 200) : null, 'modem_acquisition_choice' => null, 'manual_device_brand' => null, 'manual_device_model' => null,
                        'manual_device_serial_number' => null, 'modem_sale_price' => null, 'created_at' => $registeredAt, 'updated_at' => now(), 'start_time' => $installed ? '10:00:00' : null,
                        'end_time' => $installed ? '11:30:00' : null, 'technicians' => null, 'assigned_at' => $registeredAt, 'fop_id' => $code,
                        'started_at' => $activation, 'completed_at' => $activation, 'extra_device_shortage' => 0, 'extra_device_shortage_reason' => null, 'extra_devices_reported_at' => null,
                    ];
                }

                if ($installed) {
                    $rows['customer_technical_details'][] = [
                        'customer_id' => $customerId, 'old_report_id' => null, 'old_customer_id' => null, 'old_request_id' => null, 'connection_type' => 'KABEL', 'test_upload' => (string) mt_rand(20, 100),
                        'test_download' => (string) mt_rand(20, 100), 'ssid' => 'WHUS-'.strtoupper(Str::random(4)), 'antenna_mac' => null, 'router_mac' => null, 'router_or_ont_serial' => $ontSerial,
                        'odp_number' => $odp['code'] ?? null, 'odp_port' => $portNo ? (string) $portNo : null, 'olt_port' => isset($odp) ? (string) $odp['pon'] : null, 'olt_number' => $odp['olt'] ?? null,
                        'olt_slot' => isset($odp) ? (string) $odp['slot'] : null, 'vlan' => isset($odp['vlan']) ? (string) $odp['vlan'] : null, 'wireless_signal' => null,
                        'fiber_signal' => (string) (-1 * mt_rand(1800, 2400) / 100), 'location_source' => null, 'note' => 'Dummy', 'speedtest_photo' => null, 'form_photo' => null,
                        'signed_form_photo' => null, 'router_photo' => null, 'cable_photo' => null, 'created_at' => $activation, 'updated_at' => now(), 'passive_device' => null,
                        'passive_device_type' => null, 'passive_device_qty' => null, 'passive_device_note' => null, 'branch_number' => null, 'pop_number' => null, 'router_number' => null,
                        'initial_attenuation' => mt_rand(1800, 2400) / 100, 'actual_attenuation' => null, 'test_date' => $activation?->toDateString(), 'test_time' => '10:45:00',
                        'jitter_ms' => mt_rand(1, 20) / 10, 'latency_ms' => mt_rand(5, 30), 'packet_loss_percent' => 0, 'speed_conformity_percent' => mt_rand(90, 100), 'quality_score' => mt_rand(3, 5),
                    ];

                    $rows['customer_devices'][] = [
                        'customer_id' => $customerId, 'device_type' => 'ONT', 'brand' => 'ZTE', 'model' => 'F609', 'serial_number' => $ontSerial, 'mac_address' => $this->mac(),
                        'pppoe_username' => sprintf('%s_%s_%s', $cid ?? $code, strtoupper(str_replace(' ', '', $village->name)), strtoupper(str_replace(' ', '', $name))),
                        'pppoe_password' => Str::random(8), 'wifi_ssid' => 'WHUS-'.$code, 'wifi_password' => Str::random(8), 'vlan_id' => $odp['vlan'] ?? null,
                        'odp' => $odp['code'] ?? null, 'odp_port' => $portNo ? (string) $portNo : null, 'signal_rx_power' => -1 * mt_rand(1800, 2400) / 100, 'connection_mode' => 'pppoe',
                        'technical_note' => null, 'device_retrieved_at' => $status === 'terminated' ? $terminatedAt : null, 'acquisition_source' => 'sewa',
                        'created_at' => $activation, 'updated_at' => now(),
                    ];
                }

                if ($installed) {
                    $rows['customer_status_logs'][] = ['customer_id' => $customerId, 'from_status' => 'verification_admin', 'to_status' => 'active', 'changed_by' => 1, 'note' => 'Dummy seed', 'created_at' => $activation];
                    if ($terminatedAt) {
                        $rows['customer_status_logs'][] = ['customer_id' => $customerId, 'from_status' => 'active', 'to_status' => 'terminated', 'changed_by' => 1, 'note' => 'Dummy seed', 'created_at' => $terminatedAt];
                    }
                }

                if ($odp && $portNo) {
                    $rows['network_port_assignments'][] = [
                        'id' => $ids['network_port_assignments']++, 'network_node_port_id' => $odp['first_port'] + $portNo - 1, 'customer_id' => $customerId, 'target_node_id' => null,
                        'manual_label' => null, 'active_key' => 1, 'assigned_by' => 1, 'released_by' => null, 'assigned_at' => $activation, 'released_at' => null,
                        'created_at' => $activation, 'updated_at' => now(), 'release_note' => null, 'target_port_id' => null,
                    ];
                    $rows['ports_used'][] = $odp['first_port'] + $portNo - 1;
                }

                if (! $billed) {
                    continue;
                }

                $lastReferrer = ['id' => $customerId, 'code' => $code];

                // Normalnya dibuat CustomerObserver saat pertama kali ACTIVE: cukup pelanggan yang aktif di jendela
                // tagihan (aktivasi ≥ periode pertama) + semua pelanggan Bisnis (supaya List Pelanggan Bisnis terisi).
                if ($isBusiness || $activation->gte($periodStart)) {
                    $rows['customer_acquisitions'][] = [
                        'customer_id' => $customerId, 'periode' => $activation->format('Y-m'), 'verified_at' => $activation->copy()->setTime(14, 0),
                        'installation_fee' => $isBusiness ? mt_rand(5, 25) * 100000 : null, 'installation_fee_invoice_id' => null,
                        'created_at' => $activation, 'updated_at' => now(),
                    ];
                }
                $stats['active']++;
                $serviceIdForInvoice = $ids['customer_services'] + count($rows['customer_services']) - 1;

                foreach ($this->periods as $pi => $period) {
                    $pStart = Carbon::parse($period.'-01', 'Asia/Jakarta');
                    if ($activation->isSameMonth($pStart)) {
                        $type = 'awal';
                        $days = $pStart->daysInMonth;
                        $remainingDays = $days - $activation->day + 1;
                        $prorate = round($monthly * $remainingDays / $days / 1000) * 1000;
                        $subtotal = $prorate + self::ACTIVATION_FEE;
                        $issue = $activation->copy();
                        $due = $activation->copy()->addDays(7);
                        $prefix = 'ACT';
                    } elseif ($activation->lt($pStart)) {
                        $type = 'bulanan';
                        $subtotal = $monthly;
                        $prorate = null;
                        $issue = $pStart->copy();
                        $due = $pStart->copy()->day(10);
                        $prefix = 'TAG';
                    } else {
                        continue;
                    }

                    $after = max(0, $subtotal - $discount);
                    $ppn = round($after * 0.11, 2);
                    $totalAmount = $after + $ppn;
                    $dateCode = $issue->format('Ymd');
                    $this->docCounters["INV:{$prefix}-{$dateCode}"] = ($this->docCounters["INV:{$prefix}-{$dateCode}"] ?? 0) + 1;
                    $invoiceNo = sprintf('%s-%s-%06d', $prefix, $dateCode, $this->docCounters["INV:{$prefix}-{$dateCode}"]);
                    $invoiceId = $ids['invoices']++;

                    // Peluang bayar: bulan lama hampir lunas, bulan berjalan baru separuh.
                    [$pLunas, $pSebagian] = [[0.93, 0.04], [0.82, 0.06], [0.45, 0.05]][$pi];
                    $dice = mt_rand(0, 9999) / 10000;
                    $plan = $dice < $pLunas ? 'lunas' : ($dice < $pLunas + $pSebagian ? 'sebagian' : 'belum');

                    $paid = 0;
                    $payRows = [];
                    if ($plan !== 'belum') {
                        $installments = $plan === 'lunas' && mt_rand(1, 100) <= 8 ? 2 : 1;
                        $amounts = $plan === 'sebagian'
                            ? [min($totalAmount - 1000, max(1000, round($totalAmount * mt_rand(20, 70) / 100 / 1000) * 1000))]
                            : ($installments === 2 ? [$first = round($totalAmount * 0.4 / 1000) * 1000, $totalAmount - $first] : [$totalAmount]);

                        foreach ($amounts as $k => $amount) {
                            $payDate = $issue->copy()->addDays(mt_rand(0, 9) + $k * 6);
                            if ($payDate->gt($today)) {
                                $payDate = $today->copy();
                            }
                            $method = $collectorId && mt_rand(1, 100) <= 55 ? 'kolektor' : (mt_rand(0, 1) ? 'cash' : 'transfer');
                            $payRows[] = [
                                'id' => $ids['payments']++, 'payment_number' => count($amounts) > 1 ? sprintf('PAY-%s-%02d', $invoiceNo, $k + 1) : "PAY-{$invoiceNo}",
                                'idempotency_key' => null, 'old_payment_id' => null, 'old_transaction_id' => null, 'old_request_id' => null, 'billing_period' => $period,
                                'received_by_old' => null, 'deposited_by_old' => null, 'invoice_id' => $invoiceId, 'payment_batch_id' => null, 'collector_deposit_id' => null,
                                'cash_deposit_id' => null, 'customer_id' => $customerId, 'pop_id' => $pop->id, 'payment_date' => $payDate->toDateString(),
                                'collected_date' => $method === 'kolektor' ? $payDate->toDateString() : null, 'payment_method' => $method, 'bank_account_id' => null,
                                'bank_name' => $method === 'transfer' ? $banks[$g % 4] : null, 'account_number' => $method === 'transfer' ? (string) mt_rand(1000000000, 9999999999) : null,
                                'sender_name' => $method === 'transfer' ? $name : null, 'amount' => $amount, 'balance_used_amount' => 0, 'overpay_amount' => null,
                                'received_by' => $method === 'kolektor' ? null : 1, 'collected_by' => $method === 'kolektor' ? $collectorId : null,
                                'collected_by_role' => $method === 'kolektor' ? 'kolektor' : null, 'proof_file' => null, 'payment_status' => 'valid', 'reject_reason' => null,
                                'rejected_at' => null, 'rejected_by' => null, 'note' => null, 'created_at' => $payDate, 'updated_at' => $payDate,
                            ];
                            $paid += $amount;
                        }
                    }
                    $remaining = max(0, $totalAmount - $paid);
                    $invStatus = $remaining <= 0 ? 'lunas' : ($paid > 0 ? 'sebagian' : 'belum_dibayar');

                    $rows['invoices'][] = [
                        'id' => $invoiceId, 'invoice_number' => $invoiceNo, 'invoice_type' => $type, 'manual_category' => null, 'manual_subtype_name' => null, 'description' => null,
                        'old_invoice_id' => null, 'old_cost_id' => null, 'old_request_id' => null, 'customer_id' => $customerId, 'pop_id' => $pop->id,
                        'customer_service_id' => $serviceIdForInvoice, 'internet_package_id' => $package->id, 'billing_period' => $period, 'issue_date' => $issue->toDateString(),
                        'due_date' => $due->toDateString(), 'subtotal' => $subtotal, 'discount' => $discount, 'ppn' => 11, 'total_amount' => $totalAmount, 'paid_amount' => $paid,
                        'remaining_amount' => $remaining, 'invoice_status' => $invStatus, 'written_off_at' => null, 'written_off_by' => null, 'written_off_amount' => null,
                        'write_off_reason' => null, 'write_off_reversed_at' => null, 'write_off_reversed_by' => null, 'created_by' => null, 'created_at' => $issue, 'updated_at' => now(),
                        'prorate_amount' => $prorate, 'extra_cable_fee' => null, 'other_fee' => $type === 'awal' ? self::ACTIVATION_FEE : null, 'extra_installation_fee' => null, 'extra_pole_fee' => null,
                    ];

                    $lines = $type === 'awal'
                        ? [['sub:prorata', 'cat:jasa_layanan_internet', "Prorata {$period}", $prorate], ['sub:biaya_aktivasi', 'cat:jasa_instalasi', 'Biaya aktivasi', self::ACTIVATION_FEE]]
                        : [['sub:langganan_bulanan', 'cat:jasa_layanan_internet', "Langganan {$period}", $subtotal]];
                    foreach ($lines as $sort => [$subKey, $catKey, $desc, $amount]) {
                        $rows['invoice_items'][] = [
                            'id' => $ids['invoice_items']++, 'invoice_id' => $invoiceId, 'revenue_category_id' => $revenue[$catKey]['id'], 'revenue_subcategory_id' => $revenue[$subKey]['id'],
                            'category_name_snapshot' => $revenue[$catKey]['name'], 'subcategory_name_snapshot' => $revenue[$subKey]['name'], 'description' => $desc,
                            'amount' => $amount, 'sort_order' => ($sort + 1) * 10, 'created_at' => $issue, 'updated_at' => now(),
                        ];
                    }
                    array_push($rows['payments'], ...$payRows);
                    $stats['invoices']++;
                    $stats['payments'] += count($payRows);
                }
            }

            DB::transaction(function () use (&$rows, &$ids) {
                // Urutan = urutan FK: orang → pelanggan → anak → keuangan → inventori/port.
                foreach (['persons', 'customers'] as $t) {
                    $this->bulk($t, $rows[$t]);
                }
                $this->bulkWithIds('customer_services', $rows['customer_services']);
                foreach (['customer_addresses', 'customer_technical_details', 'customer_installations', 'customer_surveys', 'customer_devices', 'customer_status_logs'] as $t) {
                    $this->bulk($t, $rows[$t]);
                }
                foreach (['customer_acquisitions', 'invoices', 'invoice_items', 'payments', 'inventory_serials', 'network_port_assignments', 'inventory_transactions'] as $t) {
                    $this->bulk($t, $rows[$t]);
                }
                foreach (array_chunk($rows['ports_used'], 1000) as $portIds) {
                    DB::table('network_node_ports')->whereIn('id', $portIds)->update(['is_used' => 1]);
                }
            });

            // customer_services dipakai FK invoices (customer_service_id) — id eksplisit dihitung dari counter.
            $ids['customer_services'] += count($rows['customer_services']);

            $this->info(sprintf('  %d/%d pelanggan…', min($offset + self::CHUNK, $total), $total));
        }

        foreach ($this->ref['branches'] as $branch) {
            DB::table('pop_sequences')->updateOrInsert(
                ['pop_id' => $branch['pop']->id, 'sequence_type' => 'registration'],
                ['current_number' => $this->nextCode[$branch['pop']->id] - 1, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $this->info("Pelanggan billing (active+suspended): {$stats['active']}, invoice: {$stats['invoices']}, pembayaran: {$stats['payments']}.");
    }

    // ───────────────────────── sekuens, purge, helper ─────────────────────────

    /**
     * Counter nomor dokumen disinkronkan supaya terbit berikutnya lewat
     * `NumberSequenceService` tidak menabrak nomor dummy. Tidak pernah
     * menurunkan counter yang sudah lebih tinggi.
     */
    private function syncSequences(): void
    {
        $rows = [];
        foreach ($this->docCounters as $key => $value) {
            $full = str_starts_with($key, 'INS:') ? 'LEDGER:'.$key : $key;
            $existing = (int) DB::table('number_sequences')->where('key', $full)->value('value');
            $rows[] = ['key' => $full, 'value' => max($existing, $value)];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('number_sequences')->upsert($chunk, ['key'], ['value']);
        }
    }

    private function purge(): void
    {
        Schema::disableForeignKeyConstraints();
        $customerIds = DB::table('customers')->where('registered_by_name', self::MARK)->pluck('id');

        foreach ($customerIds->chunk(2000) as $chunk) {
            $ids = $chunk->all();
            $this->purgeOperations($ids);
            DB::table('customers')->whereIn('id', $ids)->update(['referral_customer_id' => null]);
            DB::table('customer_acquisitions')->whereIn('customer_id', $ids)->delete();
            $invoiceIds = DB::table('invoices')->whereIn('customer_id', $ids)->pluck('id')->all();
            DB::table('payments')->whereIn('customer_id', $ids)->delete();
            foreach (array_chunk($invoiceIds, 2000) as $inv) {
                DB::table('invoice_items')->whereIn('invoice_id', $inv)->delete();
            }
            DB::table('invoices')->whereIn('customer_id', $ids)->delete();
            foreach (['network_port_assignments', 'customer_devices', 'customer_technical_details', 'customer_installations', 'customer_surveys', 'customer_status_logs', 'customer_addresses', 'customer_services'] as $t) {
                DB::table($t)->whereIn('customer_id', $ids)->delete();
            }
        }
        $personIds = DB::table('customers')->where('registered_by_name', self::MARK)->pluck('person_id');
        DB::table('customers')->where('registered_by_name', self::MARK)->delete();
        foreach ($personIds->chunk(2000) as $chunk) {
            DB::table('persons')->whereIn('id', $chunk->all())->delete();
        }

        DB::table('inventory_transactions')->where('notes', 'like', '[DUMMY]%')->delete();
        DB::table('inventory_serials')->where('serial_number', 'like', 'DMY-%')->delete();
        DB::table('inventory_rolls')->where('roll_code', 'like', 'DMY-%')->delete();
        DB::table('inventory_balances')->where('lot_no', 'DMY-LOT')->delete();
        DB::table('technician_custody')->where('lot_no', 'DMY-LOT')->delete();
        DB::table('inventory_transfers')->where('reference_number', 'like', 'DMYTRF-%')->delete();

        // Permintaan stok & tim FOP harian tidak menempel ke pelanggan, jadi ditandai lewat catatan/nama.
        $requestIds = DB::table('stock_requests')->where('notes', 'like', '[DUMMY]%')->pluck('id');
        foreach ($requestIds->chunk(2000) as $chunk) {
            DB::table('stock_request_items')->whereIn('stock_request_id', $chunk->all())->delete();
        }
        DB::table('stock_requests')->where('notes', 'like', '[DUMMY]%')->delete();

        $teamIds = DB::table('fop_task_teams')->where('name', 'like', 'DMY %')->pluck('id');
        foreach ($teamIds->chunk(2000) as $chunk) {
            DB::table('fop_task_team_user')->whereIn('fop_task_team_id', $chunk->all())->delete();
        }
        DB::table('fop_task_teams')->where('name', 'like', 'DMY %')->delete();
        DB::table('agents')->where('code', 'like', 'DMY-AGT-%')->delete();

        $nodeIds = DB::table('network_nodes')->where('code', 'like', 'DMY-%')->pluck('id');
        foreach ($nodeIds->chunk(2000) as $chunk) {
            DB::table('network_node_ports')->whereIn('network_node_id', $chunk->all())->delete();
            // `infrastructure_balances.network_node_id` RESTRICT — harus putus dulu sebelum node dihapus di bawah.
            DB::table('infrastructure_balances')->whereIn('network_node_id', $chunk->all())->delete();
        }
        // RESTRICT di `parent_id` (self-FK) dicek per baris, bukan per statement — urutan hapus dalam
        // satu query tak menolong, jadi putus dulu rantai induk-anaknya.
        DB::table('network_nodes')->where('code', 'like', 'DMY-%')->update(['parent_id' => null]);
        DB::table('network_nodes')->where('code', 'like', 'DMY-%')->delete();

        $userIds = DB::table('users')->where('email', 'like', 'dummy.%@whusnet.test')->pluck('id');
        $scopeIds = DB::table('user_role_scopes')->whereIn('user_id', $userIds)->pluck('id');
        DB::table('user_role_scope_targets')->whereIn('user_role_scope_id', $scopeIds)->delete();
        DB::table('user_role_scopes')->whereIn('user_id', $userIds)->delete();
        DB::table('warehouse_pop_pics')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
        $this->purgeOrphans();
        Schema::enableForeignKeyConstraints();

        $this->info('Data dummy dihapus ('.$customerIds->count().' pelanggan). Counter number_sequences/pop_sequences sengaja tidak diturunkan.');
    }

    /**
     * Hapus tiket, FOP task, dan task teknisi milik sekumpulan pelanggan dummy
     * (dibuat `DummyOperationsSeeder`). Urutan: anak dulu, induk belakangan.
     *
     * @param  list<int>  $customerIds
     */
    private function purgeOperations(array $customerIds): void
    {
        $ticketIds = DB::table('tickets')->whereIn('customer_id', $customerIds)->pluck('id')->all();
        $fopIds = DB::table('fop_tasks')->whereIn('customer_id', $customerIds)->pluck('id')->all();
        $taskIds = DB::table('tasks')->whereIn('customer_id', $customerIds)->pluck('id')->all();

        foreach (array_chunk($ticketIds, 2000) as $chunk) {
            DB::table('ticket_histories')->whereIn('ticket_id', $chunk)->delete();
        }
        DB::table('tickets')->whereIn('customer_id', $customerIds)->delete();

        foreach (array_chunk($fopIds, 2000) as $chunk) {
            foreach (['fop_task_status_history', 'fop_task_user', 'task_materials'] as $table) {
                DB::table($table)->whereIn('fop_task_id', $chunk)->delete();
            }
        }
        DB::table('fop_tasks')->whereIn('customer_id', $customerIds)->delete();

        foreach (array_chunk($taskIds, 2000) as $chunk) {
            foreach (['task_reports', 'task_teams', 'task_maintenances', 'task_creq_details'] as $table) {
                DB::table($table)->whereIn('task_id', $chunk)->delete();
            }
        }
        DB::table('tasks')->whereIn('customer_id', $customerIds)->delete();
    }

    /**
     * Sapu baris yatim: record keuangan/operasional yang pelanggannya sudah tidak ada.
     *
     * Di production FK `restrictOnDelete` mencegah ini terjadi; di sini purge mematikan FK, jadi run
     * yang terputus/bertabrakan (dua seeder bersamaan, proses dibunuh di tengah) bisa meninggalkan
     * invoice/tiket/task tanpa pelanggan — dan nomor dokumennya lalu menabrak unique index di run berikutnya.
     * Pelanggan sungguhan tidak mungkin yatim, jadi menyapu semua `customer_id` tak bertuan aman.
     */
    private function purgeOrphans(): void
    {
        $orphans = fn (string $table) => DB::table($table)->whereNotNull('customer_id')
            ->whereNotIn('customer_id', DB::table('customers')->select('id'));

        $invoiceIds = $orphans('invoices')->pluck('id')->all();
        foreach (array_chunk($invoiceIds, 2000) as $chunk) {
            DB::table('invoice_items')->whereIn('invoice_id', $chunk)->delete();
        }
        $orphans('payments')->delete();
        $orphans('invoices')->delete();

        $ticketIds = $orphans('tickets')->pluck('id')->all();
        foreach (array_chunk($ticketIds, 2000) as $chunk) {
            DB::table('ticket_histories')->whereIn('ticket_id', $chunk)->delete();
        }
        $orphans('tickets')->delete();

        $fopIds = $orphans('fop_tasks')->pluck('id')->all();
        foreach (array_chunk($fopIds, 2000) as $chunk) {
            foreach (['fop_task_status_history', 'fop_task_user', 'task_materials'] as $table) {
                DB::table($table)->whereIn('fop_task_id', $chunk)->delete();
            }
        }
        $orphans('fop_tasks')->delete();

        $taskIds = $orphans('tasks')->pluck('id')->all();
        foreach (array_chunk($taskIds, 2000) as $chunk) {
            foreach (['task_reports', 'task_teams', 'task_maintenances', 'task_creq_details'] as $table) {
                DB::table($table)->whereIn('task_id', $chunk)->delete();
            }
        }
        $orphans('tasks')->delete();

        foreach (['network_port_assignments', 'customer_devices', 'customer_technical_details', 'customer_installations', 'customer_surveys', 'customer_status_logs', 'customer_addresses', 'customer_services', 'customer_acquisitions'] as $table) {
            DB::table($table)->whereNotIn('customer_id', DB::table('customers')->select('id'))->delete();
        }
    }

    private function takeId(string $table): int
    {
        return $this->nextId[$table] = (int) DB::table($table)->max('id') + 1;
    }

    /** Nomor urut pelanggan berikutnya di satu Cabang (maks angka `customer_code` yang ada + 1). */
    private function nextCustomerNumber(int $popId): int
    {
        $max = (int) DB::table('customers')->where('pop_id', $popId)
            ->selectRaw('MAX(CAST(SUBSTRING(customer_code, 3) AS UNSIGNED)) as m')->value('m');
        $seq = (int) DB::table('pop_sequences')->where(['pop_id' => $popId, 'sequence_type' => 'registration'])->value('current_number');

        return max($max, $seq) + 1;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function bulk(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * `customer_services` tidak pakai id eksplisit di baris (kolom id absen),
     * tapi invoice butuh id layanan yang pasti — jadi di sini id disuntikkan
     * berurutan dari counter sebelum insert.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function bulkWithIds(string $table, array &$rows): void
    {
        $start = $this->nextId[$table];
        foreach ($rows as $i => &$row) {
            $row = ['id' => $start + $i] + $row;
        }
        unset($row);
        $this->nextId[$table] = $start + count($rows);
        $this->bulk($table, $rows);
    }

    private function mac(): string
    {
        return strtoupper(implode(':', str_split(bin2hex(random_bytes(6)), 2)));
    }

    private function info(string $message): void
    {
        $this->command?->info($message);
    }
}
