<?php

namespace App\Http\Controllers\Network;

use App\Enums\InfrastructureSlotKind;
use App\Http\Controllers\Controller;
use App\Models\NetworkNode;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureLocationResolver;
use App\Services\InfrastructurePositionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Halaman tersendiri (bukan modal) untuk generate massal Slot + PON/Port
 * Switch: ini mutasi data dengan validasi server, jadi gagal validasi harus
 * kembali ke form ini lewat back()->withInput(), bukan menutup modal di List.
 * Controller terpisah dari InfrastructurePositionController supaya tidak
 * bergantung pada aksi-aksinya yang masih memakai model lama.
 */
class GenerateInfrastructureStructureController extends Controller
{
    public function __construct(
        private readonly EffectiveAccessService $access,
        private readonly InfrastructureLocationResolver $resolver,
    ) {}

    public function create(NetworkNode $node): View
    {
        $kind = $this->authorizeDevice($node);

        return view('network.positions.generate-structure', ['node' => $node, 'kind' => $kind]);
    }

    public function store(Request $request, NetworkNode $node, InfrastructurePositionService $positions): RedirectResponse
    {
        $kind = $this->authorizeDevice($node);
        $isOlt = $kind === InfrastructureSlotKind::OLT;
        $validated = $request->validate([
            // Switch hanya punya satu Slot logis, jadi jumlah slot tidak ditanyakan.
            // Batas 32 × 64 = 2.048 node per aksi, satu transaksi + lock perangkat:
            // cukup untuk OLT terbesar yang realistis (16 slot × 16 PON), dan
            // mencegah satu salah ketik (64 × 128 = 8.192 node) menahan lock lama
            // serta menenggelamkan pohon dengan posisi fiktif.
            'slot_count' => [$isOlt ? 'required' : 'nullable', 'integer', 'min:1', 'max:32'],
            'ports_per_slot' => ['required', 'integer', 'min:1', 'max:64'],
            'pon_capacity' => [$isOlt ? 'nullable' : 'prohibited', 'integer', 'min:1', 'max:100000'],
        ]);

        try {
            $result = $positions->generateStructure(
                $node,
                (int) ($validated['slot_count'] ?? 1),
                (int) $validated['ports_per_slot'],
                isset($validated['pon_capacity']) ? (int) $validated['pon_capacity'] : null,
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['ports_per_slot' => $exception->getMessage()]);
        }

        $portLabel = $isOlt ? 'PON' : 'Port';

        return redirect()->route('network.nodes.show', $node)
            ->with('success', "Struktur dibuat: {$result['slots_created']} slot dan {$result['ports_created']} {$portLabel} baru.");
    }

    private function authorizeDevice(NetworkNode $node): InfrastructureSlotKind
    {
        $node->loadMissing('nodeType');
        $kind = InfrastructureSlotKind::forDeviceTypeCode($node->nodeType?->code);
        abort_if($kind === null, 404);

        $user = auth()->user();
        if (! $this->access->hasAllPopAccess($user)) {
            // Perangkat yang belum tertambat ke POP (resolver melempar) tidak
            // pernah bisa dibuktikan masuk scope → 404 seperti node tak
            // dikenal, bukan 500.
            try {
                $popId = $this->resolver->forNode($node)->deepestPopId();
            } catch (InvalidArgumentException) {
                abort(404);
            }
            abort_unless($popId !== null && in_array($popId, $this->access->getAllowedPopIds($user), true), 403);
        }

        return $kind;
    }
}
