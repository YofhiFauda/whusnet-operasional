<?php

namespace Database\Seeders;

use App\Enums\CReqCategory;
use App\Enums\CReqVerificationStatus;
use App\Enums\DeviceOwnership;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskCreqDetail;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryService;
use App\Services\InventoryTransferService;
use App\Services\NumberSequenceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data contoh untuk laporan Rekonsiliasi Modem Terjual
 * (`reports.modem-sale-reconciliation.index`) — SN "Beli dari Gudang" yang
 * belum tertaut ke baris tagihan mana pun.
 *
 * SN dijalankan lewat Service asli (Receive → Transfer → Issue → install →
 * recordSerialSale), BUKAN insert langsung ke `inventory_serials`, supaya
 * ledger & saldo stok gudang tetap konsisten dengan laporan Gudang lain.
 * Yang ditulis langsung cuma kerangka FopTask/Task/TaskCreqDetail — laporan
 * ini hanya membaca jalur & status verifikasinya, bukan riwayat task.
 *
 * Sengaja TIDAK ada baris tagihan (`invoice_items`/`task_billing_items`) —
 * itu inti laporannya. Hasil per status (lihat ModemSaleReconciliationService):
 *   - Menunggu BD            : 2 (Pemasangan, pelanggan menunggu verifikasi BD)
 *   - Menunggu verifikasi CS : 2 (Pemasangan, pelanggan di verification_admin)
 *   - Menunggu approve CS    : 2 (C-REQ Tambah Modem, verifikasi CS pending)
 *   - Anomali                : 3 (2 Pemasangan pelanggan sudah aktif,
 *                                 1 C-REQ sudah diverifikasi) — modem keluar
 *                                 gudang tapi tak pernah ditagih
 *
 * Jalankan MANUAL, jangan didaftarkan ke `DatabaseSeeder::run()`:
 *   php artisan db:seed --class=ModemSaleReconciliationDemoSeeder
 *
 * Idempotent guard: SN `REKDEMO-ONT-%` — kalau sudah ada, di-skip.
 */
class ModemSaleReconciliationDemoSeeder extends Seeder
{
    private const SN_PREFIX = 'REKDEMO-ONT-';

    /**
     * [jalur, status pelanggan, status verifikasi C-REQ, harga, hari lalu]
     *
     * @var list<array{0: string, 1: WorkflowTransition, 2: CReqVerificationStatus|null, 3: int, 4: int}>
     */
    private const SCENARIOS = [
        ['psb', WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION, null, 450000, 1],
        ['psb', WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION, null, 450000, 2],
        ['psb', WorkflowTransition::VERIFICATION_ADMIN, null, 450000, 1],
        ['psb', WorkflowTransition::VERIFICATION_ADMIN, null, 500000, 3],
        ['creq', WorkflowTransition::ACTIVE, CReqVerificationStatus::PENDING, 350000, 2],
        ['creq', WorkflowTransition::ACTIVE, CReqVerificationStatus::PENDING, 350000, 4],
        ['psb', WorkflowTransition::ACTIVE, null, 450000, 12],
        ['psb', WorkflowTransition::ACTIVE, null, 500000, 20],
        ['creq', WorkflowTransition::ACTIVE, CReqVerificationStatus::VERIFIED, 350000, 9],
    ];

    public function run(): void
    {
        $owner = User::whereHas('role', fn ($q) => $q->where('code', 'owner'))->first();
        if (! $owner) {
            $this->command?->error('Belum ada user role owner — jalankan php artisan db:seed dulu.');

            return;
        }

        if (InventorySerial::where('serial_number', 'like', self::SN_PREFIX.'%')->exists()) {
            $this->command?->info('ModemSaleReconciliationDemoSeeder sudah pernah dijalankan (SN '.self::SN_PREFIX.'% ditemukan) — skip.');

            return;
        }

        $category = ItemCategory::where('code', 'media_converter')->first();
        $teknisiRole = Role::where('code', 'teknisi')->first();
        if (! $category || ! $teknisiRole) {
            $this->command?->error('Kategori media_converter / role teknisi belum ada — jalankan php artisan db:seed dulu.');

            return;
        }

        $modem = Item::updateOrCreate(
            ['code' => 'ONT-REKDEMO'],
            ['name' => 'ONT ZTE F670L (Demo Rekonsiliasi)', 'item_category_id' => $category->id, 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable', 'is_active' => true]
        );

        $pusat = Pop::firstOrCreate(
            ['code' => 'REKDEMO-PUSAT'],
            ['pop_code' => 'RKP', 'registration_prefix' => 'R', 'cid_prefix' => 'R', 'name' => 'Gudang Pusat (Demo Rekonsiliasi)', 'type' => 'pusat', 'status' => 'active']
        );
        $cabangs = [
            Pop::firstOrCreate(
                ['code' => 'REKDEMO-CABANG-A'],
                ['pop_code' => 'RKA', 'registration_prefix' => 'R', 'cid_prefix' => 'R', 'name' => 'Cabang Jetis (Demo Rekonsiliasi)', 'type' => 'cabang', 'status' => 'active']
            ),
            Pop::firstOrCreate(
                ['code' => 'REKDEMO-CABANG-B'],
                ['pop_code' => 'RKB', 'registration_prefix' => 'R', 'cid_prefix' => 'R', 'name' => 'Cabang Sandya (Demo Rekonsiliasi)', 'type' => 'cabang', 'status' => 'active']
            ),
        ];

        $teknisis = [];
        foreach ($cabangs as $i => $cabang) {
            $teknisi = User::firstOrCreate(
                ['email' => 'teknisi.rekdemo.'.($i + 1).'@whusnet.test'],
                ['name' => 'Teknisi Demo Rekonsiliasi '.($i + 1), 'phone' => '08130000000'.($i + 1), 'password' => Hash::make('password'), 'status' => 'active', 'role_id' => $teknisiRole->id, 'email_verified_at' => now()]
            );
            $scope = UserRoleScope::firstOrCreate(['user_id' => $teknisi->id, 'role_id' => $teknisiRole->id], ['scope_type' => 'selected_pop']);
            UserRoleScopeTarget::firstOrCreate(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
            $teknisis[$i] = $teknisi;
        }

        // ── Stok masuk lewat alur gudang asli: Pusat → Cabang → teknisi ─────
        $serialNumbers = collect(range(1, count(self::SCENARIOS)))
            ->map(fn ($n) => self::SN_PREFIX.str_pad((string) $n, 4, '0', STR_PAD_LEFT))
            ->all();

        app(InventoryReceiveService::class)->receiveBatch($pusat, [
            ['item_id' => $modem->id, 'serial_numbers' => $serialNumbers, 'unit_price' => 320000],
        ], $owner, 'Demo Rekonsiliasi Modem Terjual — Barang Masuk');

        $snPerCabang = [[], []];
        foreach ($serialNumbers as $idx => $sn) {
            $snPerCabang[$idx % 2][] = $sn;
        }

        $transferSvc = app(InventoryTransferService::class);
        $issueSvc = app(InventoryIssueService::class);
        foreach ($cabangs as $i => $cabang) {
            $transfer = $transferSvc->createTransfer($pusat, $cabang, [
                ['item_id' => $modem->id, 'serial_numbers' => $snPerCabang[$i]],
            ], $owner);
            $transferSvc->receiveTransfer($transfer, $snPerCabang[$i], [], $owner);
            $issueSvc->issue($cabang, $teknisis[$i], [
                ['item_id' => $modem->id, 'serial_numbers' => $snPerCabang[$i]],
            ], $owner);
        }

        // ── Pasang + jual tiap SN sesuai skenario ──────────────────────────
        $inventory = app(InventoryService::class);
        $numbers = app(NumberSequenceService::class);

        foreach (self::SCENARIOS as $idx => [$jalur, $customerStatus, $verification, $price, $daysAgo]) {
            $cabangIndex = $idx % 2;
            $cabang = $cabangs[$cabangIndex];
            $teknisi = $teknisis[$cabangIndex];
            $isCreq = $jalur === 'creq';

            $customer = Customer::factory()->create([
                'pop_id' => $cabang->id,
                'full_name' => 'Pelanggan Demo Rekonsiliasi '.($idx + 1),
                'status' => $customerStatus->value,
            ]);

            $taskType = $isCreq ? TaskType::CREQ : TaskType::PEMASANGAN;

            $task = Task::create([
                'task_number' => $numbers->taskNumber(),
                'customer_id' => $customer->id,
                'pop_id' => $cabang->id,
                'task_type' => $taskType->value,
                'title' => $isCreq ? 'Tambah Modem' : 'Pemasangan Baru',
                'status' => TaskStatus::SELESAI->value,
                'completed_at' => now()->subDays($daysAgo),
            ]);

            $fopTask = FopTask::create([
                'task_number' => $numbers->fopTaskNumber(),
                'category' => $taskType->value,
                'tugas' => ($customer->customer_code ?? 'DEMO').'_'.$customer->full_name,
                'issue' => $isCreq ? 'Tambah Modem' : 'Pemasangan Baru',
                'pop_id' => $cabang->id,
                'customer_id' => $customer->id,
                'status' => TaskStatus::SELESAI->value,
                'task_date' => now()->subDays($daysAgo)->toDateString(),
                'task_id' => $task->id,
            ]);

            if ($isCreq) {
                TaskCreqDetail::create([
                    'task_id' => $task->id,
                    'category' => CReqCategory::TAMBAH_MODEM->value,
                    'is_billable' => true,
                    'billing_note' => 'Modem tambahan dibeli dari gudang (demo).',
                    'verification_status' => $verification->value,
                    'verified_by' => $verification === CReqVerificationStatus::VERIFIED ? $owner->id : null,
                    'verified_at' => $verification === CReqVerificationStatus::VERIFIED ? now()->subDays($daysAgo - 1) : null,
                ]);
            }

            $serial = InventorySerial::where('serial_number', $serialNumbers[$idx])->firstOrFail();

            $inventory->installSerial(
                $serial,
                $customer,
                $fopTask,
                [$teknisi],
                $owner,
                returnExistingSerial: ! $isCreq,
                ownership: DeviceOwnership::CUSTOMER,
            );

            $sale = $inventory->recordSerialSale($serial->fresh(), $customer, $fopTask, $price, $owner);

            // Tanggal Jual bervariasi supaya urutan & umur anomali kelihatan.
            $soldAt = now()->subDays($daysAgo)->setTime(10, 0)->addMinutes($idx * 17);
            InventorySerialSale::whereKey($sale->id)->update(['created_at' => $soldAt, 'updated_at' => $soldAt]);
        }

        $this->command?->info('Selesai: '.count(self::SCENARIOS).' SN terjual tanpa tagihan — buka /reports/modem-sale-reconciliation.');
    }
}
