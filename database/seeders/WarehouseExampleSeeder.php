<?php

namespace Database\Seeders;

use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Contoh data Gudang/Inventory (ADHOC-54) — jalankan MANUAL, jangan
 * didaftarkan ke `DatabaseSeeder::run()`. Ini demo buat lihat modul jalan
 * end-to-end (Receive → Transfer → Cabang terima → Issue → custody teknisi),
 * bukan data produksi.
 *
 * Jalankan: php artisan db:seed --class=WarehouseExampleSeeder
 *
 * Idempotent buat item/user/POP (updateOrCreate/firstOrCreate by kode/email
 * unik). Receive/Transfer/Issue-nya SENGAJA cuma jalan sekali — SN demo
 * (`ZTEDEMO0001` dst) py unique constraint asli (SN fisik gak boleh dobel),
 * jadi re-run bakal nabrak, bukan "ledger nambah baris baru" yang wajar.
 * Dicek lewat keberadaan SN demo, bukan flag terpisah.
 */
class WarehouseExampleSeeder extends Seeder
{
    public function run(): void
    {
        $catAktif = ItemCategory::where('code', 'media_converter')->first();
        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->first();

        if (! $catAktif || ! $catPasif) {
            $this->command?->error('ItemCategoryFeatureSeeder belum jalan — kategori media_converter/kabel_dropcore gak ada. Jalankan php artisan db:seed dulu.');

            return;
        }

        $owner = User::whereHas('role', fn ($q) => $q->where('code', 'owner'))->first();

        if (! $owner) {
            $this->command?->error('Belum ada user role owner — jalankan php artisan db:seed dulu (UserSeeder).');

            return;
        }

        // ── 1. Master Barang contoh ─────────────────────────────────────
        $modem = Item::updateOrCreate(
            ['code' => 'ZTE-F609-DEMO'],
            [
                'name' => 'Modem ZTE F609 (Contoh)',
                'item_category_id' => $catAktif->id,
                'unit' => 'pcs',
                'tracking_type' => 'serialized',
                'ownership_mode' => 'installable',
                'is_active' => true,
            ]
        );

        $kabel = Item::updateOrCreate(
            ['code' => 'DC-1C-DEMO'],
            [
                'name' => 'Kabel Dropcore 1 Core (Contoh)',
                'item_category_id' => $catPasif->id,
                'unit' => 'meter',
                'tracking_type' => 'quantity',
                'ownership_mode' => 'installable',
                'is_active' => true,
            ]
        );

        // ── 2. Gudang Pusat & Cabang — reuse yang udah ada, jangan bikin
        //      dobel kalau POP asli udah pernah dibuat lewat Master Data.
        $pusat = Pop::where('type', 'pusat')->first();
        if (! $pusat) {
            $pusat = Pop::create([
                'code' => 'DEMO-PUSAT', 'pop_code' => 'DPS', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
                'name' => 'Gudang Pusat (Contoh)', 'type' => 'pusat', 'status' => 'active',
            ]);
        }

        $cabang = Pop::where('type', 'cabang')->first();
        if (! $cabang) {
            $cabang = Pop::create([
                'code' => 'DEMO-CABANG', 'pop_code' => 'DCB', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
                'name' => 'Cabang Ponorogo (Contoh)', 'type' => 'cabang', 'status' => 'active',
            ]);
        }

        // ── 3. Teknisi contoh, scope ke Cabang di atas ──────────────────
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $teknisi = User::firstOrCreate(
            ['email' => 'teknisi.demo@whusnet.test'],
            [
                'name' => 'Teknisi Demo Gudang',
                'phone' => '081200000000',
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
        UserRoleScopeTarget::firstOrCreate(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);

        // ── 4. Barang Masuk — 10 SN modem + 200m kabel ke Pusat ─────────
        if (InventorySerial::where('serial_number', 'like', 'ZTEDEMO%')->exists()) {
            $this->command?->info('Contoh Gudang udah pernah dijalankan sebelumnya — skip Receive/Transfer/Issue (SN demo gak boleh dobel). Item/POP/Teknisi Demo tetap disinkron di atas.');

            return;
        }

        $serials = collect(range(1, 10))->map(fn ($n) => 'ZTEDEMO'.str_pad((string) $n, 4, '0', STR_PAD_LEFT))->all();

        $receiveRef = app(InventoryReceiveService::class)->receiveBatch($pusat, [
            ['item_id' => $modem->id, 'serial_numbers' => $serials, 'unit_price' => 350000],
            ['item_id' => $kabel->id, 'qty' => 200, 'unit_price' => 4500],
        ], $owner, 'Contoh data Gudang (WarehouseExampleSeeder)');

        // ── 5. Transfer separuh ke Cabang, langsung dikonfirmasi terima ─
        $transferSvc = app(InventoryTransferService::class);
        $transferredSerials = array_slice($serials, 0, 5);

        $transfer = $transferSvc->createTransfer($pusat, $cabang, [
            ['item_id' => $modem->id, 'serial_numbers' => $transferredSerials],
            ['item_id' => $kabel->id, 'qty' => 100],
        ], $owner);

        $transferSvc->receiveTransfer($transfer, $transferredSerials, [$kabel->id => 100], $owner);

        // ── 6. Issue sebagian ke Teknisi Demo — ini yang nongol di dropdown
        //      SN Laporan Pemasangan begitu Teknisi Demo login.
        app(InventoryIssueService::class)->issue($cabang, $teknisi, [
            ['item_id' => $modem->id, 'serial_numbers' => array_slice($transferredSerials, 0, 2)],
            ['item_id' => $kabel->id, 'qty' => 20],
        ], $owner);

        $this->command?->info("Contoh Gudang siap — Barang Masuk {$receiveRef}, Transfer {$transfer->reference_number}.");
        $this->command?->info("Teknisi Demo pegang 2 SN ({$transferredSerials[0]}, {$transferredSerials[1]}) + 20m kabel — login teknisi.demo@whusnet.test / password buat lihat dropdown SN di Laporan Pemasangan.");
        $this->command?->info("Sisa 3 SN + 100m kabel masih di Cabang '{$cabang->name}' (belum di-issue) — 5 SN + 100m kabel masih di Pusat (belum ditransfer) — buat coba /warehouse/issues/create & /warehouse/transfers/create manual.");
    }
}
