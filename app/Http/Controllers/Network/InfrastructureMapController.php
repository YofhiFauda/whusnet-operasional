<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Services\InfrastructureMapService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InfrastructureMapController extends Controller
{
    public function __construct(private readonly InfrastructureMapService $map) {}

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'pop_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:topology,utilization,free'],
            'q' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);
        $data = $this->map->build(
            $request->user(),
            isset($filters['pop_id']) ? (int) $filters['pop_id'] : null,
            $filters['sort'] ?? 'topology'
        );
        $search = mb_strtolower(trim($filters['q'] ?? ''));
        $type = $filters['type'] ?? 'all';

        return response()->streamDownload(function () use ($data, $search, $type): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }

            fputcsv($stream, ['Node', 'Nama node', 'Tipe', 'Lokasi', 'Port terpakai', 'Total port', 'Status', 'Barang', 'Jenis', 'SN / Lot', 'Jumlah', 'Tanggal pasang', 'Teknisi', 'Task']);
            foreach ($data['inventoryNodes'] as $node) {
                $index = $data['nodeIndex'][$node->id];
                if (($type !== 'all' && $index['type'] !== $type)
                    || ($search !== '' && ! str_contains($index['search'], $search))) {
                    continue;
                }

                $assets = $data['assetsByNode'][$node->id] ?: [[]];
                foreach ($assets as $asset) {
                    fputcsv($stream, [
                        $node->code,
                        $node->name,
                        $node->nodeType?->name,
                        $data['pathsByNode'][$node->id],
                        $node->used_ports_count,
                        $node->ports_count,
                        $node->status,
                        $asset['name'] ?? '',
                        $asset['kind'] ?? '',
                        $asset['reference'] ?? '',
                        $asset['amount'] ?? '',
                        $asset['installed_at'] ?? '',
                        $asset['technician'] ?? '',
                        $asset['task'] ?? '',
                    ]);
                }
            }
            fclose($stream);
        }, 'inventaris-infrastruktur-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Di atas jumlah node ini, ODP dimuat per PON (lihat `children()`). */
    private const LAZY_ODP_THRESHOLD = 400;

    /**
     * Detail anak lazy milik satu node: ODP milik satu PON, atau (mode ONT)
     * kartu ONT milik satu ODP. Sumbernya hasil build milik user ini sendiri
     * (cache 5 menit, kunci memuat user + filter), jadi scope POP otomatis
     * terjaga; kalau cache sudah habis, build diulang dengan scope yang sama.
     */
    public function children(Request $request, NetworkNode $node): JsonResponse
    {
        $lazy = $this->lazyData($request)['lazy'];
        abort_unless(isset($lazy[$node->id]), 404);

        return response()->json(['nodes' => $lazy[$node->id]]);
    }

    /**
     * Mode ONT: seluruh ONT di bawah satu Slot dalam satu permintaan (Buka
     * Semua = puluhan permintaan, bukan satu per ODP).
     */
    public function childrenBatch(Request $request, NetworkNode $node): JsonResponse
    {
        abort_unless($request->boolean('ont'), 404);

        $data = $this->lazyData($request);
        $nodes = [];
        foreach ($data['slotOf'] as $parentId => $slotId) {
            if ($slotId === $node->id) {
                $nodes += $data['lazy'][$parentId];
            }
        }
        abort_if($nodes === [], 404);

        return response()->json(['nodes' => $nodes]);
    }

    /** @return array{lazy: array<int, array<int, array<string, mixed>>>, slotOf: array<int, int>} */
    private function lazyData(Request $request): array
    {
        $filters = $request->validate([
            'pop_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:topology,utilization,free'],
            'ont' => ['nullable', 'boolean'],
        ]);
        $withCustomerOnt = (bool) ($filters['ont'] ?? false);

        $key = $this->lazyCacheKey($request, $filters);
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['lazy'], $cached['slotOf'])) {
            return $cached;
        }

        $data = $this->map->build(
            $request->user(),
            isset($filters['pop_id']) ? (int) $filters['pop_id'] : null,
            $filters['sort'] ?? 'topology',
            $withCustomerOnt
        );
        $split = $withCustomerOnt
            ? $this->map->splitLazyOnt($data['topologyDetails'], [])
            : $this->map->splitLazyOdp($data['topologyDetails'], []) + ['slotOf' => []];
        $lazyData = ['lazy' => $split['lazy'], 'slotOf' => $split['slotOf']];
        Cache::put($key, $lazyData, now()->addMinutes(5));

        return $lazyData;
    }

    /** @param  array<string, mixed>  $filters */
    private function lazyCacheKey(Request $request, array $filters): string
    {
        return 'peta:'.(($filters['ont'] ?? false) ? 'ont' : 'odp').':'.$request->user()->id.':'.($filters['pop_id'] ?? 'all').':'.($filters['sort'] ?? 'topology');
    }

    /** Cari pelanggan (nama / CID / SN) yang terpasang di jaringan, untuk kotak cari Peta. */
    public function search(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:100'],
        ]);

        return response()->json(['results' => $this->map->searchCustomers($request->user(), $filters['q'])]);
    }

    /** Kartu ONT satu pelanggan (hasil cari) — tanpa membangun seluruh peta. */
    public function customerOnt(Request $request, int $customer): JsonResponse
    {
        $detail = $this->map->ontDetailFor($request->user(), $customer);
        abort_if($detail === null, 404);

        return response()->json($detail);
    }

    /** Daftar port satu node, dimuat saat node diklik di Peta (bukan dipukul-rata di halaman awal). */
    public function ports(Request $request, NetworkNode $node): JsonResponse
    {
        $ports = $this->map->portsFor($request->user(), $node);
        abort_if($ports === null, 404);

        return response()->json(['ports' => $ports]);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $filters = $request->validate([
            'pop_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:topology,utilization,free'],
            'node_id' => ['nullable', 'integer'],
            'ont' => ['nullable', 'boolean'],
            'asset_pop_id' => ['nullable', 'integer'],
            'asset_mini_id' => ['nullable', 'integer'],
            'asset_olt_id' => ['nullable', 'integer'],
            'asset_slot_id' => ['nullable', 'integer'],
            'asset_pon_id' => ['nullable', 'integer'],
            'asset_odp_id' => ['nullable', 'integer'],
            'asset_page' => ['nullable', 'integer', 'min:1'],
            'inv_pop_id' => ['nullable', 'integer'],
            'inv_mini_id' => ['nullable', 'integer'],
            'inv_olt_id' => ['nullable', 'integer'],
            'inv_slot_id' => ['nullable', 'integer'],
            'inv_pon_id' => ['nullable', 'integer'],
            'inv_odp_id' => ['nullable', 'integer'],
            'inv_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $withCustomerOnt = (bool) ($filters['ont'] ?? false);
        $popFilter = isset($filters['pop_id']) ? (int) $filters['pop_id'] : null;
        $sort = $filters['sort'] ?? 'topology';

        // Tab Topologi jaringan besar: seluruh model tampilan (tanpa ODP) disimpan
        // sebagai array biasa per user + filter selama 5 menit. Membangunnya
        // memakan ±2,5 dtk server (TTFB langsung ikut jelek di Lighthouse); hit
        // cache cuma merender view. `?fresh=1` (tombol "Muat Ulang") membuangnya.
        // Deep link node_id selalu lewat jalur penuh karena butuh pengecekan scope.
        $onTopologyTab = ! isset($filters['node_id']) && ! in_array($request->query('tab'), ['inventaris', 'pohon', 'terpasang'], true);
        $viewCacheKey = 'peta:view:'.$request->user()->id.':'.($popFilter ?? 'all').':'.$sort;
        if ($onTopologyTab && ! $withCustomerOnt) {
            if ($request->boolean('fresh')) {
                Cache::forget($viewCacheKey);
                Cache::forget($this->lazyCacheKey($request, $filters));
                Cache::forget(InfrastructureMapService::hierarchyCacheKey($request->user()->id, $popFilter));

                // PRG: `fresh` tidak boleh menempel di URL — kalau tidak, setiap reload
                // (dan setiap audit Lighthouse) melewati cache dan membangun ulang.
                return redirect()->route('network.map.index', array_filter(['pop_id' => $popFilter, 'sort' => $filters['sort'] ?? null]));
            }
            if (is_array($cached = Cache::get($viewCacheKey))) {
                return view('network.map.index', $this->cachedViewData($cached) + ['initialNodeId' => null, 'withCustomerOnt' => false]);
            }
        }

        // Kartu ONT hanya dibangun untuk tab Topologi. Tab Pohon/Inventaris tidak
        // memakainya, tetapi tetap membawa `ont=1` di URL supaya kembali ke Topologi
        // mempertahankan pilihan pengguna — tanpa pembedaan ini pindah tab membangun
        // ulang belasan ribu ONT (±13 dtk) hanya untuk daftar yang tidak menampilkannya.
        $buildOnt = $onTopologyTab && $withCustomerOnt;
        // Tab "Barang Terpasang": lewat `$lightweight` (skip eager load SN/QTY +
        // query ledger buat SELURUH scope POP, ±2,3 dtk dari ±3,5 dtk total di 3500
        // node) — tab ini cuma butuh childrenByNode/pathsByNode/cabangs dari sini
        // buat resolve filter+subtree; barangnya sendiri diquery belakangan lewat
        // `assetRowsForScopedNodes()`, DIBATASI ke subtree hasil filter.
        $data = $this->map->build($request->user(), $popFilter, $sort, $buildOnt, lightweight: $request->query('tab') === 'terpasang');
        // Deep link dari Master Node ("Lihat detail di Peta Infrastruktur").
        // Node HARUS sudah lolos scope POP + filter pop_id di atas (nodeIndex
        // dibangun dari $nodes yang sama) — node di luar scope atau ID yang
        // gak ada mendapat 404 YANG SAMA, biar gak kebocoran info lewat beda
        // status/pesan error (rancangan-pembagian-detail-master-node-vs-peta-infrastruktur.md §Ketepatan sumber data).
        $initialNodeId = null;
        if (isset($filters['node_id'])) {
            abort_unless(array_key_exists((int) $filters['node_id'], $data['nodeIndex']), 404);
            $initialNodeId = (int) $filters['node_id'];
        }

        // Jaringan besar + tab Topologi: ODP tidak ikut payload kanvas (lihat
        // InfrastructureMapService::splitLazyOdp). Tab lain butuh data penuh.
        $lazyOdp = $onTopologyTab && ! $withCustomerOnt && $data['nodes']->count() > self::LAZY_ODP_THRESHOLD;
        if ($lazyOdp) {
            $urlQuery = array_filter(['pop_id' => $filters['pop_id'] ?? null, 'sort' => $filters['sort'] ?? null]);
            $split = $this->map->splitLazyOdp($data['topologyDetails'], $urlQuery);
            $data['topologyDetails'] = $split['details'];
            // Hanya array biasa yang di-cache (bukan Collection/model).
            Cache::put($this->lazyCacheKey($request, $filters), ['lazy' => $split['lazy'], 'slotOf' => []], now()->addMinutes(5));
        }

        // Mode ONT: kartu ONT dipecah per induk (lihat splitLazyOnt) dan diambil
        // lewat `children`/`childrenBatch`; payload awal hanya struktur sampai ODP.
        if ($buildOnt) {
            $split = $this->map->splitLazyOnt($data['topologyDetails'], array_filter(['pop_id' => $popFilter, 'sort' => $filters['sort'] ?? null, 'ont' => 1]));
            $data['topologyDetails'] = $split['details'];
            Cache::put($this->lazyCacheKey($request, $filters), ['lazy' => $split['lazy'], 'slotOf' => $split['slotOf']], now()->addMinutes(5));
        }

        $summaryVars = [
            'nodeCount' => $data['nodes']->count(),
            'firstNodeId' => $data['nodes']->first()?->id,
            'hasPops' => $data['topologyPops']->isNotEmpty(),
            'firstPopId' => $data['topologyPops']->first()?->id,
        ];

        if ($lazyOdp) {
            Cache::put($viewCacheKey, [
                'topologyDetails' => $data['topologyDetails'],
                'topologyIndex' => $data['topologyIndex'],
                'hierarchy' => ['items' => array_map(fn (array $item): array => array_intersect_key($item, array_flip(['label', 'name', 'type', 'parent'])), $data['hierarchy']['items']), 'children' => $data['hierarchy']['children'], 'roots' => []],
                'summary' => $data['summary'],
                'cabangs' => $data['cabangs']->map(fn ($pop): array => ['id' => $pop->id, 'name' => $pop->name])->all(),
                'selectedPopId' => $data['selectedPopId'],
                'sort' => $data['sort'],
            ] + $summaryVars, now()->addMinutes(5));
        }

        // Tab "Barang Terpasang" dirender view TERPISAH (`network.map.terpasang`), bukan
        // numpang di `network.map.index`: index.blade.php bawa `x-data` Alpine raksasa +
        // kanvas/drawer buat tab Topologi/Pohon/Inventaris yang tetap di-walk & di-bind
        // Alpine walau disembunyikan — itu sumber TBT ~5-6 dtk di Lighthouse Performance
        // tab ini (lihat investigasi ADHOC). View terpasang.blade.php sama sekali tanpa
        // Alpine: murni form GET + tabel server-rendered.
        if ($request->query('tab') === 'terpasang') {
            return view('network.map.terpasang', $this->assetTerpasangData($data, $request, $filters) + [
                'cabangs' => $data['cabangs'],
                'selectedPopId' => $data['selectedPopId'],
                'sort' => $data['sort'],
            ]);
        }

        // Tab Inventaris: filter cascading sama pola dengan Terpasang (POP/Mini POP/
        // OLT/Slot/PON/ODP, "Semua" default) + paginasi 100 baris/halaman — subtree besar
        // (ribuan node) dirender sekaligus tanpa batas = DOM raksasa, masalah performa
        // yang sama dengan tab Terpasang sebelum ADHOC-237. `inventoryNodes` di-OVERRIDE
        // di `$data` (bukan cuma ditambah) supaya view tetap baca key yang sama.
        // Deep link node_id memaksa $activeTab = 'inventaris' di index.blade.php TANPA
        // query `tab` (lihat blok @php di sana) — gate ini HARUS ikut kondisi yang sama,
        // bukan cuma `$request->query('tab')`, atau view pecah "Undefined variable $invSelected".
        if ($initialNodeId !== null || $request->query('tab') === 'inventaris') {
            $data = array_merge($data, $this->inventoryFilterData($data, $request, $filters));
        }

        return view('network.map.index', $data + $summaryVars + [
            'initialNodeId' => $initialNodeId,
            'withCustomerOnt' => $withCustomerOnt,
            'ontActive' => $buildOnt,
        ]);
    }

    /** Baris per halaman tabel Barang Terpasang (lihat `assetTerpasangData()`). */
    private const ASSET_ROWS_PER_PAGE = 100;

    /**
     * Tab "Barang Terpasang": pilih satu titik di hierarki (POP/Mini
     * POP/OLT/Slot/PON/ODP) dan tampilkan semua barang gudang yang terpasang
     * di subtree-nya (bukan cuma device itu sendiri, seperti tab Inventaris).
     * Seluruhnya dihitung dari data `build()` yang sudah di-scope POP user —
     * tidak ada query baru selain opsi Mini POP (ringan, ikut `forUser()`).
     *
     * Hasilnya WAJIB dipaginasi di PHP (bukan query-level — `assetRowsForScopedNodes()`
     * sudah murah karena dibatasi ke subtree) sebelum dikirim ke view: scope besar
     * (misal subtree Jetis ±6000 baris, "Semua" ±12000 baris) kalau dirender SEKALIGUS
     * jadi ribuan `<tr>` tanpa batas = DOM raksasa, itu sumber tab browser 1-1,6GB &
     * skor Lighthouse ambruk di scope besar (TIDAK ada hubungannya lagi dengan Alpine,
     * tab ini sudah 0 Alpine — lihat `terpasang.blade.php`). Jangan hilangkan paginasi.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function assetTerpasangData(array $data, Request $request, array $filters): array
    {
        $nodes = $data['nodes'];
        $cabangs = $data['cabangs'];

        $selectedCabangId = isset($filters['asset_pop_id']) ? (int) $filters['asset_pop_id'] : null;
        if ($selectedCabangId !== null && ! $cabangs->contains('id', $selectedCabangId)) {
            $selectedCabangId = null;
        }

        $miniPops = $selectedCabangId !== null
            ? Pop::forUser($request->user())->where('type', 'mini_pop')->where('parent_id', $selectedCabangId)->orderBy('name')->get(['id', 'name'])
            : collect();

        $selectedMiniId = isset($filters['asset_mini_id']) ? (int) $filters['asset_mini_id'] : null;
        if ($selectedMiniId !== null && ! $miniPops->contains('id', $selectedMiniId)) {
            $selectedMiniId = null;
        }

        $oltOptions = $this->childNodeOptions($nodes, $selectedMiniId ?? $selectedCabangId, 'olt_chassis', byAnchor: true);
        $selectedOltId = $this->validatedId($filters['asset_olt_id'] ?? null, $oltOptions);

        $slotOptions = $this->childNodeOptions($nodes, $selectedOltId, 'olt_slot', byAnchor: false);
        $selectedSlotId = $this->validatedId($filters['asset_slot_id'] ?? null, $slotOptions);

        $ponOptions = $this->childNodeOptions($nodes, $selectedSlotId, 'olt_pon', byAnchor: false);
        $selectedPonId = $this->validatedId($filters['asset_pon_id'] ?? null, $ponOptions);

        $odpOptions = $this->childNodeOptions($nodes, $selectedPonId, 'odp', byAnchor: false);
        $selectedOdpId = $this->validatedId($filters['asset_odp_id'] ?? null, $odpOptions);

        $rootNodeIds = match (true) {
            $selectedOdpId !== null => [$selectedOdpId],
            $selectedPonId !== null => [$selectedPonId],
            $selectedSlotId !== null => [$selectedSlotId],
            $selectedOltId !== null => [$selectedOltId],
            $selectedMiniId !== null => $nodes->where('anchor_pop_id', $selectedMiniId)->pluck('id')->all(),
            $selectedCabangId !== null => $nodes->whereIn('anchor_pop_id', [$selectedCabangId, ...$miniPops->pluck('id')->all()])->pluck('id')->all(),
            // "Semua" = default: belum pilih apapun -> seluruh node dalam scope POP user
            // (`$nodes` sudah di-scope `build()`), bukan empty state.
            default => $nodes->pluck('id')->all(),
        };

        // `assetRowsForScopedNodes()` (bukan `assetRowsForNodes()` + `$data['assetsByNode']`)
        // — `$data` di sini hasil `build(..., lightweight: true)`, `assetsByNode`-nya KOSONG
        // buat semua node. Query SN/QTY/ledger baru jalan di sini, DIBATASI ke subtree hasil
        // filter saja — bukan seluruh node dalam scope POP. Ini intinya optimasi tab ini.
        $allAssetRows = $this->map->assetRowsForScopedNodes(
            $this->map->subtreeNodeIds($data['childrenByNode'], $rootNodeIds),
            $data['pathsByNode'],
            $request->user()->hasPermission('warehouse_traceability.view'),
        );

        $totalRows = count($allAssetRows);
        $perPage = self::ASSET_ROWS_PER_PAGE;
        $lastPage = max(1, (int) ceil($totalRows / $perPage));
        $page = min($lastPage, max(1, (int) ($filters['asset_page'] ?? 1)));

        return [
            'assetMiniPops' => $miniPops,
            'assetOltOptions' => $oltOptions,
            'assetSlotOptions' => $slotOptions,
            'assetPonOptions' => $ponOptions,
            'assetOdpOptions' => $odpOptions,
            'assetSelected' => [
                'pop_id' => $selectedCabangId,
                'mini_id' => $selectedMiniId,
                'olt_id' => $selectedOltId,
                'slot_id' => $selectedSlotId,
                'pon_id' => $selectedPonId,
                'odp_id' => $selectedOdpId,
            ],
            'assetRows' => array_slice($allAssetRows, ($page - 1) * $perPage, $perPage),
            'assetTotalRows' => $totalRows,
            'assetPage' => $page,
            'assetLastPage' => $lastPage,
            'assetPerPage' => $perPage,
        ];
    }

    /** Baris per halaman tabel Inventaris (lihat `inventoryFilterData()`). */
    private const INVENTORY_ROWS_PER_PAGE = 100;

    /**
     * Tab "Inventaris": filter cascading sama pola dengan `assetTerpasangData()`
     * (POP/Mini POP/OLT/Slot/PON/ODP, "Semua" default) lalu paginasi 100 baris/halaman.
     * Tidak ada query baru selain opsi Mini POP — `$data['inventoryNodes']` cuma
     * difilter ke subtree hasil pilihan (in-memory, `childrenByNode` dari `build()`).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function inventoryFilterData(array $data, Request $request, array $filters): array
    {
        $nodes = $data['nodes'];
        $cabangs = $data['cabangs'];

        $selectedCabangId = isset($filters['inv_pop_id']) ? (int) $filters['inv_pop_id'] : null;
        if ($selectedCabangId !== null && ! $cabangs->contains('id', $selectedCabangId)) {
            $selectedCabangId = null;
        }

        $miniPops = $selectedCabangId !== null
            ? Pop::forUser($request->user())->where('type', 'mini_pop')->where('parent_id', $selectedCabangId)->orderBy('name')->get(['id', 'name'])
            : collect();

        $selectedMiniId = isset($filters['inv_mini_id']) ? (int) $filters['inv_mini_id'] : null;
        if ($selectedMiniId !== null && ! $miniPops->contains('id', $selectedMiniId)) {
            $selectedMiniId = null;
        }

        $oltOptions = $this->childNodeOptions($nodes, $selectedMiniId ?? $selectedCabangId, 'olt_chassis', byAnchor: true);
        $selectedOltId = $this->validatedId($filters['inv_olt_id'] ?? null, $oltOptions);

        $slotOptions = $this->childNodeOptions($nodes, $selectedOltId, 'olt_slot', byAnchor: false);
        $selectedSlotId = $this->validatedId($filters['inv_slot_id'] ?? null, $slotOptions);

        $ponOptions = $this->childNodeOptions($nodes, $selectedSlotId, 'olt_pon', byAnchor: false);
        $selectedPonId = $this->validatedId($filters['inv_pon_id'] ?? null, $ponOptions);

        $odpOptions = $this->childNodeOptions($nodes, $selectedPonId, 'odp', byAnchor: false);
        $selectedOdpId = $this->validatedId($filters['inv_odp_id'] ?? null, $odpOptions);

        $rootNodeIds = match (true) {
            $selectedOdpId !== null => [$selectedOdpId],
            $selectedPonId !== null => [$selectedPonId],
            $selectedSlotId !== null => [$selectedSlotId],
            $selectedOltId !== null => [$selectedOltId],
            $selectedMiniId !== null => $nodes->where('anchor_pop_id', $selectedMiniId)->pluck('id')->all(),
            $selectedCabangId !== null => $nodes->whereIn('anchor_pop_id', [$selectedCabangId, ...$miniPops->pluck('id')->all()])->pluck('id')->all(),
            // "Semua" = default: belum pilih apapun -> seluruh node dalam scope POP user.
            default => $nodes->pluck('id')->all(),
        };

        $subtreeIds = array_flip($this->map->subtreeNodeIds($data['childrenByNode'], $rootNodeIds));
        $filteredNodes = $data['inventoryNodes']->filter(fn (NetworkNode $node): bool => isset($subtreeIds[$node->id]))->values();

        $totalRows = $filteredNodes->count();
        $perPage = self::INVENTORY_ROWS_PER_PAGE;
        $lastPage = max(1, (int) ceil($totalRows / $perPage));
        $page = min($lastPage, max(1, (int) ($filters['inv_page'] ?? 1)));

        return [
            'invMiniPops' => $miniPops,
            'invOltOptions' => $oltOptions,
            'invSlotOptions' => $slotOptions,
            'invPonOptions' => $ponOptions,
            'invOdpOptions' => $odpOptions,
            'invSelected' => [
                'pop_id' => $selectedCabangId,
                'mini_id' => $selectedMiniId,
                'olt_id' => $selectedOltId,
                'slot_id' => $selectedSlotId,
                'pon_id' => $selectedPonId,
                'odp_id' => $selectedOdpId,
            ],
            'inventoryNodes' => $filteredNodes->slice(($page - 1) * $perPage, $perPage)->values(),
            'invTotalRows' => $totalRows,
            'invPage' => $page,
            'invLastPage' => $lastPage,
            'invPerPage' => $perPage,
        ];
    }

    /**
     * Opsi satu level dropdown cascading: anak dari `$parentId` (lewat
     * `anchor_pop_id` untuk level OLT yang ditambatkan ke POP/Mini POP, atau
     * `parent_id` untuk level Slot/PON/ODP yang ditambatkan ke node lain)
     * bertipe `$typeCode`. `$parentId` null (belum ada level di atas
     * terpilih) → tidak ada opsi.
     */
    private function childNodeOptions(Collection $nodes, ?int $parentId, string $typeCode, bool $byAnchor): Collection
    {
        if ($parentId === null) {
            return new Collection;
        }

        return $nodes->filter(fn (NetworkNode $node): bool => $node->nodeType?->code === $typeCode
            && ($byAnchor ? $node->anchor_pop_id === $parentId : $node->parent_id === $parentId))->values();
    }

    /** @param  Collection<int, NetworkNode>  $options */
    private function validatedId(?string $raw, Collection $options): ?int
    {
        if ($raw === null) {
            return null;
        }

        $id = (int) $raw;

        return $options->contains('id', $id) ? $id : null;
    }

    /**
     * Model tampilan tab Topologi dari cache — hanya yang dipakai tab itu
     * (tab Pohon & Inventaris selalu lewat jalur penuh).
     *
     * @param  array<string, mixed>  $cached
     * @return array<string, mixed>
     */
    private function cachedViewData(array $cached): array
    {
        $cached['cabangs'] = collect($cached['cabangs'])->map(fn (array $pop): object => (object) $pop);

        return $cached;
    }
}
