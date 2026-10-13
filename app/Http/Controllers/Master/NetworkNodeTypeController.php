<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\NetworkNodeType;
use App\Services\MasterRecordRemovalService;
use App\Support\LikeSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NetworkNodeTypeController extends Controller
{
    public function index(Request $request): View
    {
        $search = LikeSearch::sanitize((string) $request->query('search', ''));

        $types = NetworkNodeType::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('master.network-node-types.index', compact('types', 'search'));
    }

    public function create(): View
    {
        $parentTypes = NetworkNodeType::query()->active()->orderBy('name')->get();

        return view('master.network-node-types.create', compact('parentTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateType($request);
        $rules = $this->validateRules($request);

        DB::transaction(function () use ($validated, $rules): void {
            $type = NetworkNodeType::create($validated);
            if ($rules !== null) {
                $this->saveRules($type, $rules);
            }
        });

        return redirect()
            ->route('master.network-node-types.index')
            ->with('success', 'Tipe node "'.$validated['name'].'" berhasil ditambahkan.');
    }

    public function edit(NetworkNodeType $networkNodeType): View
    {
        $parentTypes = NetworkNodeType::query()->active()->whereKeyNot($networkNodeType->id)->orderBy('name')->get();
        $rules = DB::table('network_node_type_rules')->where('child_type_id', $networkNodeType->id)->get();
        $allowedParentTypeIds = $rules->pluck('parent_type_id')->filter()->map(fn ($id) => (int) $id)->all();
        $allowedAnchorPopTypes = $rules->pluck('anchor_pop_type')->filter()->all();

        return view('master.network-node-types.edit', [
            'type' => $networkNodeType,
            'parentTypes' => $parentTypes,
            'allowedParentTypeIds' => $allowedParentTypeIds,
            'allowedAnchorPopTypes' => $allowedAnchorPopTypes,
        ]);
    }

    public function update(Request $request, NetworkNodeType $networkNodeType): RedirectResponse
    {
        $validated = $this->validateType($request, $networkNodeType);
        $rules = $this->validateRules($request, $networkNodeType);

        DB::transaction(function () use ($networkNodeType, $validated, $rules): void {
            $networkNodeType->update($validated);
            if ($rules !== null) {
                $this->saveRules($networkNodeType, $rules);
            }
        });

        return redirect()
            ->route('master.network-node-types.index')
            ->with('success', 'Tipe node "'.$networkNodeType->name.'" berhasil diperbarui.');
    }

    public function destroy(NetworkNodeType $networkNodeType, MasterRecordRemovalService $removal): RedirectResponse
    {
        $deleted = $removal->remove($networkNodeType, ['is_active' => false]);

        return back()->with(
            $deleted ? 'success' : 'warning',
            $deleted
                ? "Tipe node \"{$networkNodeType->name}\" berhasil dihapus."
                : "Tipe node \"{$networkNodeType->name}\" masih dipakai node jaringan atau SKU Gudang, jadi hanya dinonaktifkan."
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateType(Request $request, ?NetworkNodeType $type = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('network_node_types', 'code')->ignore($type)],
            'name' => ['required', 'string', 'max:100'],
            'max_ports' => ['nullable', 'integer', 'min:0'],
            'red_zone_threshold' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
            'has_ports' => ['nullable', 'boolean'],
            'monitors_capacity' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['has_ports'] = $request->boolean('has_ports');
        $validated['monitors_capacity'] = $request->boolean('monitors_capacity');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        return $validated;
    }

    /**
     * @return array{parents:list<int>,anchors:list<string>}|null
     */
    private function validateRules(Request $request, ?NetworkNodeType $type = null): ?array
    {
        if (! $request->boolean('rules_configured')) {
            return null;
        }

        $validated = $request->validate([
            'allowed_parent_type_ids' => ['sometimes', 'array'],
            'allowed_parent_type_ids.*' => ['integer', Rule::exists('network_node_types', 'id')],
            'allowed_anchor_pop_types' => ['sometimes', 'array'],
            'allowed_anchor_pop_types.*' => ['string', Rule::in(['pusat', 'cabang', 'mini_pop'])],
        ]);

        $parents = array_values(array_unique(array_map('intval', $validated['allowed_parent_type_ids'] ?? [])));
        if ($type !== null && in_array($type->id, $parents, true)) {
            throw ValidationException::withMessages([
                'allowed_parent_type_ids' => 'Tipe node tidak boleh menjadi induk dirinya sendiri.',
            ]);
        }

        return [
            'parents' => $parents,
            'anchors' => array_values(array_unique($validated['allowed_anchor_pop_types'] ?? [])),
        ];
    }

    /**
     * @param  array{parents:list<int>,anchors:list<string>}  $rules
     */
    private function saveRules(NetworkNodeType $type, array $rules): void
    {
        DB::table('network_node_type_rules')->where('child_type_id', $type->id)->delete();

        $rows = [];
        foreach ($rules['parents'] as $parentId) {
            $rows[] = ['child_type_id' => $type->id, 'parent_type_id' => $parentId, 'anchor_pop_type' => null, 'created_at' => now(), 'updated_at' => now()];
        }
        foreach ($rules['anchors'] as $popType) {
            $rows[] = ['child_type_id' => $type->id, 'parent_type_id' => null, 'anchor_pop_type' => $popType, 'created_at' => now(), 'updated_at' => now()];
        }
        if ($rows !== []) {
            DB::table('network_node_type_rules')->insert($rows);
        }
    }
}
