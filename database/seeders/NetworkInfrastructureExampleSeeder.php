<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryService;
use App\Services\InventoryTransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Contoh data Master Jaringan (`network_nodes`) + Inventaris Infrastruktur
 * (ADHOC-167) — jalankan MANUAL, jangan didaftarkan ke `DatabaseSeeder::run()`.
 * Tujuannya cuma lihat `/network/nodes` dan `/network/inventaris` kebentuk
 * dari data yang nyambung end-to-end, bukan data produksi.
 *
 * Jalankan: php artisan db:seed --class=NetworkInfrastructureExampleSeeder
 *
 * Alamat node (desa/kecamatan) REAL — dipinjam dari `PonorogoRegionSeeder`
 * (Kecamatan Jetis beneran ada di Ponorogo), bukan dikarang. Pohon dibangun
 * tepat sesuai spec v3 (`docs/superpowers/specs/2026-10-09-aset-infrastruktur-
 * lokasi-design.md`): OLT = Mini POP (bukan `network_node` sendiri — lihat
 * §7 open item 1), jadi `pon_port` di sini ditambatkan LANGSUNG ke Mini POP,
 * bukan ke node "OLT" yang tidak ada.
 *
 * Ledger DEPLOY dibuat lewat `InventoryService` SUNGGUHAN (bukan
 * `InventoryTransaction::create()` manual) — kalau servicenya berubah,
 * seeder ini ikut kedetect rusak, bukan diam-diam jadi gak representatif.
 * `created_at` ledger di-backdate SETELAH deploy (service selalu pakai
 * `now()`) supaya 3 FopTask yang dibikin di tanggal berbeda tampil beneran
 * beda tanggal di kolom "Tanggal Pasang" halaman Inventaris — kalau tidak,
 * semuanya numpuk di tanggal seeder dijalankan, kurang representatif buat
 * demo laporan historis.
 *
 * Idempotent utk master (node type/POP/item/user) — dicek lewat kode node
 * puncak (`ODC-JETIS-C1-01`). SN/roll/qty demo py identitas unik (SN gak
 * boleh dobel), jadi re-run SKIP seluruh blok deploy kalau sudah pernah,
 * sama pola `WarehouseExampleSeeder`.
 */
class NetworkInfrastructureExampleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(NetworkNodeTypeSeeder::class);

        $jetisCabang = Pop::where('pop_code', 'C')->first();
        $jetisC1 = Pop::where('pop_code', 'C1')->first();

        if (! $jetisCabang || ! $jetisC1) {
            $this->call(MasterPopSeeder::class);
            $jetisCabang = Pop::where('pop_code', 'C')->firstOrFail();
            $jetisC1 = Pop::where('pop_code', 'C1')->firstOrFail();
        }

        $pusat = Pop::where('type', 'pusat')->first() ?? Pop::create([
            'code' => 'DEMO-PUSAT', 'pop_code' => 'DPS', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'Gudang Pusat (Contoh)', 'type' => 'pusat', 'status' => 'active',
        ]);

        $owner = User::whereHas('role', fn ($q) => $q->where('code', 'owner'))->first();
        if (! $owner) {
            $this->command?->error('Belum ada user role owner — jalankan php artisan db:seed dulu (UserSeeder).');

            return;
        }

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $teknisi = User::firstOrCreate(
            ['email' => 'teknisi.jaringan.demo@whusnet.test'],
            [
                'name' => 'Teknisi Jaringan Demo',
                'phone' => '081300000001',
                'password' => Hash::make('password'),
                'status' => 'active',
                'role_id' => $teknisiRole?->id,
                'email_verified_at' => now(),
            ]
        );
        $scope = UserRoleScope::firstOrCreate(
            ['user_id' => $teknisi->id, 'role_id' => $teknisiRole?->id],
            ['scope_type' => 'selected_pop']
        );
        UserRoleScopeTarget::firstOrCreate(['user_role_scope_id' => $scope->id, 'pop_id' => $jetisCabang->id]);

        // ── 1. Pohon Master Jaringan (idempotent — firstOrCreate by code) ──
        $types = NetworkNodeType::whereIn('code', ['core_router', 'backbone', 'aggregation_switch', 'pon_port', 'odc', 'odp'])
            ->get()->keyBy('code');

        $coreRouter = NetworkNode::firstOrCreate(
            ['code' => 'CR-PUSAT-01'],
            [
                'anchor_pop_id' => $pusat->id,
                'node_type_id' => $types['core_router']->id,
                'name' => 'Core Router Pusat (Mikrotik CCR2004-16G-2S+)',
                'capacity' => 20000, // Mbps — uplink agregat
                'status' => 'active',
            ]
        );

        $backbone = NetworkNode::firstOrCreate(
            ['code' => 'BB-PUSAT-JETIS-01'],
            [
                'parent_id' => $coreRouter->id,
                'node_type_id' => $types['backbone']->id,
                'name' => 'Backbone Fiber Pusat - Jetis',
                'capacity' => 10000, // Mbps
                'status' => 'active',
            ]
        );

        $aggSwitch = NetworkNode::firstOrCreate(
            ['code' => 'AGG-JETIS-01'],
            [
                'anchor_pop_id' => $jetisCabang->id,
                'node_type_id' => $types['aggregation_switch']->id,
                'name' => 'Switch Agregasi POP Jetis (Huawei S5700-28P)',
                'capacity' => 10000, // Mbps
                'status' => 'active',
            ]
        );

        // `pon_port` ditambatkan LANGSUNG ke Mini POP (= OLT ke-1 Jetis C1) —
        // bukan ke node "OLT" yang sengaja tidak ada (spec v3 §2 poin 3, §7
        // open item 1). Ini representasi kartu/card PON di OLT itu.
        $ponPort = NetworkNode::firstOrCreate(
            ['code' => 'OLT-JETISC1-PON-01'],
            [
                'anchor_pop_id' => $jetisC1->id,
                'node_type_id' => $types['pon_port']->id,
                'name' => 'OLT ZTE C320 Jetis C1 - Card PON Slot 1 (GPON 8 Port)',
                'capacity' => 8,
                'status' => 'active',
            ]
        );

        $odc = NetworkNode::firstOrCreate(
            ['code' => 'ODC-JETIS-C1-01'],
            [
                'parent_id' => $ponPort->id,
                'node_type_id' => $types['odc']->id,
                'name' => 'ODC Jetis C1-01 (Tiang Jl. Raya Jetis-Coper KM 2)',
                'capacity' => 12, // slot tube/core terminal
                'address' => 'Tiang Telkom No. 47, Jl. Raya Jetis-Coper',
                'village' => 'Jetis',
                'district' => 'Jetis',
                'city' => 'Ponorogo',
                'status' => 'active',
            ]
        );

        $odp1 = NetworkNode::firstOrCreate(
            ['code' => 'ODP-JETIS-C1-01-01'],
            [
                'parent_id' => $odc->id,
                'node_type_id' => $types['odp']->id,
                'name' => 'ODP Dusun Coper RT02/RW01',
                'capacity' => 8,
                'address' => 'Tiang listrik depan Masjid Jami Coper',
                'village' => 'Coper',
                'district' => 'Jetis',
                'city' => 'Ponorogo',
                'status' => 'active',
            ]
        );

        $odp2 = NetworkNode::firstOrCreate(
            ['code' => 'ODP-JETIS-C1-01-02'],
            [
                'parent_id' => $odc->id,
                'node_type_id' => $types['odp']->id,
                'name' => 'ODP Dusun Josari RT01/RW03',
                'capacity' => 8,
                'address' => 'Tiang listrik Balai Dusun Josari',
                'village' => 'Josari',
                'district' => 'Jetis',
                'city' => 'Ponorogo',
                'status' => 'active',
            ]
        );

        // Port + pemakaian realistis: ODC baru 2 dari 12 tube terpakai (ke
        // ODP-01 & ODP-02). ODP-01 SENGAJA 7/8 (87.5%, > red_zone_threshold
        // 80%) — contoh nyata badge Red Zone jalan. ODP-02 masih longgar.
        $this->seedPorts($odc, 12, 2);
        $this->seedPorts($odp1, 8, 7);
        $this->seedPorts($odp2, 8, 3);

        $this->command?->info('Master Jaringan: pohon Pusat -> Backbone -> POP Jetis -> OLT C1 -> ODC -> 2 ODP siap.');

        // ── 2. Inventaris — deploy aset Gudang ke pohon di atas ─────────
        if (Item::where('code', 'SPL-PLC-18-DEMO')->exists()) {
            $this->command?->info('Contoh Inventaris Infrastruktur udah pernah dijalankan — skip Receive/Transfer/Issue/Deploy (SN/roll demo gak boleh dobel).');

            return;
        }

        $catSplitter = ItemCategory::where('code', 'splitter_odp')->firstOrFail();
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $catAksesoris = ItemCategory::where('code', 'aksesoris_pasang')->firstOrFail();

        $splitter18 = Item::create([
            'code' => 'SPL-PLC-18-DEMO', 'name' => 'Splitter PLC 1:8 (Contoh)',
            'item_category_id' => $catSplitter->id, 'unit' => 'pcs',
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset',
        ]);
        $splitter14 = Item::create([
            'code' => 'SPL-PLC-14-DEMO', 'name' => 'Splitter PLC 1:4 / Closure ODC (Contoh)',
            'item_category_id' => $catSplitter->id, 'unit' => 'pcs',
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset',
        ]);
        $kabelFo = Item::create([
            'code' => 'FO-12CORE-DEMO', 'name' => 'Kabel Fiber Optic 12 Core (Contoh)',
            'item_category_id' => $catKabel->id, 'unit' => 'meter',
            'tracking_type' => 'roll', 'ownership_mode' => 'infrastructure_asset', 'meter_per_roll' => 1000,
        ]);
        $closure = Item::create([
            'code' => 'AKS-CLOSURE-DEMO', 'name' => 'Dome Closure Sambungan Fiber (Contoh)',
            'item_category_id' => $catAksesoris->id, 'unit' => 'pcs',
            'tracking_type' => 'quantity', 'ownership_mode' => 'infrastructure_asset',
        ]);

        // Barang Masuk ke Pusat — 1 bon gabungan (serialized + quantity).
        $serialSplitter18 = ['SPL18-DEMO-0001', 'SPL18-DEMO-0002'];
        $serialSplitter14 = ['SPL14-DEMO-0001'];
        $receiveRef = app(InventoryReceiveService::class)->receiveBatch($pusat, [
            ['item_id' => $splitter18->id, 'serial_numbers' => $serialSplitter18, 'unit_price' => 85000],
            ['item_id' => $splitter14->id, 'serial_numbers' => $serialSplitter14, 'unit_price' => 65000],
            ['item_id' => $closure->id, 'qty' => 5, 'unit_price' => 45000],
        ], $owner, 'Contoh data Inventaris Infrastruktur (NetworkInfrastructureExampleSeeder)');

        $rolls = app(InventoryReceiveService::class)->receiveRoll($pusat, $kabelFo, 1, 'Vendor Demo Fiber Optic', 3200000, $owner, 'Contoh kabel feeder/distribusi');
        $roll = $rolls[0];

        // Transfer semua ke Cabang Jetis, langsung dikonfirmasi terima.
        $transferSvc = app(InventoryTransferService::class);
        $allSerials = array_merge($serialSplitter18, $serialSplitter14);
        $transfer = $transferSvc->createTransfer($pusat, $jetisCabang, [
            ['item_id' => $splitter18->id, 'serial_numbers' => $serialSplitter18],
            ['item_id' => $splitter14->id, 'serial_numbers' => $serialSplitter14],
            ['item_id' => $closure->id, 'qty' => 5],
            ['item_id' => $kabelFo->id, 'roll_codes' => [$roll->roll_code]],
        ], $owner);
        $transferSvc->receiveTransfer($transfer, $allSerials, [$closure->id => 5], $owner, [$roll->roll_code]);

        // Issue semua ke Teknisi Jaringan Demo — baru dari sini custody
        // teknisi terbentuk, syarat `deploySerial()`/`deployQuantity()`/
        // `deployRoll()` (harus di custody tim yang deploy).
        app(InventoryIssueService::class)->issue($jetisCabang, $teknisi, [
            ['item_id' => $splitter18->id, 'serial_numbers' => $serialSplitter18],
            ['item_id' => $splitter14->id, 'serial_numbers' => $serialSplitter14],
            ['item_id' => $closure->id, 'qty' => 5],
            ['item_id' => $kabelFo->id, 'roll_codes' => [$roll->roll_code]],
        ], $owner);

        // ── 3. Deploy — 3 FopTask INFR REQ di 3 tanggal historis berbeda ─
        $inventory = app(InventoryService::class);
        $splitterSerials = InventorySerial::whereIn('serial_number', array_merge($serialSplitter18, $serialSplitter14))
            ->get()->keyBy('serial_number');

        // Task A, 4 bulan lalu: bangun ODC + tarik feeder.
        $taskA = $this->historicalInfraTask($jetisCabang, 'Bangun ODC Jetis C1-01 + Tarik Kabel Feeder', now()->subMonths(4));
        $txA = [];
        $txA[] = $inventory->deploySerial($splitterSerials['SPL14-DEMO-0001'], $odc, $taskA, [$teknisi], $owner);
        $txA[] = $inventory->deployRoll($roll->refresh(), 150, $odc, [$teknisi], $taskA, $owner);
        $txA[] = $inventory->deployQuantity([$teknisi], $closure, 1, $odc, $taskA, $owner)[0]['transaction_id'];

        // Task B, 2 bulan lalu: pasang splitter ODP-01 (Coper).
        $taskB = $this->historicalInfraTask($jetisCabang, 'Pasang Splitter ODP-01 Dusun Coper', now()->subMonths(2));
        $txB = [];
        $txB[] = $inventory->deploySerial($splitterSerials['SPL18-DEMO-0001'], $odp1, $taskB, [$teknisi], $owner);
        $txB[] = $inventory->deployRoll($roll->refresh(), 80, $odp1, [$teknisi], $taskB, $owner);
        $txB[] = $inventory->deployQuantity([$teknisi], $closure, 1, $odp1, $taskB, $owner)[0]['transaction_id'];

        // Task C, 3 minggu lalu: pasang splitter ODP-02 (Josari).
        $taskC = $this->historicalInfraTask($jetisCabang, 'Pasang Splitter ODP-02 Dusun Josari', now()->subWeeks(3));
        $txC = [];
        $txC[] = $inventory->deploySerial($splitterSerials['SPL18-DEMO-0002'], $odp2, $taskC, [$teknisi], $owner);
        $txC[] = $inventory->deployRoll($roll->refresh(), 70, $odp2, [$teknisi], $taskC, $owner);
        $txC[] = $inventory->deployQuantity([$teknisi], $closure, 1, $odp2, $taskC, $owner)[0]['transaction_id'];

        $this->backdateLedger($txA, $taskA->task_date);
        $this->backdateLedger($txB, $taskB->task_date);
        $this->backdateLedger($txC, $taskC->task_date);

        $this->command?->info("Inventaris Infrastruktur siap — Barang Masuk {$receiveRef}, Transfer {$transfer->reference_number}.");
        $this->command?->info('3 FopTask INFR REQ (bangun ODC, pasang ODP-01, pasang ODP-02) sudah deploy aset nyata ke pohon di atas.');
        $this->command?->info('Lihat hasilnya: /network/nodes (pohon + port + Red Zone ODP-01) dan /network/inventaris (4 item terpasang, lintas tanggal).');
    }

    /**
     * @param  array<int, InventoryTransaction|int>  $transactions  Elemen int = transaction_id (dari deployQuantity())
     */
    private function backdateLedger(array $transactions, Carbon $date): void
    {
        $timestamp = $date->copy()->setTime(9, 0);

        foreach ($transactions as $tx) {
            $id = is_int($tx) ? $tx : $tx->id;
            InventoryTransaction::where('id', $id)->update([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    private function historicalInfraTask(Pop $cabang, string $tugas, Carbon $date): FopTask
    {
        static $counter = 0;
        $counter++;

        return FopTask::create([
            'task_number' => 'TFOP-DEMO-INFR-'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'task_date' => $date,
            'category' => 'INFR REQ',
            'tugas' => $tugas,
            'pop_id' => $cabang->id,
            'issue' => 'Pembangunan/pemasangan infrastruktur baru',
            'status' => TaskStatus::SELESAI->value,
            'priority' => 'Medium',
        ]);
    }

    private function seedPorts(NetworkNode $node, int $total, int $used): void
    {
        for ($number = 1; $number <= $total; $number++) {
            $node->ports()->firstOrCreate(
                ['number' => $number],
                ['is_used' => $number <= $used]
            );
        }
    }
}
