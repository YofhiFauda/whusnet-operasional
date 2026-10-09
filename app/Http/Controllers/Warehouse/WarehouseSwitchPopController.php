<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Services\EffectiveAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switcher cabang global (analisa-ui-ux-warehouse.md §S1) — satu-satunya
 * penulis `session('warehouse.pop_id')`. Dipicu dari chip di header, berlaku
 * di SEMUA halaman gudang (dibaca `ResolvesSelectedPop::resolveSelectedPopId()`),
 * sampai diganti lagi atau logout (session-nya ikut hilang).
 *
 * Tanpa permission khusus — gerbangnya `auth` biasa (sama kayak akses
 * halaman gudang pakai permission masing-masing). Yang jadi gerbang
 * sebenarnya: pop yang dipilih WAJIB ada di scope aktor, dicek di sini
 * sendiri (bukan nitip ke controller tujuan) — pop di luar scope ditolak
 * keras (403), bukan diam-diam gak match kayak filter list biasa, karena
 * ini nulis STATE yang dipakai berulang, bukan filter sekali pakai.
 */
class WarehouseSwitchPopController extends Controller
{
    public function store(Request $request, EffectiveAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'pop_id' => 'nullable|integer|exists:pops,id',
        ]);

        $user = $request->user();
        $popId = $validated['pop_id'] ?? null;

        if ($popId !== null) {
            $inScope = $access->hasAllPopAccess($user) || in_array((int) $popId, $access->getAllowedPopIds($user), true);
            abort_unless($inScope, 403, 'Cabang di luar cakupan Anda.');
            session(['warehouse.pop_id' => (int) $popId]);
        } else {
            session()->forget('warehouse.pop_id');
        }

        return back();
    }
}
