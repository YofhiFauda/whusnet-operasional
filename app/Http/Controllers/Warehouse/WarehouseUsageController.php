<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\MaterialKind;
use App\Http\Controllers\Controller;
use App\Models\FopTask;
use App\Models\InventoryTransaction;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\TaskMaterial;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureLocationResolver;
use App\Support\InfrastructureLocation;
use App\Support\LikeSearch;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap Pemakaian Material Lapangan (Daily Material Consumption Report).
 *
 * Menjawab kebutuhan Admin Gudang & PIC Cabang untuk memantau konsumsi harian:
 * - Berapa modem yang terpasang kemarin/hari ini?
 * - Berapa meter kabel dropcore yang dipotong dari roll?
 * - Berapa pcs patchcord/konektor/aksesori pasif yang dipakai teknisi?
 *
 * Menggabungkan tiga sumber data konsumsi lapangan:
 * 1. `task_materials` (kind=TERPAKAI) — kabel per-roll (meter) dan material pasif ke pelanggan.
 * 2. `inventory_transactions` (type=INSTALL) — modem ONT ke pelanggan (serialized).
 * 3. `inventory_transactions` (type=DEPLOY) — barang terpasang netap ke titik infrastruktur
 *    (OLT/Slot/PON/ODP, bukan ke pelanggan). Sebelumnya cuma kelihatan di drawer "Detail
 *    Mutasi Material" (riwayat ledger mentah, gampang tertimbun) — di sini lokasi pasang
 *    (breadcrumb `InfrastructureLocationResolver`), pelaksana, waktu, dan task-nya digabung
 *    ke laporan yang sama (laporan 2026-10-22).
 */
class WarehouseUsageController extends Controller
{
    public function index(Request $request, EffectiveAccessService $access, InfrastructureLocationResolver $locationResolver): View
    {
        $user = auth()->user();

        $pops = Pop::query()
            ->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $popIds = $pops->pluck('id')->all();
        $categories = ItemCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get();

        $filters = $this->resolveFilters($request, $popIds);
        $usageEntries = $this->fetchUnifiedUsageEntries($filters, $popIds, $locationResolver);

        // Filter pencarian teks & kategori
        $filteredEntries = $this->applySearchAndCategoryFilters($usageEntries, $filters);

        // Agregasi KPI
        $kpi = $this->calculateKpi($filteredEntries);

        // Agregasi Tabel 1: Rekap per Item
        $itemSummaries = $this->buildItemSummaries($filteredEntries);

        // Paginasi Tabel 2: Log Rincian
        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginatedLogs = new LengthAwarePaginator(
            $filteredEntries->forPage($page, $perPage)->values(),
            $filteredEntries->count(),
            $perPage,
            $page,
            ['path' => route('warehouse.usage.index'), 'query' => $request->query()]
        );

        return view('warehouse.usage.index', [
            'pops' => $pops,
            'categories' => $categories,
            'preset' => $filters['preset'],
            'dateFrom' => $filters['start']->format('Y-m-d'),
            'dateTo' => $filters['end']->format('Y-m-d'),
            'popFilter' => $filters['pop_id'],
            'categoryFilter' => $filters['category_id'],
            'search' => $filters['search'],
            'kpi' => $kpi,
            'itemSummaries' => $itemSummaries,
            'logs' => $paginatedLogs,
            'periodLabel' => $filters['period_label'],
        ]);
    }

    public function export(Request $request, EffectiveAccessService $access, InfrastructureLocationResolver $locationResolver): StreamedResponse
    {
        $user = auth()->user();

        $pops = Pop::query()
            ->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $popIds = $pops->pluck('id')->all();
        $filters = $this->resolveFilters($request, $popIds);
        $usageEntries = $this->fetchUnifiedUsageEntries($filters, $popIds, $locationResolver);
        $filteredEntries = $this->applySearchAndCategoryFilters($usageEntries, $filters);

        $filename = 'rekap-pemakaian-material-'.$filters['start']->format('Ymd').'-sd-'.$filters['end']->format('Ymd').'.xlsx';

        return response()->streamDownload(function () use ($filteredEntries) {
            $writer = SimpleExcelWriter::create('php://output', 'xlsx');

            $headerStyle = (new Style)
                ->setFontBold()
                ->setFontSize(11)
                ->setBackgroundColor(Color::rgb(224, 242, 254)); // Sky-100

            // Sheet 1: Rincian Log Pemakaian
            $writer->nameCurrentSheet('Rincian Pemakaian');
            $writer->addHeader([
                'Waktu Pemakaian',
                'Gudang POP / Cabang',
                'Nama Barang',
                'Kode / SKU',
                'Kategori',
                'Qty Terpakai',
                'Satuan',
                'Identitas (Roll ID / SN / Lot)',
                'Teknisi Pelaksana',
                'No. Tiket Tugas',
                'Tipe Tugas',
                'Nama Pelanggan',
                'Lokasi Infrastruktur',
                'Catatan',
            ], $headerStyle);

            foreach ($filteredEntries as $entry) {
                $writer->addRow([
                    $entry['created_at']->format('d/m/Y H:i'),
                    $entry['pop_name'],
                    $entry['item_name'],
                    $entry['item_code'],
                    $entry['category_name'],
                    $entry['qty'],
                    $entry['unit'],
                    $entry['identifier'],
                    $entry['technician_name'],
                    $entry['task_number'],
                    $entry['task_category'],
                    $entry['customer_name'],
                    $entry['location_breadcrumb'] ?? '-',
                    $entry['note'] ?? '-',
                ]);
            }

            // Sheet 2: Rekapitulasi per Barang
            $itemSummaries = $this->buildItemSummaries($filteredEntries);
            $writer->addNewSheetAndMakeItCurrent('Rekap per Barang');
            $writer->addHeader([
                'Nama Barang',
                'Kode / SKU',
                'Kategori',
                'Total Terpakai',
                'Satuan',
                'Jumlah Tiket / Tugas',
                'Roll / SN Terpakai',
            ], $headerStyle);

            foreach ($itemSummaries as $summary) {
                $writer->addRow([
                    $summary['item_name'],
                    $summary['item_code'],
                    $summary['category_name'],
                    $summary['total_qty'],
                    $summary['unit'],
                    $summary['task_count'],
                    implode(', ', $summary['identifiers']) ?: '-',
                ]);
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<int>  $popIds
     * @return array{preset: string, start: Carbon, end: Carbon, pop_id: ?int, category_id: ?int, search: string, period_label: string}
     */
    private function resolveFilters(Request $request, array $popIds): array
    {
        $preset = $request->query('preset', 'today');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $now = Carbon::now();

        switch ($preset) {
            case 'all':
                // Tanpa batas tanggal — laporan ini defaultnya date-scoped
                // ('today'), beda sama Task FOP/Riwayat Mutasi yang nampilin
                // histori penuh tanpa filter waktu. User ngira itu bug "cuma
                // alat terakhir" padahal cuma gara-gara filter default sempit
                // (laporan 2026-10-01). Start dipatok ke tanggal paling awal
                // data biar whereBetween tetap kebatas rentang valid.
                $start = Carbon::createFromDate(2000, 1, 1)->startOfDay();
                $end = $now->copy()->endOfDay();
                $periodLabel = 'Semua Waktu';
                break;
            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                $periodLabel = 'Kemarin ('.$start->translatedFormat('d F Y').')';
                break;
            case 'last_7_days':
                $start = $now->copy()->subDays(6)->startOfDay();
                $end = $now->copy()->endOfDay();
                $periodLabel = '7 Hari Terakhir ('.$start->translatedFormat('d M').' - '.$end->translatedFormat('d M Y').')';
                break;
            case 'this_month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $periodLabel = 'Bulan Ini ('.$start->translatedFormat('F Y').')';
                break;
            case 'custom':
                $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $now->copy()->startOfDay();
                $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : $now->copy()->endOfDay();
                $periodLabel = $start->translatedFormat('d M Y').' s/d '.$end->translatedFormat('d M Y');
                break;
            case 'today':
            default:
                $preset = 'today';
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $periodLabel = 'Hari Ini ('.$start->translatedFormat('d F Y').')';
                break;
        }

        $popFilter = $request->integer('pop_id') ?: null;
        if ($popFilter && ! in_array($popFilter, $popIds, true)) {
            $popFilter = null;
        }

        $categoryFilter = $request->integer('category_id') ?: null;
        $search = LikeSearch::sanitize((string) $request->query('search', ''));

        return [
            'preset' => $preset,
            'start' => $start,
            'end' => $end,
            'pop_id' => $popFilter,
            'category_id' => $categoryFilter,
            'search' => $search,
            'period_label' => $periodLabel,
        ];
    }

    /**
     * @param  array{preset: string, start: Carbon, end: Carbon, pop_id: ?int, category_id: ?int, search: string}  $filters
     * @param  list<int>  $popIds
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchUnifiedUsageEntries(array $filters, array $popIds, InfrastructureLocationResolver $locationResolver): Collection
    {
        $scopedPops = $filters['pop_id'] ? [$filters['pop_id']] : $popIds;
        $start = $filters['start'];
        $end = $filters['end'];

        $entries = collect();

        // 1. Ambil data material pasif & kabel per-roll dari task_materials (kind=TERPAKAI)
        $taskMaterials = TaskMaterial::query()
            ->where('kind', MaterialKind::TERPAKAI->value)
            ->whereBetween('created_at', [$start, $end])
            ->with([
                'item.category',
                'category',
                'fopTask.pop',
                'fopTask.customer.pop',
                'fopTask.technicians',
                'fopTask.task.creqDetail',
                'customer.pop',
                'recordedBy',
            ])
            ->where(function ($q) use ($scopedPops) {
                $q->whereHas('fopTask', fn ($tq) => $tq->whereIn('pop_id', $scopedPops))
                    ->orWhereHas('customer', fn ($cq) => $cq->whereIn('pop_id', $scopedPops));
            })
            ->get();

        foreach ($taskMaterials as $tm) {
            $popName = $tm->fopTask?->pop?->name
                ?? $tm->customer?->pop?->name
                ?? '-';

            $itemName = $tm->item?->name ?? $tm->item_name ?? 'Material';
            $itemCode = $tm->item?->code ?? '-';
            $categoryName = $tm->item?->category?->name ?? $tm->category?->name ?? $tm->category_label ?? 'Lainnya';
            $categoryCode = $tm->item?->category?->code ?? $tm->item_type ?? 'lainnya';
            $unit = $tm->unit ?: ($tm->item?->unit ?? 'meter');

            $technicianName = $tm->recordedBy?->name
                ?? $tm->fopTask?->technicians->first()?->name
                ?? '-';

            $badge = FopTask::resolveUsageBadgeForTask($tm->fopTask);

            $entries->push([
                'source' => 'task_material',
                'source_id' => $tm->id,
                'created_at' => $tm->created_at,
                'item_id' => $tm->item_id,
                'item_category_id' => $tm->item?->item_category_id ?? $tm->item_category_id,
                'item_name' => $itemName,
                'item_code' => $itemCode,
                'category_name' => $categoryName,
                'category_code' => $categoryCode,
                'tracking_type' => $tm->item?->tracking_type?->value ?? ($tm->lot_no ? 'roll' : 'quantity'),
                'qty' => (float) $tm->qty,
                'unit' => $unit,
                'identifier' => $tm->lot_no ?: '-',
                'technician_name' => $technicianName,
                'customer_name' => $tm->customer?->full_name ?? $tm->fopTask?->customer?->full_name ?? '-',
                'customer_id' => $tm->customer_id ?? $tm->fopTask?->customer_id,
                'fop_task_id' => $tm->fop_task_id,
                'task_number' => $tm->fopTask?->task_number ?? '-',
                'task_type' => $badge['type'],
                'task_detail' => $badge['detail'],
                'task_category' => $badge['label'],
                'pop_name' => $popName,
                'unit_price' => (float) ($tm->unit_price_snapshot ?? 0),
                'total_price' => (float) ($tm->unit_price_snapshot ?? 0) * (float) $tm->qty,
                'note' => $tm->note,
                'location_breadcrumb' => null,
            ]);
        }

        // 2. Ambil data modem terpasang dari inventory_transactions (type=INSTALL)
        $installTransactions = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::INSTALL->value)
            ->whereBetween('created_at', [$start, $end])
            ->with([
                'item.category',
                'serial.customer.pop',
                'fopTask.pop',
                'fopTask.customer.pop',
                'fopTask.task.creqDetail',
                'fromTechnician',
                'createdBy',
            ])
            ->where(function ($q) use ($scopedPops) {
                $q->whereIn('from_pop_id', $scopedPops)
                    ->orWhereIn('to_pop_id', $scopedPops)
                    ->orWhereHas('fopTask', fn ($tq) => $tq->whereIn('pop_id', $scopedPops))
                    ->orWhereHas('serial.customer', fn ($cq) => $cq->whereIn('pop_id', $scopedPops));
            })
            ->get();

        foreach ($installTransactions as $trx) {
            $popName = $trx->fopTask?->pop?->name
                ?? $trx->serial?->customer?->pop?->name
                ?? $trx->fromPop?->name
                ?? '-';

            $itemName = $trx->item?->name ?? 'Modem ONT';
            $itemCode = $trx->item?->code ?? '-';
            $categoryName = $trx->item?->category?->name ?? 'ONT';
            $categoryCode = $trx->item?->category?->code ?? 'ont';
            $unit = $trx->item?->unit ?? 'Unit';

            $technicianName = $trx->fromTechnician?->name
                ?? $trx->createdBy?->name
                ?? '-';

            $isModemInstall = ($trx->item?->tracking_type?->value === 'serialized') || $trx->serial_id !== null;
            $badge = FopTask::resolveUsageBadgeForTask($trx->fopTask, $isModemInstall);

            $entries->push([
                'source' => 'inventory_transaction',
                'source_id' => $trx->id,
                'created_at' => $trx->created_at,
                'item_id' => $trx->item_id,
                'item_category_id' => $trx->item?->item_category_id,
                'item_name' => $itemName,
                'item_code' => $itemCode,
                'category_name' => $categoryName,
                'category_code' => $categoryCode,
                'tracking_type' => 'serialized',
                'qty' => (float) ($trx->qty ?: 1),
                'unit' => $unit,
                'identifier' => $trx->serial?->serial_number ?: ($trx->lot_no ?: '-'),
                'technician_name' => $technicianName,
                'customer_name' => $trx->fopTask?->customer?->full_name ?? $trx->serial?->customer?->full_name ?? '-',
                'customer_id' => $trx->fopTask?->customer_id ?? $trx->serial?->customer_id,
                'fop_task_id' => $trx->fop_task_id,
                'task_number' => $trx->fopTask?->task_number ?? '-',
                'task_type' => $badge['type'],
                'task_detail' => $badge['detail'],
                'task_category' => $badge['label'],
                'pop_name' => $popName,
                'unit_price' => (float) ($trx->unit_price_snapshot ?? 0),
                'total_price' => (float) ($trx->unit_price_snapshot ?? 0) * (float) ($trx->qty ?: 1),
                'note' => $trx->reason,
                'location_breadcrumb' => null,
            ]);
        }

        // 3. Ambil barang yang terpasang NETAP ke titik infrastruktur (type=DEPLOY) —
        // OLT/Slot/PON/ODP, bukan ke pelanggan. Dulu cuma kelihatan di drawer Riwayat
        // Mutasi (ledger mentah, numpuk seiring waktu); di sini digabung supaya "dipasang
        // dimana, siapa, jam berapa, task apa" kebaca langsung dari laporan Pemakaian
        // Material (laporan 2026-10-22).
        $deployTransactions = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::DEPLOY->value)
            ->whereBetween('created_at', [$start, $end])
            ->with([
                'item.category',
                'serial',
                'roll',
                'fopTask.pop',
                'fopTask.task.creqDetail',
                'fromTechnician',
                'createdBy',
                'toInfrastructurePop',
                'toMiniPop',
            ])
            ->where(function ($q) use ($scopedPops) {
                $q->whereIn('to_pop_id', $scopedPops)
                    ->orWhereHas('fopTask', fn ($tq) => $tq->whereIn('pop_id', $scopedPops));
            })
            ->get();

        foreach ($deployTransactions as $trx) {
            $popName = $trx->fopTask?->pop?->name
                ?? $trx->toInfrastructurePop?->name
                ?? $trx->toMiniPop?->name
                ?? '-';

            $itemName = $trx->item?->name ?? 'Material Infrastruktur';
            $itemCode = $trx->item?->code ?? '-';
            $categoryName = $trx->item?->category?->name ?? 'Infrastruktur';
            $categoryCode = $trx->item?->category?->code ?? 'infrastruktur';
            $unit = $trx->roll_id !== null ? 'meter' : ($trx->item?->unit ?? 'Unit');

            $technicianName = $trx->fromTechnician?->name
                ?? $trx->createdBy?->name
                ?? '-';

            $locationBreadcrumb = $this->resolveDeployLocationBreadcrumb($trx, $locationResolver);

            $badge = FopTask::resolveUsageBadgeForTask($trx->fopTask);

            $entries->push([
                'source' => 'inventory_transaction_deploy',
                'source_id' => $trx->id,
                'created_at' => $trx->created_at,
                'item_id' => $trx->item_id,
                'item_category_id' => $trx->item?->item_category_id,
                'item_name' => $itemName,
                'item_code' => $itemCode,
                'category_name' => $categoryName,
                'category_code' => $categoryCode,
                'tracking_type' => $trx->item?->tracking_type?->value ?? ($trx->serial_id ? 'serialized' : ($trx->roll_id ? 'roll' : 'quantity')),
                'qty' => (float) ($trx->qty ?: 1),
                'unit' => $unit,
                'identifier' => $trx->serial?->serial_number ?: ($trx->roll?->roll_code ?: ($trx->lot_no ?: '-')),
                'technician_name' => $technicianName,
                'customer_name' => '-',
                'customer_id' => null,
                'fop_task_id' => $trx->fop_task_id,
                'task_number' => $trx->fopTask?->task_number ?? '-',
                'task_type' => $badge['type'],
                'task_detail' => $badge['detail'],
                'task_category' => $badge['label'],
                'pop_name' => $popName,
                'unit_price' => (float) ($trx->unit_price_snapshot ?? 0),
                'total_price' => (float) ($trx->unit_price_snapshot ?? 0) * (float) ($trx->qty ?: 1),
                'note' => $trx->notes,
                'location_breadcrumb' => $locationBreadcrumb,
            ]);
        }

        return $entries->sortByDesc('created_at')->values();
    }

    /**
     * Breadcrumb lokasi pasang DEPLOY ("Cabang → OLT-X → Slot 2 → PON 4"), dibaca dari
     * kolom `to_*` ledger (bukan posisi SN SEKARANG — SN bisa sudah dicabut/dipindah
     * belakangan, tapi baris ledger ini tetap wajib nunjuk lokasi SAAT dipasang).
     * `InfrastructureLocationResolver` yang dipakai sama di Peta Infrastruktur &
     * Detail Pelanggan — satu sumber breadcrumb, jangan dihitung manual di sini.
     */
    private function resolveDeployLocationBreadcrumb(InventoryTransaction $trx, InfrastructureLocationResolver $locationResolver): ?string
    {
        try {
            return match (true) {
                $trx->to_infrastructure_port_id !== null => $locationResolver->resolve(InfrastructureLocation::PORT, $trx->to_infrastructure_port_id)->breadcrumb(),
                $trx->to_infrastructure_slot_id !== null => $locationResolver->resolve(InfrastructureLocation::SLOT, $trx->to_infrastructure_slot_id)->breadcrumb(),
                $trx->to_network_node_id !== null => $locationResolver->resolve(InfrastructureLocation::NODE, $trx->to_network_node_id)->breadcrumb(),
                $trx->to_infrastructure_pop_id !== null => $locationResolver->resolve(InfrastructureLocation::POP, $trx->to_infrastructure_pop_id)->breadcrumb(),
                $trx->to_mini_pop_id !== null => $locationResolver->resolve(InfrastructureLocation::MINI_POP, $trx->to_mini_pop_id)->breadcrumb(),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @param  array{category_id: ?int, search: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applySearchAndCategoryFilters(Collection $entries, array $filters): Collection
    {
        return $entries->filter(function ($entry) use ($filters) {
            if ($filters['category_id'] && (int) $entry['item_category_id'] !== (int) $filters['category_id']) {
                return false;
            }

            if ($filters['search'] !== '') {
                $search = mb_strtolower($filters['search']);
                $haystack = mb_strtolower(implode(' ', [
                    $entry['item_name'],
                    $entry['item_code'],
                    $entry['identifier'],
                    $entry['technician_name'],
                    $entry['customer_name'],
                    $entry['task_number'],
                    $entry['pop_name'],
                    $entry['location_breadcrumb'] ?? '',
                ]));

                if (! str_contains($haystack, $search)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array{modem_count: int, cable_meters: float, passive_count: float, task_count: int}
     */
    private function calculateKpi(Collection $entries): array
    {
        $modemCount = 0;
        $cableMeters = 0.0;
        $passiveCount = 0.0;
        $taskIds = [];

        foreach ($entries as $entry) {
            // Tile KPI ini khusus konsumsi KE PELANGGAN (modem ONT, dropcore, aksesori
            // pasang baru) — DEPLOY ke infrastruktur (OLT/Slot/PON/ODP) ikut dihitung di
            // tile "Material Pasif" lewat `cableMeters`/dibawah, bukan "Modem ONT Terpasang"
            // yang labelnya eksplisit buat pelanggan.
            if ($entry['source'] === 'inventory_transaction_deploy') {
                if ($entry['unit'] === 'meter') {
                    $cableMeters += (float) $entry['qty'];
                } else {
                    $passiveCount += (float) $entry['qty'];
                }
            } elseif ($entry['tracking_type'] === 'serialized' || str_contains(mb_strtolower($entry['category_code']), 'ont')) {
                $modemCount += (int) $entry['qty'];
            } elseif ($entry['unit'] === 'meter' || str_contains(mb_strtolower($entry['category_code']), 'kabel')) {
                $cableMeters += (float) $entry['qty'];
            } else {
                $passiveCount += (float) $entry['qty'];
            }

            if ($entry['fop_task_id']) {
                $taskIds[$entry['fop_task_id']] = true;
            }
        }

        return [
            'modem_count' => $modemCount,
            'cable_meters' => $cableMeters,
            'passive_count' => $passiveCount,
            'task_count' => count($taskIds),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    private function buildItemSummaries(Collection $entries): Collection
    {
        return $entries->groupBy('item_name')->map(function (Collection $group, string $itemName) {
            $first = $group->first();
            $identifiers = $group->pluck('identifier')->filter(fn ($id) => $id !== '-')->unique()->values()->all();
            $taskCount = $group->pluck('fop_task_id')->filter()->unique()->count();

            return [
                'item_name' => $itemName,
                'item_code' => $first['item_code'],
                'category_name' => $first['category_name'],
                'unit' => $first['unit'],
                'total_qty' => (float) $group->sum('qty'),
                'task_count' => $taskCount,
                'identifiers' => $identifiers,
                'total_value' => (float) $group->sum('total_price'),
            ];
        })->sortByDesc('total_qty')->values();
    }
}
