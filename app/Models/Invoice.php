<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Models\Concerns\RecordsAuditLogs;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasPopScope, RecordsAuditLogs;

    /**
     * Jenis tagihan yang mewakili langganan satu periode — hanya boleh ada
     * satu per pelanggan per periode (AWAL atau BULANAN, tidak pernah
     * keduanya). REAKTIVASI sengaja tidak masuk: pelanggan yang disuspend
     * lalu aktif lagi di bulan yang sama boleh punya record tambahan.
     *
     * Dipakai bareng InvoiceObserver (guard insert) & GenerateMonthlyInvoicesCommand
     * (skip generate) — satu sumber, supaya "invoice BATAL tak dihitung"
     * tak menyimpang antara dua tempat (docs/plan/analisa-billing-tagihan-
     * pembayaran-kolektor.md §A-7 #3).
     *
     * @var list<string>
     */
    public const SUBSCRIPTION_TYPES = [
        InvoiceType::AWAL->value,
        InvoiceType::BULANAN->value,
    ];

    protected string $auditModule = 'Tagihan';

    protected array $auditEvents = ['updated', 'deleted'];

    protected $fillable = [
        'invoice_number',
        'invoice_type',
        'old_invoice_id',
        'old_cost_id',
        'old_request_id',
        'customer_id',
        'pop_id',
        'customer_service_id',
        'internet_package_id',
        'billing_period',
        'issue_date',
        'due_date',
        'subtotal',
        'discount',
        'ppn',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'invoice_status',
        'created_by',
        'prorate_amount',
        'extra_cable_fee',
        'other_fee',
        'extra_installation_fee',
        'extra_pole_fee',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_type' => InvoiceType::class,
            'invoice_status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'ppn' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'other_fee' => 'decimal:2',
        ];
    }

    /**
     * Get the customer associated with this invoice.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the POP associated with this invoice.
     *
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * Get the customer service associated with this invoice.
     *
     * @return BelongsTo<CustomerService, $this>
     */
    public function customerService(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class);
    }

    /**
     * Get the internet package associated with this invoice.
     *
     * @return BelongsTo<InternetPackage, $this>
     */
    public function internetPackage(): BelongsTo
    {
        return $this->belongsTo(InternetPackage::class);
    }

    /**
     * Get the user who created this invoice.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the payments recorded for this invoice.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Satu sumber kebenaran untuk `paid_amount` / `remaining_amount` /
     * `invoice_status`, dipakai semua jalur (single payment, batch, void).
     * Sebelumnya logika ini ter-duplikasi di PaymentController::store dan
     * bulkStore — dua salinan yang gampang menyimpang (docs/plan/analisa-
     * billing-tagihan-pembayaran-kolektor.md §A-5, §A-7 #1).
     *
     * Keputusan eksplisit: hanya payment berstatus VALID yang dijumlah.
     * Kolom `payments.payment_status` default `pending` di migration, tapi
     * seluruh jalur insert saat ini (PaymentController::store, bulkStore)
     * selalu menulis VALID — jadi PENDING/DITOLAK sengaja diabaikan di sini
     * supaya voided/rejected payment otomatis tidak lagi dihitung begitu
     * status-nya diubah.
     *
     * Invoice BATAL sengaja dilewati — statusnya tidak boleh berubah hanya
     * karena ada payment yang menyimpang (mis. sisa payment dari sebelum
     * invoice dibatalkan).
     *
     * Pemanggil bertanggung jawab mengunci baris (`lockForUpdate()`) di
     * dalam transaksi kalau konsistensi di bawah beban konkuren dibutuhkan —
     * method ini sendiri tidak mengunci apa pun.
     */
    public function recalculateFromPayments(): void
    {
        if ($this->invoice_status === InvoiceStatus::BATAL) {
            return;
        }

        $paidAmount = round(
            (float) $this->payments()->where('payment_status', PaymentStatus::VALID->value)->sum('amount'),
            2
        );

        $remainingAmount = max(0, round((float) $this->total_amount - $paidAmount, 2));

        $status = match (true) {
            $paidAmount <= 0 => InvoiceStatus::BELUM_DIBAYAR,
            $remainingAmount <= 0 => InvoiceStatus::LUNAS,
            default => InvoiceStatus::SEBAGIAN,
        };

        $this->update([
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'invoice_status' => $status->value,
        ]);
    }

    /**
     * Cek apakah pelanggan sudah punya tagihan langganan (AWAL/BULANAN) untuk
     * periode ini. Tagihan BATAL tidak dihitung — kalau dihitung, tagihan
     * yang sudah dibatalkan akan memblokir penerbitan penggantinya.
     *
     * Satu query dipakai InvoiceObserver::rejectSecondSubscriptionInvoice()
     * dan GenerateMonthlyInvoicesCommand — lihat SUBSCRIPTION_TYPES di atas.
     */
    public static function hasActiveSubscriptionInvoiceForPeriod(int $customerId, string $billingPeriod): bool
    {
        return static::where('customer_id', $customerId)
            ->where('billing_period', $billingPeriod)
            ->whereIn('invoice_type', self::SUBSCRIPTION_TYPES)
            ->where('invoice_status', '!=', InvoiceStatus::BATAL->value)
            ->exists();
    }
}
