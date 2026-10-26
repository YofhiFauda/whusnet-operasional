<?php

namespace App\Http\Controllers\Network;

use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureSlotKind;
use App\Enums\SerialStatus;
use App\Http\Controllers\Concerns\RedirectsToMasterNode;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureConnectionService;
use App\Services\InfrastructureHierarchyService;
use App\Services\InfrastructurePositionService;
use App\Services\MasterRecordRemovalService;
use App\Services\NetworkNodeAttentionService;
use App\Services\NetworkNodeService;
use App\Services\NetworkPortAssignmentService;
use App\Services\PonCapacityService;
use App\Support\InfrastructureCutover;
use App\Support\MasterNodeInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Master node jaringan ber-port (ODC/ODP/dst) + status port manual.
 * Generalisasi `OdpController` lama: tipe node tidak lagi hardcode "ODP",
 * batas port & ambang Red Zone datang dari flag `network_node_types`.
 *
 * Scope POP: `NetworkNode` tidak punya kolom `pop_id` (cabang di-resolve
 * dari pohon), jadi `HasPopScope` tidak berlaku — listing lewat
 * `scopeInPops()`, otorisasi per-record lewat `authorizeNodeScope()` yang
 * memakai `inPops()` yang sama (bukan `resolvePop()` — alasannya di docblock
 * `authorizeNodeScope()`).
 */
class NetworkNodeController extends Controller
{
    use RedirectsToMasterNode;

    public function __construct(private readonly EffectiveAccessService $access) {}

    public function index(NetworkNodeAttentionService $attention): View
    {
        $user = auth()->user();
        $positionCodes = NetworkNode::positionTypeCodes();
        // Slot/PON/Port dan apa pun yang tergantung di bawahnya (ODP anak PON)
        // SENGAJA tidak dimuat: tiap node di sini dirender jadi satu blok
        // inspector lengkap, jadi memuat ~10 ribu baris posisi (16 Slot × 8
        // PON × 60 OLT, rancangan §8) membuat halaman O(N²) — inspector per
        // node × select relokasi berisi semua node. Posisi & ODP-nya dibuka
        // dari pohon (lazy) dan halaman Detail masing-masing.
        $nodes = NetworkNode::query()
            ->when(! $this->access->hasAllPopAccess($user), fn ($query) => $query->inPops($this->access->getAllowedPopIds($user)))
            ->whereDoesntHave('nodeType', fn ($query) => $query->whereIn('code', $positionCodes))
            ->where(fn ($query) => $query->whereNull('parent_id')
                ->orWhereDoesntHave('parent.nodeType', fn ($type) => $type->whereIn('code', $positionCodes)))
            ->with(
                'nodeType', 'ports.activeAssignment.customer', 'ports.activeAssignment.targetNode',
                'anchorPop.parent', 'parent', 'deployedSerials.item', 'infrastructureBalances.item'
            )
            ->withCount(['ports', 'ports as used_ports_count' => fn ($query) => $query->where('is_used', true)])
            ->orderBy('code')
            ->get();
        // Sambungkan relasi `parent` ke instance yang SUDAH dimuat: tanpa ini
        // `effectiveLocation()`/`resolvePop()` di tiap blok inspector naik
        // rantai induk lewat `find()` per leluhur (N query per node).
        $loadedById = $nodes->keyBy('id');
        foreach ($nodes as $loaded) {
            if ($loaded->parent_id !== null && $loadedById->has($loaded->parent_id)) {
                $loaded->setRelation('parent', $loadedById->get($loaded->parent_id));
            }
        }
        // Cabang tiap node dihitung SEKALI per anchor POP (memo), bukan
        // `Pop::resolveCabangId($node->resolvePop()?->id)` di tiap blok
        // inspector — itu 2-3 query per node.
        $cabangIdByAnchor = [];
        $cabangByNodeId = $nodes->mapWithKeys(function (NetworkNode $node) use (&$cabangIdByAnchor): array {
            $top = $node;
            $guard = 0;
            while ($top->parent_id !== null && $top->relationLoaded('parent') && $top->parent !== null && $guard++ < 64) {
                $top = $top->parent;
            }
            if ($top->anchor_pop_id === null) {
                return [$node->id => Pop::resolveCabangId($node->resolvePop()?->id)];
            }

            return [$node->id => $cabangIdByAnchor[$top->anchor_pop_id] ??= Pop::resolveCabangId($top->anchor_pop_id)];
        });
        $pops = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();
        $nodeTypes = NetworkNodeType::where('is_active', true)->orderBy('sort_order')->get();
        $parentOptions = $nodes;
        // Target relokasi "node induk" = node non-ODP (perangkat/Server/node
        // lama). ODP tidak pernah jadi induk node lain di model baru; PON
        // tujuan relokasi ODP dicari di server (`network.nodes.pon-search`).
        $relocateTargets = $nodes->reject(fn (NetworkNode $node): bool => $node->nodeType?->code === 'odp')->values();
        $treeChildren = $parentOptions->groupBy(fn (NetworkNode $node) => $node->parent_id ?? 0);
        $nodesById = $parentOptions->keyBy('id');
        $topologyPops = Pop::forUser($user)
            ->whereIn('type', ['pusat', 'cabang', 'mini_pop'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $visiblePopIds = $topologyPops->pluck('id')->all();
        $visiblePopsById = $topologyPops->keyBy('id');
        $uplinkPopChildren = $topologyPops
            ->filter(function (Pop $pop) use ($nodesById, $visiblePopsById): bool {
                $uplink = $nodesById->get($pop->uplink_node_id);
                $parentPop = $visiblePopsById->get($pop->parent_id);

                return $uplink !== null && $parentPop !== null
                    && $uplink->resolvePop()?->id === $parentPop->id;
            })
            ->groupBy('uplink_node_id');
        $linkedPopIds = $uplinkPopChildren->flatten()->pluck('id')->all();
        $topologyPopChildren = $topologyPops
            ->reject(fn (Pop $pop): bool => in_array($pop->id, $linkedPopIds, true))
            ->groupBy(fn (Pop $pop): int => in_array($pop->parent_id, $visiblePopIds, true) ? $pop->parent_id : 0);
        $rootNodesByPop = $parentOptions
            ->filter(fn (NetworkNode $node): bool => $node->parent_id === null || ! $nodesById->has($node->parent_id))
            ->groupBy(fn (NetworkNode $node): int => (int) $node->anchor_pop_id);

        $breadcrumbLabels = [];
        $labelFor = function (NetworkNode $node) use (&$labelFor, &$breadcrumbLabels, $nodesById): string {
            if (isset($breadcrumbLabels[$node->id])) {
                return $breadcrumbLabels[$node->id];
            }

            $parent = $node->parent_id ? $nodesById->get($node->parent_id) : null;
            $anchor = $node->anchorPop;
            $prefix = $parent ? $labelFor($parent) : implode(' / ', array_filter([$anchor?->parent?->name, $anchor?->name]));

            return $breadcrumbLabels[$node->id] = trim($prefix.' / '.$node->name, ' /');
        };
        foreach ($parentOptions as $option) {
            $labelFor($option);
        }

        $totalNodesCount = $parentOptions->count();
        $totalPortsCount = (int) $parentOptions->sum('ports_count');
        $totalUsedPortsCount = (int) $parentOptions->sum('used_ports_count');
        $redZoneNodesCount = $parentOptions->filter(fn ($n) => $n->isInRedZone())->count();

        $customerCabangMap = [];
        foreach ($topologyPops as $pop) {
            $customerCabangMap[$pop->id] = Pop::resolveCabangId($pop->id);
        }
        $availableCustomers = ! empty($visiblePopIds)
            ? Customer::query()
                ->whereIn('pop_id', $visiblePopIds)
                ->whereDoesntHave('portAssignments', fn ($q) => $q->where('active_key', 1))
                ->orderBy('full_name')
                ->limit(500)
                ->get(['id', 'full_name', 'customer_code', 'cid', 'pop_id'])
                ->map(fn ($cust) => [
                    'id' => $cust->id,
                    'full_name' => $cust->full_name,
                    'customer_code' => $cust->customer_code ?: ($cust->cid ?: '#'.$cust->id),
                    'pop_id' => $cust->pop_id,
                    'cabang_id' => $customerCabangMap[$cust->pop_id] ?? $cust->pop_id,
                ])->values()
            : collect();

        $hierarchy = app(InfrastructureHierarchyService::class)->build($user);
        $unseenNodeIds = $attention->unseenNodeIds($user);

        // `?node=` memilih ulang node yang terakhir dibuka: inspector menulis
        // parameter ini ke URL (history.replaceState) saat node dipilih, jadi
        // form di panel perangkat yang gagal validasi lalu `back()` mendarat
        // di node yang sama, bukan selalu node pertama. Hanya dipakai bila
        // node itu memang ada di `$nodes` (sudah ter-scope POP) — id di luar
        // scope diabaikan diam-diam, tidak 403 dan tidak membocorkan apa pun.
        $requestedNodeId = request()->integer('node');
        $selectedPosition = $requestedNodeId > 0 && ! $nodes->contains('id', $requestedNodeId)
            ? $this->selectedPositionPayload($requestedNodeId)
            : null;

        // ODP di bawah PON (umpan balik user 2026-10-26, putaran UI 3): ODP
        // HARUS tampil dengan inspector ODP yang sama (grid port, Hubungkan,
        // Tambah Port, modal port, tab Lokasi & Relokasi) dari mana pun dipilih.
        // ODP anak PON sengaja tidak ada di `$nodes` (fix C2), jadi yang
        // dipilih lewat `?node=` dimuat SENDIRIAN — tepat satu node, eager-load
        // sama dengan `$nodes`, rantai induk disambung ke instance perangkat
        // yang sudah dimuat. Jumlah query tambahan konstan, berapa pun ODP/PON-
        // nya (dites). Ditaruh di `$inspectorNodes`, BUKAN di `$nodes`: `$nodes`
        // juga sumber pohon/penghitung — induk PON-nya tidak ada di sana, jadi
        // ODP itu akan muncul sebagai node akar palsu dan ikut terhitung.
        $inspectorNodes = $nodes;
        $inspectorPopLabels = [];
        $treeOpenIds = [];
        $selectedOdp = $requestedNodeId > 0 && $selectedPosition === null && ! $nodes->contains('id', $requestedNodeId)
            ? $this->selectedOdpUnderPon($requestedNodeId)
            : null;
        if ($selectedOdp !== null) {
            $pon = $selectedOdp->parent;
            $slot = $pon->parent;
            $device = $slot?->parent_id !== null ? $loadedById->get($slot->parent_id) : null;
            if ($slot !== null && $device !== null) {
                $slot->setRelation('parent', $device);
            }
            $devicePart = $device !== null ? $labelFor($device) : (string) $slot?->parent?->code;
            $breadcrumbLabels[$selectedOdp->id] = trim(implode(' / ', array_filter([
                $devicePart, $slot?->displayName(), $pon->displayName(), $selectedOdp->name,
            ])), ' /');
            $cabangByNodeId->put($selectedOdp->id, $device !== null && $cabangByNodeId->has($device->id)
                ? $cabangByNodeId->get($device->id)
                : Pop::resolveCabangId($selectedOdp->resolvePop()?->id));
            // ODP anak PON tidak punya anchor sendiri — header "POP:" memakai
            // cabang hasil resolusi rantai induk, bukan "—".
            $inspectorPopLabels[$selectedOdp->id] = $selectedOdp->resolvePop()?->name;
            $inspectorNodes = $nodes->concat([$selectedOdp]);
            $treeOpenIds = array_values(array_filter([$slot?->parent_id, $slot?->id, $pon->id]));
        } elseif ($selectedPosition !== null) {
            $treeOpenIds = $this->ancestorIds($requestedNodeId);
        }

        $selectedNodeId = $requestedNodeId > 0 && ($nodes->contains('id', $requestedNodeId) || $selectedPosition !== null || $selectedOdp !== null)
            ? $requestedNodeId
            : $nodes->first(fn (NetworkNode $candidate): bool => ! in_array($candidate->nodeType?->code, ['pon_port', 'olt_card', 'aggregation_switch', 'backbone', 'feeder', 'drop_cable'], true))?->id;

        return view('network.nodes.index', compact(
            'nodes', 'pops', 'nodeTypes', 'parentOptions', 'treeChildren', 'breadcrumbLabels',
            'totalNodesCount', 'totalPortsCount', 'totalUsedPortsCount', 'redZoneNodesCount',
            'topologyPops', 'topologyPopChildren', 'uplinkPopChildren', 'rootNodesByPop', 'availableCustomers', 'hierarchy',
            'unseenNodeIds', 'relocateTargets', 'cabangByNodeId', 'selectedNodeId', 'selectedPosition',
            'inspectorNodes', 'inspectorPopLabels', 'treeOpenIds'
        ));
    }

    /**
     * ODP anak PON yang diminta `?node=`, dimuat dengan eager-load yang sama
     * dengan `$nodes` di `index()` (port + penghuni, aset, anchor) supaya blok
     * inspector ODP-nya dirender tanpa query per port. Scope POP = `inPops()`
     * yang sama dengan listing; di luar scope / bukan ODP / induknya bukan
     * PON → null, diabaikan diam-diam seperti `?node=` lain (tidak 403, tidak
     * membocorkan keberadaannya).
     */
    private function selectedOdpUnderPon(int $nodeId): ?NetworkNode
    {
        $user = auth()->user();
        $odp = NetworkNode::query()
            ->when(! $this->access->hasAllPopAccess($user), fn ($query) => $query->inPops($this->access->getAllowedPopIds($user)))
            ->whereHas('nodeType', fn ($query) => $query->where('code', 'odp'))
            ->with(
                'nodeType', 'ports.activeAssignment.customer', 'ports.activeAssignment.targetNode',
                'anchorPop.parent', 'parent.nodeType', 'parent.parent.nodeType', 'deployedSerials.item', 'infrastructureBalances.item'
            )
            ->withCount(['ports', 'ports as used_ports_count' => fn ($query) => $query->where('is_used', true)])
            ->find($nodeId);

        return $odp !== null && $odp->parent?->isPon() ? $odp : null;
    }

    /**
     * Leluhur Slot/PON terpilih (paling banyak tiga langkah naik, satu query
     * kecil per langkah) — dipakai pohon lazy untuk membuka cabang OLT → Slot
     * sampai baris terpilih terlihat setelah PRG `?node=`.
     *
     * @return list<int>
     */
    private function ancestorIds(int $nodeId): array
    {
        $ids = [];
        $parentId = NetworkNode::query()->whereKey($nodeId)->value('parent_id');
        $guard = 0;
        while ($parentId !== null && $guard++ < 3) {
            $ids[] = (int) $parentId;
            $parentId = NetworkNode::query()->whereKey($parentId)->value('parent_id');
        }

        return array_reverse($ids);
    }

    /**
     * `?node=` yang menunjuk Slot/PON: posisi SENGAJA tidak ada di `$nodes`
     * (fix C2), jadi pemilihan ulang setelah PRG dari panel Slot/PON butuh
     * data inspector-nya sendiri — SATU node saja, bentuknya sama dengan
     * baris JSON `children()` yang dipakai klik di pohon. Node di luar scope
     * atau bukan Slot/PON → null (diabaikan diam-diam seperti `?node=` lain,
     * tidak 403 dan tidak membocorkan keberadaannya).
     *
     * @return array{id: int, type: string, label: string, path: string, url: string, panel_url: string}|null
     */
    private function selectedPositionPayload(int $nodeId): ?array
    {
        $user = auth()->user();
        $node = NetworkNode::query()
            ->when(! $this->access->hasAllPopAccess($user), fn ($query) => $query->inPops($this->access->getAllowedPopIds($user)))
            ->with('nodeType')
            ->find($nodeId);

        if ($node === null || ! ($node->isSlot() || $node->isPon())) {
            return null;
        }

        return MasterNodeInspector::positionPayload($node, $this->positionPath($node));
    }

    public function store(Request $request, NetworkNodeService $service): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $user = auth()->user();
        $popRule = $this->access->hasAllPopAccess($user)
            ? Rule::exists('pops', 'id')
            : Rule::in($this->access->getAllowedPopIds($user));

        $validated = $request->validate([
            'node_type_id' => ['required', 'integer', Rule::exists('network_node_types', 'id')->where('is_active', true)],
            'parent_id' => ['nullable', 'integer', Rule::exists('network_nodes', 'id')],
            'anchor_pop_id' => ['nullable', 'integer', $popRule],
            'code' => ['required', 'string', 'max:50', Rule::unique('network_nodes', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000'],
            ...$this->locationRules(),
        ]);

        $parent = isset($validated['parent_id']) ? NetworkNode::findOrFail($validated['parent_id']) : null;
        $anchor = isset($validated['anchor_pop_id']) ? Pop::findOrFail($validated['anchor_pop_id']) : null;
        try {
            NetworkNodeService::assertPlacement($parent, $anchor);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['parent_id' => $exception->getMessage()]);
        }

        if ($parent !== null) {
            $this->authorizeNodeScope($parent);
        }

        $nodeType = NetworkNodeType::findOrFail($validated['node_type_id']);

        // Slot/PON/Port Switch HANYA lewat InfrastructurePositionService
        // (form Generate struktur / Tambah Slot / Tambah PON): di sana nomor
        // unik per induk, kode turunan, dan satuan kapasitas dijaga. Lewat
        // jalur generik ini POST buatan tangan bisa membuat "PON" tanpa
        // nomor dengan kode bebas — identitas posisinya rusak sejak lahir.
        if (in_array($nodeType->code, NetworkNode::positionTypeCodes(), true)) {
            throw ValidationException::withMessages([
                'node_type_id' => 'Slot/PON/Port dibuat lewat Generate struktur atau form Tambah Slot/PON pada perangkat, bukan form node umum.',
            ]);
        }

        // ODP wajib anak langsung PON (rancangan "Slot & PON sebagai node"
        // §2.4/§7, Task 12) — uplink sekarang murni `parent_id`, bukan lagi
        // sambungan `infrastructure_connections` terpisah (tabel itu + model
        // InfrastructurePort sudah dibuang Task 9). Dicek eksplisit di sini
        // (bukan cuma lewat `network_node_type_rules`) supaya pesannya jelas
        // dan tidak bergantung ke baris aturan lama (`odc`) yang sengaja
        // ditutup tapi belum tentu ikut dihapus dari tabel aturan.
        if ($nodeType->code === 'odp' && ($parent === null || ! $parent->isPon())) {
            throw ValidationException::withMessages(['parent_id' => 'ODP wajib dibuat sebagai anak langsung PON.']);
        }

        if (in_array($nodeType->code, InfrastructureCutover::LEGACY_NODE_TYPES, true)) {
            InfrastructureCutover::assertLegacyWriteAllowed('node_type_id', "Node {$nodeType->name}");
        }
        try {
            $service->assertTypePlacement($nodeType, $parent, $anchor);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['parent_id' => $exception->getMessage()]);
        }
        $isDevice = InfrastructureSlotKind::forDeviceTypeCode($nodeType->code) !== null;
        $hasLegacyPorts = $nodeType->has_ports && ! $isDevice;
        $portCount = $hasLegacyPorts ? (int) ($validated['capacity'] ?? 0) : 0;

        if ($hasLegacyPorts && $portCount < 1) {
            throw ValidationException::withMessages(['capacity' => 'Kapasitas port wajib minimal satu.']);
        }

        if ($nodeType->max_ports !== null && $portCount > $nodeType->max_ports) {
            throw ValidationException::withMessages(['capacity' => "Kapasitas maksimal {$nodeType->max_ports} port."]);
        }

        $node = DB::transaction(function () use ($validated, $parent, $anchor, $nodeType, $portCount, $hasLegacyPorts): NetworkNode {
            $node = NetworkNode::create([
                'parent_id' => $parent?->id,
                'anchor_pop_id' => $anchor?->id,
                'node_type_id' => $nodeType->id,
                'code' => $validated['code'],
                'name' => trim($validated['name']),
                'capacity' => $hasLegacyPorts ? null : ($validated['capacity'] ?? null),
                'status' => 'active',
                ...array_intersect_key($validated, array_flip(['address', 'village', 'district', 'city', 'latitude', 'longitude'])),
            ]);

            for ($number = 1; $number <= $portCount; $number++) {
                $node->ports()->create(['number' => $number, 'is_used' => false]);
            }

            return $node;
        });

        // PRG ke Detail record baru (docs/PRG_REDIRECT_CONVENTION.md): form
        // "Tambah ODP" di halaman PON tidak boleh mendarat di Master Node yang
        // tidak memuat ODP anak PON sama sekali (lazy) — user tak bisa melihat
        // hasilnya.
        // Dari panel PON Master Node (`master_node` = PON) kembali ke PON itu
        // terpilih — ODP barunya tampil di daftar ODP panel tersebut.
        return $this->redirectToNodeContext($request, redirect()->route('network.nodes.show', $node)->with('success', 'Node berhasil ditambahkan.'));
    }

    public function show(NetworkNode $node, NetworkNodeAttentionService $attention): View
    {
        $this->authorizeNodeScope($node);
        $attention->markSeen(auth()->user(), $node);
        $node->load([
            'nodeType',
            'ports.activeAssignment.customer.customerTechnicalDetail',
            'ports.activeAssignment.customer.customerDevice',
            'ports.activeAssignment.customer.inventorySerials' => fn ($query) => $query
                ->where('status', SerialStatus::INSTALLED->value)
                ->with('item.category'),
            'ports.activeAssignment.targetNode',
            'ports.activeAssignment.targetPort',
            'children.nodeType',
            'deployedSerials.item',
            'infrastructureBalances.item',
        ]);

        $user = auth()->user();
        $cabangPopId = Pop::resolveCabangId($node->resolvePop()?->id);
        $popIds = $cabangPopId !== null
            ? Pop::query()->where('id', $cabangPopId)->orWhere('parent_id', $cabangPopId)->pluck('id')->all()
            : [];
        $availableCustomers = $node->nodeType?->code === 'odp' && ! empty($popIds)
            ? Customer::query()
                ->whereIn('pop_id', $popIds)
                ->whereDoesntHave('portAssignments', fn ($q) => $q->where('active_key', 1))
                ->orderBy('full_name')
                ->limit(200)
                ->get(['id', 'full_name', 'customer_code', 'cid', 'pop_id'])
            : collect();

        return view('network.nodes.show', [
            'node' => $node,
            'hierarchy' => app(InfrastructureHierarchyService::class)->build($user),
            'nodeTypes' => NetworkNodeType::query()->where('is_active', true)->orWhere('id', $node->node_type_id)->orderBy('sort_order')->get(),
            'location' => $node->effectiveLocation(),
            'availableCustomers' => $availableCustomers,
            'infrastructureActions' => $node->deviceInfrastructureActions()->with(['task', 'technician'])->latest('performed_at')->limit(20)->get(),
            'canViewDeviceSerials' => $user->hasPermission('customers.detail.devices.view_sensitive'),
            'allowedCustomerPopIds' => $this->access->hasAllPopAccess($user) ? null : $this->access->getAllowedPopIds($user),
            // Halaman Detail OLT/Switch memakai partial level-perangkat yang
            // sama dengan panel Master Node (daftar Slot + kapasitas); PON/
            // Port tiap Slot dibuka di Detail Slot — satu level per halaman.
            'deviceSlots' => InfrastructureSlotKind::forDeviceTypeCode($node->nodeType?->code) !== null
                ? $node->slots()->with('nodeType')->withCount('children')->get()
                : collect(),
        ]);
    }

    /**
     * Anak langsung satu node, dimuat JIT (Task 11, rancangan "Slot & PON
     * sebagai node" §7) — Master Node default-nya tidak memuat Slot ke
     * bawah sama sekali (`InfrastructureHierarchyService::build()` dengan
     * `$withPositions = false`), supaya payload halaman gak ikut menyeret
     * ribuan baris PON/Port yang belum dibuka usernya. Dipanggil Alpine
     * lewat `data-children-url` yang dirender server-side (`route()`),
     * BUKAN dirakit dari id/type di klien (konvensi CLAUDE.md).
     *
     * Scope POP sama dengan `show()`/`markSeen()` — `authorizeNodeScope()`,
     * jadi node di luar scope dapat 403, bukan bocor lewat endpoint lain.
     */
    public function children(NetworkNode $node, PonCapacityService $ponCapacity): JsonResponse
    {
        $this->authorizeNodeScope($node);

        $children = $node->children()
            ->with('nodeType')
            ->withCount(['children', 'ports', 'ports as used_ports_count' => fn ($query) => $query->where('is_used', true)])
            ->orderBy('number')
            ->orderBy('code')
            ->get();

        // Satu query agregat untuk seluruh PON anak (Slot 16 PON = 1 query,
        // bukan 16).
        $ponSummaries = $ponCapacity->summaries($children->filter(fn (NetworkNode $child): bool => $child->isPon()));
        // Jalur induk dihitung SEKALI per permintaan (maks. dua query naik
        // induk), lalu tiap anak cukup menambahkan labelnya sendiri.
        $node->loadMissing('nodeType');
        $parentPath = $this->positionPath($node);

        return response()->json(['children' => $children->map(function (NetworkNode $child) use ($ponSummaries, $parentPath): array {
            $capacity = null;
            $used = null;
            if ($child->isPon()) {
                $capacity = $ponSummaries[$child->id]['capacity'];
                $used = $ponSummaries[$child->id]['used'];
            } elseif ($child->isSlot()) {
                // Definisi sama dengan panel OLT: terpakai Slot = PON/Port
                // terdata, kapasitas = kapasitas Slot (null = belum didata).
                $capacity = $child->capacity;
                $used = (int) $child->children_count;
            } elseif ((bool) $child->nodeType?->has_ports) {
                $capacity = (int) $child->ports_count;
                $used = (int) $child->used_ports_count;
            } else {
                $capacity = $child->capacity;
            }

            return [
                'id' => $child->id,
                'key' => 'node:'.$child->id,
                'type' => $child->nodeType?->code ?? 'other',
                'label' => $child->isPosition() ? $child->displayName() : $child->code,
                'has_children' => (int) $child->children_count > 0,
                'capacity' => $capacity,
                'used' => $used,
                'record_status' => $child->record_status?->value,
                'url' => match (true) {
                    $child->isSlot() => route('network.infrastructure-slots.show', $child),
                    $child->isPort() => route('network.infrastructure-ports.show', $child),
                    default => route('network.nodes.show', $child),
                },
                // URL anak & target POST dirender DI SINI (server), bukan
                // dirakit Alpine dari id — pohon lazy bertingkat dan form
                // "Tambah PON" di modal hanya memilih di antara URL ini
                // (konvensi CLAUDE.md, PostTargetRenderedServerSideTest).
                'children_url' => (int) $child->children_count > 0 ? route('network.nodes.children', $child) : null,
                'store_port_url' => $child->isSlot() ? route('network.infrastructure-slots.ports.store', $child) : null,
                // Slot/PON bisa DIPILIH di pohon: inspector kanan memuat panel
                // per level dari URL ini (dirender server, bukan dirakit klien).
                // null = baris tetap tautan ke Detail (ODP, Port Switch). OLT/
                // Switch tidak lewat sini: mereka ada di `$nodes` dan dipilih
                // lewat inspector biasa (`selectNode`).
                'panel_url' => $child->isSlot() || $child->isPon() ? route('network.nodes.panel', $child) : null,
                // ODP dipilih lewat Master Node `?node=` (putaran UI 3): index
                // memuat SATU ODP itu dengan inspector ODP lengkap (grid port,
                // modal port, relokasi). URL dirender di sini, bukan dirakit klien.
                'select_url' => $child->nodeType?->code === 'odp' ? route('network.nodes.index', ['node' => $child->id]) : null,
                'path' => $parentPath.' / '.($child->isPosition() ? $child->displayName() : $child->code),
            ];
        })->values()]);
    }

    /**
     * Daftar PON milik satu OLT, berlabel jalur "OLT / Slot n / PON m" —
     * sumber pilihan "PON induk" di modal Tambah ODP dan relokasi ODP di
     * Master Node. Dimuat per OLT (bukan semua PON sekaligus di halaman):
     * satu deploy bisa punya ribuan PON. Scope POP sama dengan `children()`
     * lewat `authorizeNodeScope()` — OLT cabang lain 403, jadi PON-nya tidak
     * pernah bocor ke pilihan.
     */
    public function ponOptions(NetworkNode $node): JsonResponse
    {
        $this->authorizeNodeScope($node);
        $node->loadMissing('nodeType');
        abort_unless(InfrastructureSlotKind::forDeviceTypeCode($node->nodeType?->code) === InfrastructureSlotKind::OLT, 404);

        $pons = NetworkNode::query()
            ->whereIn('parent_id', $node->slots()->reorder()->select('network_nodes.id'))
            ->whereHas('nodeType', fn ($query) => $query->where('code', InfrastructurePortKind::PON->typeCode()))
            ->with('parent')
            ->get()
            ->sortBy(fn (NetworkNode $pon): array => [(int) $pon->parent?->number, (int) $pon->number])
            ->values();

        return response()->json(['options' => $pons->map(fn (NetworkNode $pon): array => [
            'id' => $pon->id,
            'label' => "{$node->code} / Slot {$pon->parent?->number} / PON {$pon->number}",
        ])->values()]);
    }

    /**
     * Pencarian PON untuk field "PON induk" di modal Tambah ODP (umpan balik
     * user 2026-10-26: satu field bercari, bukan OLT → PON dua langkah).
     * Dicari di SERVER dan dibatasi 20 baris karena satu deploy bisa punya
     * ribuan PON — memuat semuanya ke halaman persis masalah O(N²) final
     * review C2. Slot & perangkat diambil lewat JOIN (bukan eager-load per
     * PON), jadi jumlah query konstan berapa pun PON-nya.
     *
     * Scope POP = `inPops()` yang SAMA dengan `authorizeNodeScope()`/listing:
     * PON cabang lain tidak muncul walau kodenya diketik persis, dan scope
     * kosong = deny-by-default (`inPops([])` → 1 = 0), bukan "semua".
     */
    public function ponSearch(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) ($validated['q'] ?? ''));
        $user = $request->user();
        $ponTypeId = NetworkNodeType::query()->where('code', InfrastructurePortKind::PON->typeCode())->value('id');

        if ($ponTypeId === null) {
            return response()->json(['options' => []]);
        }

        $pons = NetworkNode::query()
            ->join('network_nodes as pon_slot', 'pon_slot.id', '=', 'network_nodes.parent_id')
            ->join('network_nodes as pon_device', 'pon_device.id', '=', 'pon_slot.parent_id')
            ->where('network_nodes.node_type_id', $ponTypeId)
            ->when(! $this->access->hasAllPopAccess($user), fn ($query) => $query->inPops($this->access->getAllowedPopIds($user)))
            ->when($term !== '', function ($query) use ($term): void {
                // `!` sebagai karakter escape LIKE, eksplisit lewat ESCAPE:
                // SQLite tidak punya escape default (beda dengan MySQL), jadi
                // tanpa ini "%"/"_" ketikan user jadi wildcard di satu DB saja.
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
                $query->where(fn ($match) => $match
                    ->whereRaw("network_nodes.code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("pon_device.code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("pon_device.name LIKE ? ESCAPE '!'", [$pattern]));
            })
            ->orderBy('pon_device.code')
            ->orderBy('pon_slot.number')
            ->orderBy('network_nodes.number')
            ->limit(20)
            ->get(['network_nodes.id', 'network_nodes.number', 'pon_slot.number as slot_number', 'pon_device.code as device_code']);

        return response()->json(['options' => $pons->map(fn (NetworkNode $pon): array => [
            'id' => $pon->id,
            'label' => "{$pon->device_code} / Slot {$pon->slot_number} / PON {$pon->number}",
        ])->values()]);
    }

    /**
     * Panel "Manajemen Node — <tipe>" per level untuk inspector Master Node
     * (spesifikasi user 2026-10-26, putaran UI 2): tiap level HANYA
     * menampilkan anak langsungnya beserta kapasitasnya —
     *   OLT/Switch → daftar Slot (PON/Port terdata vs kapasitas slot),
     *   Slot       → daftar PON/Port Switch slot itu (pelanggan vs kapasitas PON),
     *   PON        → daftar ODP PON itu (port terpakai vs jumlah port ODP).
     * Dulu panel OLT menumpuk kartu PON di dalam tiap Slot — terlalu banyak
     * untuk dibaca, dan Slot/PON sendiri tidak punya inspector sama sekali.
     *
     * Dimuat LAZY untuk node yang dipilih saja: merender panel untuk setiap
     * node di index membuat halaman O(N²) (final review C2). Jumlah query
     * tiap panel konstan terhadap jumlah anak (kapasitas lewat agregat
     * massal `PonCapacityService::summaries()`/`customersPerOdp()` dan
     * `InfrastructureConnectionService::occupanciesOf()`), dijaga tes.
     *
     * Mengembalikan potongan HTML server-rendered tanpa atribut Alpine; form
     * di dalamnya form biasa ber-@csrf dengan target `route()` dan membawa
     * `master_node` supaya PRG kembali ke Master Node dengan node yang sama
     * terpilih. Scope dicek dulu (403) baru tipe (404), sama urutannya
     * dengan `ponOptions()` — node cabang lain tidak bisa ditebak tipenya.
     */
    public function panel(NetworkNode $node, PonCapacityService $ponCapacity, InfrastructureConnectionService $connections): View
    {
        $this->authorizeNodeScope($node);
        $node->loadMissing('nodeType');

        if (InfrastructureSlotKind::forDeviceTypeCode($node->nodeType?->code) !== null) {
            return view('network.nodes._panel-device-slots', [
                'device' => $node,
                'slots' => $node->slots()->with('nodeType')->withCount('children')->get(),
                'inMasterNode' => true,
            ]);
        }

        if ($node->isSlot()) {
            $ports = $node->positionPorts()->with('nodeType')->withCount('children')->get();
            $isOlt = $node->slotKind() === InfrastructureSlotKind::OLT;

            return view('network.nodes._panel-slot-ports', [
                'slot' => $node,
                'ports' => $ports,
                'isOlt' => $isOlt,
                'ponSummaries' => $isOlt ? $ponCapacity->summaries($ports) : [],
                'occupancies' => $isOlt ? [] : $connections->occupanciesOf($ports),
                'path' => $this->positionPath($node),
                'inMasterNode' => true,
            ]);
        }

        abort_unless($node->isPon(), 404);
        $odps = $node->children()->with('nodeType')
            ->withCount(['ports', 'ports as used_ports_count' => fn ($query) => $query->where('is_used', true)])
            ->orderBy('code')
            ->get();

        return view('network.nodes._panel-pon-odps', [
            'pon' => $node,
            'summary' => $ponCapacity->summaries([$node])[$node->id],
            'odps' => $odps,
            'customersPerOdp' => $ponCapacity->customersPerOdp($odps),
            'odpTypeId' => NetworkNodeType::query()->where('code', 'odp')->where('is_active', true)->value('id'),
            'path' => $this->positionPath($node),
            'inMasterNode' => true,
        ]);
    }

    /**
     * Jalur tampilan posisi, mis. `OLT-PNG-01 / Slot 2 / PON 3` — breadcrumb
     * inspector Slot/PON di Master Node. Naik paling banyak dua induk (PON →
     * Slot → perangkat), jadi query-nya terbatas dan tidak tergantung
     * jumlah anak.
     */
    private function positionPath(NetworkNode $node): string
    {
        $parts = [];
        $current = $node;
        $guard = 0;
        while ($current !== null && $guard++ < 4) {
            $current->loadMissing('nodeType');
            $parts[] = $current->isPosition() ? $current->displayName() : (string) $current->code;
            if (! $current->isPosition()) {
                break;
            }
            $current = $current->parent()->with('nodeType')->first();
        }

        return implode(' / ', array_reverse($parts));
    }

    public function markSeen(NetworkNode $node, NetworkNodeAttentionService $attention): JsonResponse
    {
        $this->authorizeNodeScope($node);
        $attention->markSeen(auth()->user(), $node);

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, NetworkNode $node, NetworkNodeService $service, MasterRecordRemovalService $removal, InfrastructurePositionService $positions): RedirectResponse
    {
        $this->authorizeNodeScope($node);
        $node->loadMissing('nodeType');

        // Kode & tipe Slot/PON/Port DITURUNKAN dari induknya (`{OLT}-S3-P5`,
        // bisa sampai 120 karakter) — bukan isian user. Nilai kiriman
        // diabaikan di server, bukan cuma disembunyikan di form: tanpa ini
        // aturan `max:50` menolak setiap simpan nama PON berkode panjang, dan
        // POST buatan tangan bisa memutus kode dari jalurnya.
        if ($node->isPosition()) {
            $request->request->remove('code');
            $request->request->remove('node_type_id');
        } elseif ($request->exists('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('network_nodes', 'code')->ignore($node)],
            'node_type_id' => ['sometimes', 'required', 'integer', Rule::exists('network_node_types', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            ...$this->locationRules(),
        ]);

        $typeChanged = isset($validated['node_type_id']) && (int) $validated['node_type_id'] !== $node->node_type_id;
        $codeChanged = isset($validated['code']) && $validated['code'] !== $node->code;
        if ($typeChanged && $removal->hasDependents($node)) {
            throw ValidationException::withMessages([
                'node_type_id' => 'Tipe node yang sudah memiliki relasi tidak dapat diubah.',
            ]);
        }
        if ($codeChanged && CustomerTechnicalDetail::query()->where('odp_number', $node->code)->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Kode node masih dipakai sebagai rujukan ODP pelanggan dan tidak dapat diubah.',
            ]);
        }

        if ($typeChanged) {
            $nodeType = NetworkNodeType::findOrFail($validated['node_type_id']);
            if (in_array($nodeType->code, InfrastructureCutover::LEGACY_NODE_TYPES, true)) {
                InfrastructureCutover::assertLegacyWriteAllowed('node_type_id', "Node {$nodeType->name}");
            }
            if (! $nodeType->is_active) {
                throw ValidationException::withMessages(['node_type_id' => 'Tipe node tidak aktif.']);
            }

            try {
                $service->assertTypePlacement($nodeType, $node->parent, $node->anchorPop);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['node_type_id' => $exception->getMessage()]);
            }
        }

        $oldValues = $node->only(array_keys($validated));
        try {
            DB::transaction(function () use ($node, $validated, $codeChanged, $positions, $oldValues): void {
                $node->update($validated);
                // Ganti kode perangkat → kode turunan Slot/PON/Port di bawahnya
                // ikut diganti DALAM transaksi yang sama, supaya `{kode}-S3-P5`
                // tidak menunjuk kode perangkat lama (identitas posisi = jalur).
                if ($codeChanged) {
                    $positions->rederiveCodes($node);
                }
                if ($node->wasChanged()) {
                    AuditLog::log($node, 'update', $oldValues, $node->only(array_keys($validated)));
                }
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        return redirect()->route('network.nodes.show', $node)->with('success', 'Detail node berhasil diperbarui.');
    }

    public function destroy(NetworkNode $node, MasterRecordRemovalService $removal): RedirectResponse
    {
        $this->authorizeNodeScope($node);
        $deleted = DB::transaction(function () use ($node, $removal): bool {
            $lockedNode = NetworkNode::query()->whereKey($node->id)->lockForUpdate()->firstOrFail();
            $oldValues = $lockedNode->only(['code', 'name', 'node_type_id', 'status']);
            $deleted = $removal->remove($lockedNode, ['status' => 'inactive']);
            AuditLog::log($lockedNode, $deleted ? 'delete' : 'deactivate', $oldValues, $deleted ? null : $lockedNode->only(['code', 'name', 'node_type_id', 'status']));

            return $deleted;
        });

        return redirect()->route('network.nodes.index')->with(
            'success',
            $deleted ? 'Node kosong berhasil dihapus.' : 'Node memiliki relasi dan telah dinonaktifkan.'
        );
    }

    private function locationRules(): array
    {
        return [
            'address' => ['nullable', 'string', 'max:2000'],
            'village' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function move(Request $request, NetworkNode $node, NetworkNodeService $service): RedirectResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('network_nodes', 'id')],
            'anchor_pop_id' => ['nullable', 'integer', Rule::exists('pops', 'id')],
        ]);
        $parent = isset($validated['parent_id']) ? NetworkNode::findOrFail($validated['parent_id']) : null;
        $anchor = isset($validated['anchor_pop_id']) ? Pop::findOrFail($validated['anchor_pop_id']) : null;

        try {
            $service->move($node, $parent, $anchor, $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['parent_id' => $exception->getMessage()]);
        }

        // Dari inspector Master Node (`master_node` = node yang dipindah) kembali
        // ke node itu terpilih — ODP anak PON tidak ada di daftar default index,
        // jadi tanpa ini user mendarat di node pertama dan kehilangan hasilnya.
        return $this->redirectToNodeContext($request, redirect()->route('network.nodes.index')->with('success', 'Node berhasil dipindah.'));
    }

    public function addPorts(Request $request, NetworkNode $node): RedirectResponse
    {
        $this->authorizeNodeScope($node);

        $validated = $request->validate([
            'additional_capacity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        DB::transaction(function () use ($node, $validated): void {
            $lockedNode = NetworkNode::query()->whereKey($node->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedNode->nodeType?->has_ports, 422, 'Tipe node ini tidak memiliki port.');
            abort_if(InfrastructureSlotKind::forDeviceTypeCode($lockedNode->nodeType?->code) !== null,
                422, 'Port OLT/Switch harus dibuat pada Slot perangkat, bukan sebagai port node lama.');

            $lastNumber = (int) $lockedNode->ports()->max('number');
            // max_ports null = tipe tanpa batas — cek dilewati, bukan dianggap 0.
            $maxPorts = $lockedNode->nodeType?->max_ports;
            abort_if(
                $maxPorts !== null && $lastNumber + $validated['additional_capacity'] > $maxPorts,
                422,
                "Kapasitas maksimal {$maxPorts} port."
            );

            for ($number = $lastNumber + 1; $number <= $lastNumber + $validated['additional_capacity']; $number++) {
                $lockedNode->ports()->create(['number' => $number, 'is_used' => false]);
            }
        });

        return $this->redirectToNodeContext($request, redirect()->route('network.nodes.index')->with('success', 'Kapasitas node berhasil ditambah.'));
    }

    public function updatePort(Request $request, NetworkNodePort $port, NetworkPortAssignmentService $assignments): RedirectResponse
    {
        $port->load('node');
        $this->authorizeNodeScope($port->node);

        $validated = $request->validate([
            'is_used' => ['required', 'boolean'],
            'physical_removal_confirmed' => ['accepted_if:is_used,0'],
            'release_note' => [Rule::requiredIf(! $request->boolean('is_used')), 'nullable', 'string', 'max:500'],
        ]);
        if ((bool) $validated['is_used']) {
            $assignments->assignManual($port, 'Terpakai (belum diverifikasi)', $request->user());

            return $this->redirectToNodeContext($request, redirect()->route('network.nodes.index')->with('success', 'Port ditandai terpakai dan perlu diverifikasi.'));
        }

        $assignments->release($port, $request->user(), $validated['release_note']);

        return redirect()->back(fallback: route('network.nodes.show', $port->node))->with('success', 'Port berhasil dilepas dengan catatan fisik.');
    }

    public function assignNodePort(Request $request, NetworkNodePort $port, NetworkPortAssignmentService $assignments): RedirectResponse
    {
        $port->load('node');
        $this->authorizeNodeScope($port->node);
        $validated = $request->validate([
            'target_node_id' => ['required', 'integer', Rule::exists('network_nodes', 'id')],
        ]);
        $target = NetworkNode::findOrFail($validated['target_node_id']);
        $this->authorizeNodeScope($target);

        try {
            $assignments->assignNode($port, $target, $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['target_node_id' => $exception->getMessage()]);
        }

        return redirect()->back(fallback: route('network.nodes.show', $port->node))->with('success', 'Anak node terhubung ke port.');
    }

    public function assignCustomerPort(Request $request, NetworkNodePort $port, NetworkPortAssignmentService $assignments): RedirectResponse
    {
        $port->load('node');
        $this->authorizeNodeScope($port->node);
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
        ]);
        $customer = Customer::findOrFail($validated['customer_id']);

        try {
            $assignments->assignCustomer($port, $customer, $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['customer_id' => $exception->getMessage()]);
        }

        return redirect()->back(fallback: route('network.nodes.show', $port->node))->with('success', "Pelanggan {$customer->full_name} berhasil disambungkan ke port #{$port->number}.");
    }

    /**
     * 403 kalau node di luar scope POP user. Sengaja memakai `scopeInPops()`
     * yang SAMA dengan listing `index()`, bukan membandingkan
     * `resolvePop()` (yang selalu naik ke Cabang): user ber-scope
     * SELECTED_POP satu Mini POP saja bakal melihat node yang ditambatkan di
     * Mini POP itu di listing, tapi 403 saat mengubah port-nya kalau yang
     * dibandingkan id Cabang induknya. Satu definisi "node dalam scope" untuk
     * lihat dan ubah. Node tanpa anchor terselesaikan otomatis tidak lolos
     * (deny-by-default).
     */
    private function authorizeNodeScope(NetworkNode $node): void
    {
        $user = auth()->user();

        if ($this->access->hasAllPopAccess($user)) {
            return;
        }

        abort_unless(
            NetworkNode::query()->inPops($this->access->getAllowedPopIds($user))->whereKey($node->id)->exists(),
            403
        );
    }
}
