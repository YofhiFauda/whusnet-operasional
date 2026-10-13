<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\NetworkNodeService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
        $nodeTypes = NetworkNodeType::where('has_ports', true)->where('is_active', true)->orderBy('sort_order')->get();

        return view('network.nodes.index', compact('nodes', 'pops', 'nodeTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $user = auth()->user();
        $popRule = $this->access->hasAllPopAccess($user)
            ? Rule::exists('pops', 'id')
            : Rule::in($this->access->getAllowedPopIds($user));
        $nodeType = NetworkNodeType::find($request->integer('node_type_id'));

        $validated = $request->validate([
            'node_type_id' => [
                'required',
                'integer',
                Rule::exists('network_node_types', 'id'),
                // Endpoint ini selalu membuat N port sekaligus — tipe tanpa
                // port (Core Router, Backbone, dst) tidak punya arti di sini.
                function (string $attribute, mixed $value, Closure $fail) use ($nodeType): void {
                    if ($nodeType !== null && ! $nodeType->has_ports) {
                        $fail('Tipe node ini tidak memiliki port.');
                    }
                },
            ],
            'anchor_pop_id' => ['required', 'integer', $popRule],
            'code' => ['required', 'string', 'max:50', Rule::unique('network_nodes', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'capacity' => [
                'required',
                'integer',
                'min:1',
                // Plafon datar: tipe `max_ports = null` (tanpa batas) membuat
                // closure di bawah dilewati, padahal loop di bawah membuat satu
                // baris per port — tanpa ini satu request bisa minta jutaan baris.
                'max:1000',
                function (string $attribute, mixed $value, Closure $fail) use ($nodeType): void {
                    if ($nodeType?->max_ports !== null && (int) $value > $nodeType->max_ports) {
                        $fail("Kapasitas maksimal {$nodeType->max_ports} port.");
                    }
                },
            ],
        ]);

        // Jalur ini cuma membuat node puncak (ditambatkan ke POP, sama dengan
        // ODP flat lama). Aturan XOR tetap lewat satu pintu.
        NetworkNodeService::assertPlacement(null, Pop::findOrFail($validated['anchor_pop_id']));

        DB::transaction(function () use ($validated): void {
            $node = NetworkNode::create([
                'parent_id' => null,
                'anchor_pop_id' => $validated['anchor_pop_id'],
                'node_type_id' => $validated['node_type_id'],
                'code' => strtoupper(trim($validated['code'])),
                'name' => trim($validated['name']),
                'status' => 'active',
            ]);

            for ($number = 1; $number <= $validated['capacity']; $number++) {
                $node->ports()->create(['number' => $number, 'is_used' => false]);
            }
        });

        return redirect()->route('network.nodes.index')->with('success', 'Node dan port berhasil ditambahkan.');
    }

    public function addPorts(Request $request, NetworkNode $node): RedirectResponse
    {
        $this->authorizeNodeScope($node);

        $validated = $request->validate([
            'additional_capacity' => ['required', 'integer', 'min:1', 'max:128'],
        ]);

        DB::transaction(function () use ($node, $validated): void {
            $lockedNode = NetworkNode::query()->whereKey($node->id)->lockForUpdate()->firstOrFail();
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

    public function updatePort(Request $request, NetworkNodePort $port): RedirectResponse
    {
        $port->load('node');
        $this->authorizeNodeScope($port->node);

        $validated = $request->validate([
            'is_used' => ['required', 'boolean'],
        ]);
        $port->update(['is_used' => (bool) $validated['is_used']]);

        return redirect()->route('network.nodes.index')->with('success', 'Status port berhasil diperbarui.');
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
