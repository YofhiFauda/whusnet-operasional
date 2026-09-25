<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\ManualInvoiceCategory;
use App\Enums\PaymentStatus;
use App\Events\InvoiceStatusUpdated;
use App\Models\Concerns\RecordsAuditLogs;
use App\Support\Money;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

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
        'manual_category',
        'manual_subtype_name',
        'description',
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
        'written_off_at',
        'written_off_by',
        'written_off_amount',
        'write_off_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_type' => InvoiceType::class,
            'manual_category' => ManualInvoiceCategory::class,
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
            'written_off_at' => 'datetime',
            'written_off_amount' => 'decimal:2',
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
     * Baris pembebasan (ADHOC-87) yang membatalkan invoice ini, kalau ada —
     * dipakai Detail Tagihan untuk menampilkan alasan & siapa yang membatalkan
     * invoice `batal` lewat mekanisme ini (bukan write-off/hard cancel lain).
     *
     * @return HasOne<CustomerBillingWaiver, $this>
     */
    public function billingWaiver(): HasOne
    {
        return $this->hasOne(CustomerBillingWaiver::class);
    }

    /**
     * Rincian baris tagihan per kategori pendapatan (ADHOC-60).
     *
     * Jumlah `amount` seluruh baris SAMA DENGAN `subtotal` — bukan
     * `total_amount`, karena diskon & PPN berlaku di level tagihan. Tagihan
     * lama yang terbit sebelum ADHOC-60 bisa tidak punya baris sama sekali;
     * `invoices/show.blade.php` jatuh balik ke kolom biaya lama untuk kasus
     * itu, jadi jangan asumsikan relasi ini selalu terisi.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
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
        // TAK_TERTAGIH dijaga seperti BATAL: status ini hanya boleh keluar lewat
        // InvoiceWriteOffService::reverse(). Tanpa guard, payment susulan (mis.
        // void/reject) akan diam-diam menghidupkan lagi piutang yang sudah
        // dihapus buku dan menggeser Laporan Bulanan yang sudah ditutup.
        if (in_array($this->invoice_status, [InvoiceStatus::BATAL, InvoiceStatus::TAK_TERTAGIH], true)) {
            return;
        }

        // SUM dikerjakan DB (decimal, eksak); Money menjaga hasilnya tetap
        // eksak setelah masuk PHP. Yang dipertaruhkan bukan tampilan angkanya
        // melainkan CABANGNYA: sisa −0,0000008 hasil galat float membuat
        // tagihan yang sudah lunas tetap berstatus Sebagian.
        $paidAmount = Money::of(
            $this->payments()->where('payment_status', PaymentStatus::VALID->value)->sum('amount')
        );

        $remainingAmount = Money::atLeastZero(Money::sub($this->total_amount, $paidAmount));

        $status = match (true) {
            Money::isZero($paidAmount) => InvoiceStatus::BELUM_DIBAYAR,
            Money::isZero($remainingAmount) => InvoiceStatus::LUNAS,
            default => InvoiceStatus::SEBAGIAN,
        };

        $this->update([
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'invoice_status' => $status->value,
        ]);

        // Satu titik broadcast buat semua jalur payment (single, bulk,
        // batch kolektor, reject) — lihat InvoiceStatusUpdated.
        InvoiceStatusUpdated::dispatch($this);
    }

    /**
     * Status yang masih punya sisa tagihan. BATAL & LUNAS tidak pernah piutang.
     *
     * @var list<string>
     */
    public const OUTSTANDING_STATUSES = [
        InvoiceStatus::BELUM_DIBAYAR->value,
        InvoiceStatus::SEBAGIAN->value,
    ];

    /**
     * SATU-SATUNYA definisi "piutang" / "terlambat" untuk tagihan.
     *
     * `due_date` (mis. tanggal 10) hanya formalitas & label UI — pelanggan
     * masih boleh bayar sampai akhir bulan tanpa konsekuensi apa pun. Batas
     * riil adalah pergantian bulan: pembukuan bulan lalu sudah tutup, jadi
     * tagihan `billing_period` < bulan berjalan yang belum lunas = piutang.
     *
     * Jangan bandingkan `due_date` dengan hari ini untuk menentukan terlambat
     * di tempat lain; pakai scope ini / isPiutang() supaya dashboard, list
     * pelanggan, dan tabel kolektor tidak menyimpang satu sama lain.
     * `billing_period` berformat 'Y-m', jadi perbandingan string aman.
     *
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopePiutang(Builder $query): Builder
    {
        return $query
            ->whereIn('invoice_status', self::OUTSTANDING_STATUSES)
            ->where('billing_period', '<', now()->format('Y-m'));
    }

    public function isPiutang(): bool
    {
        return in_array($this->invoice_status?->value, self::OUTSTANDING_STATUSES, true)
            && $this->billing_period !== null
            && $this->billing_period < now()->format('Y-m');
    }

    /**
     * Tagihan LEBIH LAMA (`billing_period` lebih kecil) milik pelanggan yang
     * sama, yang masih `belum_dibayar`/`sebagian` — dasar peringatan piutang
     * di form/modal bayar (ADHOC-84 §2.4). Dipakai dua jalur (form Input
     * Pembayaran & Modal Bayar Cepat via JSON `InvoiceController::show()`),
     * satu definisi supaya keduanya tak pernah menyimpang.
     *
     * @return Collection<int, Invoice>
     */
    public function olderUnpaidInvoices(): Collection
    {
        if (! $this->customer_id || $this->billing_period === null) {
            return collect();
        }

        return static::query()
            ->where('customer_id', $this->customer_id)
            ->where('id', '!=', $this->id)
            ->where('billing_period', '<', $this->billing_period)
            ->whereIn('invoice_status', self::OUTSTANDING_STATUSES)
            ->orderBy('billing_period')
            ->get(['id', 'invoice_number', 'billing_period', 'remaining_amount']);
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
