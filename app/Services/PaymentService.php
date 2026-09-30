<?php

namespace App\Services;

use App\Enums\BalanceMutationSource;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\BankAccount;
use App\Models\CashDeposit;
use App\Models\CollectorDeposit;
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
            // diminta admin pisah sendiri. Rumus sama dipakai revise()
            // (Edit Pembayaran, ADHOC-108) lewat splitAmount() di bawah,
            // supaya dua jalur tak pernah menyimpang.
            [$appliedAmount, $overpayAmount] = $this->splitAmount($validated['amount'], $useBalanceAmount, $remaining);

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
     * Auto-split tunai+saldo terhadap sisa tagihan — SATU rumus dipakai
     * `record()` (payment baru) DAN `revise()` (koreksi payment lama,
     * ADHOC-108). Bagian yang menutup tagihan dulu (tunai + saldo digabung),
     * sisanya (kalau ada) jadi overpay — dipisah di ranah sen (Money) supaya
     * "bayar pas" tak melahirkan lebih bayar Rp0,000001 hantu.
     *
     * @return array{0: float, 1: float} [appliedAmount, overpayAmount]
     */
    private function splitAmount(float $tunai, float $saldoDipakai, float $remaining): array
    {
        $totalReceived = Money::add($tunai, $saldoDipakai);
        $appliedAmount = Money::min($totalReceived, $remaining);
        $overpayAmount = Money::sub($totalReceived, $appliedAmount);

        return [$appliedAmount, $overpayAmount];
    }

    /**
     * Edit Pembayaran PENUH (ADHOC-108) — bukan cuma metode/tanggal seperti
     * jalur lama, nominal & saldo dipakai ikut bisa dikoreksi.
     *
     * Urutan lock: PAYMENT dulu, baru SETORAN (kalau tertaut), baru INVOICE.
     * Payment dikunci paling awal (beda dari rencana awal "setoran → invoice
     * → payment") karena setoran mana yang menautnya baru diketahui SETELAH
     * baris payment dibaca — dan mengunci payment lebih dulu justru
     * mencegah `CollectorDepositService::submit()` (yang juga
     * `lockForUpdate()` baris payment saat menyusun setoran baru) menautkan
     * payment ini ke setoran lain DI TENGAH proses edit ini berlangsung.
     *
     * @param  array<string, mixed>  $validated  hasil validate() controller,
     *                                           sudah lolos aturan kondisional per metode.
     */
    public function revise(Payment $payment, array $validated, ?string $proofPath): Payment
    {
        return DB::transaction(function () use ($payment, $validated, $proofPath): Payment {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $collectorDeposit = $lockedPayment->collector_deposit_id
                ? CollectorDeposit::query()->whereKey($lockedPayment->collector_deposit_id)->lockForUpdate()->firstOrFail()
                : null;

            $cashDeposit = $lockedPayment->cash_deposit_id
                ? CashDeposit::query()->whereKey($lockedPayment->cash_deposit_id)->lockForUpdate()->firstOrFail()
                : null;

            // Guard ulang DI BAWAH LOCK — state payment/setoran bisa berubah
            // antara form Edit dibuka dan disubmit (TOCTOU klasik, alasan
            // sama dengan pengecekan ulang tutup buku/hapus-buku di record()).
            if ($lockedPayment->payment_status === PaymentStatus::DITOLAK) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran yang sudah dikembalikan tidak dapat diedit.',
                ]);
            }

            if ($lockedPayment->isAutoSaldoPayment()) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran dari Saldo Pelanggan dibuat otomatis oleh sistem dan tidak bisa diedit. Koreksi lewat Kembalikan.',
                ]);
            }

            // K3 — ketat: payment LAMA maupun tanggal BARU wajib di bulan
            // berjalan. Payment bulan lalu cuma bisa dikoreksi lewat
            // Kembalikan + catat ulang di periode berjalan.
            $oldPeriod = $lockedPayment->payment_date?->format('Y-m');
            if (BookPeriod::isLocked($oldPeriod)) {
                throw ValidationException::withMessages([
                    'payment_date' => "Pembayaran periode {$oldPeriod} sudah tutup buku — hanya pembayaran bulan berjalan yang bisa diedit. Gunakan Kembalikan lalu catat ulang di periode berjalan.",
                ]);
            }

            $newPeriod = Carbon::parse($validated['payment_date'])->format('Y-m');
            if (BookPeriod::isLocked($newPeriod)) {
                throw ValidationException::withMessages([
                    'payment_date' => "Periode {$newPeriod} sudah tutup buku. Tanggal bayar minimal ".Carbon::parse(BookPeriod::firstOpenDate())->format('d/m/Y').'.',
                ]);
            }

            // K4 — payment yang tertaut setoran BOLEH diedit selama
            // setorannya belum diverifikasi (setoran turunan, otomatis
            // mengikuti angka baru lewat computedAmount()). Begitu
            // terverifikasi, dokumennya sudah disepakati dua pihak — batas
            // sama persis PaymentController::reject().
            if ($collectorDeposit && $collectorDeposit->status->isVerified()) {
                throw ValidationException::withMessages([
                    'payment' => "Pembayaran ini sudah masuk setoran {$collectorDeposit->deposit_number} yang berstatus {$collectorDeposit->status->label()}. Setoran terverifikasi tidak boleh diubah.",
                ]);
            }

            if ($cashDeposit && $cashDeposit->status->isVerified()) {
                throw ValidationException::withMessages([
                    'payment' => "Pembayaran ini sudah masuk setoran kas {$cashDeposit->deposit_number} yang berstatus {$cashDeposit->status->label()}. Setoran terverifikasi tidak boleh diubah.",
                ]);
            }

            $method = PaymentMethod::from($validated['payment_method']);

            // Payment yang tertaut setoran APA PUN (pending sekalipun):
            // metode & kolektor DIBEKUKAN. Mengubahnya membuat payment
            // tersangkut di setoran orang/kas yang salah —
            // CollectorBalanceService/AdminCashBalanceService menyaring
            // dari kolom `payment_method`/`collected_by` ini. Field lain
            // (nominal, saldo, tanggal, catatan, bukti, pengirim) tetap boleh.
            if ($collectorDeposit || $cashDeposit) {
                if ($method->value !== $lockedPayment->payment_method) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Metode pembayaran tidak bisa diubah — pembayaran ini sudah masuk setoran.',
                    ]);
                }

                $newCollectedBy = $method->requiresCollector() ? ($validated['collected_by'] ?? null) : null;
                if ((int) $newCollectedBy !== (int) $lockedPayment->collected_by) {
                    throw ValidationException::withMessages([
                        'collected_by' => 'Kolektor tidak bisa diubah — pembayaran ini sudah masuk setoran.',
                    ]);
                }
            }

            $lockedInvoice = Invoice::query()
                ->whereKey($lockedPayment->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedInvoice->invoice_status, [InvoiceStatus::BATAL, InvoiceStatus::TAK_TERTAGIH], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'Tagihan pembayaran ini sudah batal/dihapus buku (tak tertagih) — pembayaran tidak bisa diedit.',
                ]);
            }

            $bankAccount = null;
            if ($method->requiresBankDetails()) {
                $newBankAccountId = $validated['bank_account_id'] ?? null;

                // Rekening TIDAK diganti — pertahankan snapshot lama apa
                // adanya walau rekeningnya kini sudah nonaktif di master
                // (konsisten dengan komentar `Payment::bankAccount()`: histori
                // tak berubah begitu master diedit/nonaktif belakangan).
                // Rekening BARU wajib lolos `resolveActiveBankAccount()`.
                $bankAccount = ((int) $newBankAccountId === (int) $lockedPayment->bank_account_id)
                    ? $lockedPayment->bankAccount
                    : $this->resolveActiveBankAccount($newBankAccountId);
            }

            $customer = $lockedInvoice->customer;

            $oldOverpayAmount = (float) ($lockedPayment->overpay_amount ?? 0);
            $oldBalanceUsedAmount = (float) $lockedPayment->balance_used_amount;

            $newUseBalanceAmount = Money::of($validated['use_balance_amount'] ?? 0);

            if (Money::greaterThan($newUseBalanceAmount, 0)) {
                if (! $customer) {
                    throw ValidationException::withMessages([
                        'use_balance_amount' => 'Tagihan ini tidak terhubung ke pelanggan mana pun — saldo tidak bisa dipakai.',
                    ]);
                }

                // Saldo tersedia untuk NILAI BARU = saldo berjalan SEKARANG
                // (sudah termasuk efek payment lama) DITAMBAH saldo lama yang
                // dulu dipakai payment ini (dikembalikan dulu secara nalar
                // sebelum dipakai ulang dengan angka baru). Dikunci
                // (`lockedBalance`) supaya dua edit simultan pada pelanggan
                // yang sama tak lolos berbarengan.
                $available = Money::add($this->balances->lockedBalance($customer), $oldBalanceUsedAmount);

                if (Money::greaterThan($newUseBalanceAmount, $available)) {
                    throw ValidationException::withMessages([
                        'use_balance_amount' => "Saldo pelanggan tidak cukup. Saldo tersedia: Rp {$available}.",
                    ]);
                }
            }

            // Sisa tagihan TANPA payment ini sendiri — kalau payment lama
            // masih ikut dijumlah, "sisa" akan salah dobel-hitung nominal
            // lama payment yang sedang dikoreksi ini.
            $remainingWithoutThisPayment = Money::atLeastZero(Money::sub(
                $lockedInvoice->total_amount,
                Money::of(
                    $lockedInvoice->payments()
                        ->where('payment_status', PaymentStatus::VALID->value)
                        ->where('id', '!=', $lockedPayment->id)
                        ->sum('amount')
                )
            ));

            [$appliedAmount, $newOverpayAmount] = $this->splitAmount(
                (float) $validated['amount'],
                $newUseBalanceAmount,
                $remainingWithoutThisPayment,
            );

            if (Money::compare($appliedAmount, 0) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran tidak valid — pastikan nominal lebih besar dari nol.',
                ]);
            }

            $lockedPayment->update([
                'payment_date' => $validated['payment_date'],
                'payment_method' => $method->value,
                'bank_account_id' => $bankAccount?->id,
                'bank_name' => $bankAccount?->bank_name,
                'account_number' => $bankAccount?->account_number,
                'sender_name' => $method->requiresSenderName() ? $this->nullIfBlank($validated['sender_name'] ?? null) : null,
                'collected_by' => $method->requiresCollector() ? ($validated['collected_by'] ?? null) : null,
                'amount' => $appliedAmount,
                'balance_used_amount' => $newUseBalanceAmount,
                'overpay_amount' => Money::isZero($newOverpayAmount) ? null : $newOverpayAmount,
                'proof_file' => $proofPath ?? $lockedPayment->proof_file,
                'note' => $validated['note'] ?? null,
            ]);

            // Koreksi saldo — SATU baris delta, bukan balik-lalu-terapkan-ulang
            // (lihat CustomerBalanceService::applyCorrection()). Dilewati
            // kalau invoice tak punya customer (mustahil dalam praktik kalau
            // use_balance_amount/overpay lama > 0, tapi dijaga eksplisit).
            if ($customer) {
                $this->balances->applyCorrection(
                    $lockedPayment,
                    $oldOverpayAmount,
                    $oldBalanceUsedAmount,
                    Money::isZero($newOverpayAmount) ? 0.0 : $newOverpayAmount,
                    $newUseBalanceAmount,
                );
            }

            $lockedInvoice->recalculateFromPayments();

            // K2 — alasan koreksi OPSIONAL, dicatat sebagai entri audit
            // terpisah (bukan kolom `payments`, jadi tak ikut diff otomatis).
            if (! empty($validated['reason'])) {
                $lockedPayment->logCorrectionReason($validated['reason']);
            }

            return $lockedPayment->fresh();
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
