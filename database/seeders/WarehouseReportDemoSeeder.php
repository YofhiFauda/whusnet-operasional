<?php

namespace Database\Seeders;

use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Skema fiktif TAPI BENERAN dijalankan lewat Service asli (Receive/Transfer/
 * Issue/Adjustment) — BUKAN insert langsung ke `inventory_transactions`/
 * `inventory_balances` — biar ledger konsisten dan Laporan Gudang
 * (`warehouse.reports.index` + Download Excel, ADHOC-79) nampilin angka
 * yang BENERAN diturunkan dari alur sistem, bukan data karangan yang
 * kebetulan cocok sama kolom laporan.
 *
 * Beda dari `WarehouseExampleSeeder` (demo umum modul Gudang, 1 Pusat + 1
 * Cabang, ringkas) — seeder ini SENGAJA dirancang buat "menyalakan" semua
 * kolom Laporan Gudang sekaligus: 1 Pusat + 2 Cabang (biar laporan ke-plot
 * lebih dari 1 sheet), 3 tracking type (serialized/quantity/roll — biar
 * kelakuan Stok Awal/Akhir beda-beda ikut kelihatan), transaksi dipecah 2
 * PERIODE (bulan lalu vs bulan ini, lewat backdate `created_at` SETELAH
 * Service jalan — satu-satunya cara nyuntik histori tanpa nabrak "ledger
 * append-only, gak boleh diedit APAPUN kecuali baris baru") biar Stok Awal
 * bulan ini != 0, dan RUSAK/HILANG di kedua kategori (`reason` literal buat
 * QUANTITY/custody, `resulting_status` buat SERIALIZED/ROLL) biar kolom
 * Nilai Rugi keisi.
 *
 * Jalankan MANUAL, jangan didaftarkan ke `DatabaseSeeder::run()`:
 *   php artisan db:seed --class=WarehouseReportDemoSeeder
 *
 * Abis jalan, buka /warehouse/reports lalu pilih periode BULAN INI —
 * download Excel-nya buat lihat laporan per-POP lengkap (Stok Beli/
 * Transfer/Terpakai/Rusak/Hilang/Stok Awal/Stok Akhir).
 *
 * Idempotent guard: dicek dari SN demo (`LAPDEMO-MODEM-%`) — sama pola
 * `WarehouseExampleSeeder`, re-run bakal nabrak unique constraint SN kalau
 * dipaksa, jadi di-skip otomatis kalau udah pernah jalan.
 */
class WarehouseReportDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::whereHas('role', fn ($q) => $q->where('code', 'owner'))->first();
        if (! $owner) {
            $this->command?->error('Belum ada user role owner — jalankan php artisan db:seed dulu (UserSeeder).');

            return;
        }

        if (InventorySerial::where('serial_number', 'like', 'LAPDEMO-MODEM-%')->exists()) {
            $this->command?->info('WarehouseReportDemoSeeder udah pernah dijalankan sebelumnya (SN LAPDEMO-MODEM-% ketemu) — skip biar gak nabrak unique constraint SN.');

            return;
        }

        $catAktif = ItemCategory::where('code', 'media_converter')->first();
        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->first();
        if (! $catAktif || ! $catPasif) {
            $this->command?->error('ItemCategoryFeatureSeeder belum jalan — kategori media_converter/kabel_dropcore gak ada. Jalankan php artisan db:seed dulu.');

            return;
        }

        // ── 1. Master Barang — 3 tracking type sekaligus ────────────────
        $modem = Item::updateOrCreate(
            ['code' => 'MODEM-LAPDEMO'],
            ['name' => 'Modem Router ZTE (Demo Laporan)', 'item_category_id' => $catAktif->id, 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable', 'is_active' => true]
        );
        $kabel = Item::updateOrCreate(
            ['code' => 'KABEL-LAPDEMO'],
            ['name' => 'Kabel Dropcore 1 Core (Demo Laporan)', 'item_category_id' => $catPasif->id, 'unit' => 'meter', 'tracking_type' => 'quantity', 'ownership_mode' => 'installable', 'is_active' => true]
        );
        $roll = Item::updateOrCreate(
            ['code' => 'ROLL-LAPDEMO'],
            ['name' => 'Kabel Fiber Optic Roll (Demo Laporan)', 'item_category_id' => $catPasif->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'ownership_mode' => 'installable', 'is_active' => true, 'meter_per_roll' => 1000]
        );

        // ── 2. 1 Pusat + 2 Cabang — dedicated, gak numpang POP asli ─────
        $pusat = Pop::firstOrCreate(
            ['code' => 'LAPDEMO-PUSAT'],
            ['pop_code' => 'LDP', 'registration_prefix' => 'L', 'cid_prefix' => 'M', 'name' => 'Gudang Pusat (Demo Laporan)', 'type' => 'pusat', 'status' => 'active']
        );
        $cabangA = Pop::firstOrCreate(
            ['code' => 'LAPDEMO-CABANG-A'],
            ['pop_code' => 'LDCA', 'registration_prefix' => 'L', 'cid_prefix' => 'M', 'name' => 'Cabang Ponorogo (Demo Laporan)', 'type' => 'cabang', 'status' => 'active']
        );
        $cabangB = Pop::firstOrCreate(
            ['code' => 'LAPDEMO-CABANG-B'],
            ['pop_code' => 'LDCB', 'registration_prefix' => 'L', 'cid_prefix' => 'M', 'name' => 'Cabang Pacitan (Demo Laporan)', 'type' => 'cabang', 'status' => 'active']
        );

        // ── 3. 2 Teknisi, masing-masing scope ke Cabang-nya ─────────────
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $teknisiA = User::firstOrCreate(
            ['email' => 'teknisi.lapdemo.a@whusnet.test'],
            ['name' => 'Teknisi Demo Laporan A', 'phone' => '081200000001', 'password' => Hash::make('password'), 'status' => 'active', 'role_id' => $teknisiRole?->id, 'email_verified_at' => now()]
        );
        $teknisiB = User::firstOrCreate(
            ['email' => 'teknisi.lapdemo.b@whusnet.test'],
            ['name' => 'Teknisi Demo Laporan B', 'phone' => '081200000002', 'password' => Hash::make('password'), 'status' => 'active', 'role_id' => $teknisiRole?->id, 'email_verified_at' => now()]
        );
        foreach ([[$teknisiA, $cabangA], [$teknisiB, $cabangB]] as [$teknisi, $cabang]) {
            $scope = UserRoleScope::firstOrCreate(['user_id' => $teknisi->id, 'role_id' => $teknisiRole?->id], ['scope_type' => 'selected_pop']);
            UserRoleScopeTarget::firstOrCreate(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        }

        $receiveSvc = app(InventoryReceiveService::class);
        $transferSvc = app(InventoryTransferService::class);
        $issueSvc = app(InventoryIssueService::class);
        $adjustSvc = app(InventoryAdjustmentService::class);

        // ════════════════════════════════════════════════════════════════
        // BULAN LALU — jadi baseline "Stok Awal" buat bulan ini. Dijalanin
        // pakai Service seperti biasa (timestamp asli = sekarang), BARU
        // di-backdate created_at-nya ke bulan lalu SETELAH selesai — jangan
        // dibalik urutannya (kalau backdate duluan, referensi bakal ke-mix
        // sama transaksi bulan ini yang belum kejadian).
        // ════════════════════════════════════════════════════════════════
        $refsBulanLalu = [];

        $snModemAwal = collect(range(1, 30))->map(fn ($n) => 'LAPDEMO-MODEM-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT))->all();

        $refsBulanLalu[] = $receiveSvc->receiveBatch($pusat, [
            ['item_id' => $modem->id, 'serial_numbers' => $snModemAwal, 'unit_price' => 300000],
            ['item_id' => $kabel->id, 'qty' => 800, 'unit_price' => 5000],
            ['item_id' => $roll->id, 'roll_count' => 5, 'vendor' => 'Vendor Demo', 'unit_price' => 2200],
        ], $owner, 'Demo Laporan Gudang — Barang Masuk Bulan Lalu');

        $rollCodesAwal = InventoryRoll::where('item_id', $roll->id)->orderBy('id')->pluck('roll_code')->all();

        $transferA1 = $transferSvc->createTransfer($pusat, $cabangA, [
            ['item_id' => $modem->id, 'serial_numbers' => array_slice($snModemAwal, 0, 10)],
            ['item_id' => $kabel->id, 'qty' => 300],
            ['item_id' => $roll->id, 'roll_codes' => array_slice($rollCodesAwal, 0, 2)],
        ], $owner);
        $transferSvc->receiveTransfer($transferA1, array_slice($snModemAwal, 0, 10), [$kabel->id => 300], $owner, array_slice($rollCodesAwal, 0, 2));
        $refsBulanLalu[] = $transferA1->reference_number;

        $transferB1 = $transferSvc->createTransfer($pusat, $cabangB, [
            ['item_id' => $modem->id, 'serial_numbers' => array_slice($snModemAwal, 10, 8)],
            ['item_id' => $kabel->id, 'qty' => 200],
            ['item_id' => $roll->id, 'roll_codes' => array_slice($rollCodesAwal, 2, 1)],
        ], $owner);
        $transferSvc->receiveTransfer($transferB1, array_slice($snModemAwal, 10, 8), [$kabel->id => 200], $owner, array_slice($rollCodesAwal, 2, 1));
        $refsBulanLalu[] = $transferB1->reference_number;

        $issueA1 = $issueSvc->issue($cabangA, $teknisiA, [
            ['item_id' => $modem->id, 'serial_numbers' => array_slice($snModemAwal, 0, 3)],
            ['item_id' => $kabel->id, 'qty' => 50],
            ['item_id' => $roll->id, 'roll_codes' => array_slice($rollCodesAwal, 0, 1)],
        ], $owner);
        $refsBulanLalu[] = $issueA1[0]->reference_number;

        $issueB1 = $issueSvc->issue($cabangB, $teknisiB, [
            ['item_id' => $modem->id, 'serial_numbers' => array_slice($snModemAwal, 10, 2)],
            ['item_id' => $kabel->id, 'qty' => 30],
        ], $owner);
        $refsBulanLalu[] = $issueB1[0]->reference_number;

        $lastMonthTimestamp = now()->subMonthNoOverflow()->startOfMonth()->addDays(9);
        InventoryTransaction::query()
            ->whereIn('reference_number', array_unique($refsBulanLalu))
            ->update(['created_at' => $lastMonthTimestamp, 'updated_at' => $lastMonthTimestamp]);

        // ════════════════════════════════════════════════════════════════
        // BULAN INI — timestamp asli (sekarang), gak di-backdate. Harga
        // kabel dinaikin (5000→6000) biar Stok Beli/Stok Akhir Pusat
        // kelihatan 2-slot Lama/Baru-nya. Ditutup dengan Adjustment RUSAK/
        // HILANG lintas ketiga tracking type biar tab Kerugian + Nilai
        // Rugi keisi semua.
        // ════════════════════════════════════════════════════════════════
        $snModemBaru = collect(range(31, 40))->map(fn ($n) => 'LAPDEMO-MODEM-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT))->all();

        $receiveSvc->receiveBatch($pusat, [
            ['item_id' => $modem->id, 'serial_numbers' => $snModemBaru, 'unit_price' => 310000],
            ['item_id' => $kabel->id, 'qty' => 400, 'unit_price' => 6000],
            ['item_id' => $roll->id, 'roll_count' => 3, 'vendor' => 'Vendor Demo', 'unit_price' => 2300],
        ], $owner, 'Demo Laporan Gudang — Barang Masuk Bulan Ini (harga kabel naik)');

        $rollCodesSisaPusat = InventoryRoll::where('item_id', $roll->id)->where('current_pop_id', $pusat->id)->orderBy('id')->pluck('roll_code')->all();

        $transferA2 = $transferSvc->createTransfer($pusat, $cabangA, [
            ['item_id' => $modem->id, 'serial_numbers' => array_merge(array_slice($snModemAwal, 18, 3), array_slice($snModemBaru, 0, 2))],
            ['item_id' => $kabel->id, 'qty' => 150],
            ['item_id' => $roll->id, 'roll_codes' => array_slice($rollCodesSisaPusat, 0, 2)],
        ], $owner);
        $transferSvc->receiveTransfer(
            $transferA2,
            array_merge(array_slice($snModemAwal, 18, 3), array_slice($snModemBaru, 0, 2)),
            [$kabel->id => 150],
            $owner,
            array_slice($rollCodesSisaPusat, 0, 2)
        );

        $transferB2 = $transferSvc->createTransfer($pusat, $cabangB, [
            ['item_id' => $modem->id, 'serial_numbers' => array_merge(array_slice($snModemAwal, 21, 2), array_slice($snModemBaru, 2, 1))],
            ['item_id' => $kabel->id, 'qty' => 100],
            ['item_id' => $roll->id, 'roll_codes' => array_slice($rollCodesSisaPusat, 2, 1)],
        ], $owner);
        $transferSvc->receiveTransfer(
            $transferB2,
            array_merge(array_slice($snModemAwal, 21, 2), array_slice($snModemBaru, 2, 1)),
            [$kabel->id => 100],
            $owner,
            array_slice($rollCodesSisaPusat, 2, 1)
        );

        // SN 0004/0005 (dari yang udah di Cabang A sejak bulan lalu) diambil
        // teknisi A bulan ini — SN 0006 SENGAJA disisain di gudang Cabang A
        // (bukan diambil) biar ada yang bisa di-Adjustment RUSAK di bawah.
        $issueSvc->issue($cabangA, $teknisiA, [
            ['item_id' => $modem->id, 'serial_numbers' => array_slice($snModemAwal, 3, 2)],
            ['item_id' => $kabel->id, 'qty' => 40],
        ], $owner);

        $issueSvc->issue($cabangB, $teknisiB, [
            ['item_id' => $modem->id, 'serial_numbers' => array_slice($snModemAwal, 12, 2)],
            ['item_id' => $kabel->id, 'qty' => 25],
        ], $owner);

        // ── RUSAK ────────────────────────────────────────────────────────
        // (a) QUANTITY di gudang Cabang A — reason literal 'damaged' (bukan
        //     teks bebas) biar kehitung Nilai Rugi di Laporan Gudang.
        $adjustSvc->adjustPopBalance($cabangA, $kabel->id, -15, 'damaged', $owner, null, 'Kabel digigit tikus di gudang cabang.');

        // (b) SERIALIZED — SN 0006 yang sengaja disisain di Cabang A.
        $serial0006 = InventorySerial::where('serial_number', 'LAPDEMO-MODEM-0006')->firstOrFail();
        $adjustSvc->adjustSerialStatus($serial0006, SerialStatus::DAMAGED, 'Jatuh saat proses instalasi, lensa optik retak.', $owner, null, 'warehouse/evidence/demo/damaged-modem-0006.jpg');

        // ── HILANG ───────────────────────────────────────────────────────
        // (c) Custody kabel Teknisi A — reason literal 'lost'.
        $custodyKabelA = TechnicianCustody::where('technician_id', $teknisiA->id)->where('item_id', $kabel->id)->latest('id')->firstOrFail();
        $adjustSvc->adjustCustody($custodyKabelA, -10, 'lost', $owner, 'Kabel hilang pas kerja lapangan, gak ketemu.', 'warehouse/evidence/demo/lost-kabel-teknisi-a.jpg');

        // (d) ROLL di gudang Cabang B.
        $rollDiCabangB = InventoryRoll::where('item_id', $roll->id)->where('current_pop_id', $cabangB->id)->first();
        if ($rollDiCabangB) {
            $adjustSvc->adjustRollStatus($rollDiCabangB, RollStatus::LOST, 'Roll gak ketemu pas stock opname mendadak.', $owner, null, 'warehouse/evidence/demo/lost-roll-cabang-b.jpg');
        }

        // ── QUARANTINE (BUKAN nilai rugi, cuma buat nunjukin kategori lain
        //    tetap tercatat & tampil di tab Kerugian, gak ikut ke Nilai Rugi).
        //    SN 0015 SENGAJA dipilih — bukan 0013/0014 (udah keburu di-issue
        //    ke teknisiB di atas, gak AVAILABLE lagi di gudang Cabang B).
        $serial0015 = InventorySerial::where('serial_number', 'LAPDEMO-MODEM-0015')->first();
        if ($serial0015 && $serial0015->status === SerialStatus::AVAILABLE) {
            $adjustSvc->adjustSerialStatus($serial0015, SerialStatus::QUARANTINE, 'Dicurigai cacat produksi, ditahan buat dicek ulang dulu.', $owner);
        }

        $this->command?->info('WarehouseReportDemoSeeder siap.');
        $this->command?->info("POP: {$pusat->name} / {$cabangA->name} / {$cabangB->name}.");
        $this->command?->info('Buka /warehouse/reports, pilih periode bulan ini, klik Download Excel — laporan ke-plot per POP lengkap (Stok Beli/Transfer/Terpakai/Rusak/Hilang/Stok Awal/Stok Akhir).');
        $this->command?->info('Login teknisi demo: teknisi.lapdemo.a@whusnet.test / teknisi.lapdemo.b@whusnet.test, password: password.');
    }
}
