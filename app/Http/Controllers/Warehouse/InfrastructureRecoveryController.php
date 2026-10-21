<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\InfrastructureRecovery;
use App\Models\InventoryTransaction;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureMaterialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class InfrastructureRecoveryController extends Controller
{
    public function index(EffectiveAccessService $access): View
    {
        $user = auth()->user();
        $recoveries = InfrastructureRecovery::query()
            ->when(! $access->hasAllPopAccess($user), function ($query) use ($access, $user): void {
                $query->whereHas('sourceTransaction', fn ($source) => $source->whereIn('to_pop_id', $access->getAllowedPopIds($user)));
            })
            ->with('sourceTransaction.toNetworkNode', 'retrieveTransaction.fopTask.task', 'sourceTransaction.item')
            ->orderByRaw("CASE WHEN status = 'pending_inspection' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(25);

        return view('warehouse.infrastructure-recoveries.index', compact('recoveries'));
    }

    public function show(InfrastructureRecovery $recovery, EffectiveAccessService $access): View
    {
        $this->assertInScope($recovery, $access);
        $recovery->load('sourceTransaction.toNetworkNode', 'retrieveTransaction.fopTask.task', 'sourceTransaction.item');

        return view('warehouse.infrastructure-recoveries.show', compact('recovery'));
    }

    public function store(Request $request, InfrastructureRecovery $recovery, InfrastructureMaterialService $service, EffectiveAccessService $access): RedirectResponse
    {
        $this->assertInScope($recovery, $access);
        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['reusable', 'damaged'])],
            'verified_qty' => ['required', 'numeric', 'gt:0'],
            'inspection_note' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $service->inspect($recovery, $validated['outcome'], $request->user(), $validated['inspection_note'], (float) $validated['verified_qty']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('warehouse.infrastructure-recoveries.show', $recovery)
            ->with('success', 'Pemeriksaan hasil cabut tersimpan.');
    }

    private function assertInScope(InfrastructureRecovery $recovery, EffectiveAccessService $access): void
    {
        $user = auth()->user();
        if ($access->hasAllPopAccess($user)) {
            return;
        }

        abort_unless(InventoryTransaction::query()->whereKey($recovery->source_transaction_id)
            ->whereIn('to_pop_id', $access->getAllowedPopIds($user))->exists(), 403);
    }
}
