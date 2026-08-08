<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Payment extends Model
{
    use HasPopScope;

    protected $fillable = [
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
        'amount',
        'overpay_amount',
        'received_by',
        'collected_by',
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
     * Get audit logs for this payment.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    /**
     * Generate `payment_number` berikutnya untuk periode (Ym) tanggal bayar
     * yang diberikan. Format: `PAY-{Ym}-{nomor berjalan}`.
     *
     * Pengganti MAX+1 (`orderBy(...)->lockForUpdate()->first()`) yang lama —
     * pola itu tak mengunci apa pun kalau belum ada payment di periode itu
     * (phantom read: dua request pertama bulan itu bisa dapat nomor sama).
     * Di sini yang dikunci adalah baris `payment_number_sequences`, yang
     * SELALU ada setelah dibuat sekali — pola sama `Pop::generateRegistrationNumber()`.
     *
     * Sinkronisasi dengan MAX existing tetap dilakukan (jaga-jaga data lama/
     * import yang penomorannya di luar sequence ini), sama seperti pola POP.
     *
     * Lebar digit menyesuaikan otomatis begitu nomor berjalan lewat 9999
     * (dari %04d ke %05d, dst) — bukan dipatok statis, supaya nomor lama
     * (4 digit) tetap valid dan generator tak jebol di skala tinggi
     * (docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §A-7 #5).
     */
    public static function generatePaymentNumber(string $paymentDate): string
    {
        $periodCode = date('Ym', strtotime($paymentDate));

        return DB::transaction(function () use ($periodCode): string {
            $sequence = PaymentNumberSequence::query()
                ->where('period_code', $periodCode)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = PaymentNumberSequence::create([
                    'period_code' => $periodCode,
                    'current_number' => 0,
                ]);
            }

            $numberStartsAt = strlen('PAY-'.$periodCode.'-') + 1;

            $maxExisting = static::where('payment_number', 'like', "PAY-{$periodCode}-%")
                ->selectRaw("MAX(CAST(SUBSTRING(payment_number, {$numberStartsAt}) AS UNSIGNED)) as max_num")
                ->value('max_num') ?? 0;

            if ($maxExisting >= $sequence->current_number) {
                $sequence->current_number = $maxExisting;
            }

            $sequence->current_number++;
            $sequence->save();

            $width = max(4, strlen((string) $sequence->current_number));

            return sprintf("PAY-%s-%0{$width}d", $periodCode, $sequence->current_number);
        });
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
            'amount',
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
