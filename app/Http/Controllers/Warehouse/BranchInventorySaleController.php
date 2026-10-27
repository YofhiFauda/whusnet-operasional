<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\DeviceOwnership;
use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\BranchInventorySale;
use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Services\BranchInventorySaleService;
use App\Services\EffectiveAccessService;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;

class BranchInventorySaleController extends Controller
{
    use AuthorizesWarehousePop;

    public function index(Request $request, EffectiveAccessService $access): View
    {
        $request->validate([
            'from_pop_id' => ['nullable', 'integer'],
            'to_pop_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $user = $request->user();
        $allowedPopIds = $access->hasAllPopAccess($user) ? null : $access->getAllowedPopIds($user);
        $query = BranchInventorySale::query()
            ->with(['fromPop', 'toPop', 'transfer'])
            ->withCount('lines')
            ->when($allowedPopIds !== null, fn ($q) => $q->where(fn ($side) => $side->whereIn('from_pop_id', $allowedPopIds)->orWhereIn('to_pop_id', $allowedPopIds)))
            ->when($request->filled('from_pop_id'), fn ($q) => $q->where('from_pop_id', $request->integer('from_pop_id')))
            ->when($request->filled('to_pop_id'), fn ($q) => $q->where('to_pop_id', $request->integer('to_pop_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')));

        $canSeePrice = $user->hasPermission('warehouse_branch_sale_price.view');

        return view('warehouse.branch-sales.index', [
            'sales' => $query->latest('id')->paginate(25)->withQueryString(),
            'totalForCrossCheck' => $canSeePrice ? (clone $query)->whereNotNull('posted_at')->sum('posted_total') : null,
            'pops' => Pop::query()->where('type', 'cabang')->when($allowedPopIds !== null, fn ($q) => $q->whereIn('id', $allowedPopIds))->orderBy('name')->get(),
            'canSeePrice' => $canSeePrice,
        ]);
    }

    public function create(Request $request, EffectiveAccessService $access): View
    {
        $user = $request->user();
        $sourcePops = Pop::query()->where('type', 'cabang')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')->get();
        $source = $sourcePops->firstWhere('id', $request->integer('from_pop_id'));
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $serials = $source
            ? InventorySerial::query()->with('item')->where('current_pop_id', $source->id)
                ->where('status', SerialStatus::AVAILABLE->value)->whereNull('current_technician_id')->where(function ($q) {
                    $q->whereNull('ownership')->orWhere('ownership', DeviceOwnership::COMPANY->value);
                })
                ->whereNotIn('id', InventorySerialSale::query()->select('inventory_serial_id'))
                ->when($search !== '', fn ($q) => $q->where('serial_number', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->whereHas('item', fn ($q) => $q->where('tracking_type', TrackingType::SERIALIZED->value))
                ->orderBy('serial_number')->limit(100)->get()
                ->filter(fn (InventorySerial $serial) => $serial->isClearedForIssue())
            : collect();

        return view('warehouse.branch-sales.create', [
            'sourcePops' => $sourcePops,
            'destinationPops' => Pop::query()->where('type', 'cabang')->orderBy('name')->get(),
            'source' => $source,
            'search' => $search,
            'serials' => $serials,
        ]);
    }

    public function store(Request $request, EffectiveAccessService $access, BranchInventorySaleService $service): RedirectResponse
    {
        $selectedLines = array_filter($request->input('lines', []), fn ($line) => is_array($line) && isset($line['serial_id']));
        $request->merge(['lines' => array_values($selectedLines)]);
        $data = $request->validate([
            'from_pop_id' => ['required', 'integer', 'exists:pops,id'],
            'to_pop_id' => ['required', 'integer', 'different:from_pop_id', 'exists:pops,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.serial_id' => ['required', 'integer', 'exists:inventory_serials,id'],
            'lines.*.sale_price' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);
        $from = Pop::findOrFail($data['from_pop_id']);
        $to = Pop::findOrFail($data['to_pop_id']);
        $this->assertPopInScope($from, $request->user(), $access);
        $this->assertPicGudangCanActOnPop($from, $request->user());

        try {
            $sale = $service->create($from, $to, array_values($data['lines']), $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('warehouse.branch-sales.show', $sale)->with('success', 'Dokumen jual beli dibuat dan SN dikirim.');
    }

    public function show(BranchInventorySale $sale, Request $request, EffectiveAccessService $access): View
    {
        $this->assertVisible($sale, $request, $access);
        $sale->load(['fromPop', 'toPop', 'transfer', 'lines.serial.item', 'createdBy']);
        $dispatches = InventoryTransaction::query()
            ->where('inventory_transfer_id', $sale->inventory_transfer_id)
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->whereNotNull('from_pop_id')->whereNotNull('serial_id')
            ->get()->keyBy('serial_id');
        $receivedIds = InventoryTransaction::query()
            ->where('inventory_transfer_id', $sale->inventory_transfer_id)
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->where('to_pop_id', $sale->to_pop_id)
            ->whereNotNull('serial_id')->pluck('serial_id')->all();

        return view('warehouse.branch-sales.show', [
            'sale' => $sale,
            'dispatches' => $dispatches,
            'receivedIds' => $receivedIds,
            'canSeePrice' => $request->user()->hasPermission('warehouse_branch_sale_price.view'),
            'canReceive' => $request->user()->hasPermission('warehouse_branch_sale.receive') && $this->canActOnPop($sale->to_pop_id, $request, $access),
            'canClose' => $request->user()->hasPermission('warehouse_branch_sale.create') && $this->canActOnPop($sale->from_pop_id, $request, $access),
        ]);
    }

    public function receive(Request $request, BranchInventorySale $sale, EffectiveAccessService $access, BranchInventorySaleService $service): RedirectResponse
    {
        $this->assertPopIdInScope($sale->to_pop_id, $request->user(), $access);
        $this->assertPopAdminNotBlockedByActivePicGudang($sale->toPop, $request->user());
        $this->assertPicGudangCanActOnPop($sale->toPop, $request->user());
        $data = $request->validate([
            'serial_ids' => ['required', 'array', 'min:1'],
            'serial_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        try {
            $service->receive($sale, array_map('intval', $data['serial_ids']), $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('warehouse.branch-sales.show', $sale)->with('success', 'SN yang diterima telah masuk stok Cabang pembeli.');
    }

    public function closeMissing(Request $request, BranchInventorySale $sale, EffectiveAccessService $access, BranchInventorySaleService $service): RedirectResponse
    {
        $this->assertPopIdInScope($sale->from_pop_id, $request->user(), $access);
        $this->assertPicGudangCanActOnPop($sale->fromPop, $request->user());
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'evidence' => ['required', 'image', 'max:4096'],
        ]);
        $evidencePath = FileUploadService::uploadWarehouseEvidence($request->file('evidence'), 'lost-branch-sale');
        try {
            $service->closeMissing($sale, $data['reason'], $evidencePath, $request->user());
        } catch (InvalidArgumentException $exception) {
            Storage::disk('public')->delete($evidencePath);

            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('warehouse.branch-sales.show', $sale)->with('success', 'Sisa SN ditutup sebagai barang hilang.');
    }

    private function assertVisible(BranchInventorySale $sale, Request $request, EffectiveAccessService $access): void
    {
        if ($access->hasAllPopAccess($request->user())) {
            return;
        }
        $allowed = $access->getAllowedPopIds($request->user());
        abort_unless(in_array($sale->from_pop_id, $allowed, true) || in_array($sale->to_pop_id, $allowed, true), 403);
    }

    private function canActOnPop(int $popId, Request $request, EffectiveAccessService $access): bool
    {
        $user = $request->user();
        if (! $access->hasAllPopAccess($user) && ! in_array($popId, $access->getAllowedPopIds($user), true)) {
            return false;
        }

        return $user->role?->code !== 'pic_gudang' || $user->isPicGudangOf(Pop::findOrFail($popId));
    }
}
