<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\District;
use App\Services\RegionMasterService;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegionMasterController extends Controller
{
    public function __construct(private RegionMasterService $regions) {}

    public function kelola(Request $request): View
    {
        $level = $this->resolveLevel($request->query('level', RegionMasterService::LEVEL_KOTA));
        $search = LikeSearch::sanitize((string) $request->query('search', ''));

        $query = $this->regions->modelClass($level)::query()
            ->with($this->parentRelation($level))
            ->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $rows = $query->paginate(25)->withQueryString();

        return view('master.wilayah-kelola', [
            'level' => $level,
            'rows' => $rows,
            'search' => $search,
            'levelLabel' => $this->levelLabel($level),
        ]);
    }

    public function create(Request $request): View
    {
        $level = $this->resolveLevel($request->query('level', RegionMasterService::LEVEL_KOTA));

        return view('master.wilayah-form', $this->formData($level, null));
    }

    public function store(Request $request): RedirectResponse
    {
        $level = $this->resolveLevel($request->input('level'));
        $validated = $request->validate($this->rules($level, null));

        $region = $this->regions->modelClass($level)::create($validated);

        return redirect()
            ->route('master.wilayah.kelola', ['level' => $level])
            ->with('success', "{$this->levelLabel($level)} \"{$region->name}\" berhasil ditambahkan.");
    }

    public function edit(string $level, int $id): View
    {
        $level = $this->resolveLevel($level);
        $region = $this->findRegion($level, $id);

        return view('master.wilayah-form', $this->formData($level, $region));
    }

    public function update(Request $request, string $level, int $id): RedirectResponse
    {
        $level = $this->resolveLevel($level);
        $region = $this->findRegion($level, $id);
        $region->update(Arr::except($request->validate($this->rules($level, $region)), ['city_id', 'district_id']));

        return redirect()
            ->route('master.wilayah.kelola', ['level' => $level])
            ->with('success', "{$this->levelLabel($level)} \"{$region->name}\" berhasil diubah.");
    }

    public function destroy(string $level, int $id): RedirectResponse
    {
        $level = $this->resolveLevel($level);
        $region = $this->findRegion($level, $id);
        $name = $region->name;

        if (! $this->regions->delete($region)) {
            return redirect()
                ->route('master.wilayah.kelola', ['level' => $level])
                ->with('error', "{$this->levelLabel($level)} \"{$name}\" tidak bisa dihapus karena masih dipakai — ada pelanggan di dalamnya atau wilayah di bawahnya.");
        }

        return redirect()
            ->route('master.wilayah.kelola', ['level' => $level])
            ->with('success', "{$this->levelLabel($level)} \"{$name}\" berhasil dihapus.");
    }

    private function resolveLevel(?string $level): string
    {
        abort_unless(in_array($level, [
            RegionMasterService::LEVEL_KOTA,
            RegionMasterService::LEVEL_KECAMATAN,
            RegionMasterService::LEVEL_DESA,
        ], true), 404);

        return $level;
    }

    private function levelLabel(string $level): string
    {
        return match ($level) {
            RegionMasterService::LEVEL_KOTA => 'Kota/Kabupaten',
            RegionMasterService::LEVEL_KECAMATAN => 'Kecamatan',
            RegionMasterService::LEVEL_DESA => 'Desa/Kelurahan',
        };
    }

    private function parentRelation(string $level): array
    {
        return match ($level) {
            RegionMasterService::LEVEL_KOTA => [],
            RegionMasterService::LEVEL_KECAMATAN => ['city'],
            RegionMasterService::LEVEL_DESA => ['district.city'],
        };
    }

    private function findRegion(string $level, int $id): Model
    {
        return $this->regions->modelClass($level)::query()->findOrFail($id);
    }

    /**
     * Induk (kota/kecamatan) dikunci saat edit — memindah kecamatan/desa ke
     * induk lain mengubah arti alamat pelanggan yang sudah tercatat.
     *
     * @return array<string, array<int, mixed>|string>
     */
    private function rules(string $level, ?Model $current): array
    {
        return match ($level) {
            RegionMasterService::LEVEL_KOTA => [
                'name' => ['required', 'string', 'max:100', Rule::unique('cities', 'name')->ignore($current?->id)],
            ],
            RegionMasterService::LEVEL_KECAMATAN => [
                'city_id' => $current ? [] : ['required', 'integer', 'exists:cities,id'],
                'name' => ['required', 'string', 'max:100', Rule::unique('districts', 'name')
                    ->where('city_id', $current?->city_id ?? request('city_id'))
                    ->ignore($current?->id)],
            ],
            RegionMasterService::LEVEL_DESA => [
                'district_id' => $current ? [] : ['required', 'integer', 'exists:districts,id'],
                'name' => ['required', 'string', 'max:100', Rule::unique('villages', 'name')
                    ->where('district_id', $current?->district_id ?? request('district_id'))
                    ->ignore($current?->id)],
                'postal_code' => ['nullable', 'string', 'max:10'],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(string $level, ?Model $region): array
    {
        return [
            'level' => $level,
            'levelLabel' => $this->levelLabel($level),
            'region' => $region,
            'cities' => $level === RegionMasterService::LEVEL_KECAMATAN && ! $region
                ? City::orderBy('name')->get(['id', 'name'])
                : collect(),
            'districts' => $level === RegionMasterService::LEVEL_DESA && ! $region
                ? District::with('city:id,name')->orderBy('name')->get(['id', 'name', 'city_id'])
                : collect(),
            'parentLabel' => $region ? $this->parentLabel($level, $region) : null,
        ];
    }

    private function parentLabel(string $level, Model $region): ?string
    {
        return match ($level) {
            RegionMasterService::LEVEL_KECAMATAN => $region->city?->name,
            RegionMasterService::LEVEL_DESA => $region->district?->name.', '.$region->district?->city?->name,
            default => null,
        };
    }
}
