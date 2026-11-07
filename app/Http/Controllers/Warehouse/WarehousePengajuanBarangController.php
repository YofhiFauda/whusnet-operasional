<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\PengajuanBarang;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\PengajuanBarangService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Surat Pengajuan Barang (gap #2 dari 3 feature report 2026-11-05) — lihat
 * docblock migration `create_pengajuan_barang_table`.
 */
class WarehousePengajuanBarangController extends Controller
{
    use AuthorizesWarehousePop;

    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();
        $hasAllAccess = $access->hasAllPopAccess($user);
        $allowedPopIds = $hasAllAccess ? [] : $access->getAllowedPopIds($user);

        $statusFilter = $request->query('status', 'open');

        $pengajuans = PengajuanBarang::query()
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->when($statusFilter === 'open', fn ($q) => $q->menungguFop())
            ->when(! in_array($statusFilter, ['all', 'open'], true), fn ($q) => $q->where('status', $statusFilter))
            ->with(['pop', 'pengaju', 'decidedBy'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('warehouse.pengajuan-barang.index', compact('pengajuans', 'statusFilter'));
    }

    public function create(EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $pops = Pop::query()->whereIn('type', ['pusat', 'cabang'])
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')->get();

        return view('warehouse.pengajuan-barang.create', compact('pops'));
    }

    public function store(Request $request, PengajuanBarangService $service, EffectiveAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'pop_id' => 'required|integer|exists:pops,id',
            'nama_kegiatan' => 'required|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.nama_barang' => 'required|string|max:255',
            'lines.*.spesifikasi' => 'nullable|string|max:255',
            'lines.*.jumlah' => 'required|numeric|min:0.01',
            'lines.*.satuan' => 'required|string|max:30',
            'lines.*.keterangan_link' => 'nullable|url|max:500',
        ]);

        $pop = Pop::findOrFail($validated['pop_id']);
        $this->assertPopInScope($pop, auth()->user(), $access);

        try {
            $pengajuan = $service->create($pop, $validated['nama_kegiatan'], $validated['lines'], auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.pengajuan-barang.show', $pengajuan)
            ->with('success', "Surat Pengajuan Barang {$pengajuan->nomor_request} berhasil diajukan.");
    }

    public function show(PengajuanBarang $pengajuanBarang, EffectiveAccessService $access): View
    {
        $this->assertPopIdInScope($pengajuanBarang->pop_id, auth()->user(), $access);

        $pengajuanBarang->load(['pop', 'pengaju', 'decidedBy', 'items']);

        return view('warehouse.pengajuan-barang.show', ['pengajuan' => $pengajuanBarang]);
    }

    public function approve(PengajuanBarang $pengajuanBarang, PengajuanBarangService $service, EffectiveAccessService $access): RedirectResponse
    {
        $this->assertPopIdInScope($pengajuanBarang->pop_id, auth()->user(), $access);

        try {
            $service->approve($pengajuanBarang, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.pengajuan-barang.show', $pengajuanBarang)
            ->with('success', 'Pengajuan disetujui.');
    }

    public function reject(Request $request, PengajuanBarang $pengajuanBarang, PengajuanBarangService $service, EffectiveAccessService $access): RedirectResponse
    {
        $this->assertPopIdInScope($pengajuanBarang->pop_id, auth()->user(), $access);

        $validated = $request->validate(['reason' => 'required|string|max:500']);

        try {
            $service->reject($pengajuanBarang, $validated['reason'], auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.pengajuan-barang.show', $pengajuanBarang)
            ->with('success', 'Pengajuan ditolak.');
    }

    /**
     * PDF Surat Pengajuan Barang — KOP CONNEXA (reuse partial existing,
     * BUKAN "CV WHUSNET BERTIGA KREATIF" dari contoh dokumen lama).
     * Tanda tangan "Yang Mengajukan" = nama pengaju (otomatis, submit =
     * tanda tangan). "Disetujui" kosong sampai FOP approve, baru kecetak
     * nama FOP yang BENERAN approve (bukan nama statis).
     */
    public function pdf(PengajuanBarang $pengajuanBarang, EffectiveAccessService $access, Request $request): HttpResponse
    {
        $this->assertPopIdInScope($pengajuanBarang->pop_id, auth()->user(), $access);

        $pengajuanBarang->load(['pop', 'pengaju', 'decidedBy', 'items']);

        $pdf = Pdf::loadView('warehouse.pengajuan-barang.pdf', ['pengajuan' => $pengajuanBarang])->setPaper('a4');

        $filename = "pengajuan-barang-{$pengajuanBarang->nomor_request}.pdf";

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
