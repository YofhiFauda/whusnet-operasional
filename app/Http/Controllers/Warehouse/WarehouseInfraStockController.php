<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Models\InfrastructureBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\NetworkNode;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureLocationResolver;
use App\Support\IndonesianDate;
use App\Support\InfrastructureLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Barang Terpasang di Infrastruktur — state SAAT INI barang Gudang (SN/qty/
 * kabel) yang nempel di titik infrastruktur jaringan, khusus Gudang Pusat.
 *
 * BEDA dari `WarehouseUsageController` (`/warehouse/usage`): itu log ALUR
 * per periode waktu (hari ini/minggu ini/dll) dari `inventory_transactions`
 * — barang yang sudah DEPLOY lalu RETRIEVE_INFRA tetap nongol di log lama.
 * Halaman ini proyeksi STATE: cuma yang SEKARANG masih di infra, sama pola
 * dengan `InfrastructureBalance` (proyeksi diturunkan dari ledger, bukan
 * sumber kebenaran sendiri — lihat docblock model itu).
 *
 * SENGAJA TIDAK pernah query/join kolom topologi `network_nodes` selain buat
 * breadcrumb teks (nama + rantai leluhur) lewat `InfrastructureLocationResolver`
 * yang sama dipakai Peta Infrastruktur & laporan Usage — tujuan halaman ini
 * justru Gudang Pusat bisa tahu "barang ada di mana" TANPA permission
 * `network_nodes.*` (gak buka Peta/Master Node). Jangan tambah kolom
 * port-mapping/parent_id mentah ke view.
 *
 * Tab (bukan 3 tabel sekaligus) + paginate di level DB (laporan user
 * 2026-11-05: halaman gak kebuka, OOM, gak ada paginate) — SEBELUMNYA
 * `->get()` SELURUH baris ketiga sumber tiap request lalu difilter/di-sort
 * di PHP, meledak begitu datanya ribuan. Sekarang cuma SATU sumber yang
 * di-query per request (tab aktif), scope POP didorong ke WHERE SQL (bukan
 * filter Collection pasca-load), dan breadcrumb di-memo per request supaya
 * SN/qty yang numpuk di node yang sama gak nge-walk pohon node berkali-kali.
 */
class WarehouseInfraStockController extends Controller
{
    private const PER_PAGE = 25;

    /** @var array<string, ?InfrastructureLocation> */
    private array $locationCache = [];

    public function index(Request $request, EffectiveAccessService $access, InfrastructureLocationResolver $locationResolver): View
    {
        $user = auth()->user();
        $allowAll = $access->hasAllPopAccess($user);
        $allowedPopIds = $allowAll ? [] : $access->getAllowedPopIds($user);
        // Node yang anchor-nya ADA di salah satu POP/Mini POP yang diizinkan —
        // dipakai nge-scope SN/qty yang posisinya di NODE/SLOT/PORT (bukan
        // langsung di kolom current_pop_id). `getAllowedPopIds()` sudah
        // termasuk Mini POP turunan (pop_tree), jadi satu whereIn cukup.
        $allowedNodeIds = $allowAll ? [] : NetworkNode::query()->whereIn('anchor_pop_id', $allowedPopIds)->pluck('id')->all();

        $tab = in_array($request->query('tab'), ['sn', 'qty', 'roll'], true) ? $request->query('tab') : 'sn';

        $paginator = match ($tab) {
            'qty' => $this->paginateQuantity($allowAll, $allowedPopIds, $allowedNodeIds),
            'roll' => $this->paginateRoll($allowAll, $allowedPopIds),
            default => $this->paginateSerial($allowAll, $allowedPopIds, $allowedNodeIds),
        };

        $rows = match ($tab) {
            'qty' => $this->mapQuantityRows($paginator, $locationResolver),
            'roll' => $this->mapRollRows($paginator, $locationResolver),
            default => $this->mapSerialRows($paginator, $locationResolver),
        };

        return view('warehouse.infra-stock.index', [
            'tab' => $tab,
            'rows' => $rows,
            'paginator' => $paginator,
        ]);
    }

    /**
     * @param  list<int>  $allowedPopIds
     * @param  list<int>  $allowedNodeIds
     */
    private function paginateSerial(bool $allowAll, array $allowedPopIds, array $allowedNodeIds): LengthAwarePaginator
    {
        return InventorySerial::query()
            ->status(SerialStatus::DEPLOYED)
            ->with(['item.category'])
            ->when(! $allowAll, function ($query) use ($allowedPopIds, $allowedNodeIds) {
                $query->where(function ($q) use ($allowedPopIds, $allowedNodeIds) {
                    $q->whereIn('current_pop_id', $allowedPopIds)
                        ->orWhereIn('current_mini_pop_id', $allowedPopIds)
                        ->orWhereIn('current_infrastructure_pop_id', $allowedPopIds)
                        ->orWhereIn('current_network_node_id', $allowedNodeIds)
                        ->orWhereIn('current_infrastructure_slot_id', $allowedNodeIds)
                        ->orWhereIn('current_infrastructure_port_id', $allowedNodeIds);
                });
            })
            ->orderBy('serial_number')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function mapSerialRows(LengthAwarePaginator $paginator, InfrastructureLocationResolver $locationResolver): Collection
    {
        $serials = collect($paginator->items());

        // DEPLOY terakhir per SN di halaman ini — satu query `whereIn` buat
        // SEMUA baris halaman (bukan N query per baris). Diurut `id` desc
        // lalu `unique('serial_id')` ambil kemunculan pertama = yang terbaru.
        $latestDeploys = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::DEPLOY->value)
            ->whereIn('serial_id', $serials->pluck('id'))
            ->with('fromTechnician')
            ->orderByDesc('id')
            ->get()
            ->unique('serial_id')
            ->keyBy('serial_id');

        return $serials->map(function (InventorySerial $serial) use ($locationResolver, $latestDeploys) {
            $deploy = $latestDeploys->get($serial->id);

            return [
                'item_name' => $serial->item?->name ?? '-',
                'category_name' => $serial->item?->category?->name ?? '-',
                'serial_number' => $serial->serial_number,
                'breadcrumb' => $this->currentLocationOfCached($serial, $locationResolver)?->breadcrumb() ?? '-',
                'deployed_at' => $deploy?->created_at !== null ? IndonesianDate::date($deploy->created_at) : '-',
                'technician_name' => $deploy?->fromTechnician?->name ?? '-',
                // Klik baris → riwayat lengkap SN itu (Asset Traceability, reuse
                // halaman yang sudah ada — jangan bikin riwayat sendiri di sini).
                'detail_route' => route('warehouse.traceability.index', ['sn' => $serial->serial_number]),
            ];
        })->values();
    }

    /**
     * Sama persis prioritas kolom `InventoryService::currentLocationOf()`
     * (method itu `private`, jadi logiknya diduplikasi SENGAJA di sini, bukan
     * dipanggil langsung) tapi lewat cache per request — kalau N serial di
     * halaman ini numpuk di node yang sama, resolver cuma jalan sekali per
     * node (bukan N kali).
     */
    private function currentLocationOfCached(InventorySerial $serial, InfrastructureLocationResolver $locationResolver): ?InfrastructureLocation
    {
        [$kind, $id] = match (true) {
            $serial->current_infrastructure_port_id !== null => [InfrastructureLocation::PORT, $serial->current_infrastructure_port_id],
            $serial->current_infrastructure_slot_id !== null => [InfrastructureLocation::SLOT, $serial->current_infrastructure_slot_id],
            $serial->current_network_node_id !== null => [InfrastructureLocation::NODE, $serial->current_network_node_id],
            $serial->current_infrastructure_pop_id !== null => [InfrastructureLocation::POP, $serial->current_infrastructure_pop_id],
            $serial->current_mini_pop_id !== null => [InfrastructureLocation::MINI_POP, $serial->current_mini_pop_id],
            default => [null, null],
        };

        return $kind === null ? null : $this->resolveCached($locationResolver, $kind, $id);
    }

    /**
     * @param  list<int>  $allowedPopIds
     * @param  list<int>  $allowedNodeIds
     */
    private function paginateQuantity(bool $allowAll, array $allowedPopIds, array $allowedNodeIds): LengthAwarePaginator
    {
        return InfrastructureBalance::query()
            ->where('qty', '>', 0)
            ->with(['item.category'])
            ->when(! $allowAll, function ($query) use ($allowedPopIds, $allowedNodeIds) {
                $query->where(function ($q) use ($allowedPopIds, $allowedNodeIds) {
                    $q->whereIn('pop_id', $allowedPopIds)
                        ->orWhereIn('network_node_id', $allowedNodeIds)
                        ->orWhereIn('infrastructure_slot_id', $allowedNodeIds)
                        ->orWhereIn('infrastructure_port_id', $allowedNodeIds);
                });
            })
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function mapQuantityRows(LengthAwarePaginator $paginator, InfrastructureLocationResolver $locationResolver): Collection
    {
        return collect($paginator->items())->map(function (InfrastructureBalance $balance) use ($locationResolver) {
            $location = $this->resolveBalanceLocation($balance, $locationResolver);
            $deploy = $location !== null ? $this->latestDeployAtLocation($balance->item_id, $location) : null;

            return [
                'item_name' => $balance->item?->name ?? '-',
                'category_name' => $balance->item?->category?->name ?? '-',
                'unit' => $balance->item?->unit ?? '-',
                'qty' => (float) $balance->qty,
                'breadcrumb' => $location?->breadcrumb() ?? '-',
                'deployed_at' => $deploy?->created_at !== null ? IndonesianDate::date($deploy->created_at) : '-',
                'technician_name' => $deploy?->fromTechnician?->name ?? '-',
            ];
        })->values();
    }

    /**
     * DEPLOY qty terakhir yang nunjuk lokasi yang sama persis dengan saldo
     * ini (kolom `to_*` ledger, bukan `network_node_id`/`pop_id` milik
     * `InfrastructureBalance` — nama kolomnya beda, lihat
     * `InventoryService::transactionLocationColumns()`). Satu query per
     * baris, dibatasi ukuran halaman (≤25) — bukan dihitung ulang dari
     * seluruh ledger.
     */
    private function latestDeployAtLocation(int $itemId, InfrastructureLocation $location): ?InventoryTransaction
    {
        $column = match ($location->kind) {
            InfrastructureLocation::NODE => 'to_network_node_id',
            InfrastructureLocation::SLOT => 'to_infrastructure_slot_id',
            InfrastructureLocation::PORT => 'to_infrastructure_port_id',
            InfrastructureLocation::POP => 'to_infrastructure_pop_id',
            InfrastructureLocation::MINI_POP => 'to_mini_pop_id',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        return InventoryTransaction::query()
            ->where('type', InventoryTransactionType::DEPLOY->value)
            ->where('item_id', $itemId)
            ->where($column, $location->id)
            ->whereNull('roll_id')
            ->with('fromTechnician')
            ->latest('id')
            ->first();
    }

    private function resolveBalanceLocation(InfrastructureBalance $balance, InfrastructureLocationResolver $locationResolver): ?InfrastructureLocation
    {
        [$kind, $id] = match (true) {
            $balance->infrastructure_port_id !== null => [InfrastructureLocation::PORT, $balance->infrastructure_port_id],
            $balance->infrastructure_slot_id !== null => [InfrastructureLocation::SLOT, $balance->infrastructure_slot_id],
            $balance->network_node_id !== null => [InfrastructureLocation::NODE, $balance->network_node_id],
            $balance->pop_id !== null => [InfrastructureLocation::POP, $balance->pop_id],
            default => [null, null],
        };

        return $kind === null ? null : $this->resolveCached($locationResolver, $kind, $id);
    }

    /**
     * Kabel/roll TIDAK punya saldo terpasang per-node seperti barang qty
     * (`InfrastructureBalance` tidak diisi buat roll) — tapi tiap event
     * DEPLOY-nya TETAP mencatat titik pasang persis di ledger (kolom `to_*`
     * yang sama dipakai SN/qty, lihat `InventoryService::transactionLocationColumns()`).
     * Jadi akumulasi di-GROUP BY item+SEMUA kolom lokasi (bukan cuma
     * `to_pop_id`) — kombinasi lokasi yang beda jadi baris terpisah, supaya
     * "kabel ini terpasang di mana" presisi sampai node/slot/port (permintaan
     * user 2026-11-05), bukan cuma level Cabang. Tetap agregat PER KOMBINASI
     * lokasi (banyak roll fisik ke titik yang sama digabung jadi 1 baris) —
     * grouping di SQL, bukan `->get()` seluruh ledger lalu `groupBy()` PHP.
     *
     * @param  list<int>  $allowedPopIds
     */
    private function paginateRoll(bool $allowAll, array $allowedPopIds): LengthAwarePaginator
    {
        return InventoryTransaction::query()
            ->select(['item_id', 'to_pop_id', 'to_mini_pop_id', 'to_infrastructure_pop_id', 'to_network_node_id', 'to_infrastructure_slot_id', 'to_infrastructure_port_id'])
            ->selectRaw('SUM(qty) as total_qty')
            ->where('type', InventoryTransactionType::DEPLOY->value)
            ->whereNotNull('roll_id')
            ->when(! $allowAll, fn ($query) => $query->whereIn('to_pop_id', $allowedPopIds))
            ->groupBy('item_id', 'to_pop_id', 'to_mini_pop_id', 'to_infrastructure_pop_id', 'to_network_node_id', 'to_infrastructure_slot_id', 'to_infrastructure_port_id')
            ->orderBy('item_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function mapRollRows(LengthAwarePaginator $paginator, InfrastructureLocationResolver $locationResolver): Collection
    {
        $itemIds = collect($paginator->items())->pluck('item_id')->unique()->values();
        $items = Item::query()->whereIn('id', $itemIds)->with('category')->get()->keyBy('id');

        return collect($paginator->items())->map(function (InventoryTransaction $row) use ($items, $locationResolver) {
            $item = $items->get($row->item_id);
            $location = $this->resolveRollLocation($row, $locationResolver);

            // Baris ini agregat (banyak roll fisik ke kombinasi lokasi yang
            // sama bisa nyumbang qty) — "tanggal/teknisi" di sini
            // representasi DEPLOY TERAKHIR buat item+lokasi itu, bukan milik
            // satu roll tertentu.
            $latestDeploy = $this->latestRollDeployAtLocation($row);

            return [
                'item_name' => $item?->name ?? '-',
                'category_name' => $item?->category?->name ?? '-',
                'unit' => $item?->unit ?? 'meter',
                'total_qty' => (float) $row->total_qty,
                'breadcrumb' => $location?->breadcrumb() ?? '-',
                'deployed_at' => $latestDeploy?->created_at !== null ? IndonesianDate::date($latestDeploy->created_at) : '-',
                'technician_name' => $latestDeploy?->fromTechnician?->name ?? '-',
                // Agregat per item+lokasi (bukan 1 roll fisik) — gak ada SN/roll
                // tunggal buat ditrace, jadi klik baris nunjuk Riwayat Mutasi
                // (ledger lengkap item itu, dibatasi ke Cabang baris ini),
                // bukan Asset Traceability (itu buat 1 SN/roll spesifik).
                'detail_route' => $item !== null
                    ? route('warehouse.history.index', array_filter(['search' => $item->code, 'pop_id' => $row->to_pop_id]))
                    : null,
            ];
        })->values();
    }

    /** Prioritas sama dengan `currentLocationOfCached()`/`resolveBalanceLocation()`, fallback ke `to_pop_id` (snapshot Cabang) kalau semua kolom chain lain null. */
    private function resolveRollLocation(InventoryTransaction $row, InfrastructureLocationResolver $locationResolver): ?InfrastructureLocation
    {
        [$kind, $id] = match (true) {
            $row->to_infrastructure_port_id !== null => [InfrastructureLocation::PORT, $row->to_infrastructure_port_id],
            $row->to_infrastructure_slot_id !== null => [InfrastructureLocation::SLOT, $row->to_infrastructure_slot_id],
            $row->to_network_node_id !== null => [InfrastructureLocation::NODE, $row->to_network_node_id],
            $row->to_infrastructure_pop_id !== null => [InfrastructureLocation::POP, $row->to_infrastructure_pop_id],
            $row->to_mini_pop_id !== null => [InfrastructureLocation::MINI_POP, $row->to_mini_pop_id],
            $row->to_pop_id !== null => [InfrastructureLocation::POP, $row->to_pop_id],
            default => [null, null],
        };

        return $kind === null ? null : $this->resolveCached($locationResolver, $kind, $id);
    }

    /**
     * Satu query per baris (dibatasi ≤25/halaman) — cocokkan SEMUA kolom
     * lokasi, bukan cuma `to_pop_id`, biar gak numpuk dengan kombinasi
     * lokasi lain yang item-nya sama. `where('col', null)` di Eloquent jadi
     * `col = NULL` yang TIDAK PERNAH match di SQL — kolom yang nilainya null
     * di baris agregat ini wajib lewat `whereNull()`, bukan `where()`.
     */
    private function latestRollDeployAtLocation(InventoryTransaction $row): ?InventoryTransaction
    {
        $match = function ($query, string $column, ?int $value) {
            return $value === null ? $query->whereNull($column) : $query->where($column, $value);
        };

        $query = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::DEPLOY->value)
            ->whereNotNull('roll_id')
            ->where('item_id', $row->item_id);

        $match($query, 'to_pop_id', $row->to_pop_id);
        $match($query, 'to_mini_pop_id', $row->to_mini_pop_id);
        $match($query, 'to_infrastructure_pop_id', $row->to_infrastructure_pop_id);
        $match($query, 'to_network_node_id', $row->to_network_node_id);
        $match($query, 'to_infrastructure_slot_id', $row->to_infrastructure_slot_id);
        $match($query, 'to_infrastructure_port_id', $row->to_infrastructure_port_id);

        return $query->with('fromTechnician')->latest('id')->first();
    }

    private function resolveCached(InfrastructureLocationResolver $locationResolver, string $kind, int $id): ?InfrastructureLocation
    {
        $key = "{$kind}:{$id}";
        if (! array_key_exists($key, $this->locationCache)) {
            try {
                $this->locationCache[$key] = $locationResolver->resolve($kind, $id);
            } catch (\Throwable) {
                $this->locationCache[$key] = null;
            }
        }

        return $this->locationCache[$key];
    }
}
