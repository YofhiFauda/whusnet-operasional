<?php

namespace App\Http\Controllers;

use App\Models\CollectorDeposit;
use App\Models\User;
use App\Services\CollectorBalanceService;
use App\Services\CollectorDepositService;
use App\Support\ReasonValidationRule;
use App\Support\RupiahInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Setoran Kolektor — empat aksi, empat pemilik kewenangan yang berbeda:
 *
 *   store()            → KOLEKTOR menyetorkan seluruh saldonya. Rute tanpa
 *                         parameter, kolektor = auth()->user(), sama alasannya
 *                         dengan rute bayar (§9): id kolektor tak boleh
 *                         datang dari klien.
 *   storeForCollector() → ADMIN menyetor atas nama kolektor yang tak bisa
 *                         mengakses Worklist-nya sendiri (HP rusak, cuti
 *                         mendadak, dst) — dari Worksheet Admin.
 *   verify()            → ADMIN menghitung uang fisik & menutup setoran.
 *   writeOff()           → OWNER mengakui kerugian atas selisih yang tak
 *                         tertagih.
 *
 * Guard-guard uangnya ada di CollectorDepositService, bukan di sini — supaya
 * tak ada jalur masuk lain yang bisa melewatinya.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §11, §14.2.
 */
class CollectorDepositController extends Controller
{
    public function __construct(
        private readonly CollectorDepositService $deposits,
        private readonly CollectorBalanceService $balance,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $collector = $request->user();

        abort_unless($collector->hasRole('kolektor'), 403, 'Hanya kolektor yang bisa menyetorkan hasil tagihan.');

        $validated = $request->validate([
            'idempotency_key' => 'nullable|string|max:191',
        ]);

        try {
            $deposit = $this->deposits->submit($collector, $validated['idempotency_key'] ?? null);
        } catch (\Throwable $e) {
            return redirect()
                ->route('collector-worklist.index')
                ->withErrors(['deposit' => $e->getMessage()]);
        }

        return redirect()
            ->route('collector-worklist.index')
            ->with('success', "Setoran {$deposit->deposit_number} terkirim. Menunggu verifikasi admin — saldo Anda kembali nol.");
    }

    /**
     * Admin menyetor SELURUH saldo kolektor `$collector` atas namanya —
     * dari Worksheet Admin, bukan Worklist Kolektor. Satu-satunya alasan ini
     * ada: kolektor yang bersangkutan sedang tak bisa membuka aplikasinya
     * sendiri. Kalau dia bisa, dia yang harus menyetor sendiri lewat
     * `store()` — jalur ini bukan pintas buat admin yang malas menunggu.
     *
     * Setoran yang lahir dari sini tetap `collector_id = $collector->id`
     * (saldo nol adalah saldo DIA, bukan admin) dan tetap berstatus
     * MENUNGGU_VERIFIKASI seperti biasa — admin yang sama TETAP boleh
     * memverifikasinya sendiri sesudahnya (`assertVerifierIsNotDepositor`
     * cuma membandingkan `verifier` dengan `collector_id`, bukan dengan
     * siapa yang menyetor). Itu relaksasi yang disengaja untuk kasus
     * kolektor lumpuh akses — jejak siapa yang benar-benar menekan tombol
     * tetap utuh di audit log (`CollectorDepositService::submit()`), jadi
     * Owner/atasan tetap bisa mengaudit kalau satu admin menyetor DAN
     * memverifikasi sendiri berkali-kali.
     */
    public function storeForCollector(Request $request, User $collector): RedirectResponse
    {
        abort_unless($collector->hasRole('kolektor'), 404, 'User ini bukan kolektor.');

        // Guard POP sama persis dengan yang menggerbang halaman
        // Worksheet-nya (CollectorWorksheetController::show()) — admin yang
        // tak berhak MEMBUKA kas kolektor ini jelas tak berhak MENYETOR atas
        // namanya juga.
        abort_unless(
            $this->balance->isVisibleTo($collector, $request->user()),
            403,
            'Kolektor ini menagih di POP di luar scope Anda.'
        );

        $validated = $request->validate([
            'idempotency_key' => 'nullable|string|max:191',
        ]);

        try {
            $deposit = $this->deposits->submit(
                $collector,
                $validated['idempotency_key'] ?? null,
                $request->user(),
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'pembayaran'])
                ->withErrors(['deposit' => $e->getMessage()]);
        }

        return redirect()
            ->route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'setoran'])
            ->with('success', "Setoran {$deposit->deposit_number} tercatat atas nama {$collector->name}. Saldonya kembali nol — tinggal diverifikasi.");
    }

    public function verify(Request $request, CollectorDeposit $deposit): RedirectResponse
    {
        // Admin menghitung uang fisik lalu mengetik `1.250.000`. Titik ribuan
        // wajib dinormalkan di sini juga — nominal setoran yang salah baca
        // melahirkan "selisih" palsu yang harus ditagihkan ke kolektor.
        $request->merge(RupiahInput::parseKeys(
            $request->only(['declared_amount', 'settlement_amount']),
            'declared_amount',
            'settlement_amount',
        ));

        $validated = $request->validate([
            'declared_amount' => 'required|numeric|min:0',
            'settles_deposit_id' => 'nullable|integer|exists:collector_deposits,id',
            'settlement_amount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        $settles = $validated['settles_deposit_id'] ?? null
            ? CollectorDeposit::findOrFail($validated['settles_deposit_id'])
            : null;

        try {
            $this->deposits->verify(
                $deposit,
                $request->user(),
                (float) $validated['declared_amount'],
                $settles,
                (float) ($validated['settlement_amount'] ?? 0),
                $validated['note'] ?? null,
            );
        } catch (\Throwable $e) {
            return $this->backToWorksheet($deposit)->withErrors(['deposit' => $e->getMessage()]);
        }

        return $this->backToWorksheet($deposit)
            ->with('success', "Setoran {$deposit->deposit_number} selesai diperiksa.");
    }

    public function writeOff(Request $request, CollectorDeposit $deposit): RedirectResponse
    {
        $validated = $request->validate([
            'write_off_reason' => ReasonValidationRule::required(1000),
        ]);

        try {
            $this->deposits->writeOff($deposit, $request->user(), $validated['write_off_reason']);
        } catch (\Throwable $e) {
            return $this->backToWorksheet($deposit)->withErrors(['deposit' => $e->getMessage()]);
        }

        return $this->backToWorksheet($deposit)
            ->with('success', "Selisih setoran {$deposit->deposit_number} dihapus buku.");
    }

    private function backToWorksheet(CollectorDeposit $deposit): RedirectResponse
    {
        return redirect()->route('collector-worksheet.show', [
            'collector' => $deposit->collector_id,
            'tab' => 'setoran',
        ]);
    }
}
