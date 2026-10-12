<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\NetworkNodeType;
use App\Services\MasterRecordRemovalService;
use App\Support\LikeSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        return view('master.network-node-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateType($request);

        NetworkNodeType::create($validated);

        return redirect()
            ->route('master.network-node-types.index')
            ->with('success', 'Tipe node "'.$validated['name'].'" berhasil ditambahkan.');
    }

    public function edit(NetworkNodeType $networkNodeType): View
    {
        return view('master.network-node-types.edit', ['type' => $networkNodeType]);
    }

    public function update(Request $request, NetworkNodeType $networkNodeType): RedirectResponse
    {
        $validated = $this->validateType($request, $networkNodeType);

        $networkNodeType->update($validated);

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
                : "Tipe node \"{$networkNodeType->name}\" masih dipakai node jaringan, jadi hanya dinonaktifkan."
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateType(Request $request, ?NetworkNodeType $type = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('network_node_types', 'code')->ignore($type),
            ],
            'name' => ['required', 'string', 'max:100'],
            'max_ports' => ['nullable', 'integer', 'min:0'],
            'red_zone_threshold' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
            // Checkbox HTML gak ngirim apa pun kalau gak dicentang — nullable
            // di rule, dinormalisasi eksplisit ke bool di bawah lewat
            // $request->boolean() (BUKAN 'required|boolean', checkbox
            // "unchecked" gak lolos required; BUKAN dibiarkan absen dari
            // $validated juga — update() dengan key absen gak mereset
            // flag true balik ke false waktu checkbox-nya di-uncheck lagi).
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
}
