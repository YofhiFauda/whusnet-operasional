<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OdpController extends Controller
{
    public function __construct(private readonly EffectiveAccessService $access) {}

    public function index(): View
    {
        $user = auth()->user();
        $odps = Odp::query()
            ->applyUserScope($user)
            ->with('pop', 'ports')
            ->withCount(['ports', 'ports as used_ports_count' => fn ($query) => $query->where('is_used', true)])
            ->orderBy('code')
            ->paginate(10);
        $pops = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();

        return view('network.odps.index', compact('odps', 'pops'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $user = auth()->user();
        $popRule = $this->access->hasAllPopAccess($user)
            ? Rule::exists('pops', 'id')
            : Rule::in($this->access->getAllowedPopIds($user));

        $validated = $request->validate([
            'pop_id' => ['required', 'integer', $popRule],
            'code' => ['required', 'string', 'max:50', Rule::unique('odps', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'capacity' => ['required', 'integer', 'min:1', 'max:128'],
        ]);

        DB::transaction(function () use ($validated): void {
            $odp = Odp::create([
                'pop_id' => $validated['pop_id'],
                'code' => strtoupper(trim($validated['code'])),
                'name' => trim($validated['name']),
            ]);

            for ($number = 1; $number <= $validated['capacity']; $number++) {
                $odp->ports()->create(['number' => $number, 'is_used' => false]);
            }
        });

        return redirect()->route('network.odps.index')->with('success', 'ODP dan port berhasil ditambahkan.');
    }

    public function addPorts(Request $request, Odp $odp): RedirectResponse
    {
        $this->authorizePop($odp);

        $validated = $request->validate([
            'additional_capacity' => ['required', 'integer', 'min:1', 'max:128'],
        ]);

        DB::transaction(function () use ($odp, $validated): void {
            $lockedOdp = Odp::query()->whereKey($odp->id)->lockForUpdate()->firstOrFail();
            $lastNumber = (int) $lockedOdp->ports()->max('number');
            abort_if($lastNumber + $validated['additional_capacity'] > 128, 422, 'Kapasitas maksimal 128 port.');

            for ($number = $lastNumber + 1; $number <= $lastNumber + $validated['additional_capacity']; $number++) {
                $lockedOdp->ports()->create(['number' => $number, 'is_used' => false]);
            }
        });

        return redirect()->route('network.odps.index')->with('success', 'Kapasitas ODP berhasil ditambah.');
    }

    public function updatePort(Request $request, OdpPort $port): RedirectResponse
    {
        $port->load('odp');
        $this->authorizePop($port->odp);

        $validated = $request->validate([
            'is_used' => ['required', 'boolean'],
        ]);
        $port->update(['is_used' => (bool) $validated['is_used']]);

        return redirect()->route('network.odps.index')->with('success', 'Status port ODP berhasil diperbarui.');
    }

    private function authorizePop(Odp $odp): void
    {
        $user = auth()->user();

        if ($this->access->hasAllPopAccess($user)) {
            return;
        }

        abort_unless(
            in_array((int) $odp->pop_id, $this->access->getAllowedPopIds($user), true),
            403
        );
    }
}
