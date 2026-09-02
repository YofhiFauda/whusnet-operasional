<?php

namespace App\Services;

use App\Enums\CashDepositStatus;
use App\Enums\DepositStatus;
use App\Enums\PaymentStatus;
use App\Models\CashDeposit;
use App\Models\CollectorDeposit;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Posisi kas seorang ADMIN. Tiga angka, dan seperti di sisi kolektor
 * ([[CollectorBalanceService]]) ketiganya JANGAN pernah dijumlahkan jadi satu:
 *
 *   1. **Saldo Tunai Belum Disetor** — uang fisik yang ada di tangan admin,
 *      berasal dari setoran kolektor yang dia verifikasi + pembayaran manual
 *      tunai yang dia terima di kantor. Ini satu-satunya angka yang menjadi
 *      KEWAJIBAN SETOR.
 *   2. **Non-Tunai** — transfer & QRIS. Uangnya sudah ada di bank, tak pernah
 *      lewat tangan admin. Ditampilkan sebagai INFORMASI saja; kalau ikut
 *      dijumlahkan, admin diminta menyetorkan uang yang tak pernah dia pegang.
 *   3. **Selisih Setoran Kas Terbuka** — hasil pemeriksaan yang belum ditutup
 *      Owner. Tidak ikut nol saat admin menyetor; justru harus terus terlihat.
 *
 * Semua DITURUNKAN dari data. Tidak ada kolom saldo yang di-increment — kolom
 * seperti itu berhenti benar begitu satu payment di-reject, dan angka uang yang
 * bohong tidak punya alarm.
 *
 * docs/plan/kolektor/analisa-setoran-kas-admin.md §2, §4.3.
 */
class AdminCashBalanceService
{
    /**
     * Setoran kolektor yang uangnya sudah diterima admin ini tapi belum
     * diteruskan ke owner/bank.
     *
     * `verified_by` yang dipakai sebagai penanda kepemilikan, bukan `pop_id`:
     * yang memegang uang fisik adalah orang yang menghitungnya di meja
     * (keputusan D1 — saldo per admin).
     *
     * `MENUNGGU_VERIFIKASI` tidak pernah ikut: uangnya masih di tas kolektor.
     * `DIHAPUS_BUKU` juga tidak — nilai yang dihapus buku memang tidak pernah
     * sampai ke kantor.
     *
     * @return Builder<CollectorDeposit>
     */
    public function unsettledCollectorDepositsQuery(User $admin): Builder
    {
        return CollectorDeposit::query()
            ->where('verified_by', $admin->id)
            ->whereNull('cash_deposit_id')
            ->whereIn('status', $this->statusSetoranSudahDiterima());
    }

    /**
     * Pembayaran manual di kantor: tunai, sah, tak pernah lewat kolektor, dan
     * belum ikut setoran kas mana pun.
     *
     * `collector_deposit_id IS NULL` WAJIB ikut meski `collected_by` sudah
     * null: tanpa itu, pembayaran yang sudah masuk setoran kolektor bisa
     * terhitung SEKALI LAGI di jalur manual, dan uang yang sama muncul dua kali
     * sebagai kewajiban setor.
     *
     * @return Builder<Payment>
     */
    public function unsettledManualPaymentsQuery(User $admin): Builder
    {
        return Payment::query()
            ->where('received_by', $admin->id)
            ->whereNull('collected_by')
            ->whereNull('collector_deposit_id')
            ->whereNull('cash_deposit_id')
            ->where('payment_method', 'cash')
            ->where('payment_status', PaymentStatus::VALID->value);
    }

    /**
     * Saldo Tunai Belum Disetor — angka 1.
     */
    public function tunaiBelumDisetor(User $admin): float
    {
        // Dijumlahkan lewat Money (ranah sen), bukan `sum()` biasa: angka ini
        // yang menentukan setoran kas ditandai cocok atau selisih, dan selisih
        // palsu berarti admin ditagih uang yang sudah dia serahkan.
        $dariKolektor = Money::sum(
            $this->unsettledCollectorDepositsQuery($admin)
                ->get(['id', 'declared_amount', 'difference'])
                ->map(fn (CollectorDeposit $deposit) => $deposit->cashReceivedByOffice())
        );

        $dariManual = Money::of($this->unsettledManualPaymentsQuery($admin)->sum('amount'));

        return Money::add($dariKolektor, $dariManual);
    }

    /**
     * Rekap non-tunai — angka 2. INFORMASI, bukan kewajiban setor.
     *
     * Tak ada penanda "sudah disetor" di sini karena uangnya memang tak pernah
     * disetor siapa pun: ia langsung mendarat di rekening. Karena itu yang
     * dipakai jendela periode, bukan status.
     *
     * @return array{total: float, per_metode: array<string, float>}
     */
    public function nonTunaiRekap(User $admin, ?string $sejak = null, ?string $sampai = null): array
    {
        $sejak ??= now()->startOfMonth()->toDateString();
        $sampai ??= now()->endOfMonth()->toDateString();

        $baris = Payment::query()
            ->where('received_by', $admin->id)
            ->whereNull('collected_by')
            ->where('payment_status', PaymentStatus::VALID->value)
            ->where('payment_method', '!=', 'cash')
            ->whereBetween('payment_date', [$sejak, $sampai])
            ->get(['payment_method', 'amount']);

        $perMetode = $baris
            ->groupBy('payment_method')
            ->map(fn (Collection $group) => Money::sum($group->pluck('amount')))
            ->all();

        return [
            'total' => Money::sum($baris->pluck('amount')),
            'per_metode' => $perMetode,
        ];
    }

    /**
     * Setoran kas milik admin ini yang selisihnya masih terbuka.
     *
     * @return Collection<int, CashDeposit>
     */
    public function openDifferenceDeposits(User $admin): Collection
    {
        return CashDeposit::query()
            ->realDeposits()
            ->where('depositor_id', $admin->id)
            ->whereIn('status', [
                CashDepositStatus::SELISIH_KURANG->value,
                CashDepositStatus::SELISIH_LEBIH->value,
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * Total selisih terbuka — angka 3. Nilai absolut, karena dua arah selisih
     * sama-sama pekerjaan yang belum selesai.
     */
    public function selisihTerbuka(User $admin): float
    {
        return Money::sum(
            $this->openDifferenceDeposits($admin)->map(fn (CashDeposit $deposit) => abs((float) $deposit->difference))
        );
    }

    /**
     * Status setoran kolektor yang uangnya SUDAH mendarat di tangan admin.
     *
     * Satu tempat, dipakai seluruh query saldo — kalau tiap pemanggil
     * menuliskan daftarnya sendiri, cepat atau lambat mereka menyimpang dan
     * "siapa yang memegang uang" berbeda dari "berapa uangnya".
     *
     * @return array<int, string>
     */
    private function statusSetoranSudahDiterima(): array
    {
        return [
            DepositStatus::TERVERIFIKASI->value,
            DepositStatus::SELISIH->value,
            DepositStatus::SELISIH_LUNAS->value,
            DepositStatus::LEBIH_SETOR->value,
        ];
    }

    /**
     * Seluruh POP yang tersentuh jejak uang admin ini.
     *
     * Sama alasannya dengan [[CollectorBalanceService::popFootprint()]]: yang
     * relevan bukan "admin ini ditugaskan di mana", tapi "uang di halaman ini
     * berasal dari POP mana" — dua hal yang bisa berbeda setelah seseorang
     * dipindah cabang, dan yang bocor adalah uangnya.
     *
     * @return array<int, int>
     */
    public function popFootprint(User $admin): array
    {
        $dariSetoranKolektor = CollectorDeposit::query()
            ->where('verified_by', $admin->id)
            ->distinct()
            ->pluck('pop_id');

        $dariPembayaran = Payment::query()
            ->where('received_by', $admin->id)
            ->distinct()
            ->pluck('pop_id');

        $dariSetoranKas = CashDeposit::query()
            ->where('depositor_id', $admin->id)
            ->distinct()
            ->pluck('pop_id');

        return $dariSetoranKolektor
            ->merge($dariPembayaran)
            ->merge($dariSetoranKas)
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Boleh atau tidak `$viewer` membuka posisi kas `$admin`.
     *
     * All-or-nothing, bukan "tampilkan yang boleh saja" — halaman ini
     * menyajikan ANGKA TOTAL, dan total yang diam-diam disaring bukan
     * menyembunyikan baris: ia berbohong.
     */
    public function isVisibleTo(User $admin, User $viewer): bool
    {
        if ($admin->id === $viewer->id) {
            return true;
        }

        $access = app(EffectiveAccessService::class);

        // Dua nama role di sini SENGAJA disebut eksplisit — dan ini satu-satunya
        // tempat di modul kas yang begitu.
        //
        // Alasannya bukan kelalaian: `HasPopScope::scopeApplyUserScope()`
        // memberi owner & atasan akses penuh TANPA melihat scope mereka, jadi
        // seluruh query di halaman ini memang sudah mengembalikan data lintas
        // POP untuk keduanya. Kalau gerbang di sini memakai aturan yang berbeda,
        // hasilnya tidak konsisten: halamannya ditolak padahal datanya boleh
        // dibaca lewat jalur mana pun yang lain.
        //
        // Yang benar bila ingin dihapus: cabut perlakuan khusus itu dari
        // HasPopScope lebih dulu, jangan di sini saja.
        //
        // `hasAllPopAccess()` diperiksa SESUDAHNYA: getAllowedPopIds()
        // mengembalikan array kosong untuk ALL_POP, dan array kosong itu
        // ambigu (bisa berarti scope belum di-setup).
        if ($viewer->hasRole('owner', 'atasan') || $access->hasAllPopAccess($viewer)) {
            return true;
        }

        $footprint = $this->popFootprint($admin);

        if ($footprint === []) {
            return true;
        }

        return array_diff($footprint, $access->getAllowedPopIds($viewer)) === [];
    }

    /**
     * POP representatif untuk setoran yang sedang dibuat — diambil dari sumber
     * uangnya, bukan dari profil admin. Otorisasi tidak bersandar ke kolom ini
     * (lihat CashDepositService::assertVerifierCanSeeAllSources()).
     */
    public function representativePopId(User $admin): ?int
    {
        $popId = $this->unsettledCollectorDepositsQuery($admin)->value('pop_id')
            ?? $this->unsettledManualPaymentsQuery($admin)->value('pop_id');

        return $popId ? (int) $popId : null;
    }
}
