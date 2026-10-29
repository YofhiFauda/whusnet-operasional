<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\NetworkNode;
use App\Services\InfrastructureMapService;
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
        ]);

        $withCustomerOnt = (bool) ($filters['ont'] ?? false);
        $popFilter = isset($filters['pop_id']) ? (int) $filters['pop_id'] : null;
        $sort = $filters['sort'] ?? 'topology';

        // Tab Topologi jaringan besar: seluruh model tampilan (tanpa ODP) disimpan
        // sebagai array biasa per user + filter selama 5 menit. Membangunnya
        // memakan ±2,5 dtk server (TTFB langsung ikut jelek di Lighthouse); hit
        // cache cuma merender view. `?fresh=1` (tombol "Muat Ulang") membuangnya.
        // Deep link node_id selalu lewat jalur penuh karena butuh pengecekan scope.
        $onTopologyTab = ! isset($filters['node_id']) && ! in_array($request->query('tab'), ['inventaris', 'pohon'], true);
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
        $data = $this->map->build($request->user(), $popFilter, $sort, $buildOnt);
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

        return view('network.map.index', $data + $summaryVars + [
            'initialNodeId' => $initialNodeId,
            'withCustomerOnt' => $withCustomerOnt,
            'ontActive' => $buildOnt,
        ]);
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
