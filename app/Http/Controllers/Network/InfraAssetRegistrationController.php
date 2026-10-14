<?php

namespace App\Http\Controllers\Network;

use App\Enums\OwnershipMode;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\NetworkNode;
use App\Services\EffectiveAccessService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Halaman registrasi aset infrastruktur LAMA (sudah berdiri di lapangan
 * tapi belum pernah masuk ledger) per node — pola "halaman create
 * tersendiri" (CLAUDE.md § aturan 3 pola aksi), bukan modal: ini mutasi
 * ledger (`InventoryService::registerExistingInfraSerial()`, Task 6), jadi
 * gagal validasi wajib balik ke halaman create sendiri (`withErrors()`
 * default Laravel = `back()`), bukan menutup modal di atas List node yang
 * kosong tanpa pesan error.
 */
class InfraAssetRegistrationController extends Controller
{
    public function __construct(private readonly EffectiveAccessService $access) {}

    public function create(NetworkNode $node): View
    {
        $this->authorizeNodeScope($node);

        $items = Item::query()
            ->where('ownership_mode', OwnershipMode::INFRASTRUCTURE_ASSET)
            ->where('tracking_type', TrackingType::SERIALIZED)
            ->orderBy('name')
            ->get();

        return view('network.nodes.register-asset', [
            'node' => $node,
            'items' => $items,
        ]);
    }

    public function store(Request $request, NetworkNode $node, InventoryService $inventory): RedirectResponse
    {
        $this->authorizeNodeScope($node);

        $validated = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'serial_number' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $item = Item::findOrFail($validated['item_id']);

        try {
            $inventory->registerExistingInfraSerial(
                $validated['serial_number'],
                $item,
                $node,
                $validated['reason'],
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['serial_number' => $exception->getMessage()]);
        }

        return redirect()->route('network.nodes.show', $node)->with('success', 'Aset lama berhasil diregistrasi.');
    }

    /**
     * 403 kalau node di luar scope POP user — definisi SAMA dengan
     * `NetworkNodeController::authorizeNodeScope()` (`scopeInPops()`, bukan
     * `resolvePop()`; lihat docblock aslinya untuk alasannya), supaya lihat
     * dan ubah node memakai satu definisi "dalam scope" yang sama.
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
