<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Services\NetworkInventoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inventaris infrastruktur: daftar barang gudang yang terpasang netap di
 * node jaringan, lintas cabang sesuai scope user. Read-only — pemasangan
 * dan pencabutan tetap lewat laporan Task (O-REQ/INFR REQ).
 */
class NetworkInventoryController extends Controller
{
    public function __construct(private readonly NetworkInventoryService $inventory) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'pop_id' => ['nullable', 'integer'],
            'tipe' => ['nullable', 'in:sn,qty,roll'],
        ]);

        return view('network.inventaris.index', [
            'rows' => $this->inventory->paginate($user, $filters),
            'summary' => $this->inventory->summary($user, $filters),
            'cabangs' => $this->inventory->cabangOptions($user),
            'filters' => $filters,
        ]);
    }
}
