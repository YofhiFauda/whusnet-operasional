<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\InventoryAdjustmentService;
use App\Support\LikeSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman "Barang Rusak" (ADHOC-108, diperluas) — SATU hub buat semua
 * koreksi kerugian gudang, dipecah 4 tab karena struktur datanya beda-beda
 * (gak bisa dipaksa satu tabel):
 *   - `serial`  : SN (`inventory_serials`) status DAMAGED/LOST/QUARANTINE/
 *                 SCRAPPED, ATAU condition=used_damaged — mencakup retur
 *                 rusak (Tahap 3 Pusat, `InventoryReturnTransferService::
 *                 confirmAtPusat()`) maupun Lapor Rusak
 *                 (`InventoryAdjustmentService::adjustSerialStatus()`).
 *   - `roll`    : roll kabel (`inventory_rolls`) status sama 4 itu, lewat
 *                 `adjustRollStatus()`.
 *   - `balance` : koreksi saldo bulk (`adjustPopBalance()`) — ledger
 *                 ADJUSTMENT qty negatif, TANPA serial/roll. `reason` di
 *                 sini TEKS BEBAS (datalist, bukan enum — lihat docblock
 *                 `InventoryAdjustmentService::REASON_CATEGORIES`), jadi
 *                 difilter dari STRUKTUR (qty<0, serial_id & roll_id null)
 *                 bukan dari isi `reason`.
 *   - `custody` : koreksi custody teknisi (`adjustCustody()`) — `reason`
 *                 DI SINI terarah (`REASON_CATEGORIES`), difilter
 *                 `lost`/`damaged` yang KEDUANYA wajib evidence foto.
 *
 * Semua tab read-only, reuse permission `warehouse.view` (pola sama Lacak
 * Barang/Dashboard).
 */
class WarehouseDamagedStockController extends Controller
{
    private const SERIAL_ROLL_STATUSES = ['damaged', 'lost', 'quarantine', 'scrapped'];

    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();
        $tab = in_array($request->input('tab'), ['roll', 'balance', 'custody'], true) ? $request->input('tab') : 'serial';
        $search = LikeSearch::sanitize((string) $request->input('q', ''));
        $popFilter = $request->integer('pop_id') ?: null;
        $statusFilter = $request->input('status');

        $serials = $this->serialsQuery($access, $user, $search, $popFilter, $statusFilter);
        $rolls = $this->rollsQuery($access, $user, $search, $popFilter, $statusFilter);
        $balanceAdjustments = $this->balanceAdjustmentsQuery($access, $user, $search, $popFilter);
        $custodyAdjustments = $this->custodyAdjustmentsQuery($access, $user, $search, $popFilter);

        $pops = Pop::query()->whereIn('type', Pop::WAREHOUSE_TYPES)
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')->get(['id', 'name']);

        return view('warehouse.damaged.index', compact(
            'tab', 'serials', 'rolls', 'balanceAdjustments', 'custodyAdjustments',
            'search', 'popFilter', 'statusFilter', 'pops'
        ));
    }

    private function serialsQuery(EffectiveAccessService $access, $user, string $search, ?int $popFilter, ?string $statusFilter)
    {
        return InventorySerial::query()
            ->where(function ($q) {
                $q->whereIn('status', [
                    SerialStatus::DAMAGED->value,
                    SerialStatus::LOST->value,
                    SerialStatus::QUARANTINE->value,
                    SerialStatus::SCRAPPED->value,
                ])->orWhere('condition', ItemCondition::USED_DAMAGED->value);
            })
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->where(
                fn ($w) => $w->whereIn('current_pop_id', $access->getAllowedPopIds($user))
                    ->orWhereIn('issued_from_pop_id', $access->getAllowedPopIds($user))
            ))
            ->when($popFilter, fn ($q) => $q->where(fn ($w) => $w->where('current_pop_id', $popFilter)->orWhere('issued_from_pop_id', $popFilter)))
            ->when(in_array($statusFilter, self::SERIAL_ROLL_STATUSES, true), fn ($q) => $q->where('status', $statusFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($i) => $i->where('name', 'like', "%{$search}%"));
                });
            })
            ->with(['item', 'currentPop', 'issuedFromPop', 'latestRetrievalLog'])
            ->orderByDesc('updated_at')
            ->paginate(25, ['*'], 'serial_page')
            ->withQueryString();
    }

    private function rollsQuery(EffectiveAccessService $access, $user, string $search, ?int $popFilter, ?string $statusFilter)
    {
        return InventoryRoll::query()
            ->whereIn('status', [
                RollStatus::DAMAGED->value,
                RollStatus::LOST->value,
                RollStatus::QUARANTINE->value,
                RollStatus::SCRAPPED->value,
            ])
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->where(
                fn ($w) => $w->whereIn('current_pop_id', $access->getAllowedPopIds($user))
                    ->orWhereIn('issued_from_pop_id', $access->getAllowedPopIds($user))
            ))
            ->when($popFilter, fn ($q) => $q->where(fn ($w) => $w->where('current_pop_id', $popFilter)->orWhere('issued_from_pop_id', $popFilter)))
            ->when(in_array($statusFilter, self::SERIAL_ROLL_STATUSES, true), fn ($q) => $q->where('status', $statusFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('roll_code', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($i) => $i->where('name', 'like', "%{$search}%"));
                });
            })
            ->with(['item', 'currentPop', 'issuedFromPop'])
            ->orderByDesc('updated_at')
            ->paginate(25, ['*'], 'roll_page')
            ->withQueryString();
    }

    private function balanceAdjustmentsQuery(EffectiveAccessService $access, $user, string $search, ?int $popFilter)
    {
        return InventoryTransaction::query()
            ->where('type', InventoryTransactionType::ADJUSTMENT->value)
            ->whereNull('serial_id')
            ->whereNull('roll_id')
            ->whereNull('from_technician_id')
            ->where('qty', '<', 0)
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('to_pop_id', $access->getAllowedPopIds($user)))
            ->when($popFilter, fn ($q) => $q->where('to_pop_id', $popFilter))
            ->when($search !== '', fn ($q) => $q->whereHas('item', fn ($i) => $i->where('name', 'like', "%{$search}%")))
            ->with(['item', 'toPop', 'createdBy'])
            ->orderByDesc('created_at')
            ->paginate(25, ['*'], 'balance_page')
            ->withQueryString();
    }

    /**
     * Discope lewat `technician_custody.issued_from_pop_id` (match
     * technician_id+item_id+lot_no persis baris custody-nya), BUKAN
     * teknisinya sendiri — ledger ADJUSTMENT custody gak nyimpen pop_id
     * langsung, dan `user_pops` legacy gak paham pop_tree (sama alasan
     * `WarehouseCustodyController` docblock).
     */
    private function custodyAdjustmentsQuery(EffectiveAccessService $access, $user, string $search, ?int $popFilter)
    {
        $allowedPopIds = $access->hasAllPopAccess($user) ? null : $access->getAllowedPopIds($user);

        return InventoryTransaction::query()
            ->where('type', InventoryTransactionType::ADJUSTMENT->value)
            ->whereIn('reason', InventoryAdjustmentService::EVIDENCE_REQUIRED_REASONS)
            ->whereNotNull('from_technician_id')
            ->when($allowedPopIds !== null, fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('technician_custody')
                ->whereColumn('technician_custody.technician_id', 'inventory_transactions.from_technician_id')
                ->whereColumn('technician_custody.item_id', 'inventory_transactions.item_id')
                ->whereColumn('technician_custody.lot_no', 'inventory_transactions.lot_no')
                ->whereIn('technician_custody.issued_from_pop_id', $allowedPopIds)))
            ->when($popFilter, fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('technician_custody')
                ->whereColumn('technician_custody.technician_id', 'inventory_transactions.from_technician_id')
                ->whereColumn('technician_custody.item_id', 'inventory_transactions.item_id')
                ->whereColumn('technician_custody.lot_no', 'inventory_transactions.lot_no')
                ->where('technician_custody.issued_from_pop_id', $popFilter)))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('item', fn ($i) => $i->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('fromTechnician', fn ($t) => $t->where('name', 'like', "%{$search}%"));
                });
            })
            ->with(['item', 'fromTechnician'])
            ->orderByDesc('created_at')
            ->paginate(25, ['*'], 'custody_page')
            ->withQueryString();
    }
}
