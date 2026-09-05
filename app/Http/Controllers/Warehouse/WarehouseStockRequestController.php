<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\Item;
use App\Models\Pop;
use App\Models\StockRequest;
use App\Services\EffectiveAccessService;
use App\Services\StockRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Permintaan Stok Cabang→Pusat (2026-09-03) — lihat docblock lengkap di
 * migration `create_stock_requests_table`. Jawaban gap yang ditemukan user:
 * "kalau stok cabang habis dan admin Pusat gak sadar, gimana?" — sebelum
 * ini cuma ada badge Stok Rendah PASIF (butuh Pusat buka halaman & notice
 * sendiri, dan `minimum_stock`-nya pun gak ada form buat ngisi — lihat
 * `WarehouseStockController::createThreshold()`). Di sini cabang ATAS
 * INISIATIF SENDIRI ngirim sinyal eksplisit, tampil di antrean Pusat.
 */
class WarehouseStockRequestController extends Controller
{
    use AuthorizesWarehousePop;

    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();
        $hasAllAccess = $access->hasAllPopAccess($user);
        $allowedPopIds = $hasAllAccess ? [] : $access->getAllowedPopIds($user);

        $statusFilter = $request->query('status', 'pending');

        $requests = StockRequest::query()
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('cabang_pop_id', $allowedPopIds))
            ->when($statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter))
            ->with(['cabangPop', 'requestedBy', 'items.item'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('warehouse.stock-requests.index', compact('requests', 'statusFilter'));
    }

    public function create(EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $cabangPops = Pop::query()->where('type', 'cabang')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')->get();
        $items = Item::active()->with('category')->orderBy('name')->get();

        return view('warehouse.stock-requests.create', compact('cabangPops', 'items'));
    }

    public function store(Request $request, StockRequestService $service, EffectiveAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'cabang_pop_id' => 'required|integer|exists:pops,id',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer|exists:items,id',
            'lines.*.qty_requested' => 'required|numeric|min:0.01',
            'lines.*.lot_no' => 'nullable|string|max:50',
        ]);

        $cabang = Pop::findOrFail($validated['cabang_pop_id']);
        $this->assertPopInScope($cabang, auth()->user(), $access);

        try {
            $stockRequest = $service->create($cabang, $validated['lines'], auth()->user(), $validated['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.stock-requests.show', $stockRequest)
            ->with('success', "Permintaan Stok {$stockRequest->reference_number} berhasil diajukan.");
    }

    public function show(StockRequest $stockRequest, EffectiveAccessService $access): View
    {
        $this->assertPopIdInScope($stockRequest->cabang_pop_id, auth()->user(), $access);

        $stockRequest->load(['cabangPop', 'requestedBy', 'decidedBy', 'items.item']);

        return view('warehouse.stock-requests.show', compact('stockRequest'));
    }

    public function fulfill(Request $request, StockRequest $stockRequest, StockRequestService $service, EffectiveAccessService $access): RedirectResponse
    {
        $this->assertPopIdInScope($stockRequest->cabang_pop_id, auth()->user(), $access);

        $validated = $request->validate(['notes' => 'nullable|string|max:500']);

        try {
            $service->fulfill($stockRequest, auth()->user(), $validated['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.stock-requests.show', $stockRequest)
            ->with('success', 'Permintaan ditandai sudah dipenuhi.');
    }

    public function reject(Request $request, StockRequest $stockRequest, StockRequestService $service, EffectiveAccessService $access): RedirectResponse
    {
        $this->assertPopIdInScope($stockRequest->cabang_pop_id, auth()->user(), $access);

        $validated = $request->validate(['reason' => 'required|string|max:500']);

        try {
            $service->reject($stockRequest, $validated['reason'], auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.stock-requests.show', $stockRequest)
            ->with('success', 'Permintaan ditolak.');
    }

    /**
     * Pembatalan cuma boleh SI PENGAJU sendiri (atau full-access) — beda
     * dari `reject()` yang wewenang Pusat. `warehouse_stock_request.cancel`
     * dipegang role `pop_admin` secara umum, jadi kepemilikan dicek DI SINI,
     * bukan cuma lewat permission (pop_admin cabang A gak boleh batalin
     * punya cabang B walau POP-scope-nya kebetulan nyertain — kasus jarang
     * tapi bukan berarti aman diasumsikan gak pernah terjadi).
     */
    public function cancel(StockRequest $stockRequest, StockRequestService $service, EffectiveAccessService $access): RedirectResponse
    {
        $this->assertPopIdInScope($stockRequest->cabang_pop_id, auth()->user(), $access);

        abort_unless(
            auth()->user()->hasFullAccess() || auth()->id() === $stockRequest->requested_by,
            403,
            'Cuma pengaju sendiri yang boleh membatalkan permintaan ini.'
        );

        try {
            $service->cancel($stockRequest, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.stock-requests.show', $stockRequest)
            ->with('success', 'Permintaan dibatalkan.');
    }
}
