<?php

namespace App\Services;

use App\Enums\CashDepositChannel;
use App\Enums\CashDepositStatus;
use App\Models\CashDeposit;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Posisi kas OWNER — ujung rantai uang.
 *
 *     pelanggan → kolektor → admin → **owner/bank**
 *
 * Lahir dari kesalahan yang sama dengan §1, cuma satu tingkat lebih tinggi:
 * sesudah Owner memeriksa setoran admin, uangnya berhenti dicatat. Admin
 * kehilangan saldo (benar — uangnya sudah diserahkan), tapi tak ada siapa pun
 * yang menerimanya di sistem. Uang berpindah ke ruang kosong untuk kedua
 * kalinya.
 *
 * Tiga angka, dan seperti di dua tingkat sebelumnya JANGAN dijumlahkan:
 *
 *   1. **Brankas** — uang fisik yang benar-benar dipegang Owner. Hanya dari
 *      setoran ber-channel `tunai_brankas`.
 *   2. **Masuk Bank** — setoran ber-channel `transfer_bank`. Uangnya di
 *      rekening, tak pernah lewat tangan Owner; menjumlahkannya dengan brankas
 *      melahirkan "uang tunai" yang mustahil dihitung ulang di meja.
 *   3. **Dalam Perjalanan** — sudah dikirim admin, BELUM diperiksa Owner.
 *      Ini klaim satu pihak, bukan kas: dicatat terpisah supaya selisih
 *      ketahuan saat dihitung, bukan tertelan lebih dulu.
 *
 * Semua DITURUNKAN dari `cash_deposits`. Tak ada kolom saldo yang di-increment,
 * alasan yang sama dengan [[AdminCashBalanceService]].
 *
 * docs/plan/kolektor/analisa-setoran-kas-admin.md §11.
 */
class OwnerCashBalanceService
{
    /**
     * Setoran yang sudah DIPERIKSA — uangnya sah berpindah ke Owner.
     *
     * `MENUNGGU_VERIFIKASI` tak pernah ikut: yang belum dihitung belum jadi
     * kas siapa pun. `SALDO_AWAL` juga tidak — sentinel titik nol bukan
     * setoran (§7).
     *
     * `DIHAPUS_BUKU` justru IKUT, berbeda dari perlakuan setoran kolektor di
     * sisi admin. Alasannya beda arti: di sana hapus buku berarti uangnya tak
     * pernah sampai; di sini yang ditutup adalah SELISIHNYA, sedangkan uang
     * fisik yang sudah dihitung Owner tetap ada di brankasnya.
     *
     * @return Builder<CashDeposit>
     */
    public function receivedDepositsQuery(User $owner): Builder
    {
        return CashDeposit::query()
            ->realDeposits()
            ->where('verified_by', $owner->id)
            ->whereIn('status', [
                CashDepositStatus::TERVERIFIKASI->value,
                CashDepositStatus::SELISIH_KURANG->value,
                CashDepositStatus::SELISIH_LEBIH->value,
                CashDepositStatus::DIHAPUS_BUKU->value,
            ]);
    }

    /**
     * Angka 1 — uang fisik di brankas Owner.
     *
     * Yang dipakai `declared_amount`: itulah yang benar-benar dihitung Owner di
     * meja, bukan angka yang diklaim admin. Kelebihan setor dikurangkan lagi
     * karena — sama seperti di tingkat kolektor — uang lebih dikembalikan fisik
     * saat itu juga, jadi tak pernah mengendap di brankas.
     */
    public function saldoBrankas(User $owner): float
    {
        return Money::sum(
            $this->receivedDepositsQuery($owner)
                ->where('channel', CashDepositChannel::TUNAI_BRANKAS->value)
                ->get(['id', 'declared_amount', 'difference'])
                ->map(fn (CashDeposit $deposit) => Money::atLeastZero(
                    Money::sub($deposit->declared_amount, Money::atLeastZero($deposit->difference))
                ))
        );
    }

    /**
     * Angka 2 — yang mendarat di rekening, bukan di tangan Owner.
     *
     * Dibatasi periode karena tak ada penanda "sudah dipakai": uang di bank
     * tidak menunggu tindakan siapa pun, jadi yang bermakna adalah arus masuk
     * per periode, bukan tumpukan sejak awal waktu.
     *
     * @return array{total: float, per_bank: array<string, float>}
     */
    public function masukBank(User $owner, ?string $sejak = null, ?string $sampai = null): array
    {
        $sejak ??= now()->startOfMonth()->toDateString();
        $sampai ??= now()->endOfMonth()->toDateString();

        $baris = $this->receivedDepositsQuery($owner)
            ->where('channel', CashDepositChannel::TRANSFER_BANK->value)
            ->whereBetween('verified_at', [$sejak.' 00:00:00', $sampai.' 23:59:59'])
            ->get(['id', 'bank_name', 'declared_amount']);

        return [
            'total' => Money::sum($baris->pluck('declared_amount')),
            'per_bank' => $baris
                ->groupBy(fn (CashDeposit $deposit) => $deposit->bank_name ?: '(tanpa nama bank)')
                ->map(fn (Collection $group) => Money::sum($group->pluck('declared_amount')))
                ->all(),
        ];
    }

    /**
     * Angka 3 — sudah dikirim admin, belum dihitung Owner.
     *
     * Nilainya dari `computedAmount()` (catatan sistem), BUKAN `declared_amount`
     * yang memang masih kosong: sebelum diperiksa, tak ada uang fisik yang
     * pernah dihitung siapa pun. Angka ini klaim, dan ditampilkan sebagai
     * klaim.
     *
     * Di-scope POP seperti query lain — Owner lolos lewat `applyUserScope()`,
     * atasan cabang hanya melihat setoran wilayahnya.
     */
    public function dalamPerjalanan(User $viewer): float
    {
        $pending = CashDeposit::query()
            ->realDeposits()
            ->applyUserScope($viewer)
            ->where('status', CashDepositStatus::MENUNGGU_VERIFIKASI->value)
            ->get(['id']);

        return Money::sum(
            $pending->map(fn (CashDeposit $deposit) => $deposit->computedAmount())
        );
    }

    /**
     * Setoran yang selisihnya masih menggantung di tangan Owner ini.
     *
     * @return Collection<int, CashDeposit>
     */
    public function selisihTerbukaDiterima(User $owner): Collection
    {
        return CashDeposit::query()
            ->realDeposits()
            ->where('verified_by', $owner->id)
            ->whereIn('status', [
                CashDepositStatus::SELISIH_KURANG->value,
                CashDepositStatus::SELISIH_LEBIH->value,
            ])
            ->orderBy('id')
            ->get();
    }
}
