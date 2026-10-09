<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\DeviceRetrievalSource;
use App\Http\Controllers\Controller;
use App\Models\DeviceRetrievalLog;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Riwayat Pengambilan Alat (ADHOC-88) — log per SN: modem apa, dari pelanggan
 * mana, diambil TEKNISI siapa, diterima gudang siapa, kapan. Terpisah dari
 * Riwayat Mutasi (ledger per dokumen) karena pertanyaannya beda: di sini
 * orang mencari "siapa yang menarik modem ini", bukan "apa saja mutasinya".
 *
 * Sumber data `device_retrieval_logs`, yang sengaja TIDAK ikut berubah saat
 * `device_retrieved_at` direset (Langganan Lagi) atau SN dipakai pelanggan
 * lain. Scope POP lewat gudang tujuan (`warehouse_pop_id`).
 */
class WarehouseRetrievalHistoryController extends Controller
{
    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'technician' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:transit,diterima'],
            'source' => ['nullable', 'in:'.implode(',', array_column(DeviceRetrievalSource::cases(), 'value'))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $scoped = fn ($query) => $query->when(
            ! $access->hasAllPopAccess($user),
            fn ($q) => $q->whereIn('warehouse_pop_id', $access->getAllowedPopIds($user))
        );

        $logs = $scoped(DeviceRetrievalLog::query())
            ->with(['customer', 'item', 'retrievedBy', 'receivedBy', 'warehousePop', 'task.deviceRetrieval'])
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.$term.'%';
                $q->where(fn ($w) => $w->where('serial_number', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', $like)
                        ->orWhere('customer_code', 'like', $like)
                        ->orWhere('cid', 'like', $like)));
            })
            ->when($filters['technician'] ?? null, fn ($q, $id) => $q->where('retrieved_by', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $status === 'transit'
                ? $q->whereNull('received_at')
                : $q->whereNotNull('received_at'))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('retrieved_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('retrieved_at', '<=', $to))
            ->orderByDesc('retrieved_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Dropdown teknisi hanya berisi orang yang pernah tercatat menarik
        // modem di gudang yang boleh dilihat aktor — bukan seluruh user.
        $technicians = User::query()
            ->whereIn('id', $scoped(DeviceRetrievalLog::query())->whereNotNull('retrieved_by')->select('retrieved_by'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $sources = DeviceRetrievalSource::cases();

        return view('warehouse.retrievals.index', compact('logs', 'technicians', 'sources', 'filters'));
    }
}
