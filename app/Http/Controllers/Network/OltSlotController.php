<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\NetworkNode;
use App\Models\OltSlot;
use App\Services\EffectiveAccessService;
use App\Support\InfrastructureCutover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Slot & kartu OLT sekarang dimiliki node chassis (`network_nodes` bertipe
 * `olt_chassis`), bukan Mini POP langsung — satu Mini POP bisa punya
 * beberapa chassis (Task 2/3), jadi nomor slot & kartu discope per-chassis.
 */
class OltSlotController extends Controller
{
    public function __construct(private readonly EffectiveAccessService $access) {}

    public function store(Request $request, NetworkNode $chassis): RedirectResponse
    {
        $this->authorizeNodeScope($chassis);
        InfrastructureCutover::assertLegacyWriteAllowed('slot_count', 'Slot OLT lama (olt_slots)');
        $validated = $request->validate(['slot_count' => ['required', 'integer', 'min:1', 'max:64']]);
        if ($chassis->nodeType?->code !== 'olt_chassis') {
            throw ValidationException::withMessages(['slot_count' => 'Slot OLT hanya dapat dibuat pada node Chassis OLT.']);
        }

        DB::transaction(function () use ($chassis, $validated): void {
            NetworkNode::query()->whereKey($chassis->id)->lockForUpdate()->firstOrFail();
            $highest = (int) $chassis->oltSlots()->max('number');
            for ($number = $highest + 1; $number <= $validated['slot_count']; $number++) {
                $chassis->oltSlots()->create(['number' => $number]);
            }
        });

        return redirect()->route('network.nodes.show', $chassis)->with('success', 'Slot OLT tersimpan.');
    }

    public function attachCard(Request $request, NetworkNode $chassis, OltSlot $slot): RedirectResponse
    {
        $this->authorizeNodeScope($chassis);
        InfrastructureCutover::assertLegacyWriteAllowed('network_node_id', 'Kartu OLT sebagai node');
        $validated = $request->validate(['network_node_id' => ['required', 'integer', 'exists:network_nodes,id']]);
        if ($chassis->nodeType?->code !== 'olt_chassis' || $slot->chassis_node_id !== $chassis->id) {
            throw ValidationException::withMessages(['network_node_id' => 'Slot bukan milik Chassis OLT ini.']);
        }

        DB::transaction(function () use ($slot, $chassis, $validated): void {
            $slot = OltSlot::query()->whereKey($slot->id)->lockForUpdate()->firstOrFail();
            $card = NetworkNode::query()->whereKey($validated['network_node_id'])->lockForUpdate()->firstOrFail();
            // Kartu sekarang anak chassis lewat `parent_id` (bukan `anchor_pop_id`
            // langsung ke Mini POP) — chassis-nya yang ditambatkan ke POP.
            if ($slot->card_node_id !== null || $card->parent_id !== $chassis->id
                || $card->nodeType?->code !== 'olt_card'
                || OltSlot::query()->where('card_node_id', $card->id)->exists()) {
                throw ValidationException::withMessages(['network_node_id' => 'Kartu harus berupa node kartu OLT yang bebas dan merupakan anak Chassis ini.']);
            }
            $slot->update(['card_node_id' => $card->id]);
        });

        return redirect()->route('network.nodes.show', $chassis)->with('success', 'Kartu OLT ditempatkan pada slot.');
    }

    /**
     * 403 kalau chassis di luar scope POP user — copy persis pola
     * `NetworkNodeController::authorizeNodeScope()` (satu definisi "node
     * dalam scope" untuk lihat dan ubah, lewat `scopeInPops()` yang sama
     * dengan listing).
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
