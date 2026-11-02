<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\DeviceOwnership;
use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\BranchInventorySale;
use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Services\BranchInventorySaleReportService;
use App\Services\BranchInventorySaleService;
use App\Services\EffectiveAccessService;
use App\Support\RupiahInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // User ber-scope banyak Cabang melihat dokumen dua arah. Satu angka gabungan
        // bukan penjualan maupun pembelian siapa pun, jadi dipisah per arah.
        $posted = (clone $query)->whereNotNull('posted_at');
        $crossCheck = match (true) {
            ! $canSeePrice => null,
            $allowedPopIds === null => ['Total nominal final sesuai filter' => $posted->sum('posted_total')],
            default => [
                'Penjualan (Cabang Anda sebagai penjual)' => (clone $posted)->whereIn('from_pop_id', $allowedPopIds)->sum('posted_total'),
                'Pembelian (Cabang Anda sebagai pembeli)' => (clone $posted)->whereIn('to_pop_id', $allowedPopIds)->sum('posted_total'),
            ],
        };

        return view('warehouse.branch-sales.index', [
            'sales' => $query->latest('id')->paginate(25)->withQueryString(),
            'crossCheck' => $crossCheck,
            'pops' => Pop::query()->where('type', 'cabang')->when($allowedPopIds !== null, fn ($q) => $q->whereIn('id', $allowedPopIds))->orderBy('name')->get(),
            'canSeePrice' => $canSeePrice,
        ]);
    }

    public function report(Request $request, EffectiveAccessService $access, BranchInventorySaleReportService $reports): View
    {
        $user = $request->user();
        $allowedPopIds = $access->hasAllPopAccess($user) ? null : $access->getAllowedPopIds($user);
        $rows = $reports->rows($this->reportFilters($request), $allowedPopIds);
        $canSeePrice = $user->hasPermission('warehouse_branch_sale_price.view');

        $perPage = 50;
        $page = max(1, $request->integer('page', 1));
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('warehouse.branch-sales.report', [
            'rows' => $paginator,
            'summary' => $canSeePrice ? $reports->summary($rows, $allowedPopIds) : null,
            'statusLabels' => BranchInventorySaleReportService::STATUS_LABELS,
            'pops' => Pop::query()->where('type', 'cabang')->when($allowedPopIds !== null, fn ($q) => $q->whereIn('id', $allowedPopIds))->orderBy('name')->get(),
            'canSeePrice' => $canSeePrice,
        ]);
    }

    public function exportReport(Request $request, EffectiveAccessService $access, BranchInventorySaleReportService $reports): StreamedResponse
    {
        $user = $request->user();
        $allowedPopIds = $access->hasAllPopAccess($user) ? null : $access->getAllowedPopIds($user);
        $rows = $reports->rows($this->reportFilters($request), $allowedPopIds);
        $canSeePrice = $user->hasPermission('warehouse_branch_sale_price.view');

        return response()->streamDownload(function () use ($rows, $canSeePrice): void {
            $out = fopen('php://output', 'w');
            $header = ['Dokumen', 'Tanggal', 'Penjual', 'Pembeli', 'Nomor SN', 'Barang', 'Kondisi', 'Status'];
            fputcsv($out, $canSeePrice ? [...$header, 'HPP Penjual', 'Harga Jual'] : $header);
            // Awalan = + - @ dinetralkan: nama barang/SN berasal dari input pengguna.
            $safe = static fn (?string $value): string => preg_match('/^[=+\-@]/', $value ?? '') ? "'".$value : ($value ?? '');

            foreach ($rows as $row) {
                $line = [
                    $row['reference_number'],
                    $row['created_at']->format('Y-m-d H:i'),
                    $safe($row['from_pop']->name),
                    $safe($row['to_pop']->name),
                    $safe($row['serial_number']),
                    $safe($row['item_name']),
                    ($row['condition'] ?? ItemCondition::NEW)->label(),
                    BranchInventorySaleReportService::STATUS_LABELS[$row['status']],
                ];
                if ($canSeePrice) {
                    $line[] = $row['cost_price'] === null ? '' : number_format((float) $row['cost_price'], 2, '.', '');
                    $line[] = number_format((float) $row['sale_price'], 2, '.', '');
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, 'jual-beli-antar-cabang-per-sn.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{from_pop_id:?int, to_pop_id:?int, date_from:?string, date_to:?string, status:?string, q:?string}
     */
    private function reportFilters(Request $request): array
    {
        $data = $request->validate([
            'from_pop_id' => ['nullable', 'integer'],
            'to_pop_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(BranchInventorySaleReportService::STATUS_LABELS))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return [
            'from_pop_id' => $data['from_pop_id'] ?? null,
            'to_pop_id' => $data['to_pop_id'] ?? null,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'status' => $data['status'] ?? null,
            'q' => isset($data['q']) ? trim($data['q']) : null,
        ];
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
                // Cermin InventorySerial::isClearedForIssue() di SQL (bukan filter PHP
                // setelah limit) — SN tak layak gak lagi makan jatah baris SN layak.
                ->where(fn ($q) => $q->whereNull('condition')
                    ->orWhere('condition', ItemCondition::NEW->value)
                    ->orWhere(fn ($used) => $used->where('condition', ItemCondition::USED_GOOD->value)->whereNotNull('condition_checked_at')))
                ->orderBy('serial_number')->paginate(100)->withQueryString()
            : null;

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
        // Harga diketik gaya Indonesia ("230.000", "230000,5"). Tanpa normalisasi
        // server, `230.000` dibaca desimal Inggris (Rp230) atau ditolak `decimal:0,2`
        // — dan form bisa dikirim tanpa JS masking, jadi tidak cukup di klien.
        $selectedLines = array_map(fn (array $line) => RupiahInput::parseKeys($line, 'sale_price'), $selectedLines);
        // Kunci (id SN) sengaja dipertahankan: create.blade.php membaca old('lines.{id}.*'),
        // jadi kalau diindeks ulang, centang dan harga hilang tiap validasi gagal.
        $request->merge(['lines' => $selectedLines]);
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
        ]);
    }

    public function receive(Request $request, BranchInventorySale $sale, EffectiveAccessService $access, BranchInventorySaleService $service): RedirectResponse
    {
        $this->assertPopIdInScope($sale->to_pop_id, $request->user(), $access);
        $this->assertPopAdminNotBlockedByActivePicGudang($sale->toPop, $request->user());
        $this->assertPicGudangCanActOnPop($sale->toPop, $request->user());
        try {
            $service->receiveAll($sale, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('warehouse.branch-sales.show', $sale)->with('success', 'Semua SN telah diterima dan masuk stok Cabang pembeli.');
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
