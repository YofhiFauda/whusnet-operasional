<?php

namespace App\Services;

use App\Enums\CustomerBalanceMutationType;
use App\Models\Customer;
use App\Models\CustomerBalanceMutation;
use App\Models\Payment;
use App\Support\Money;
use InvalidArgumentException;

/**
 * Saldo pelanggan (lebih bayar yang bisa dipakai di pembayaran berikutnya).
 *
 * Saldo = SUM(credit) - SUM(debit), SELALU DITURUNKAN dari
 * `customer_balance_mutations` — tak ada kolom `customers.balance` yang
 * di-increment/decrement. Sama alasannya dengan [[CollectorBalanceService]]
 * dan [[AdminCashBalanceService]]: kolom begitu berhenti benar begitu satu
 * payment sumbernya di-reject, dan angka uang yang bohong tak punya alarm.
 *
 * `overpay_amount` di `payments` TETAP ada dan TETAP diisi (catatan
 * informatif per-payment) — tabel ini yang menjadikannya saldo AKTIF yang
 * bisa dipakai. Dua hal berbeda, jangan disatukan.
 */
class CustomerBalanceService
{
    /**
     * Saldo berjalan pelanggan saat ini.
     */
    public function balance(Customer $customer): float
    {
        $credit = Money::of(
            CustomerBalanceMutation::query()
                ->where('customer_id', $customer->id)
                ->where('type', CustomerBalanceMutationType::CREDIT->value)
                ->sum('amount')
        );

        $debit = Money::of(
            CustomerBalanceMutation::query()
                ->where('customer_id', $customer->id)
                ->where('type', CustomerBalanceMutationType::DEBIT->value)
                ->sum('amount')
        );

        return Money::sub($credit, $debit);
    }

    /**
     * Saldo berjalan dengan baris ledger customer ini DIKUNCI
     * (`lockForUpdate`) — dipanggil di dalam transaction yang sama dengan
     * penguncian invoice, supaya dua pembayaran simultan yang sama-sama
     * memakai saldo tak lolos berbarengan (satu harus menunggu baris
     * ledger yang lain rilis).
     *
     * Kalau pelanggan belum pernah punya mutasi sama sekali, tak ada baris
     * untuk dikunci — itu sudah pasti berarti saldo 0, aman tanpa lock.
     */
    private function lockedBalance(Customer $customer): float
    {
        CustomerBalanceMutation::query()
            ->where('customer_id', $customer->id)
            ->lockForUpdate()
            ->get(['id']);

        return $this->balance($customer);
    }

    /**
     * Catat kredit — dipanggil saat sebuah payment menghasilkan lebih bayar
     * (`overpay_amount > 0`). Payment sumbernya WAJIB terisi supaya kredit
     * ini bisa dibalik kalau payment tsb di-reject.
     */
    public function credit(Customer $customer, float $amount, Payment $sourcePayment, ?string $note = null): CustomerBalanceMutation
    {
        if (Money::isZero($amount) || Money::lessThan($amount, 0)) {
            throw new InvalidArgumentException('Nominal kredit saldo pelanggan harus positif.');
        }

        return CustomerBalanceMutation::create([
            'customer_id' => $customer->id,
            'type' => CustomerBalanceMutationType::CREDIT->value,
            'amount' => Money::of($amount),
            'payment_id' => $sourcePayment->id,
            'pop_id' => $sourcePayment->pop_id,
            'created_by' => auth()->id(),
            'note' => $note ?? "Lebih bayar dari {$sourcePayment->payment_number}",
        ]);
    }

    /**
     * Catat pemakaian saldo — dipanggil saat pelanggan memakai saldonya
     * untuk menutup sebagian/seluruh payment baru. WAJIB dipanggil di
     * dalam transaction yang sama dengan lock invoice
     * (lihat PaymentService::record()) supaya `lockedBalance()` efektif.
     *
     * @throws InvalidArgumentException kalau saldo tak cukup.
     */
    public function debit(Customer $customer, float $amount, Payment $consumingPayment, ?string $note = null): CustomerBalanceMutation
    {
        if (Money::isZero($amount) || Money::lessThan($amount, 0)) {
            throw new InvalidArgumentException('Nominal pemakaian saldo pelanggan harus positif.');
        }

        $available = $this->lockedBalance($customer);

        if (Money::greaterThan($amount, $available)) {
            throw new InvalidArgumentException(
                "Saldo pelanggan tidak cukup: tersedia Rp {$available}, diminta Rp {$amount}."
            );
        }

        return CustomerBalanceMutation::create([
            'customer_id' => $customer->id,
            'type' => CustomerBalanceMutationType::DEBIT->value,
            'amount' => Money::of($amount),
            'payment_id' => $consumingPayment->id,
            'pop_id' => $consumingPayment->pop_id,
            'created_by' => auth()->id(),
            'note' => $note ?? "Dipakai untuk {$consumingPayment->payment_number}",
        ]);
    }

    /**
     * Balikkan kredit yang sudah tercatat dari payment ini, dipanggil saat
     * payment sumber overpay-nya di-reject (PaymentController::reject()).
     * Idempotent: kredit yang sudah pernah dibalik tak dibalik dua kali.
     *
     * SENGAJA tidak lewat debit() (yang menolak kalau saldo tak cukup):
     * kalau kreditnya sudah keburu dipakai pelanggan sebelum payment
     * sumbernya ditolak, saldo boleh jatuh negatif — itu justru piutang
     * yang harus terlihat, sama seperti "Kurang Setor" di sisi kolektor,
     * bukan error yang harus disembunyikan.
     */
    public function reverseCreditForPayment(Payment $payment): void
    {
        $credit = CustomerBalanceMutation::query()
            ->where('payment_id', $payment->id)
            ->where('type', CustomerBalanceMutationType::CREDIT->value)
            ->first();

        if (! $credit) {
            return;
        }

        $alreadyReversed = CustomerBalanceMutation::query()
            ->where('payment_id', $payment->id)
            ->where('type', CustomerBalanceMutationType::DEBIT->value)
            ->where('note', 'like', 'Pembalikan kredit%')
            ->exists();

        if ($alreadyReversed) {
            return;
        }

        CustomerBalanceMutation::create([
            'customer_id' => $credit->customer_id,
            'type' => CustomerBalanceMutationType::DEBIT->value,
            'amount' => $credit->amount,
            'payment_id' => $payment->id,
            'pop_id' => $credit->pop_id,
            'created_by' => auth()->id(),
            'note' => "Pembalikan kredit — payment {$payment->payment_number} ditolak.",
        ]);
    }
}
