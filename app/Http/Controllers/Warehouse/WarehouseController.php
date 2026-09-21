<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\CustodyStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Enums\StockRequestStatus;
use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\Pop;
use App\Models\StockRequest;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Dashboard Gudang (ADHOC-54, rancangan-ui.md §2.1) — cuma VIEW, semua
 * penulisan data lewat WarehouseTransferController/WarehouseIssueController.
 *
 * Discope lewat POP scope existing (`EffectiveAccessService`) — pop_admin
 * cuma liat gudang cabangnya sendiri, admin/owner liat semua. Ini query
 * langsung (bukan `Pop::scopeForUser()`) karena butuh gabung `scopeWarehouse()`
 * (pusat/cabang doang, exclude mini_pop) SEKALIGUS scope user.
 */
class WarehouseController extends Controller
{
    public function index(EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $pops = Pop::query()
            ->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $selectedPopId = request('pop_id');
        $activePop = $selectedPopId ? $pops->firstWhere('id', (int) $selectedPopId) : null;
        $popIds = $activePop ? collect([$activePop->id]) : $pops->pluck('id');

        $canActAsPusat = $pops->contains(fn ($p) => $p->type === 'pusat');
        $canActAsCabang = $pops->contains(fn ($p) => $p->type === 'cabang');

        // Tabel "Stok Saat Ini" mentah (semua balance qty>0, tanpa filter/
        // pagination) PINDAH ke Management Stock (`WarehouseStockController`,
        // koreksi IA 2026-09-03) — dashboard cuma nyisain ringkasan (stats +
        // stok rendah + ledger terbaru), jangan didobel di sini.
        //
        // KPI "Total Stok (SKU & Fisik)" yang dulu pakai angka ini DIHAPUS
        // (2026-09-08, laporan user: KPI Zona 1 gak on-point) — `SUM(qty)`
        // di sini gabung LINTAS SATUAN (ONT unit + kabel meter dijumlah jadi
        // satu angka "≈ X unit/satuan fisik"), persis anti-pattern yang
        // ditolak di `WarehouseReportController`. Query-nya ikut dicabut,
        // bukan cuma disembunyiin dari view — gak ada pemakai lain.
        $lowStock = InventoryBalance::query()
            ->whereIn('pop_id', $popIds)
            ->lowStock()
            ->with(['item.category', 'pop'])
            ->get();

        // Roll kabel "Sisa Kecil" (docs/plan/warehouse/analisa-gap-roll-kabel.md
        // §8) — roll bisa lagi di gudang (`current_pop_id`) ATAU di custody
        // teknisi (`issued_from_pop_id`, current_pop_id null), dua-duanya
        // discope ke $popIds biar pop_admin cuma liat cabangnya sendiri.
        $lowRolls = InventoryRoll::query()
            ->lowRemaining()
            ->where(fn ($q) => $q->whereIn('inventory_rolls.current_pop_id', $popIds)->orWhereIn('inventory_rolls.issued_from_pop_id', $popIds))
            ->with(['item.category', 'currentPop', 'currentTechnician'])
            ->get();

        // Transfer lagi in-transit dalam scope (asal ATAU tujuan) — dua sisi
        // sengaja diikutkan: Pusat mau lihat kiriman yang belum dikonfirmasi
        // cabang, cabang mau lihat kiriman yang lagi menuju dia.
        $transitTransfers = InventoryTransfer::query()
            ->where('status', TransferStatus::IN_TRANSIT->value)
            ->where(fn ($q) => $q->whereIn('from_pop_id', $popIds)->orWhereIn('to_pop_id', $popIds))
            ->with(['transactions', 'fromPop', 'toPop'])
            ->latest('id')
            ->get();

        // Qty yang "di jalan" = baris ledger dispatch (to_pop_id masih NULL,
        // belum ditambahkan ke saldo tujuan — lihat komentar match() di view
        // ledger soal 2 baris independen per transfer). BUKAN qty saldo,
        // karena mixing satuan antar item beda-beda tetap gak valid buat
        // dijumlah — makanya cuma dihitung jumlah baris/transfer, bukan qty.
        $transitCount = $transitTransfers->count();

        // Custody Teknisi — 2 sumber data terpisah (SerialStatus vs
        // CustodyStatus, lihat docblock App\Enums\CustodyStatus), TIDAK
        // dijumlah jadi satu angka karena satuannya beda (unit vs
        // meter/pcs campur-campur per item). Ditampilkan sebagai 2 hitungan
        // terpisah di view, bukan satu angka gabungan yang menyesatkan.
        $custodySerialCount = InventorySerial::query()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('status', [SerialStatus::ISSUED->value, SerialStatus::IN_USE->value])
            ->count();

        $custodyMaterialLines = TechnicianCustody::query()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('status', [CustodyStatus::ISSUED->value, CustodyStatus::PARTIALLY_USED->value])
            ->active()
            ->count();

        $custodyTechnicianCount = InventorySerial::query()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('status', [SerialStatus::ISSUED->value, SerialStatus::IN_USE->value])
            ->distinct('current_technician_id')
            ->count('current_technician_id');

        // Top 5 teknisi dengan custody aktif terbanyak (serial + material
        // digabung PER TEKNISI di sini — beda dari custody total di atas,
        // ini cuma buat ranking "siapa paling banyak", bukan buat dijumlahin
        // lintas satuan).
        $custodyBySerial = InventorySerial::query()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('status', [SerialStatus::ISSUED->value, SerialStatus::IN_USE->value])
            ->whereNotNull('current_technician_id')
            ->selectRaw('current_technician_id, count(*) as cnt')
            ->groupBy('current_technician_id')
            ->pluck('cnt', 'current_technician_id');

        $custodyByMaterial = TechnicianCustody::query()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('status', [CustodyStatus::ISSUED->value, CustodyStatus::PARTIALLY_USED->value])
            ->active()
            ->selectRaw('technician_id, count(*) as cnt')
            ->groupBy('technician_id')
            ->pluck('cnt', 'technician_id');

        $topCustodyTechnicianIds = collect($custodyBySerial->keys())
            ->merge($custodyByMaterial->keys())
            ->unique()
            ->sortByDesc(fn ($id) => ($custodyBySerial[$id] ?? 0) + ($custodyByMaterial[$id] ?? 0))
            ->take(5);

        $topCustodyTechnicians = User::query()
            ->whereIn('id', $topCustodyTechnicianIds)
            ->get()
            ->map(fn ($tech) => [
                'name' => $tech->name,
                'total' => ($custodyBySerial[$tech->id] ?? 0) + ($custodyByMaterial[$tech->id] ?? 0),
            ])
            ->sortByDesc('total')
            ->values();

        $quarantineCount = InventorySerial::query()
            ->whereIn('current_pop_id', $popIds)
            ->where('status', SerialStatus::QUARANTINE->value)
            ->count();

        // Permintaan Stok Cabang→Pusat masih TERBUKA (PENDING + PARTIAL,
        // sama makna `StockRequestStatus::isOpen()`) — antrean kerja Pusat
        // yang SEBELUM ini gak kelihatan sama sekali dari Dashboard (laporan
        // user 2026-09-08: KPI Zona 1 gak on-point, modul ini justru
        // dibangun buat jawab "Cabang habis stok, Pusat gak sadar" tapi gak
        // ada satu pun angkanya di Dashboard). `cabang_pop_id` discope ke
        // $popIds yang UDAH lolos filter akses user di atas.
        $pendingStockRequestCount = StockRequest::query()
            ->whereIn('cabang_pop_id', $popIds)
            ->whereIn('status', [StockRequestStatus::PENDING->value, StockRequestStatus::PARTIAL->value])
            ->count();

        // Kepatuhan Stock Opname — % kombinasi item+gudang+lot (grain yang
        // sama dengan `inventory_balances`) yang punya baris STOCK_OPNAME
        // dalam 30 hari terakhir. Query sama pola dengan "Opname terakhir"
        // di WarehouseStockController::index(), tapi discope ke SELURUH
        // saldo aktif (bukan cuma 1 halaman paginasi) karena KPI ini
        // butuh gambaran menyeluruh, bukan potongan halaman yang lagi dibuka.
        $trackedBalances = InventoryBalance::query()
            ->whereIn('pop_id', $popIds)
            ->where('qty', '>', 0)
            ->get(['pop_id', 'item_id', 'lot_no']);

        $opnameCompliantCount = 0;
        $opnameDueList = collect();

        if ($trackedBalances->isNotEmpty()) {
            $lastOpnameByKey = InventoryTransaction::query()
                ->where('type', InventoryTransactionType::STOCK_OPNAME->value)
                ->whereIn('to_pop_id', $trackedBalances->pluck('pop_id')->unique())
                ->whereIn('item_id', $trackedBalances->pluck('item_id')->unique())
                ->selectRaw('to_pop_id, item_id, lot_no, MAX(created_at) as last_opname_at')
                ->groupBy('to_pop_id', 'item_id', 'lot_no')
                ->get()
                ->keyBy(fn ($row) => $row->to_pop_id.'-'.$row->item_id.'-'.($row->lot_no ?? ''));

            $cutoff = now()->subDays(30);

            $trackedBalances->each(function ($balance) use ($lastOpnameByKey, $cutoff, &$opnameCompliantCount, &$opnameDueList) {
                $key = $balance->pop_id.'-'.$balance->item_id.'-'.($balance->lot_no ?? '');
                $lastOpnameAt = $lastOpnameByKey[$key]->last_opname_at ?? null;

                if ($lastOpnameAt && Carbon::parse($lastOpnameAt)->gte($cutoff)) {
                    $opnameCompliantCount++;
                }

                $opnameDueList->push([
                    'pop_id' => $balance->pop_id,
                    'item_id' => $balance->item_id,
                    'lot_no' => $balance->lot_no,
                    'last_opname_at' => $lastOpnameAt,
                    'days_since' => $lastOpnameAt ? Carbon::parse($lastOpnameAt)->diffInDays(now()) : null,
                ]);
            });
        }

        $opnameDueList = $opnameDueList
            ->sortBy(fn ($row) => $row['days_since'] === null ? PHP_INT_MAX : $row['days_since'], SORT_REGULAR, true)
            ->take(5)
            ->map(function ($row) {
                $item = Item::find($row['item_id']);
                $pop = Pop::find($row['pop_id']);

                return [
                    'item_name' => $item?->name ?? '(item dihapus)',
                    'pop_name' => $pop?->name ?? '(gudang dihapus)',
                    'lot_no' => $row['lot_no'],
                    'days_since' => $row['days_since'],
                ];
            });

        // Arus hari ini — dihitung JUMLAH TRANSAKSI (bukan qty dijumlah),
        // sengaja: satu baris ledger bisa item beda satuan (unit vs meter),
        // menjumlah qty lintas satuan itu kesalahan kelas yang sama dengan
        // custody di atas.
        $todayReceiveCount = InventoryTransaction::query()
            ->whereIn('to_pop_id', $popIds)
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->whereDate('created_at', today())
            ->count();

        $todayIssueCount = InventoryTransaction::query()
            ->whereIn('from_pop_id', $popIds)
            ->where('type', InventoryTransactionType::ISSUE->value)
            ->whereDate('created_at', today())
            ->count();

        // Transfer KELUAR hari ini — beda dari `$transitCount` di atas
        // (snapshot KUMULATIF transfer yang masih IN_TRANSIT, bisa dari
        // hari-hari sebelumnya) — ini murni "berapa transfer DIBUAT hari
        // ini", sama pola Receive/Issue di atas (laporan user 2026-09-08:
        // dashboard py Receive & Issue harian tapi Transfer cuma
        // in-transit, gak ada padanan hariannya).
        $todayTransferCount = InventoryTransaction::query()
            ->whereIn('from_pop_id', $popIds)
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->whereDate('created_at', today())
            ->count();

        // Kerugian (Adjustment) hari ini — scope SAMA persis
        // `WarehouseReportController::buildAdjustmentSummary()` (to_pop ATAU
        // from_pop dalam scope, custody teknisi from_pop_id NULL tetap
        // ikut) tapi periode DIPERSEMPIT ke hari ini, bukan bulan berjalan.
        $todayAdjustmentCount = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::ADJUSTMENT->value)
            ->where(fn ($q) => $q->whereIn('to_pop_id', $popIds)
                ->orWhereIn('from_pop_id', $popIds)
                ->orWhere(fn ($qq) => $qq->whereNull('to_pop_id')->whereNull('from_pop_id')))
            ->whereDate('created_at', today())
            ->count();

        $stats = [
            'total_gudang' => $pops->count(),
            'low_stock_count' => $lowStock->count(),
            'low_roll_count' => $lowRolls->count(),
            'serial_tersedia' => InventorySerial::query()
                ->whereIn('current_pop_id', $popIds)
                ->where('status', SerialStatus::AVAILABLE->value)
                ->count(),
            'transit_count' => $transitCount,
            'custody_serial_count' => $custodySerialCount,
            'custody_material_lines' => $custodyMaterialLines,
            'custody_technician_count' => $custodyTechnicianCount,
            'quarantine_count' => $quarantineCount,
            'pending_stock_request_count' => $pendingStockRequestCount,
            'opname_compliant_count' => $opnameCompliantCount,
            'opname_tracked_count' => $trackedBalances->count(),
            'today_receive_count' => $todayReceiveCount,
            'today_issue_count' => $todayIssueCount,
            'today_transfer_count' => $todayTransferCount,
            'today_adjustment_count' => $todayAdjustmentCount,
        ];

        // ═══════════════════════════════════════════════════════
        // CARD PER GUDANG (POP) — Menggantikan list flat panjang
        // Menampilkan 3 pilar: Barang Masuk, Transfer Cabang, Serah Terima Teknisi
        // ═══════════════════════════════════════════════════════
        $displayPops = $activePop ? collect([$activePop]) : $pops;

        // 1. Barang Masuk (Receive) per POP
        $todayReceivesByPop = InventoryTransaction::query()
            ->whereIn('to_pop_id', $popIds)
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->whereDate('created_at', today())
            ->selectRaw('to_pop_id, count(*) as cnt')
            ->groupBy('to_pop_id')
            ->pluck('cnt', 'to_pop_id');

        $recentReceives = InventoryTransaction::query()
            ->whereIn('to_pop_id', $popIds)
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->with(['item.category', 'serial', 'roll', 'createdBy'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->groupBy('to_pop_id');

        // 2. Transfer ke Cabang (Transfer) per POP
        $todayTransfersByPop = InventoryTransaction::query()
            ->whereIn('from_pop_id', $popIds)
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->whereDate('created_at', today())
            ->selectRaw('from_pop_id, count(*) as cnt')
            ->groupBy('from_pop_id')
            ->pluck('cnt', 'from_pop_id');

        $inTransitTransfersByPop = InventoryTransfer::query()
            ->whereIn('from_pop_id', $popIds)
            ->where('status', TransferStatus::IN_TRANSIT->value)
            ->selectRaw('from_pop_id, count(*) as cnt')
            ->groupBy('from_pop_id')
            ->pluck('cnt', 'from_pop_id');

        $incomingInTransitByPop = InventoryTransfer::query()
            ->whereIn('to_pop_id', $popIds)
            ->where('status', TransferStatus::IN_TRANSIT->value)
            ->selectRaw('to_pop_id, count(*) as cnt')
            ->groupBy('to_pop_id')
            ->pluck('cnt', 'to_pop_id');

        $recentTransfers = InventoryTransaction::query()
            ->where(fn ($q) => $q->whereIn('from_pop_id', $popIds)->orWhereIn('to_pop_id', $popIds))
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->with(['item.category', 'fromPop', 'toPop', 'transfer.toPop', 'serial', 'roll', 'createdBy'])
            ->latest('id')
            ->limit(60)
            ->get();

        $recentTransfersByPop = [];
        foreach ($popIds as $pId) {
            $recentTransfersByPop[$pId] = $recentTransfers
                ->filter(fn ($t) => (int) $t->from_pop_id === (int) $pId || (int) $t->to_pop_id === (int) $pId)
                ->take(3)
                ->values();
        }

        // 3. Diserahkan ke Teknisi (Issue) per POP
        $todayIssuesByPop = InventoryTransaction::query()
            ->whereIn('from_pop_id', $popIds)
            ->where('type', InventoryTransactionType::ISSUE->value)
            ->whereDate('created_at', today())
            ->selectRaw('from_pop_id, count(*) as cnt')
            ->groupBy('from_pop_id')
            ->pluck('cnt', 'from_pop_id');

        $custodySerialByPop = InventorySerial::query()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('status', [SerialStatus::ISSUED->value, SerialStatus::IN_USE->value])
            ->selectRaw('issued_from_pop_id, count(*) as cnt')
            ->groupBy('issued_from_pop_id')
            ->pluck('cnt', 'issued_from_pop_id');

        $custodyMaterialByPop = TechnicianCustody::query()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('status', [CustodyStatus::ISSUED->value, CustodyStatus::PARTIALLY_USED->value])
            ->active()
            ->selectRaw('issued_from_pop_id, count(*) as cnt')
            ->groupBy('issued_from_pop_id')
            ->pluck('cnt', 'issued_from_pop_id');

        $recentIssues = InventoryTransaction::query()
            ->whereIn('from_pop_id', $popIds)
            ->where('type', InventoryTransactionType::ISSUE->value)
            ->with(['item.category', 'toTechnician', 'serial', 'roll', 'createdBy'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->groupBy('from_pop_id');

        // 4. Status Stok Kritis & Total SKU per POP
        $lowStockCountByPop = InventoryBalance::query()
            ->whereIn('pop_id', $popIds)
            ->lowStock()
            ->selectRaw('pop_id, count(*) as cnt')
            ->groupBy('pop_id')
            ->pluck('cnt', 'pop_id');

        $totalSkuCountByPop = InventoryBalance::query()
            ->whereIn('pop_id', $popIds)
            ->where('qty', '>', 0)
            ->selectRaw('pop_id, count(distinct item_id) as cnt')
            ->groupBy('pop_id')
            ->pluck('cnt', 'pop_id');

        $popCards = $displayPops->map(function ($pop) use (
            $todayReceivesByPop,
            $recentReceives,
            $todayTransfersByPop,
            $inTransitTransfersByPop,
            $incomingInTransitByPop,
            $recentTransfersByPop,
            $todayIssuesByPop,
            $custodySerialByPop,
            $custodyMaterialByPop,
            $recentIssues,
            $lowStockCountByPop,
            $totalSkuCountByPop
        ) {
            return [
                'pop' => $pop,
                'receive' => [
                    'today_count' => (int) ($todayReceivesByPop[$pop->id] ?? 0),
                    'recent' => ($recentReceives[$pop->id] ?? collect())->take(3),
                ],
                'transfer' => [
                    'today_count' => (int) ($todayTransfersByPop[$pop->id] ?? 0),
                    'in_transit_out' => (int) ($inTransitTransfersByPop[$pop->id] ?? 0),
                    'in_transit_in' => (int) ($incomingInTransitByPop[$pop->id] ?? 0),
                    'recent' => $recentTransfersByPop[$pop->id] ?? collect(),
                ],
                'issue' => [
                    'today_count' => (int) ($todayIssuesByPop[$pop->id] ?? 0),
                    'custody_serial' => (int) ($custodySerialByPop[$pop->id] ?? 0),
                    'custody_material' => (int) ($custodyMaterialByPop[$pop->id] ?? 0),
                    'recent' => ($recentIssues[$pop->id] ?? collect())->take(3),
                ],
                'stock' => [
                    'low_stock_count' => (int) ($lowStockCountByPop[$pop->id] ?? 0),
                    'total_sku' => (int) ($totalSkuCountByPop[$pop->id] ?? 0),
                ],
            ];
        });

        // Ledger terbaru (tetap disediakan untuk fallback ringkas/audit)
        $recentLedger = InventoryTransaction::query()
            ->where(fn ($q) => $q->whereIn('from_pop_id', $popIds)->orWhereIn('to_pop_id', $popIds))
            ->whereNotIn('type', [InventoryTransactionType::TRANSFER_CUSTODY->value])
            ->with(['item.category', 'fromPop', 'toPop', 'fromTechnician', 'toTechnician', 'serial', 'roll', 'createdBy', 'transfer.toPop'])
            ->latest('id')
            ->limit(25)
            ->get();

        return view('warehouse.index', compact(
            'pops', 'displayPops', 'popCards', 'lowStock', 'lowRolls', 'stats', 'recentLedger',
            'transitTransfers', 'topCustodyTechnicians', 'opnameDueList',
            'selectedPopId', 'activePop', 'canActAsPusat', 'canActAsCabang'
        ));
    }
}
