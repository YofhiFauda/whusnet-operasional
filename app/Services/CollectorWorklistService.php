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
     * @param  string|null  $search  filter nama/kode/CID pelanggan — cross check admin
     *                               atas ratusan tunggakan kolektor sering butuh cari
     *                               satu pelanggan tertentu, bukan scroll seluruh daftar
     */
    public function outstandingInvoices(User $collector, ?User $viewer = null, ?string $search = null): Builder
    {
        return Invoice::query()
            ->applyUserScope($viewer)
            ->whereIn('invoice_status', self::OUTSTANDING_STATUSES)
            ->whereHas('customer', function ($q) use ($collector, $search) {
                $q->where('collector_id', $collector->id);

                if ($search !== null && $search !== '') {
                    $q->where(function ($inner) use ($search) {
                        $inner->where('full_name', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%")
                            ->orWhere('cid', 'like', "%{$search}%");
                    });
                }
            })
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
     *    daftar kalau punya MINIMAL SATU tagihan yang periodenya sudah
     *    berjalan; begitu masuk, SELURUH tagihan tertunggaknya ikut tampil.
     *    Kalau tidak begitu, tunggakan lama dan tagihan berjalan pecah ke dua
     *    kunjungan berbeda, padahal kolektor cuma lewat sebulan sekali.
     *
     * 2. **Periode, bukan `due_date`.** Tagihan boleh ditagih begitu
     *    periodenya berjalan (`billing_period <= bulan ini`): terbit tanggal
     *    1, batas bayar riil akhir bulan (docs/BUSINESS_RULES.md §7 poin 4).
     *    `due_date` cuma label UI. Dulu di sini ada jendela
     *    `due_date <= hari ini + N hari` (`collector_due_window_days`) — itu
     *    membandingkan tanggal yang sudah tak bermakna, dan bikin aturan
     *    "boleh ditagih" kolektor menyimpang dari aturan piutang
     *    (`Invoice::scopePiutang()`, juga berbasis periode). Jangan hidupkan
     *    lagi perbandingan `due_date` di sini.
     *
     * Catatan: filter ini BUKAN pencegah "nagih 2× ke pelanggan yang sama" —
     * itu sudah tertutup secara struktural (bayar → `remaining_amount` turun →
     * lunas → hilang dari daftar, plus penolakan di CollectorPaymentService).
     * Yang dicegah di sini adalah nagih tagihan yang periodenya belum dimulai.
     *
     * @param  User|null  $viewer  pemilik POP scope yang dipakai; default user login
     */
    public function dueInvoices(User $collector, ?User $viewer = null): Builder
    {
        $currentPeriod = now()->format('Y-m');

        return Invoice::query()
            ->applyUserScope($viewer)
            ->whereIn('invoice_status', self::OUTSTANDING_STATUSES)
            ->whereHas('customer', function ($q) use ($collector, $currentPeriod) {
                $q->where('collector_id', $collector->id)
                    ->whereHas('invoices', fn ($i) => $i
                        ->whereIn('invoice_status', self::OUTSTANDING_STATUSES)
                        ->where('billing_period', '<=', $currentPeriod)
                    );
            })
            ->with(['customer'])
            ->orderBy('customer_id')
            ->orderBy('due_date')
            ->orderBy('id');
    }
}
