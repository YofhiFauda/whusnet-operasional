<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu-satunya sumber kebenaran "tagihan mana yang boleh ditagih kolektor".
 * Dipakai dua halaman berbeda audiens (Worklist Kolektor & Worksheet Admin),
 * jadi aturannya harus hidup di satu tempat — kalau tidak, dua halaman
 * menampilkan daftar berbeda untuk kolektor yang sama dan cross check jadi
 * mustahil.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §10.
 */
class CollectorWorklistService
{
    /**
     * Status invoice yang masih punya sisa tagihan. `sebagian` IKUT karena itu
     * sisa cicilan yang memang harus ditagih lagi — bukan tagih ulang.
     *
     * @var array<int, string>
     */
    public const OUTSTANDING_STATUSES = [
        InvoiceStatus::BELUM_DIBAYAR->value,
        InvoiceStatus::SEBAGIAN->value,
    ];

    /**
     * Seluruh tagihan tertunggak milik satu kolektor, TANPA filter jatuh
     * tempo. Ini pandangan Admin di Worksheet: admin bukan pengetuk pintu,
     * dia butuh gambaran penuh untuk cross check.
     *
     * @param  User|null  $viewer  pemilik POP scope yang dipakai; default user login
     */
    public function outstandingInvoices(User $collector, ?User $viewer = null): Builder
    {
        return Invoice::query()
            ->applyUserScope($viewer)
            ->whereIn('invoice_status', self::OUTSTANDING_STATUSES)
            ->whereHas('customer', fn ($q) => $q->where('collector_id', $collector->id))
            ->with(['customer'])
            ->orderBy('customer_id')
            ->orderBy('due_date')
            ->orderBy('id');
    }

    /**
     * Tagihan yang "sudah waktunya ditagih" — pandangan Kolektor di lapangan.
     *
     * Dua aturan yang gampang tertukar, keduanya disengaja:
     *
     * 1. **Seleksi per PELANGGAN, tampilan per INVOICE.** Pelanggan masuk
     *    daftar kalau punya MINIMAL SATU tagihan yang sudah masuk jendela
     *    tagih; begitu masuk, SELURUH tagihan tertunggaknya ikut tampil —
     *    termasuk yang belum jatuh tempo. Kalau tidak begitu, tunggakan lama
     *    dan tagihan berjalan pecah ke dua kunjungan berbeda, padahal kolektor
     *    cuma lewat sebulan sekali.
     *
     * 2. **Jendela, bukan `due_date <= hari ini`.** Lihat
     *    `config('billing.collector_due_window_days')` untuk alasannya.
     *
     * Catatan: filter ini BUKAN pencegah "nagih 2× ke pelanggan yang sama" —
     * itu sudah tertutup secara struktural (bayar → `remaining_amount` turun →
     * lunas → hilang dari daftar, plus penolakan di CollectorPaymentService).
     * Yang dicegah di sini adalah nagih terlalu awal.
     *
     * @param  User|null  $viewer  pemilik POP scope yang dipakai; default user login
     */
    public function dueInvoices(User $collector, ?User $viewer = null): Builder
    {
        $cutoff = now()->addDays($this->dueWindowDays())->toDateString();

        return Invoice::query()
            ->applyUserScope($viewer)
            ->whereIn('invoice_status', self::OUTSTANDING_STATUSES)
            ->whereHas('customer', function ($q) use ($collector, $cutoff) {
                $q->where('collector_id', $collector->id)
                    ->whereHas('invoices', fn ($i) => $i
                        ->whereIn('invoice_status', self::OUTSTANDING_STATUSES)
                        ->whereDate('due_date', '<=', $cutoff)
                    );
            })
            ->with(['customer'])
            ->orderBy('customer_id')
            ->orderBy('due_date')
            ->orderBy('id');
    }

    public function dueWindowDays(): int
    {
        return (int) config('billing.collector_due_window_days', 7);
    }
}
