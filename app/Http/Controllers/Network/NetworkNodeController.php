<?php

namespace App\Http\Controllers\Network;

use App\Enums\InfrastructureSlotKind;
use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureHierarchyService;
use App\Services\MasterRecordRemovalService;
use App\Services\NetworkNodeAttentionService;
use App\Services\NetworkNodeService;
use App\Services\NetworkPortAssignmentService;
use App\Services\PonCapacityService;
use App\Support\InfrastructureCutover;
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
    public function __construct(private readonly EffectiveAccessService $access) {}

    public function index(NetworkNodeAttentionService $attention): View
    {
        $user = auth()->user();
        $nodes = NetworkNode::query()
            ->when(! $this->access->hasAllPopAccess($user), fn ($query) => $query->inPops($this->access->getAllowedPopIds($user)))
            ->with(
                'nodeType', 'ports.activeAssignment.customer', 'ports.activeAssignment.targetNode',
                'anchorPop.parent', 'parent', 'deployedSerials.item', 'infrastructureBalances.item', 'children.nodeType'
            )
            ->withCount(['ports', 'ports as used_ports_count' => fn ($query) => $query->where('is_used', true)])
            ->orderBy('code')
            ->get();
        $pops = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();
        $nodeTypes = NetworkNodeType::where('is_active', true)->orderBy('sort_order')->get();
        $parentOptions = $nodes;
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

        return view('network.nodes.index', compact(
            'nodes', 'pops', 'nodeTypes', 'parentOptions', 'treeChildren', 'breadcrumbLabels',
            'totalNodesCount', 'totalPortsCount', 'totalUsedPortsCount', 'redZoneNodesCount',
            'topologyPops', 'topologyPopChildren', 'uplinkPopChildren', 'rootNodesByPop', 'availableCustomers', 'hierarchy',
            'unseenNodeIds'
        ));
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

        DB::transaction(function () use ($validated, $parent, $anchor, $nodeType, $portCount, $hasLegacyPorts): void {
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
        });

        return redirect()->route('network.nodes.index')->with('success', 'Node berhasil ditambahkan.');
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

        return response()->json(['children' => $children->map(function (NetworkNode $child) use ($ponCapacity): array {
            $capacity = null;
            $used = null;
            if ($child->isPon()) {
                $summary = $ponCapacity->summary($child);
                $capacity = $summary['capacity'];
                $used = $summary['used'];
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
                'url' => route('network.nodes.show', $child),
            ];
        })->values()]);
    }

    public function markSeen(NetworkNode $node, NetworkNodeAttentionService $attention): JsonResponse
    {
        $this->authorizeNodeScope($node);
        $attention->markSeen(auth()->user(), $node);

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, NetworkNode $node, NetworkNodeService $service, MasterRecordRemovalService $removal): RedirectResponse
    {
        $this->authorizeNodeScope($node);
        if ($request->exists('code')) {
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
        $node->update($validated);
        if ($node->wasChanged()) {
            AuditLog::log($node, 'update', $oldValues, $node->only(array_keys($validated)));
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

        return redirect()->route('network.nodes.index')->with('success', 'Node berhasil dipindah.');
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

        return redirect()->route('network.nodes.index')->with('success', 'Kapasitas node berhasil ditambah.');
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

            return redirect()->route('network.nodes.index')->with('success', 'Port ditandai terpakai dan perlu diverifikasi.');
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
