<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Pop;
use App\Models\User;
use App\Models\WarehousePopPic;
use App\Services\EffectiveAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kelompok I3, docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md §5.3
 * & §15.2 no. 6 — halaman TERPISAH ("Kelola PIC Gudang per Cabang"), bukan
 * panel di Edit User: penunjukan PIC itu keputusan gudang (satu layar
 * menampilkan "cabang X PIC-nya siapa saja"), bukan atribut biasa milik 1 user,
 * dan field-nya sengaja beda nama dari `pop_ids` (scope) di form user supaya
 * dua konsep ini tidak tertukar.
 *
 * Permission REUSE `users.update` — penunjukan PIC pada dasarnya mengatur
 * data penugasan operasional seorang user, sama kelas aksinya dengan scope
 * POP yang sudah lebih dulu digerbangi permission ini di UserController.
 * Rancangan tidak menetapkan permission baru untuk ini secara eksplisit;
 * keputusan reuse ini dicatat di laporan implementasi ADHOC-120.
 */
class WarehousePicGudangController extends Controller
{
    public function index(): View
    {
        $cabangPops = Pop::query()
            ->where('type', 'cabang')
            ->with(['gudangPics' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        // Dropdown "tunjuk PIC baru" — role pic_gudang doang (Kelompok I3).
        $picCandidates = User::whereHas('role', fn ($q) => $q->where('code', 'pic_gudang'))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('warehouse.pic-gudang.index', compact('cabangPops', 'picCandidates'));
    }

    public function store(Request $request, EffectiveAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'pop_id' => ['required', 'integer', 'exists:pops,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ], [
            'pop_id.required' => 'Cabang wajib dipilih.',
            'user_id.required' => 'User PIC Gudang wajib dipilih.',
        ]);

        $pop = Pop::findOrFail($validated['pop_id']);
        $user = User::findOrFail($validated['user_id']);

        // Actor harus berhak atas CABANG yang ditunjuk, bukan cuma punya
        // users.update. Sama dengan destroy(): tanpa cek ini, admin cabang A
        // bisa menunjuk PIC untuk cabang B lewat pop_id.
        $actor = $request->user();
        $actorInScope = $access->hasAllPopAccess($actor)
            || in_array($pop->id, $access->getAllowedPopIds($actor), true);

        if (! $actorInScope) {
            return back()->with('error', "Cabang {$pop->name} di luar scope Anda — tidak bisa menunjuk PIC Gudang di sana.");
        }

        if ($user->role?->code !== 'pic_gudang') {
            return back()->with('error', "{$user->name} bukan role Teknisi PIC Gudang — ganti role-nya dulu lewat Manajemen User.");
        }

        if (! $pop->isCabang()) {
            return back()->with('error', "{$pop->name} bukan Gudang Cabang.");
        }

        // Kelompok I4, §5.3.3 arah 1: cabang yang ditunjuk wajib ada dalam
        // scope user kalau scope-nya selected_pop/pop_tree. Scope all_pop
        // bebas ditunjuk ke cabang mana pun — menjawab kasus teknisi keliling
        // yang PIC cuma di 1 cabang.
        if (! $access->hasAllPopAccess($user) && ! in_array($pop->id, $access->getAllowedPopIds($user), true)) {
            return back()->with(
                'error',
                "{$user->name} scope-nya tidak mencakup {$pop->name} — penunjukan PIC akan macet (dia tidak akan pernah bisa membuka gudang itu). Perluas scope-nya dulu lewat Manajemen User, atau pilih cabang lain."
            );
        }

        if (WarehousePopPic::where('pop_id', $pop->id)->where('user_id', $user->id)->exists()) {
            return back()->with('error', "{$user->name} sudah jadi PIC Gudang {$pop->name}.");
        }

        WarehousePopPic::create(['pop_id' => $pop->id, 'user_id' => $user->id]);

        return redirect()->route('warehouse.pic-gudang.index')
            ->with('success', "{$user->name} ditunjuk jadi PIC Gudang {$pop->name}.");
    }

    public function destroy(WarehousePopPic $picGudang): RedirectResponse
    {
        $pop = $picGudang->pop;
        $user = $picGudang->user;

        // Actor harus berhak atas CABANG penunjukan ini, bukan cuma punya users.update.
        // Scope PIC sendiri tidak relevan di sini (PIC global); yang dijaga adalah
        // siapa yang boleh mencabut penunjukan di cabang tertentu. Tanpa cek ini,
        // admin cabang A bisa mencabut PIC cabang B lewat id pivot.
        $access = app(EffectiveAccessService::class);
        $actor = auth()->user();
        $inScope = $access->hasAllPopAccess($actor)
            || in_array($picGudang->pop_id, $access->getAllowedPopIds($actor), true);

        if (! $inScope) {
            return back()->with('error', 'Penunjukan PIC Gudang di cabang '.($pop?->name ?? 'ini').' di luar scope Anda — tidak bisa dicabut.');
        }

        $picGudang->delete();

        return redirect()->route('warehouse.pic-gudang.index')
            ->with('success', "Penunjukan PIC Gudang {$user?->name} untuk {$pop?->name} dicabut.");
    }
}
