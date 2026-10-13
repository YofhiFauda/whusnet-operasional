<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\NetworkNodeService;
use App\Services\NetworkPortAssignmentService;
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

    public function index(Request $request): View
    {
        $user = auth()->user();
        $nodes = NetworkNode::query()
            ->when(! $this->access->hasAllPopAccess($user), function ($query) use ($user) {
                $query->inPops($this->access->getAllowedPopIds($user));
            })
            ->with('nodeType', 'ports', 'anchorPop.parent', 'parent', 'deployedSerials.item', 'infrastructureBalances.item')
            ->withCount(['ports', 'ports as used_ports_count' => fn ($query) => $query->where('is_used', true)])
            ->when($request->filled('node_type_id'), fn ($query) => $query->where('node_type_id', $request->integer('node_type_id')))
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();
        $pops = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();
        $nodeTypes = NetworkNodeType::where('is_active', true)->orderBy('sort_order')->get();
        $parentOptions = NetworkNode::query()
            ->when(! $this->access->hasAllPopAccess($user), fn ($query) => $query->inPops($this->access->getAllowedPopIds($user)))
            ->with('nodeType', 'anchorPop.parent')
            ->withCount(['ports', 'ports as used_ports_count' => fn ($query) => $query->where('is_used', true)])
            ->orderBy('code')->get();
        $treeChildren = $parentOptions->groupBy(fn (NetworkNode $node) => $node->parent_id ?? 0);
        $nodesById = $parentOptions->keyBy('id');
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

        return view('network.nodes.index', compact(
            'nodes', 'pops', 'nodeTypes', 'parentOptions', 'treeChildren', 'breadcrumbLabels',
            'totalNodesCount', 'totalPortsCount', 'totalUsedPortsCount', 'redZoneNodesCount'
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
        try {
            $service->assertTypePlacement($nodeType, $parent, $anchor);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['parent_id' => $exception->getMessage()]);
        }
        $portCount = $nodeType->has_ports ? (int) ($validated['capacity'] ?? 0) : 0;

        if ($nodeType->has_ports && $portCount < 1) {
            throw ValidationException::withMessages(['capacity' => 'Kapasitas port wajib minimal satu.']);
        }

        if ($nodeType->max_ports !== null && $portCount > $nodeType->max_ports) {
            throw ValidationException::withMessages(['capacity' => "Kapasitas maksimal {$nodeType->max_ports} port."]);
        }

        DB::transaction(function () use ($validated, $parent, $anchor, $nodeType, $portCount): void {
            $node = NetworkNode::create([
                'parent_id' => $parent?->id,
                'anchor_pop_id' => $anchor?->id,
                'node_type_id' => $nodeType->id,
                'code' => $validated['code'],
                'name' => trim($validated['name']),
                'capacity' => $nodeType->has_ports ? null : ($validated['capacity'] ?? null),
                'status' => 'active',
                ...array_intersect_key($validated, array_flip(['address', 'village', 'district', 'city', 'latitude', 'longitude'])),
            ]);

            for ($number = 1; $number <= $portCount; $number++) {
                $node->ports()->create(['number' => $number, 'is_used' => false]);
            }
        });

        return redirect()->route('network.nodes.index')->with('success', 'Node berhasil ditambahkan.');
    }

    public function show(NetworkNode $node): View
    {
        $this->authorizeNodeScope($node);
        $node->load('nodeType', 'ports.activeAssignment.customer', 'ports.activeAssignment.targetNode', 'children.nodeType', 'deployedSerials.item', 'infrastructureBalances.item');

        return view('network.nodes.show', ['node' => $node, 'location' => $node->effectiveLocation()]);
    }

    public function update(Request $request, NetworkNode $node): RedirectResponse
    {
        $this->authorizeNodeScope($node);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            ...$this->locationRules(),
        ]);

        $node->update($validated);

        return redirect()->route('network.nodes.show', $node)->with('success', 'Detail node berhasil diperbarui.');
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

        return redirect()->route('network.nodes.show', $port->node)->with('success', 'Port berhasil dilepas dengan catatan fisik.');
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

        return redirect()->route('network.nodes.show', $port->node)->with('success', 'Anak node terhubung ke port.');
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
