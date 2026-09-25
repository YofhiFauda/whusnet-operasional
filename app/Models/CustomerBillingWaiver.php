<?php

namespace App\Models;

use App\Enums\BillingWaiverSource;
use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembebasan tagihan langganan per periode (ADHOC-87) — "Request Putus
 * Langganan" atau "Cuti Berlangganan" mengganti sebagian fungsi prorate:
 * pemakaian yang tidak terjadi dikeluarkan per bulan penuh oleh admin,
 * bukan dihitung harian (lihat CustomerTerminationService, ADHOC-69).
 *
 * Mencabut pembebasan = HAPUS baris (riwayat ada di audit log via
 * RecordsAuditLogs) — tidak ada kolom `revoked_*`. Invoice yang sudah
 * `batal` TIDAK dihidupkan lagi saat baris ini dihapus; kalau perlu ditagih
 * ulang, jalankan `billing:generate-monthly-invoices --period=…`.
 */
#[Fillable([
    'customer_id',
    'billing_period',
    'source',
    'reason',
    'invoice_id',
    'created_by',
])]
class CustomerBillingWaiver extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Pembebasan Tagihan';

    /**
     * Create & delete SENGAJA dicatat (bukan default `updated`/`deleted`) —
     * baris ini tidak pernah di-update, cuma dibuat lalu (opsional) dihapus.
     *
     * @var list<string>
     */
    protected array $auditEvents = ['created', 'deleted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => BillingWaiverSource::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Dipakai `InvoiceObserver::rejectSecondSubscriptionInvoice()` dan
     * `GenerateMonthlyInvoicesCommand` — satu method bersama supaya "periode
     * ini dibebaskan" tidak pernah menyimpang antara dua jalur penerbit
     * invoice (pola sama `Invoice::hasActiveSubscriptionInvoiceForPeriod()`).
     */
    public static function existsFor(int $customerId, string $billingPeriod): bool
    {
        return static::where('customer_id', $customerId)
            ->where('billing_period', $billingPeriod)
            ->exists();
    }
}
