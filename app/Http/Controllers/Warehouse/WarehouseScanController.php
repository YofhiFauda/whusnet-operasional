<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\EquipmentClass;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Scan Barang (2026-09-07, rancangan-layout.md solusi #4 — "mode scan-first")
 * — titik masuk KEBALIK dari alur biasa. Normalnya: pilih tujuan dulu (buka
 * halaman Transfer/Issue/dst), baru scan buat isi form. Di sini: scan dulu,
 * sistem yang nentuin "SN ini statusnya apa sekarang, aksi apa yang masuk
 * akal" — baru staf pilih salah satu, langsung ke halaman tujuan yang UDAH
 * keisi (reuse prefill `item_id`/`lot_no`/`serial` yang udah ada di
 * `<x-inventory-line-rows>`, bukan mekanisme baru).
 *
 * Cuma ngurusin barang SERIALIZED (py Serial Number) — sama scope-nya kayak
 * `barcode-scan.js` sendiri ("BUKAN QR, barcode 1D yang nempel di kemasan
 * modem/ONT/router"). Barang QUANTITY/BATCH gak punya identitas per-unit yang
 * bisa di-scan buat "lookup status", jadi tetap lewat alur biasa (Kelola Stok).
 *
 * SENGAJA gak nulis apa pun ke DB — murni lookup + kasih daftar link ke
 * halaman mutasi ASLI (Transfer/Issue/Adjustment/Reassign), yang masing-masing
 * tetap py validasi & guard sendiri sepenuhnya. Kalau lookup nyasar kasih
 * link yang salah, worst case staf cuma nyasar ke halaman gak nyambung — gak
 * ada mutasi yang kejadian dari sini sendiri.
 */
class WarehouseScanController extends Controller
{
    /**
     * Data buat "Kirim Cepat" inline (2026-09-08, laporan user: "kalau
     * barangnya AVAILABLE, bisa gak langsung Transfer/Serah dari Scan page
     * tanpa pindah halaman?") — SN AVAILABLE di Pusat/Cabang cuma butuh SATU
     * pilihan tambahan (Gudang Tujuan / Teknisi Penerima) buat lengkap jadi
     * submit. Dua daftar ini kecil & gak tergantung SN mana yang di-scan
     * (Cabang tujuan/Teknisi sama buat SN manapun), jadi dimuat SEKALI di
     * sini — bukan lewat endpoint terpisah per-scan.
     *
     * Form "Kirim Cepat" POST LANGSUNG ke `warehouse.transfers.store` /
     * `warehouse.issues.store` yang SAMA dipakai halaman create penuh —
     * BUKAN endpoint baru, BUKAN logic bisnis baru. Ini tetap murni UI
     * shortcut, validasi & guard tetap 100% di controller aslinya (prinsip
     * "SENGAJA gak nulis apa pun ke DB" di docblock kelas TETAP berlaku buat
     * controller ini sendiri — yang nulis DB tetap controller lain).
     *
     * Data buat "Mode Batch" (2026-09-08, laporan user: "bisa gak Receive/
     * Transfer/Issue mode batch full-scan DI Scan Barang") — 3 sub-mode POST
     * ke rute STORE ASLI yang SAMA (`warehouse.receive.store` /
     * `warehouse.transfers.store` / `warehouse.issues.store`), bukan
     * endpoint baru. `$pusatPops`/`$scanCategories`/`$scanItems` dipakai
     * sub-mode Receive (SN yang di-scan di situ BARU, sistem gak bisa
     * nebak barangnya — staf wajib pilih Kategori→Barang dulu, sama pola
     * yang DULU ada di tab "Batch Kategori" Receive sebelum dihapus
     * 2026-09-08 — bedanya sekarang alamatnya di sini, bukan numpang lagi
     * di Receive). Transfer/Issue GAK butuh data serupa — SN yang di-scan
     * di situ SN yang UDAH ADA, item-nya otomatis ketauan dari hasil
     * `available-stock` (endpoint yang UDAH ADA di masing-masing controller).
     */
    public function index(EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $pusatPops = Pop::query()
            ->where('type', 'pusat')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')
            ->get();

        $cabangPops = Pop::query()
            ->where('type', 'cabang')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')
            ->get();

        // Sama query `WarehouseIssueController::create()` — lihat docblock
        // di sana soal kenapa belum discope per-cabang.
        $technicians = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('code', ['teknisi', 'fop']))
            ->orderBy('name')
            ->get();

        // Sama query yang dulu ada di `WarehouseReceiveController::create()`
        // (dicabut bareng penghapusan tab Scan Cepat Receive) — kategori &
        // item difilter equipment_class=AKTIF, SEMUA perangkat aktif yang
        // bernomor seri (modem/ONT, router, OLT module, AP Wireless, SFP,
        // dst), bukan cuma modem.
        $scanCategories = ItemCategory::active()
            ->where('equipment_class', EquipmentClass::AKTIF)
            ->ordered()
            ->get();

        $scanItems = Item::active()
            ->where('tracking_type', TrackingType::SERIALIZED)
            ->whereHas('category', fn ($q) => $q->where('equipment_class', EquipmentClass::AKTIF))
            ->with('category')
            ->orderBy('name')
            ->get();

        return view('warehouse.scan.index', compact('pusatPops', 'cabangPops', 'technicians', 'scanCategories', 'scanItems'));
    }

    public function lookup(Request $request, EffectiveAccessService $access): JsonResponse
    {
        $validated = $request->validate(['sn' => 'required|string|max:100']);
        $sn = trim($validated['sn']);
        $user = auth()->user();

        $serial = InventorySerial::query()
            ->where('serial_number', $sn)
            ->with(['item', 'currentPop', 'issuedFromPop', 'currentTechnician'])
            ->first();

        if (! $serial) {
            // Fallback roll kabel (App\Enums\TrackingType::ROLL) — namespace
            // beda (roll_code vs serial_number), gak akan pernah collide,
            // aman dicoba sesudah lookup SN gagal daripada bikin endpoint
            // scan terpisah.
            $roll = InventoryRoll::query()
                ->where('roll_code', $sn)
                ->with(['item', 'currentPop', 'issuedFromPop', 'currentTechnician'])
                ->first();

            if ($roll) {
                return $this->lookupRoll($roll, $access, $user);
            }

            return response()->json([
                'found' => false,
                'sn' => $sn,
                'message' => 'SN/Roll ini belum tercatat di sistem — kemungkinan barang baru dari pengadaan.',
                'actions' => $user->hasPermission('warehouse_transfer.create') ? [
                    ['label' => 'Catat sebagai Barang Masuk', 'url' => route('warehouse.receive.create', ['sn' => $sn]), 'style' => 'primary'],
                ] : [],
            ]);
        }

        // Scope: SN di luar jangkauan POP aktor cuma dikasih tau "di luar
        // jangkauan", TANPA detail lokasi/status — sama semangatnya kayak
        // `AuthorizesWarehousePop`, tapi soft (gak abort 403) karena nge-scan
        // barcode fisik itu sendiri bukan percobaan akses, cuma staf gak
        // sengaja nge-scan barang yang emang bukan urusannya.
        $relevantPopId = $serial->current_pop_id ?? $serial->issued_from_pop_id;
        $hasAllAccess = $access->hasAllPopAccess($user);
        $inScope = $hasAllAccess || $relevantPopId === null || in_array($relevantPopId, $access->getAllowedPopIds($user), true);

        if (! $inScope) {
            return response()->json([
                'found' => true,
                'sn' => $sn,
                'in_scope' => false,
                'message' => 'SN ditemukan, tapi di luar jangkauan gudang yang Anda kelola.',
                'actions' => [],
            ]);
        }

        [$message, $actions] = $this->resolveActions($serial, $user);

        return response()->json([
            'found' => true,
            'sn' => $sn,
            'in_scope' => true,
            'item_name' => $serial->item->name ?? '(barang dihapus)',
            'status_label' => $serial->status->label(),
            // Badge Kondisi (analisa-gap-kondisi-barang.md poin 8) — staf
            // scan SN bekas belum-dicek perlu tahu SEBELUM ngarahin ke
            // Issue, yang bakal ditolak Service (`isClearedForIssue()`).
            'condition_label' => match (true) {
                ($serial->condition?->value ?? 'new') === 'new' => 'Baru',
                $serial->condition?->value === 'used_damaged' => 'Bekas — Rusak',
                $serial->condition_checked_at !== null => 'Bekas — Sudah Dicek',
                default => 'Bekas — Belum Dicek',
            },
            'location' => $this->resolveLocationLabel($serial),
            'message' => $message,
            'actions' => $actions,
        ]);
    }

    /**
     * Lookup roll kabel — shape JSON SAMA (`found/in_scope/message/actions`)
     * biar JS Scan Barang gak perlu cabang render baru. Sejalan
     * `resolveActions()` (SN) — Transfer/Issue/Reassign/Adjust roll semua
     * udah ada (fase 7 kabel-per-roll).
     */
    private function lookupRoll(InventoryRoll $roll, EffectiveAccessService $access, User $user): JsonResponse
    {
        $relevantPopId = $roll->current_pop_id ?? $roll->issued_from_pop_id;
        $hasAllAccess = $access->hasAllPopAccess($user);
        $inScope = $hasAllAccess || $relevantPopId === null || in_array($relevantPopId, $access->getAllowedPopIds($user), true);

        if (! $inScope) {
            return response()->json([
                'found' => true,
                'sn' => $roll->roll_code,
                'in_scope' => false,
                'message' => 'Roll ditemukan, tapi di luar jangkauan gudang yang Anda kelola.',
                'actions' => [],
            ]);
        }

        [$message, $actions] = $this->resolveRollActions($roll, $user);

        return response()->json([
            'found' => true,
            'sn' => $roll->roll_code,
            'in_scope' => true,
            'item_name' => $roll->item->name ?? '(barang dihapus)',
            'status_label' => $roll->status->label(),
            'location' => $this->resolveRollLocationLabel($roll),
            'message' => $message,
            'actions' => $actions,
        ]);
    }

    private function formatMeter(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }

    /**
     * @return array{0: string, 1: list<array{label: string, url: string, style: string}>}
     */
    private function resolveActions(InventorySerial $serial, User $user): array
    {
        $actions = [];

        $canTransfer = $user->hasPermission('warehouse_transfer.create');
        $canViewTransfer = $user->hasPermission('warehouse_transfer.view');
        $canIssue = $user->hasPermission('warehouse_issue.create');
        $canReassign = $user->hasPermission('warehouse_reassign.create');
        $canAdjust = $user->hasPermission('warehouse_adjustment.create');
        $canTrace = $user->hasPermission('warehouse_traceability.view');

        switch ($serial->status) {
            case SerialStatus::AVAILABLE:
            case SerialStatus::RECEIVED:
                $pop = $serial->currentPop;
                $message = $pop
                    ? "Tersedia di {$pop->name}, siap dikirim atau diserahkan."
                    : 'Tersedia di gudang, siap dikirim atau diserahkan.';

                if ($pop?->type === 'pusat' && $canTransfer) {
                    // `quick` = data buat form "Kirim Cepat" inline di Scan
                    // page sendiri (lihat docblock `index()`) — `url` TETAP
                    // disertakan sebagai jalan keluar "buka halaman penuh"
                    // (kalau staf mau sekalian nambah barang lain ke transfer
                    // yang sama, gak cuma SN ini doang).
                    $actions[] = [
                        'label' => 'Kirim Transfer ke Cabang',
                        'url' => route('warehouse.transfers.create', ['pop_id' => $pop->id, 'item_id' => $serial->item_id, 'serial' => $serial->serial_number]),
                        'style' => 'primary',
                        'quick' => 'transfer',
                        'from_pop_id' => $pop->id,
                        'item_id' => $serial->item_id,
                        'serial_number' => $serial->serial_number,
                    ];
                }
                if ($pop?->type === 'cabang' && $canIssue) {
                    $actions[] = [
                        'label' => 'Serahkan ke Teknisi',
                        'url' => route('warehouse.issues.create', ['pop_id' => $pop->id, 'item_id' => $serial->item_id, 'serial' => $serial->serial_number]),
                        'style' => 'primary',
                        'quick' => 'issue',
                        'cabang_pop_id' => $pop->id,
                        'item_id' => $serial->item_id,
                        'serial_number' => $serial->serial_number,
                    ];
                }
                break;

            case SerialStatus::ISSUED:
            case SerialStatus::IN_USE:
                $techName = $serial->currentTechnician->name ?? 'teknisi';
                $message = "Lagi dipegang {$techName} di lapangan.";

                if ($canReassign) {
                    $actions[] = ['label' => 'Alihkan Custody', 'url' => route('warehouse.reassign.serial.create', $serial), 'style' => 'primary'];
                }
                if ($canAdjust) {
                    $actions[] = ['label' => 'Lapor BAP / Rusak', 'url' => route('warehouse.adjustments.serial.create', $serial), 'style' => 'danger'];
                }
                break;

            case SerialStatus::INSTALLED:
                $message = 'Sudah terpasang aktif di pelanggan — gak ada aksi gudang buat SN ini.';
                break;

            case SerialStatus::TRANSFERRED:
                $message = 'Lagi dalam perjalanan Transfer antar gudang, belum dikonfirmasi diterima.';

                // Bug 2026-09-07 (laporan user): status TRANSFERRED cuma
                // dikasih pesan doang, gak ada link balik ke Bon Transfer-nya
                // — begitu staf ninggalin halaman redirect PRG abis dispatch
                // (`warehouse.transfers.show`), gak ada jalan lain buat balik
                // nemu tombol "Konfirmasi Penerimaan" SELAIN scroll-cari di
                // Dashboard (yang cuma nampilin transfer PALING BARU). Scan
                // Barang di sini jadi jalan pintas: temuin baris ledger
                // dispatch (`inventory_transfer_id` keisi) buat SN ini,
                // ambil transfer-nya kalau statusnya MASIH `in_transit`.
                if ($canViewTransfer) {
                    $pendingTransaction = InventoryTransaction::query()
                        ->where('serial_id', $serial->id)
                        ->whereNotNull('inventory_transfer_id')
                        ->whereHas('transfer', fn ($q) => $q->where('status', TransferStatus::IN_TRANSIT->value))
                        ->latest('id')
                        ->first();

                    if ($pendingTransaction) {
                        $actions[] = ['label' => 'Konfirmasi Penerimaan Transfer', 'url' => route('warehouse.transfers.show', $pendingTransaction->inventory_transfer_id), 'style' => 'primary'];
                    }
                }
                break;

            case SerialStatus::DAMAGED:
            case SerialStatus::LOST:
            case SerialStatus::SCRAPPED:
            case SerialStatus::QUARANTINE:
                $message = 'Status: '.$serial->status->label().' — sudah final/menunggu tindak lanjut BAP, gak ada aksi cepat dari sini.';
                break;

            default:
                $message = 'Status: '.$serial->status->label().'.';
        }

        if ($canTrace) {
            $actions[] = ['label' => 'Lihat Riwayat Lengkap', 'url' => route('warehouse.traceability.index', ['sn' => $serial->serial_number]), 'style' => 'ghost'];
        }

        return [$message, $actions];
    }

    private function resolveLocationLabel(InventorySerial $serial): string
    {
        return match (true) {
            $serial->currentPop !== null => $serial->currentPop->name,
            $serial->currentTechnician !== null => $serial->currentTechnician->name.' (Teknisi)',
            $serial->customer_id !== null => 'Pelanggan',
            default => '—',
        };
    }

    /**
     * Padanan `resolveActions()` buat roll kabel — struktur SAMA (switch per
     * status, tombol Transfer/Issue di gudang, Reassign/Adjust di custody),
     * roll gak py cabang INSTALLED (gak pernah "terpasang" atomik).
     *
     * @return array{0: string, 1: list<array{label: string, url: string, style: string}>}
     */
    private function resolveRollActions(InventoryRoll $roll, User $user): array
    {
        $actions = [];

        $canTransfer = $user->hasPermission('warehouse_transfer.create');
        $canViewTransfer = $user->hasPermission('warehouse_transfer.view');
        $canIssue = $user->hasPermission('warehouse_issue.create');
        $canReassign = $user->hasPermission('warehouse_reassign.create');
        $canAdjust = $user->hasPermission('warehouse_adjustment.create');
        $canTrace = $user->hasPermission('warehouse_traceability.view');

        switch ($roll->status) {
            case RollStatus::AVAILABLE:
            case RollStatus::RECEIVED:
                $pop = $roll->currentPop;
                $message = $pop
                    ? 'Tersedia di '.$pop->name.', sisa '.$this->formatMeter($roll->length_remaining).' dari '.$this->formatMeter($roll->length_total).' meter.'
                    : 'Tersedia di gudang, sisa '.$this->formatMeter($roll->length_remaining).' dari '.$this->formatMeter($roll->length_total).' meter.';

                if ($pop?->type === 'pusat' && $canTransfer) {
                    $actions[] = ['label' => 'Kirim Transfer ke Cabang', 'url' => route('warehouse.transfers.create', ['pop_id' => $pop->id, 'item_id' => $roll->item_id]), 'style' => 'primary'];
                }
                if ($pop?->type === 'cabang' && $canIssue) {
                    $actions[] = ['label' => 'Serahkan ke Teknisi', 'url' => route('warehouse.issues.create', ['pop_id' => $pop->id, 'item_id' => $roll->item_id]), 'style' => 'primary'];
                }
                break;

            case RollStatus::ISSUED:
            case RollStatus::IN_USE:
                $techName = $roll->currentTechnician->name ?? 'teknisi';
                $message = "Lagi dipegang {$techName} di lapangan, sisa ".$this->formatMeter($roll->length_remaining).' meter.';

                if ($canReassign) {
                    $actions[] = ['label' => 'Alihkan Custody', 'url' => route('warehouse.reassign.roll.create', $roll), 'style' => 'primary'];
                }
                if ($canAdjust) {
                    $actions[] = ['label' => 'Lapor BAP / Rusak', 'url' => route('warehouse.adjustments.roll.create', $roll), 'style' => 'danger'];
                }
                break;

            case RollStatus::DEPLETED:
                $message = 'Roll ini sudah habis — gak ada aksi gudang buat roll ini.';
                break;

            case RollStatus::TRANSFERRED:
                $message = 'Lagi dalam perjalanan Transfer antar gudang, belum dikonfirmasi diterima.';

                if ($canViewTransfer) {
                    $pendingTransaction = InventoryTransaction::query()
                        ->where('roll_id', $roll->id)
                        ->whereNotNull('inventory_transfer_id')
                        ->whereHas('transfer', fn ($q) => $q->where('status', TransferStatus::IN_TRANSIT->value))
                        ->latest('id')
                        ->first();

                    if ($pendingTransaction) {
                        $actions[] = ['label' => 'Konfirmasi Penerimaan Transfer', 'url' => route('warehouse.transfers.show', $pendingTransaction->inventory_transfer_id), 'style' => 'primary'];
                    }
                }
                break;

            case RollStatus::DAMAGED:
            case RollStatus::LOST:
            case RollStatus::SCRAPPED:
            case RollStatus::QUARANTINE:
                $message = 'Status: '.$roll->status->label().' — sudah final/menunggu tindak lanjut BAP, gak ada aksi cepat dari sini.';
                break;

            default:
                $message = 'Status: '.$roll->status->label().'.';
        }

        if ($roll->isLowRemaining()) {
            $message .= ' ⚠ Sisa kecil (di bawah ambang '.$this->formatMeter($roll->item->minimum_length).' meter) — pertimbangkan gabung/habiskan roll ini dulu.';
        }

        if ($canTrace) {
            $actions[] = ['label' => 'Lihat Riwayat Lengkap', 'url' => route('warehouse.traceability.index', ['roll' => $roll->roll_code]), 'style' => 'ghost'];
        }

        return [$message, $actions];
    }

    private function resolveRollLocationLabel(InventoryRoll $roll): string
    {
        return match (true) {
            $roll->currentPop !== null => $roll->currentPop->name,
            $roll->currentTechnician !== null => $roll->currentTechnician->name.' (Teknisi)',
            default => '—',
        };
    }
}
