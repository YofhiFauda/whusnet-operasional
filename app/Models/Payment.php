<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPeriodType;
use App\Enums\PaymentStatus;
use App\Services\NumberSequenceService;
use App\Support\BookPeriod;
use App\Support\Money;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payment extends Model
{
    use HasPopScope;

    protected $fillable = [
        'payment_number',
        'idempotency_key',
        'old_payment_id',
        'old_transaction_id',
        'old_request_id',
        'billing_period',
        'received_by_old',
        'deposited_by_old',
        'invoice_id',
        'payment_batch_id',
        'collector_deposit_id',
        'cash_deposit_id',
        'customer_id',
        'pop_id',
        'payment_date',
        'collected_date',
        'payment_method',
        'bank_account_id',
        'bank_name',
        'account_number',
        'sender_name',
        'amount',
        'balance_used_amount',
        'overpay_amount',
        'received_by',
        'collected_by',
        'collected_by_role',
        'proof_file',
        'payment_status',
        'reject_reason',
        'rejected_at',
        'rejected_by',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'collected_date' => 'date',
            'amount' => 'decimal:2',
            'balance_used_amount' => 'decimal:2',
            'overpay_amount' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'rejected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Payment $payment): void {
            $payment->writeAuditLog('create', null, $payment->auditPayload());
        });

        static::updated(function (Payment $payment): void {
            $changed = array_keys($payment->getChanges());
            $changed = array_values(array_diff($changed, ['updated_at']));

            if ($changed === []) {
                return;
            }

            $action = $payment->wasChanged('payment_status') && $payment->payment_status === PaymentStatus::DITOLAK
                ? 'cancel'
                : 'update';

            $oldValues = [];
            $newValues = [];

            foreach ($changed as $field) {
                $oldValues[$field] = $payment->getOriginal($field);
                $newValues[$field] = $payment->{$field};
            }

            $payment->writeAuditLog($action, $oldValues, $newValues);
        });

        static::deleted(function (Payment $payment): void {
            $payment->writeAuditLog('delete', $payment->auditPayload(), null);
        });
    }

    /**
     * Get the invoice associated with this payment.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Batch kolektor asal payment ini (null untuk jalur single-payment).
     *
     * @return BelongsTo<PaymentBatch, $this>
     */
    public function paymentBatch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class);
    }

    /**
     * Setoran tempat payment ini sudah ikut diserahkan ke admin. `null` =
     * uangnya masih di tangan kolektor — itulah definisi saldo kolektor
     * (docs/plan/kolektor/analisa-alur-kolektor-2.0.md §11.1).
     *
     * @return BelongsTo<CollectorDeposit, $this>
     */
    public function collectorDeposit(): BelongsTo
    {
        return $this->belongsTo(CollectorDeposit::class, 'collector_deposit_id');
    }

    /**
     * Setoran kas admin yang menyerap pembayaran ini.
     *
     * Hanya terisi untuk pembayaran MANUAL di kantor (`collected_by` null).
     * Pembayaran yang ditagih kolektor masuk kas lewat setoran kolektornya,
     * bukan lewat kolom ini — menautkan keduanya membuat uang yang sama
     * terhitung dua kali.
     *
     * @return BelongsTo<CashDeposit, $this>
     */
    public function cashDeposit(): BelongsTo
    {
        return $this->belongsTo(CashDeposit::class, 'cash_deposit_id');
    }

    /**
     * Arsip kwitansi yang tercocokkan ke pembayaran ini. Sumbu DOKUMEN —
     * ketiadaannya tidak berpengaruh apa pun pada status uang
     * (docs/kolektor/business-logic.md § Kwitansi).
     *
     * @return HasMany<PaymentReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class);
    }

    /**
     * Get the customer associated with this payment.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the POP associated with this payment.
     *
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * Get the user who received this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Kolektor yang FAKTANYA menagih payment ini — null untuk jalur
     * non-kolektor (bayar langsung/transfer). TIDAK selalu sama dengan
     * `customer->collector_id` (docs/plan/analisa-billing-tagihan-
     * pembayaran-kolektor.md §B-3 — jangan disalin buta).
     *
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * Rekening tujuan dari Master Rekening Bank (ADHOC-95). Null untuk
     * non-Transfer dan untuk payment Transfer sebelum master ini ada.
     * Tampilan riwayat tetap membaca SNAPSHOT `bank_name`/`account_number`,
     * bukan relasi ini — rekening di master boleh diedit belakangan.
     *
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Baris ledger saldo pelanggan yang menyebut payment ini — sebagai
     * SUMBER kredit (overpay) atau sebagai KONSUMEN debit (pemakaian
     * saldo). Dua peran, satu kolom `payment_id`; dibedakan lewat `type`
     * kalau perlu difilter salah satunya saja.
     *
     * @return HasMany<CustomerBalanceMutation, $this>
     */
    public function balanceMutations(): HasMany
    {
        return $this->hasMany(CustomerBalanceMutation::class);
    }

    /**
     * Uang FISIK yang benar-benar diterima/dipegang dari payment ini —
     * ADHOC-92 (G4), koreksi susulan 2026-09-24.
     *
     * `amount` (porsi diterapkan ke invoice) dikurangi `balance_used_amount`
     * (porsi dari Saldo Pelanggan, BUKAN uang fisik) ditambah `overpay_amount`
     * (uang lebih yang FISIK tetap, cuma disimpan di kolom terpisah dari
     * `amount` — lihat PaymentService::record()). Dipakai di SEMUA tempat
     * yang menghitung kewajiban setor/kas fisik (AdminCashBalanceService,
     * CollectorBalanceService, CollectorDeposit::computedAmount(),
     * CashDepositService) — sebelumnya masing-masing menjumlah `amount`
     * mentah sendiri-sendiri, ada yang lupa `overpay_amount` (kelebihan tunai
     * ikut tercatat sistem tapi hilang dari kewajiban setor) dan ada yang
     * lupa `balance_used_amount` (saldo yang dipakai dihitung dobel sebagai
     * uang fisik).
     */
    public function physicalAmount(): float
    {
        return Money::add(
            Money::sub($this->amount, $this->balance_used_amount),
            (float) ($this->overpay_amount ?? 0)
        );
    }

    /**
     * Posisi cicilan payment ini pada invoice-nya ("Cicilan Ke-N") + apakah
     * payment ini yang melunasi tagihan. Dipakai konsisten di
     * payments/show, payments/receipt, invoices/show — satu sumber
     * kebenaran supaya nomor cicilan tak menyimpang antar halaman.
     *
     * Cuma pembayaran VALID yang dihitung — pembayaran ditolak tak boleh
     * menggeser nomor cicilan (kalau tidak, riwayat jadi bolong begitu ada
     * yang ditolak).
     *
     * @return array{number: int, settles: bool}|null null kalau payment ini
     *                                                sendiri bukan VALID atau tak terhubung invoice.
     */
    public function installmentContext(): ?array
    {
        if ($this->payment_status !== PaymentStatus::VALID || ! $this->invoice_id) {
            return null;
        }

        $validPayments = static::query()
            ->where('invoice_id', $this->invoice_id)
            ->where('payment_status', PaymentStatus::VALID->value)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get(['id', 'amount']);

        $invoiceTotal = round((float) ($this->invoice?->total_amount ?? 0), 2);
        $runningPaid = 0.0;

        foreach ($validPayments as $index => $validPayment) {
            $runningPaid = round($runningPaid + (float) $validPayment->amount, 2);

            if ($validPayment->id === $this->id) {
                return [
                    'number' => $index + 1,
                    'settles' => $runningPaid >= $invoiceTotal,
                ];
            }
        }

        return null;
    }

    /**
     * Klasifikasi payment ini terhadap periode tagihannya — lihat
     * `PaymentPeriodType` untuk urutan prioritas & alasannya.
     *
     * SENGAJA tidak memakai `Invoice::isPiutang()`: method itu bersandar ke
     * `invoice_status` SEKARANG (yang berubah jadi `lunas` begitu payment ini
     * tercatat) dan ke `now()` (yang terus berjalan) — dipakai di sini, label
     * sebuah payment lama diam-diam berubah besok. Klasifikasi payment harus
     * BEKU sejak dia diterima: dibandingkan ke bulan payment ini SENDIRI
     * (`collected_date` ?: `payment_date`), bukan ke bulan berjalan.
     */
    public function periodType(): PaymentPeriodType
    {
        if ($this->overpay_amount !== null && (float) $this->overpay_amount > 0.0) {
            return PaymentPeriodType::LEBIH_BAYAR;
        }

        $billingPeriod = $this->invoice?->billing_period;
        $referenceMonth = ($this->collected_date ?? $this->payment_date)?->format('Y-m');

        if ($billingPeriod === null || $referenceMonth === null) {
            return PaymentPeriodType::BULANAN;
        }

        return $billingPeriod < $referenceMonth
            ? PaymentPeriodType::PIUTANG
            : PaymentPeriodType::BULANAN;
    }

    /**
     * Klasifikasi MAJEMUK payment ini — ADHOC-84 §8.1. Beda dari
     * `periodType()` (3 label saling lepas, prioritas overpay > piutang >
     * bulanan, dipakai badge tunggal yang sudah ada): method ini dipakai
     * lima permukaan laporan/audit yang butuh label bertumpuk sekaligus,
     * mis. payment yang mencicil piutang lama = [Piutang, Cicilan].
     *
     * Urutan label TIDAK menyatakan prioritas — semuanya independen:
     *   - Base, SELALU tepat satu: BULANAN atau PIUTANG (posisi
     *     `invoice->billing_period` terhadap bulan payment ini SENDIRI,
     *     sama seperti `periodType()` — BUKAN `invoice_status`/`now()` yang
     *     berubah-ubah, supaya label payment lama tak diam-diam berganti).
     *   - Tambahan, PALING BANYAK satu (keduanya saling tiadakan — payment
     *     tak mungkin under- dan over-pay sekaligus):
     *       - LEBIH_BAYAR kalau `overpay_amount > 0`.
     *       - CICILAN kalau `installmentContext()` bilang payment ini BUKAN
     *         yang melunasi invoice-nya (`settles === false`).
     *
     * @return list<PaymentPeriodType>
     */
    public function classification(): array
    {
        $billingPeriod = $this->invoice?->billing_period;
        $referenceMonth = ($this->collected_date ?? $this->payment_date)?->format('Y-m');

        $labels = [
            ($billingPeriod !== null && $referenceMonth !== null && $billingPeriod < $referenceMonth)
                ? PaymentPeriodType::PIUTANG
                : PaymentPeriodType::BULANAN,
        ];

        if ($this->overpay_amount !== null && (float) $this->overpay_amount > 0.0) {
            $labels[] = PaymentPeriodType::LEBIH_BAYAR;
        } elseif (($context = $this->installmentContext()) !== null && ! $context['settles']) {
            $labels[] = PaymentPeriodType::CICILAN;
        }

        return $labels;
    }

    /**
     * Get audit logs for this payment.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    /**
     * Payment `saldo` dibuat SISTEM lewat
     * `CustomerBalanceService::applyToOpenInvoices()`, bukan input manusia.
     * Dicek dari `idempotency_key` sebagai jaring pengaman tambahan kalau
     * suatu saat sumber auto-pay lain memakai metode berbeda dari SALDO —
     * lihat docs/plan/billing/rancangan-edit-pembayaran-penuh.md F5 (K5).
     */
    public function isAutoSaldoPayment(): bool
    {
        return $this->payment_method === PaymentMethod::SALDO->value
            || str_starts_with((string) $this->idempotency_key, 'auto-saldo:');
    }

    /**
     * SATU sumber kebenaran "boleh diedit lewat Edit Pembayaran?" — dipakai
     * tombol Edit (view), `PaymentController::edit()`, dan
     * `PaymentService::revise()` (guard ulang di bawah lock). Jangan tulis
     * daftar syarat sendiri di tempat lain (pola `TaskStatus::acceptsReport()`).
     *
     * SENGAJA tidak mengecek status invoice (BATAL/TAK_TERTAGIH) — itu butuh
     * baris invoice TERKUNCI supaya tak berubah di antara pengecekan dan
     * penyimpanan, jadi dicek ulang sendiri di `PaymentService::revise()`.
     *
     * @return string|null pesan Indonesia kalau terkunci, `null` kalau boleh diedit.
     */
    public function editBlockedReason(): ?string
    {
        if ($this->payment_status === PaymentStatus::DITOLAK) {
            return 'Pembayaran yang sudah dikembalikan tidak dapat diedit.';
        }

        if ($this->isAutoSaldoPayment()) {
            return 'Pembayaran dari Saldo Pelanggan dibuat otomatis oleh sistem dan tidak bisa diedit. Koreksi lewat Kembalikan.';
        }

        // K3 — ketat: hanya payment bulan berjalan yang boleh diedit, sama
        // persis definisi tutup buku (BookPeriod). Payment bulan lalu hanya
        // bisa dikoreksi lewat Kembalikan + catat ulang.
        $period = $this->payment_date?->format('Y-m');

        if (BookPeriod::isLocked($period)) {
            return "Pembayaran periode {$period} sudah tutup buku — hanya pembayaran bulan berjalan yang bisa diedit. Gunakan Kembalikan lalu catat ulang di periode berjalan.";
        }

        // K4 — payment yang masuk setoran BOLEH diedit selama setorannya
        // belum diverifikasi (setoran turunan, otomatis mengikuti angka
        // baru). Begitu terverifikasi, dokumennya sudah disepakati dua pihak
        // — sama persis batas di PaymentController::reject().
        if ($this->relationLoaded('collectorDeposit') ? $this->collectorDeposit : $this->collectorDeposit()->first()) {
            $deposit = $this->collectorDeposit;
            if ($deposit->status->isVerified()) {
                return "Pembayaran ini sudah masuk setoran {$deposit->deposit_number} yang berstatus {$deposit->status->label()}. Setoran terverifikasi tidak boleh diubah.";
            }
        }

        if ($this->relationLoaded('cashDeposit') ? $this->cashDeposit : $this->cashDeposit()->first()) {
            $cashDeposit = $this->cashDeposit;
            if ($cashDeposit->status->isVerified()) {
                return "Pembayaran ini sudah masuk setoran kas {$cashDeposit->deposit_number} yang berstatus {$cashDeposit->status->label()}. Setoran terverifikasi tidak boleh diubah.";
            }
        }

        return null;
    }

    public function isEditable(): bool
    {
        return $this->editBlockedReason() === null;
    }

    /**
     * Catat alasan koreksi (K2, opsional) sebagai entri audit TERPISAH dari
     * baris 'update' otomatis — `reason` bukan kolom `payments`, jadi tak
     * ikut diff `static::updated()` di atas. Dipanggil
     * `PaymentService::revise()` SETELAH `$payment->update()` supaya urut
     * kronologis dengan baris 'update' yang sudah tercatat lebih dulu.
     */
    public function logCorrectionReason(string $reason): void
    {
        $this->writeAuditLog('koreksi', null, ['alasan' => $reason]);
    }

    /**
     * Generate `payment_number` untuk payment baru pada `$invoice`. Format:
     * `PAY-{invoice_number}` untuk lunas sekali bayar, atau
     * `PAY-{invoice_number}-{NN}` untuk cicilan (keputusan user 2026-10-02,
     * revisi BUG 13/2026-10-01 — versi lama nempelin `-01` bahkan ke
     * pembayaran lunas sekali bayar, bikin laporan susah bedain
     * lunas-langsung vs cicilan dari nomornya saja).
     * `invoice_number`-nya ditempel APA ADANYA (bukan dipetakan ulang ke
     * prefix) — Payment dan Invoice yang sama langsung kelihatan berpasangan
     * dari bentuk nomornya.
     *
     * `{NN}` CUMA muncul kalau pembayaran ini bagian dari cicilan — yaitu
     * bukan pembayaran pertama pada invoice ini ($ordinal > 1), ATAU
     * pembayaran pertama itu sendiri tidak langsung melunasi sisa tagihan
     * ($appliedAmount < remaining_amount saat itu). Begitu sebuah invoice
     * "ketahuan" cicilan (ordinal > 1), SEMUA baris di invoice itu termasuk
     * yang nanti melunasi sisanya tetap kebagian `-NN` — nomor pertama yang
     * sudah dicetak tanpa suffix TIDAK diubah lagi (beku begitu dicetak).
     *
     * `{NN}` = urutan pembayaran ke berapa pada invoice ini (2 digit), dari
     * counter NumberSequenceService — SEMUA pembayaran dihitung apa pun
     * statusnya (termasuk yang nanti ditolak atau di-hard delete), angka
     * TIDAK PERNAH dipakai ulang. Ini SENGAJA beda basis dari badge "Cicilan Ke-N" di UI
     * (`installmentContext()`, cuma menghitung yang VALID): nomor di sini
     * identitas HISTORIS yang beku begitu dicetak, badge UI itu status
     * TERKINI yang boleh bergeser kalau ada payment lama yang ditolak
     * belakangan. Dua hal berbeda, jangan disamakan paksa.
     *
     * `$appliedAmount` wajib bagian yang menutup tagihan SAJA (bukan
     * `overpay_amount`) — pemanggil sudah memisahkannya lewat
     * `splitAmount()`/`Money::min()` sebelum ke sini.
     *
     * WAJIB dipanggil di dalam transaksi yang sama dengan `Payment::create()`
     * (semua pemanggil sudah begitu) supaya kenaikan counter ikut rollback.
     */
    public static function generatePaymentNumber(Invoice $invoice, mixed $appliedAmount): string
    {
        $ordinal = app(NumberSequenceService::class)->paymentOrdinal($invoice);

        $lunasSekaliBayar = $ordinal === 1
            && Money::compare($appliedAmount, $invoice->remaining_amount) >= 0;

        if ($lunasSekaliBayar) {
            return "PAY-{$invoice->invoice_number}";
        }

        return sprintf('PAY-%s-%02d', $invoice->invoice_number, $ordinal);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(): array
    {
        return $this->only([
            'payment_number',
            'old_payment_id',
            'old_transaction_id',
            'old_request_id',
            'billing_period',
            'received_by_old',
            'deposited_by_old',
            'invoice_id',
            'payment_batch_id',
            'customer_id',
            'pop_id',
            'payment_date',
            'collected_date',
            'payment_method',
            'bank_account_id',
            'bank_name',
            'account_number',
            'sender_name',
            'amount',
            'balance_used_amount',
            'overpay_amount',
            'received_by',
            'collected_by',
            'proof_file',
            'payment_status',
            'reject_reason',
            'rejected_at',
            'rejected_by',
            'note',
        ]);
    }

    /**
     * Get the user who rejected/voided this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function writeAuditLog(string $action, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Pembayaran',
            'action' => $action,
            'auditable_type' => self::class,
            'auditable_id' => $this->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
