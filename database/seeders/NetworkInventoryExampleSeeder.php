<?php

namespace Database\Seeders;

use App\Enums\RollStatus;
use App\Models\FopTask;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Contoh data Inventaris Infrastruktur (ADHOC-167) — jalankan MANUAL, jangan
 * didaftarkan ke `DatabaseSeeder::run()`. Demo buat lihat halaman
 * `/network/inventaris` terisi data nyata (bukan kosong), pola sama
 * `WarehouseExampleSeeder`.
 *
 * Jalankan: php artisan db:seed --class=NetworkInventoryExampleSeeder
 *
 * Barangnya REUSE katalog asli dari `ItemSeeder` (SPL-1X8, ODP-8, ODC-24,
 * KBL-FEEDER-12C) — bukan item demo palsu, supaya halaman kelihatan seperti
 * data produksi. Satu item TAMBAHAN (`CLS-FO-DOME`, closure fiber optik)
 * ditanam di sini karena katalog asli belum punya contoh barang QUANTITY
 * ber-`ownership_mode=infrastructure_asset` — kalau nanti ditambahkan ke
 * `ItemSeeder`, baris `updateOrCreate` di sini tetap aman (idempotent by
 * code, cuma menyentuh baris yang sama).
 *
 * Alur ikut persis jalur produksi (`InventoryService::deploySerial()`/
 * `deployQuantity()`/`deployRoll()`), bukan nulis `inventory_serials`/
 * `infrastructure_balances` langsung — supaya ledger `DEPLOY` yang dibaca
 * `NetworkInventoryService` (halaman Inventaris Infrastruktur) persis sama
 * bentuknya dengan hasil laporan O-REQ/INFR REQ teknisi sungguhan.
 *
 * Idempotent buat Pop/Node/Item/User/custody (firstOrCreate/updateOrCreate
 * by kode unik). Deploy-nya SENGAJA cuma sekali — SN demo (`SPL-SMN-%`) py
 * unique constraint asli, re-run bakal nabrak, bukan "ledger nambah baris
 * baru" yang wajar. Dicek lewat keberadaan SN demo.
 */
class NetworkInventoryExampleSeeder extends Seeder
{
    public function run(): void
    {
        $odcType = NetworkNodeType::where('code', 'odc')->first();
        $odpType = NetworkNodeType::where('code', 'odp')->first();

        if (! $odcType || ! $odpType) {
            $this->command?->error('NetworkNodeTypeSeeder belum jalan — tipe node odc/odp gak ada. Jalankan php artisan db:seed dulu.');

            return;
        }

        $splitter = Item::where('code', 'SPL-1X8')->first();
        $odpBox = Item::where('code', 'ODP-8')->first();
        $feeder = Item::where('code', 'KBL-FEEDER-12C')->first();

        if (! $splitter || ! $odpBox || ! $feeder) {
            $this->command?->error('ItemSeeder belum jalan — barang SPL-1X8/ODP-8/KBL-FEEDER-12C gak ada. Jalankan php artisan db:seed dulu.');

            return;
        }

        // ── 1. Barang QUANTITY tambahan — closure fiber optik, satu-satunya
        //      contoh ownership_mode=infrastructure_asset bertipe QUANTITY di
        //      katalog saat ini (lihat docblock kelas).
        $closureCategory = $splitter->item_category_id;
        $closure = Item::updateOrCreate(
            ['code' => 'CLS-FO-DOME'],
            [
                'name' => 'Closure Fiber Optik Dome 12 Core',
                'item_category_id' => $closureCategory,
                'unit' => 'pcs',
                'tracking_type' => 'quantity',
                'ownership_mode' => 'infrastructure_asset',
                'is_active' => true,
            ]
        );

        // ── 2. Cabang — reuse Cabang asli kalau sudah ada (sama pola
        //      `WarehouseExampleSeeder`), biar gak numpuk POP demo.
        $cabang = Pop::where('type', 'cabang')->first();
        if (! $cabang) {
            $cabang = Pop::create([
                'code' => 'DEMO-SIMAN', 'pop_code' => 'SMN', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
                'name' => 'Cabang Siman (Contoh)', 'type' => 'cabang', 'status' => 'active',
            ]);
        }

        // ── 3. Teknisi contoh, scope ke Cabang di atas ──────────────────
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $teknisi = User::firstOrCreate(
            ['email' => 'teknisi.inventaris.demo@whusnet.test'],
            [
                'name' => 'Edy Prasetyo',
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
        UserRoleScopeTarget::firstOrCreate(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);

        $actor = User::whereHas('role', fn ($q) => $q->where('code', 'owner'))->first();
        if (! $actor) {
            $this->command?->error('Belum ada user role owner — jalankan php artisan db:seed dulu (UserSeeder).');

            return;
        }

        // ── 4. Pohon node — ODC dengan dua ODP anak, nama mengikuti pola
        //      kode titik jaringan ISP riil (ODC-{cabang}-{urut}).
        $odc = NetworkNode::firstOrCreate(
            ['code' => 'ODC-SMN-01'],
            [
                'anchor_pop_id' => $cabang->id,
                'parent_id' => null,
                'node_type_id' => $odcType->id,
                'name' => 'ODC Siman 01',
                'status' => 'active',
            ]
        );
        if ($odc->ports()->count() === 0) {
            for ($number = 1; $number <= 24; $number++) {
                $odc->ports()->create(['number' => $number, 'is_used' => $number <= 2]);
            }
        }

        $odpA = NetworkNode::firstOrCreate(
            ['code' => 'ODP-SMN-01-A'],
            [
                'anchor_pop_id' => null,
                'parent_id' => $odc->id,
                'node_type_id' => $odpType->id,
                'name' => 'ODP Siman 01-A',
                'status' => 'active',
            ]
        );
        if ($odpA->ports()->count() === 0) {
            for ($number = 1; $number <= 8; $number++) {
                $odpA->ports()->create(['number' => $number, 'is_used' => $number <= 3]);
            }
        }

        $odpB = NetworkNode::firstOrCreate(
            ['code' => 'ODP-SMN-01-B'],
            [
                'anchor_pop_id' => null,
                'parent_id' => $odc->id,
                'node_type_id' => $odpType->id,
                'name' => 'ODP Siman 01-B',
                'status' => 'active',
            ]
        );
        if ($odpB->ports()->count() === 0) {
            for ($number = 1; $number <= 8; $number++) {
                $odpB->ports()->create(['number' => $number, 'is_used' => $number <= 1]);
            }
        }

        // ── 5. Task INFR REQ contoh — rujukan ledger DEPLOY di bawah ────
        $fopTask = FopTask::firstOrCreate(
            ['task_number' => 'TFOP-DEMO-SMN01'],
            [
                'task_date' => now()->subDays(3),
                'category' => 'INFR REQ',
                'tugas' => 'C1XDEMO0001_Pasang Splitter & Kabel Feeder ODC Siman 01',
                'pop_id' => $cabang->id,
                'issue' => 'Pemasangan infrastruktur baru ODC Siman 01 — splitter, ODP, dan kabel feeder.',
                'status' => 'draft',
                'priority' => 'Medium',
            ]
        );

        // Idempotency guard: SN demo gak boleh dobel (unique constraint asli).
        if (InventorySerial::where('serial_number', 'like', 'SPL-SMN-%')->exists()) {
            $this->command?->info('Contoh Inventaris Infrastruktur udah pernah dijalankan sebelumnya — skip Deploy (SN demo gak boleh dobel). Node/Item/Teknisi tetap disinkron di atas.');

            return;
        }

        $service = app(InventoryService::class);

        // ── 6. Splitter 1:8 — satu di ODC (induk), satu di ODP-A (turunan)
        $splitterOdc = InventorySerial::create([
            'item_id' => $splitter->id,
            'serial_number' => 'SPL-SMN-0001',
            'status' => 'issued',
            'current_technician_id' => $teknisi->id,
            'issued_from_pop_id' => $cabang->id,
        ]);
        $service->deploySerial($splitterOdc, $odc, $fopTask, [$teknisi], $actor);

        $splitterOdpA = InventorySerial::create([
            'item_id' => $splitter->id,
            'serial_number' => 'SPL-SMN-0002',
            'status' => 'issued',
            'current_technician_id' => $teknisi->id,
            'issued_from_pop_id' => $cabang->id,
        ]);
        $service->deploySerial($splitterOdpA, $odpA, $fopTask, [$teknisi], $actor);

        // ── 7. Box ODP-8 fisik terpasang di titik ODP-A ─────────────────
        $boxOdpA = InventorySerial::create([
            'item_id' => $odpBox->id,
            'serial_number' => 'ODP8-SMN-0001',
            'status' => 'issued',
            'current_technician_id' => $teknisi->id,
            'issued_from_pop_id' => $cabang->id,
        ]);
        $service->deploySerial($boxOdpA, $odpA, $fopTask, [$teknisi], $actor);

        // ── 8. Kabel Feeder 12 Core — 350 meter dipotong dari roll, dipasang
        //      netap dari ODC ke arah OLT.
        $roll = InventoryRoll::create([
            'item_id' => $feeder->id,
            'roll_code' => 'ROLL-SMN-0001',
            'status' => RollStatus::ISSUED,
            'current_technician_id' => $teknisi->id,
            'issued_from_pop_id' => $cabang->id,
            'length_total' => 1000,
            'length_remaining' => 1000,
            'unit_price_snapshot' => 8500,
        ]);
        $service->deployRoll($roll, 350, $odc, [$teknisi], $fopTask, $actor);

        // ── 9. Closure fiber optik — 3 pcs dipasang netap di ODC ────────
        TechnicianCustody::create([
            'technician_id' => $teknisi->id,
            'issued_from_pop_id' => $cabang->id,
            'item_id' => $closure->id,
            'qty_remaining' => 5,
            'unit_price_snapshot' => 75000,
            'status' => 'issued',
            'issued_at' => now()->subDays(3),
        ]);
        $service->deployQuantity([$teknisi], $closure, 3, $odc, $fopTask, $actor);

        $this->command?->info('Contoh Inventaris Infrastruktur siap — buka /network/inventaris.');
        $this->command?->info('Pohon: Cabang '.$cabang->name.' → ODC Siman 01 → ODP Siman 01-A/B.');
        $this->command?->info('Terpasang: 2 splitter (SPL-SMN-0001/0002), 1 box ODP-8 (ODP8-SMN-0001), 350m kabel feeder (ROLL-SMN-0001), 3 pcs closure.');
    }
}
