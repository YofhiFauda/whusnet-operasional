<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\InfrastructurePort;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureLocationResolver;
use App\Services\PonCapacityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Aksi tulis pada PON/Port kanonis. Scope POP aktor dicek lewat rantai
 * lokasi (resolver), bukan `pop_id` — port tidak punya kolom POP sendiri.
 */
class InfrastructurePortController extends Controller
{
    public function __construct(
        private readonly EffectiveAccessService $access,
        private readonly InfrastructureLocationResolver $resolver,
    ) {}

    public function updateCapacity(Request $request, InfrastructurePort $port, PonCapacityService $capacity): RedirectResponse
    {
        $this->authorizePortScope($port);

        $validated = $request->validate([
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'red_zone_threshold' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $capacity->updateCapacity($port, (int) $validated['capacity'], (int) $validated['red_zone_threshold'], $request->user());

        return redirect()->route('network.nodes.show', $port->slot->network_node_id)
            ->with('success', "Kapasitas {$port->displayName()} diperbarui.");
    }

    private function authorizePortScope(InfrastructurePort $port): void
    {
        $user = auth()->user();
        if ($this->access->hasAllPopAccess($user)) {
            return;
        }

        $popId = $this->resolver->forPort($port)->deepestPopId();
        abort_unless($popId !== null && in_array($popId, $this->access->getAllowedPopIds($user), true), 403);
    }
}
