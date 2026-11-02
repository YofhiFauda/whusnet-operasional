<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\StockRequestKind;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\StockRequest;
use App\Services\EffectiveAccessService;
use App\Services\MarketingMerchandiseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketingMerchandiseController extends Controller
{
    use AuthorizesWarehousePop;

    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = $request->user();
        $all = $access->hasAllPopAccess($user);
        $allowed = $all ? [] : $access->getAllowedPopIds($user);
        $filters = $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date',
            'item_id' => 'nullable|integer|exists:items,id',
            'item_category_id' => 'nullable|integer|exists:item_categories,id',
            'cabang_pop_id' => 'nullable|integer|exists:pops,id',
        ]);
        $cabangFilter = isset($filters['cabang_pop_id']) ? (int) $filters['cabang_pop_id'] : null;
        if (isset($filters['item_category_id'])) {
            abort_if(ItemCategory::findOrFail($filters['item_category_id'])->is_operational, 422);
        }
        if ($cabangFilter !== null) {
            $this->assertPopIdInScope($cabangFilter, $user, $access);
            abort_unless(Pop::findOrFail($cabangFilter)->isCabang(), 422);
        }
        $cabangPops = Pop::query()->where('type', 'cabang')
            ->when(! $all, fn ($query) => $query->whereIn('id', $allowed))
            ->orderBy('name')->get();

        $positions = InventoryBalance::query()
            ->whereHas('item.category', fn ($query) => $query->where('is_operational', false)->when(isset($filters['item_category_id']), fn ($category) => $category->whereKey($filters['item_category_id'])))
            ->when(! $all, fn ($query) => $query->whereIn('pop_id', $allowed))
            ->when($cabangFilter !== null, fn ($query) => $query->where('pop_id', $cabangFilter))
            ->when(isset($filters['item_id']), fn ($query) => $query->where('item_id', $filters['item_id']))
            ->with(['pop', 'item'])
            ->orderBy('pop_id')->orderBy('item_id')->get()
            ->groupBy(fn ($row) => $row->pop_id.':'.$row->item_id)
            ->map(fn ($rows) => ['pop' => $rows->first()->pop, 'item' => $rows->first()->item, 'qty' => $rows->sum(fn ($row) => (float) $row->qty)])
            ->values();

        $requests = StockRequest::query()
            ->where('request_kind', StockRequestKind::MARKETING->value)
            ->when(! $all, fn ($query) => $query->whereIn('cabang_pop_id', $allowed))
            ->when($cabangFilter !== null, fn ($query) => $query->where('cabang_pop_id', $cabangFilter))
            ->when(isset($filters['from_date']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->when(isset($filters['item_id']), fn ($query) => $query->whereHas('items', fn ($items) => $items->where('item_id', $filters['item_id'])))
            ->when(isset($filters['item_category_id']), fn ($query) => $query->whereHas('items.item', fn ($item) => $item->where('item_category_id', $filters['item_category_id'])))
            ->with(['cabangPop', 'requestedBy', 'items.item', 'inventoryTransfer.fromPop'])
            ->latest('id')->paginate(20)->withQueryString();

        $receipts = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->whereHas('item.category', fn ($query) => $query->where('is_operational', false)->when(isset($filters['item_category_id']), fn ($category) => $category->whereKey($filters['item_category_id'])))
            ->whereHas('toPop', fn ($query) => $query->where('type', 'pusat'))
            ->when(! $all, fn ($query) => $query->whereIn('to_pop_id', $allowed))
            ->when($cabangFilter !== null, fn ($query) => $query->where('to_pop_id', -1))
            ->when(isset($filters['from_date']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->when(isset($filters['item_id']), fn ($query) => $query->where('item_id', $filters['item_id']))
            ->with(['item', 'toPop', 'createdBy'])
            ->latest('id')->paginate(20, ['*'], 'receipts_page')->withQueryString();

        $movements = InventoryTransaction::query()
            ->whereIn('type', [InventoryTransactionType::RECEIVE->value, InventoryTransactionType::TRANSFER->value])
            ->whereHas('item.category', fn ($query) => $query->where('is_operational', false)->when(isset($filters['item_category_id']), fn ($category) => $category->whereKey($filters['item_category_id'])))
            ->when(! $all, fn ($query) => $query->where(function ($scoped) use ($allowed) {
                $scoped->whereIn('from_pop_id', $allowed)->orWhereIn('to_pop_id', $allowed);
            }))
            ->when($cabangFilter !== null, fn ($query) => $query->where(function ($scoped) use ($cabangFilter) {
                $scoped->where('from_pop_id', $cabangFilter)->orWhere('to_pop_id', $cabangFilter);
            }))
            ->when(isset($filters['from_date']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->when(isset($filters['item_id']), fn ($query) => $query->where('item_id', $filters['item_id']))
            ->with(['item', 'fromPop', 'toPop'])
            ->latest('id')->paginate(30, ['*'], 'mutasi_page')->withQueryString();

        $summaryRows = [];
        foreach (['to_pop_id' => 'incoming', 'from_pop_id' => 'outgoing'] as $popColumn => $direction) {
            $ledger = InventoryTransaction::query()
                ->whereIn('type', [InventoryTransactionType::RECEIVE->value, InventoryTransactionType::TRANSFER->value])
                ->whereHas('item.category', fn ($query) => $query->where('is_operational', false)->when(isset($filters['item_category_id']), fn ($category) => $category->whereKey($filters['item_category_id'])))
                ->whereNotNull($popColumn)
                ->when(! $all, fn ($query) => $query->whereIn($popColumn, $allowed))
                ->when($cabangFilter !== null, fn ($query) => $query->where($popColumn, $cabangFilter))
                ->when(isset($filters['item_id']), fn ($query) => $query->where('item_id', $filters['item_id']));

            if (isset($filters['from_date'])) {
                $before = (clone $ledger)->whereDate('created_at', '<', $filters['from_date'])
                    ->selectRaw("$popColumn as pop_id, item_id, SUM(qty) as total")
                    ->groupBy($popColumn, 'item_id')->get();
                foreach ($before as $row) {
                    $key = $row->pop_id.':'.$row->item_id;
                    $summaryRows[$key] ??= ['pop_id' => $row->pop_id, 'item_id' => $row->item_id, 'opening' => 0.0, 'incoming' => 0.0, 'outgoing' => 0.0];
                    $summaryRows[$key]['opening'] += $direction === 'incoming' ? (float) $row->total : -(float) $row->total;
                }
            }

            $during = $ledger
                ->when(isset($filters['from_date']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from_date']))
                ->when(isset($filters['to_date']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to_date']))
                ->selectRaw("$popColumn as pop_id, item_id, SUM(qty) as total")
                ->groupBy($popColumn, 'item_id')->get();
            foreach ($during as $row) {
                $key = $row->pop_id.':'.$row->item_id;
                $summaryRows[$key] ??= ['pop_id' => $row->pop_id, 'item_id' => $row->item_id, 'opening' => 0.0, 'incoming' => 0.0, 'outgoing' => 0.0];
                $summaryRows[$key][$direction] += (float) $row->total;
            }
        }

        $summaryPops = Pop::query()->whereIn('id', array_column($summaryRows, 'pop_id'))->get()->keyBy('id');
        $summaryItems = Item::query()->whereIn('id', array_column($summaryRows, 'item_id'))->get()->keyBy('id');
        $stockSummary = collect($summaryRows)->map(function ($row) use ($summaryPops, $summaryItems) {
            return [
                'pop' => $summaryPops[$row['pop_id']],
                'item' => $summaryItems[$row['item_id']],
                'opening' => $row['opening'],
                'incoming' => $row['incoming'],
                'outgoing' => $row['outgoing'],
                'closing' => $row['opening'] + $row['incoming'] - $row['outgoing'],
            ];
        })->sortBy(fn ($row) => $row['pop']->name.':'.$row['item']->name)->values();

        $filterItems = Item::query()
            ->whereHas('category', fn ($query) => $query->where('is_operational', false)->when(isset($filters['item_category_id']), fn ($category) => $category->whereKey($filters['item_category_id'])))
            ->orderBy('name')->get();

        $filterCategories = ItemCategory::active()->where('is_operational', false)->ordered()->get();

        return view('warehouse.marketing.index', compact('positions', 'requests', 'receipts', 'movements', 'filterItems', 'filterCategories', 'filters', 'cabangPops', 'stockSummary'));
    }

    public function export(Request $request, EffectiveAccessService $access): StreamedResponse
    {
        $filters = $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date',
            'item_id' => 'nullable|integer|exists:items,id',
            'item_category_id' => 'nullable|integer|exists:item_categories,id',
            'cabang_pop_id' => 'nullable|integer|exists:pops,id',
        ]);
        $all = $access->hasAllPopAccess($request->user());
        $allowed = $all ? [] : $access->getAllowedPopIds($request->user());
        $cabangFilter = isset($filters['cabang_pop_id']) ? (int) $filters['cabang_pop_id'] : null;
        if (isset($filters['item_category_id'])) {
            abort_if(ItemCategory::findOrFail($filters['item_category_id'])->is_operational, 422);
        }
        if ($cabangFilter !== null) {
            $this->assertPopIdInScope($cabangFilter, $request->user(), $access);
            abort_unless(Pop::findOrFail($cabangFilter)->isCabang(), 422);
        }

        $query = InventoryTransaction::query()
            ->whereIn('type', [InventoryTransactionType::RECEIVE->value, InventoryTransactionType::TRANSFER->value])
            ->whereHas('item.category', fn ($query) => $query->where('is_operational', false)->when(isset($filters['item_category_id']), fn ($category) => $category->whereKey($filters['item_category_id'])))
            ->when(! $all, fn ($query) => $query->where(function ($scoped) use ($allowed) {
                $scoped->whereIn('from_pop_id', $allowed)->orWhereIn('to_pop_id', $allowed);
            }))
            ->when($cabangFilter !== null, fn ($query) => $query->where(function ($scoped) use ($cabangFilter) {
                $scoped->where('from_pop_id', $cabangFilter)->orWhere('to_pop_id', $cabangFilter);
            }))
            ->when(isset($filters['from_date']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->when(isset($filters['item_id']), fn ($query) => $query->where('item_id', $filters['item_id']))
            ->with(['item', 'fromPop', 'toPop']);

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Tanggal', 'Referensi', 'Lokasi', 'Barang', 'Arah', 'Jumlah']);
            $safe = static fn (?string $value): string => preg_match('/^[=+\-@]/', $value ?? '') ? "'".$value : ($value ?? '');

            $query->chunkById(500, function ($rows) use ($output, $safe): void {
                foreach ($rows as $row) {
                    $location = $row->toPop ?? $row->fromPop;
                    fputcsv($output, [
                        $row->created_at->format('Y-m-d H:i:s'),
                        $safe($row->reference_number),
                        $safe($location?->name),
                        $safe($row->item->name),
                        $row->to_pop_id ? 'Masuk' : 'Keluar',
                        ($row->to_pop_id ? '+' : '-').number_format((float) $row->qty, 2, '.', ''),
                    ]);
                }
            });
            fclose($output);
        }, 'mutasi-barang-nonoperasional.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(Request $request, EffectiveAccessService $access): View
    {
        $user = $request->user();
        $all = $access->hasAllPopAccess($user);
        $cabangPops = Pop::query()->where('type', 'cabang')
            ->when(! $all, fn ($query) => $query->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')->get();
        $pusatPops = Pop::query()->where('type', 'pusat')->orderBy('name')->get();
        $items = Item::active()->where('tracking_type', TrackingType::QUANTITY->value)->whereHas('category', fn ($query) => $query->where('is_operational', false))
            ->with('category')->orderBy('name')->get();

        return view('warehouse.marketing.create', compact('cabangPops', 'pusatPops', 'items'));
    }

    public function createReceive(Request $request, EffectiveAccessService $access): View
    {
        $user = $request->user();
        $pusatPops = Pop::query()->where('type', 'pusat')
            ->when(! $access->hasAllPopAccess($user), fn ($query) => $query->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')->get();
        $items = Item::active()->where('tracking_type', TrackingType::QUANTITY->value)->whereHas('category', fn ($query) => $query->where('is_operational', false))
            ->with('category')->orderBy('name')->get();

        return view('warehouse.marketing.receive', compact('pusatPops', 'items'));
    }

    public function storeReceive(Request $request, MarketingMerchandiseService $service, EffectiveAccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'pusat_pop_id' => 'required|integer|exists:pops,id',
            'item_id' => 'required|integer|exists:items,id',
            'qty' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        $pusat = Pop::findOrFail($data['pusat_pop_id']);
        $this->assertPopInScope($pusat, $request->user(), $access);

        try {
            $service->receive($pusat, Item::findOrFail($data['item_id']), (float) $data['qty'], $request->user(), $data['notes'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('warehouse.marketing.index')->with('success', 'Barang nonoperasional masuk Pusat berhasil dicatat.');
    }

    public function store(Request $request, MarketingMerchandiseService $service, EffectiveAccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'from_pop_id' => 'required|integer|exists:pops,id',
            'cabang_pop_id' => 'required|integer|exists:pops,id',
            'item_id' => 'required|integer|exists:items,id',
            'qty' => 'required|numeric|min:0.01',
            'request_key' => 'required|uuid',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cabang = Pop::findOrFail($data['cabang_pop_id']);
        $this->assertPopInScope($cabang, $request->user(), $access);

        try {
            $stockRequest = $service->request(
                Pop::findOrFail($data['from_pop_id']),
                $cabang,
                Item::findOrFail($data['item_id']),
                (float) $data['qty'],
                $request->user(),
                $data['request_key'],
                $data['notes'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('warehouse.marketing.index')
            ->with('success', "Barang {$stockRequest->reference_number} dipindahkan ke {$cabang->name}.");
    }
}
