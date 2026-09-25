<?php

namespace App\Services;

use App\Enums\BalanceMutationSource;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\BookPeriod;
use App\Support\Money;
use Carbon\Carbon;
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
     *                                           per metode (bank_account_id untuk
     *                                           transfer, collected_by untuk kolektor).
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

            // Dicek ulang di sini (dengan lock) selain di controller: hapus buku
            // bisa terjadi antara form dibuka dan disubmit.
            if ($lockedInvoice->invoice_status === InvoiceStatus::TAK_TERTAGIH) {
                throw ValidationException::withMessages([
                    'amount' => 'Tagihan ini sudah dihapus buku (tak tertagih). Batalkan hapus buku dulu sebelum mencatat pembayaran.',
                ]);
            }

            // Tutup buku: tanggal bayar di bulan terkunci akan menggeser laporan
            // bulan yang sudah final. Uang yang baru diinput sekarang dicatat di
            // periode berjalan — termasuk pelunasan piutang bulan lalu.
            $paymentPeriod = Carbon::parse($validated['payment_date'])->format('Y-m');
            if (BookPeriod::isLocked($paymentPeriod)) {
                throw ValidationException::withMessages([
                    'payment_date' => "Periode {$paymentPeriod} sudah tutup buku. Tanggal bayar minimal ".Carbon::parse(BookPeriod::firstOpenDate())->format('d/m/Y').'.',
                ]);
            }

            $method = PaymentMethod::from($validated['payment_method']);

            $bankAccount = $method->requiresBankDetails()
                ? $this->resolveActiveBankAccount($validated['bank_account_id'] ?? null)
                : null;

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
                // Snapshot dari master, bukan input request — edit/nonaktif
                // rekening di master belakangan tidak mengubah riwayat ini.
                'bank_account_id' => $bankAccount?->id,
                'bank_name' => $bankAccount?->bank_name,
                'account_number' => $bankAccount?->account_number,
                'sender_name' => $method->requiresSenderName() ? $this->nullIfBlank($validated['sender_name'] ?? null) : null,
                'amount' => $appliedAmount,
                'balance_used_amount' => $useBalanceAmount,
                'overpay_amount' => Money::isZero($overpayAmount) ? null : $overpayAmount,
                'received_by' => auth()->id(),
                'collected_by' => $method->requiresCollector() ? ($validated['collected_by'] ?? null) : null,
                'proof_file' => $proofPath,
                'payment_status' => PaymentStatus::VALID->value,
                'note' => $validated['note'] ?? null,
            ]);

            if (Money::compare($useBalanceAmount, 0) > 0) {
                try {
                    $this->balances->debit(
                        $lockedInvoice->customer,
                        $useBalanceAmount,
                        $payment,
                        source: BalanceMutationSource::PAKAI_MANUAL,
                    );
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
                // KEPUTUSAN "input awal" (ADHOC-92): overpay pada invoice AWAL
                // = pelanggan sengaja titip saldo di muka (studi kasus 550k di
                // tagihan 100k). Overpay pada jenis invoice lain tetap
                // kelebihan bayar biasa — dua sumber sama-sama jadi saldo AKTIF,
                // bedanya cuma label untuk laporan/riwayat.
                $source = $lockedInvoice->invoice_type === InvoiceType::AWAL
                    ? BalanceMutationSource::BAYAR_DI_MUKA
                    : BalanceMutationSource::KELEBIHAN_BAYAR;

                $this->balances->credit($lockedInvoice->customer, $overpayAmount, $payment, source: $source);
            }

            $lockedInvoice->recalculateFromPayments();

            return $payment;
        });
    }

    /**
     * Rekening tujuan Transfer — wajib ada di master DAN masih aktif.
     *
     * Dicek di sini, bukan cuma rule `exists` di controller: rekening
     * nonaktif tetap "exists", dan form yang dibuka sebelum rekening
     * dinonaktifkan masih bisa mengirim id-nya. Pesannya harus jelas
     * kenapa ditolak, bukan "pilihan tidak valid".
     */
    private function resolveActiveBankAccount(mixed $bankAccountId): BankAccount
    {
        $bankAccount = $bankAccountId ? BankAccount::find($bankAccountId) : null;

        if (! $bankAccount) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Pilih rekening tujuan untuk metode Transfer.',
            ]);
        }

        if (! $bankAccount->is_active) {
            throw ValidationException::withMessages([
                'bank_account_id' => "Rekening {$bankAccount->bank_name} {$bankAccount->account_number} sudah tidak aktif — pilih rekening lain.",
            ]);
        }

        return $bankAccount;
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
