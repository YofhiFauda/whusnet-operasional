<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\PaymentStatus;
use App\Models\CollectorDeposit;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Dua angka uang milik kolektor. JANGAN pernah dijumlahkan jadi satu.
 *
 *   1. **Saldo Belum Disetor** — uang tagihan yang masih di tangan kolektor.
 *      Wajib kembali 0 tiap kali dia setor ("tidak boleh ada saldo mengendap").
 *   2. **Kurang Setor** — kewajiban kolektor dari setoran yang uang fisiknya
 *      kurang dari yang tercatat. TIDAK ikut nol; justru harus terus terlihat
 *      sampai dilunasi atau dihapus buku.
 *
 * Kalau keduanya digabung, "saldo 0" jadi ambigu: beres, atau nombok 30rb yang
 * tak tercatat. Justru angka kedua itulah yang paling penting dilacak.
 *
 * Kedua angka DITURUNKAN dari data, tak ada kolom saldo yang di-increment.
 * Kolom seperti itu berhenti benar begitu satu payment di-reject, dan angka
 * uang yang bohong tak punya alarm.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §11.1, §11.2.
 */
class CollectorBalanceService
{
    /**
     * Pembayaran yang sudah ditagih tapi belum ikut setoran mana pun.
     * Payment `ditolak` tak ikut — uangnya memang tak pernah sah, jadi
     * penolakan otomatis menurunkan saldo tanpa perlu kompensasi manual.
     *
     * @return Builder<Payment>
     */
    public function unsettledPaymentsQuery(User $collector): Builder
    {
        return Payment::query()
            ->where('collected_by', $collector->id)
            ->where('payment_status', PaymentStatus::VALID->value)
            ->whereNull('collector_deposit_id');
    }

    /**
     * Saldo Belum Disetor.
     */
    public function balance(User $collector): float
    {
        return Money::of($this->unsettledPaymentsQuery($collector)->sum('amount'));
    }

    /**
     * Setoran milik kolektor ini yang selisihnya masih terbuka.
     *
     * @return Collection<int, CollectorDeposit>
     */
    public function openShortfallDeposits(User $collector): Collection
    {
        return CollectorDeposit::query()
            ->where('collector_id', $collector->id)
            ->where('status', DepositStatus::SELISIH->value)
            ->orderBy('id')
            ->get()
            ->filter(fn (CollectorDeposit $deposit) => $deposit->outstandingShortfall() > 0)
            ->values();
    }

    /**
     * Total Kurang Setor yang belum ditutup.
     */
    public function outstandingShortfall(User $collector): float
    {
        // Penjumlahan terjadi di ranah sen: menjumlahkan banyak float yang
        // masing-masing sudah dibulatkan tetap menumpuk galat (terukur
        // −0,00000085 untuk 1.000 baris), dan angka inilah yang menentukan
        // seorang kolektor masih punya kewajiban atau tidak.
        return Money::sum(
            $this->openShortfallDeposits($collector)->map(fn (CollectorDeposit $deposit) => $deposit->outstandingShortfall())
        );
    }

    /**
     * Seluruh POP yang tersentuh jejak uang kolektor ini: pelanggan yang
     * dipegangnya, pembayaran yang dia tagih, dan setoran yang dia serahkan.
     *
     * Dipakai untuk memutuskan siapa boleh membuka halaman kas kolektor
     * (assertVisibleTo). Bukan dari `users.pops` atau scope role-nya: yang
     * relevan bukan "kolektor ini ditugaskan di mana", tapi "uang di halaman
     * ini berasal dari POP mana" — dua hal itu bisa berbeda setelah kolektor
     * dipindah cabang, dan yang bocor adalah uangnya, bukan penugasannya.
     *
     * @return array<int, int>
     */
    public function popFootprint(User $collector): array
    {
        $fromCustomers = Customer::query()
            ->where('collector_id', $collector->id)
            ->distinct()
            ->pluck('pop_id');

        $fromPayments = Payment::query()
            ->where('collected_by', $collector->id)
            ->distinct()
            ->pluck('pop_id');

        $fromDeposits = CollectorDeposit::query()
            ->where('collector_id', $collector->id)
            ->distinct()
            ->pluck('pop_id');

        return $fromCustomers
            ->merge($fromPayments)
            ->merge($fromDeposits)
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Boleh atau tidak `$viewer` membuka halaman kas `$collector`.
     *
     * Aturannya SENGAJA all-or-nothing, bukan "tampilkan yang boleh saja":
     * halaman ini menyajikan ANGKA TOTAL (saldo, kurang setor, nilai setoran).
     * Total yang diam-diam disaring bukan sekadar menyembunyikan baris — ia
     * berbohong. Admin yang melihat "saldo Rp200rb" padahal sebenarnya Rp350rb
     * akan menghitung uang fisik dengan patokan yang salah.
     *
     * Konsisten dengan syarat verifikasi setoran (§14.2): kalau seorang admin
     * tak berwenang menutup setoran karena ada POP di luar jangkauannya, dia
     * juga tak berkepentingan membaca posisi kasnya.
     *
     * Kolektor tanpa jejak uang sama sekali (baru dibuat) boleh dibuka —
     * tak ada yang bisa bocor dari halaman kosong.
     */
    public function isVisibleTo(User $collector, User $viewer): bool
    {
        $access = app(EffectiveAccessService::class);

        if ($viewer->hasRole('owner', 'atasan') || $access->hasAllPopAccess($viewer)) {
            return true;
        }

        $footprint = $this->popFootprint($collector);

        if ($footprint === []) {
            return true;
        }

        $allowed = $access->getAllowedPopIds($viewer);

        return array_diff($footprint, $allowed) === [];
    }
}
