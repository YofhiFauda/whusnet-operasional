<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan Bayar Kolektor (ADHOC-90) — tabel "Bayar Wifi Cash" yang dulu
 * dibuat manual di spreadsheet (`docs/plan/billing/tabel-bayar-list-kolektor.md`).
 *
 * Berbeda dari Laporan Bulanan Admin Collector: ini daftar TRANSAKSI per
 * kolektor, bukan rekap per POP. Satu kelompok = satu sesi kolektor input
 * bayar (`payment_batch_id`); "Total Sub" = jumlah kelompok itu. Pembayaran
 * tanpa batch (jalur single) jadi kelompok sendiri.
 *
 * "Jumlah" = `payments.amount` (bagian yang diterapkan ke tagihan), sama
 * dengan yang dijumlah `CollectorBalanceService::balance()` dan
 * `/reports/payments` — lebih bayar (`overpay_amount`) sengaja tidak masuk
 * supaya tiga angka itu tidak saling menyimpang.
 */
class CollectorPaymentReportService
{
    /**
     * @return array{groups: Collection<int, array{date: Carbon, subtotal: float, payments: Collection<int, Payment>}>, total: float, count: int}
     */
    public function build(User $viewer, ?int $collectorId, string $startDate, string $endDate, ?string $method): array
    {
        $start = Carbon::parse($startDate)->toDateString();
        // Batas atas setengah-terbuka pada string tanggal polos — alasan sama
        // dengan CollectorMonthlyReportService::bounds() (kolom bertipe date).
        $endExclusive = Carbon::parse($endDate)->addDay()->toDateString();

        $payments = Payment::query()
            ->applyUserScope($viewer)
            ->with(['customer', 'collector', 'invoice:id,billing_period'])
            ->where('payment_status', PaymentStatus::VALID->value)
            // Hanya uang yang ditagih kolektor; `collected_by` null = bayar di kantor.
            ->when($collectorId, fn ($q) => $q->where('collected_by', $collectorId), fn ($q) => $q->whereNotNull('collected_by'))
            ->when($method, fn ($q) => $q->where('payment_method', $method))
            // Tanggal uang diterima di lapangan; jatuh ke `payment_date` bila kolektor tak mengisinya.
            ->whereRaw('COALESCE(collected_date, payment_date) >= ?', [$start])
            ->whereRaw('COALESCE(collected_date, payment_date) < ?', [$endExclusive])
            ->orderBy('id')
            ->get();

        $groups = $payments
            ->groupBy(fn (Payment $p) => $p->payment_batch_id ? 'batch-'.$p->payment_batch_id : 'solo-'.$p->id)
            ->map(function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'date' => Carbon::parse($first->collected_date ?? $first->payment_date),
                    'subtotal' => Money::sum($rows->pluck('amount')),
                    'payments' => $rows->values(),
                ];
            })
            ->sortBy(fn (array $g) => $g['date']->format('Y-m-d').'|'.str_pad((string) $g['payments']->first()->id, 12, '0', STR_PAD_LEFT))
            ->values();

        return [
            'groups' => $groups,
            'total' => Money::sum($groups->pluck('subtotal')),
            'count' => $payments->count(),
        ];
    }
}
