<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Orkestrasi pencatatan satu payment: kunci invoice, pisah tunai vs lebih
 * bayar, pakai saldo pelanggan (kalau diminta), catat field per metode.
 * Diekstrak dari `PaymentController::recordPayment()` — business logic
 * pindah dari Controller ke Service sesuai aturan proyek.
 */
class PaymentService
{
    public function __construct(
        private readonly CustomerBalanceService $balances,
    ) {}

    /**
     * Simpan satu pembayaran dalam satu transaksi, dengan invoice terkunci.
     *
     * @param  array<string, mixed>  $validated  hasil validate() controller —
     *                                           wajib sudah lolos aturan kondisional
     *                                           per metode (bank_name/account_number
     *                                           untuk transfer, collected_by untuk kolektor).
     */
    public function record(Invoice $invoice, array $validated, ?string $proofPath): Payment
    {
        return DB::transaction(function () use ($invoice, $validated, $proofPath): Payment {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $remaining = Money::of($lockedInvoice->remaining_amount);

            if (Money::isZero($remaining)) {
                throw ValidationException::withMessages([
                    'amount' => 'Tagihan ini sudah lunas.',
                ]);
            }

            $method = PaymentMethod::from($validated['payment_method']);

            $useBalanceAmount = Money::of($validated['use_balance_amount'] ?? 0);

            if (Money::compare($useBalanceAmount, 0) > 0) {
                $customer = $lockedInvoice->customer;

                if (! $customer) {
                    throw ValidationException::withMessages([
                        'use_balance_amount' => 'Tagihan ini tidak terhubung ke pelanggan mana pun — saldo tidak bisa dipakai.',
                    ]);
                }

                $available = $this->balances->balance($customer);

                if (Money::greaterThan($useBalanceAmount, $available)) {
                    throw ValidationException::withMessages([
                        'use_balance_amount' => "Saldo pelanggan tidak cukup. Saldo tersedia: Rp {$available}.",
                    ]);
                }
            }

            // Auto-split: bagian yang menutup tagihan dulu (tunai + saldo
            // digabung), sisanya (kalau ada) jadi overpay_amount — bukan
            // diminta admin pisah sendiri. Dipisah di ranah sen (lihat
            // Money::class) supaya "bayar pas" tak melahirkan lebih bayar
            // Rp0,000001 hantu.
            $totalReceived = Money::add($validated['amount'], $useBalanceAmount);
            $appliedAmount = Money::min($totalReceived, $remaining);
            $overpayAmount = Money::sub($totalReceived, $appliedAmount);

            $payment = Payment::create([
                'payment_number' => Payment::generatePaymentNumber($validated['payment_date']),
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'invoice_id' => $lockedInvoice->id,
                'customer_id' => $lockedInvoice->customer_id,
                'pop_id' => $lockedInvoice->pop_id,
                'payment_date' => $validated['payment_date'],
                'payment_method' => $method->value,
                'bank_name' => $method->requiresBankDetails() ? ($validated['bank_name'] ?? null) : null,
                'account_number' => $method->requiresBankDetails() ? ($validated['account_number'] ?? null) : null,
                'amount' => $appliedAmount,
                'overpay_amount' => Money::isZero($overpayAmount) ? null : $overpayAmount,
                'received_by' => auth()->id(),
                'collected_by' => $method->requiresCollector() ? ($validated['collected_by'] ?? null) : null,
                'proof_file' => $proofPath,
                'payment_status' => PaymentStatus::VALID->value,
                'note' => $validated['note'] ?? null,
            ]);

            if (Money::compare($useBalanceAmount, 0) > 0) {
                try {
                    $this->balances->debit($lockedInvoice->customer, $useBalanceAmount, $payment);
                } catch (InvalidArgumentException $e) {
                    // Saldo berubah di antara pengecekan di atas dan titik ini
                    // (payment lain memakainya lebih dulu) — lockedBalance()
                    // di CustomerBalanceService yang menangkapnya secara
                    // pasti; di sini cukup diteruskan sebagai pesan validasi,
                    // bukan 500. Transaction ini rollback total, tak ada
                    // payment yatim yang tersisa.
                    throw ValidationException::withMessages([
                        'use_balance_amount' => $e->getMessage(),
                    ]);
                }
            }

            if (Money::greaterThan($overpayAmount, 0) && $lockedInvoice->customer) {
                $this->balances->credit($lockedInvoice->customer, $overpayAmount, $payment);
            }

            $lockedInvoice->recalculateFromPayments();

            return $payment;
        });
    }
}
