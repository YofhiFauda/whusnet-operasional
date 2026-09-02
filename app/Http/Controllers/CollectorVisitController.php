<?php

namespace App\Http\Controllers;

use App\Enums\VisitResult;
use App\Models\Customer;
use App\Services\CollectorVisitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kolektor mencatat kunjungan yang TIDAK menghasilkan uang.
 *
 * Rute tanpa parameter kolektor — sama alasannya dengan bayar & setor (§9):
 * pelakunya `auth()->user()`, tak pernah dari klien. Kalau id kolektor boleh
 * dikirim, catatan kunjungan bisa ditulis atas nama orang lain, dan laporan
 * aging — satu-satunya kontrol anti-fraud modul ini — jadi tak bisa dipercaya.
 *
 * Hasil `bayar` tidak diterima di sini: itu turunan payment yang benar-benar
 * tersimpan (CollectorPaymentService), bukan sesuatu yang boleh diketik.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §12.
 */
class CollectorVisitController extends Controller
{
    public function __construct(private readonly CollectorVisitService $visits) {}

    public function store(Request $request): RedirectResponse
    {
        $collector = $request->user();

        abort_unless($collector->hasRole('kolektor'), 403, 'Hanya kolektor yang bisa mencatat kunjungan penagihan.');

        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'result' => ['required', Rule::in(VisitResult::manualValues())],
            // Kunjungan boleh dicatat menyusul (mis. sinyal mati di lapangan),
            // tapi tidak boleh dibuat untuk masa depan — itu bukan laporan
            // kunjungan lagi, itu rencana.
            'visited_at' => 'nullable|date|before_or_equal:today',
            'promised_date' => 'nullable|date|after_or_equal:today',
            'note' => 'nullable|string|max:1000',
        ], [
            'result.in' => 'Hasil "Bayar" tidak bisa dipilih di sini — catat pembayarannya lewat tombol Bayar.',
            'promised_date.after_or_equal' => 'Tanggal janji bayar tidak boleh di masa lalu.',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        try {
            $this->visits->logManualVisit(
                $collector,
                $customer,
                VisitResult::from($validated['result']),
                $validated['visited_at'] ?? null,
                $validated['promised_date'] ?? null,
                $validated['note'] ?? null,
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('collector-worklist.index')
                ->withErrors(['visit' => $e->getMessage()]);
        }

        return redirect()
            ->route('collector-worklist.index')
            ->with('success', "Kunjungan ke {$customer->full_name} tercatat.");
    }
}
