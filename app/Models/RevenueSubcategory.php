<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sub Kategori Pendapatan — lapis yang BEBAS ditambah admin lewat
 * `/master/revenue-categories`.
 *
 * `is_system` di sini berarti "dirujuk kode", bukan sekadar bawaan: tiga sub
 * di bawah dipakai konstanta oleh `InvoiceItemBuilder`, jadi codenya kontrak
 * dan tidak boleh dinamai ulang. Sub buatan admin selalu `is_system = false`.
 *
 * Master ini tidak pernah dihapus, hanya dinonaktifkan — sub yang dihapus akan
 * membuat tagihan lama kehilangan artinya. Ditegakkan `restrictOnDelete` di
 * `invoice_items` dan absennya aksi hapus di controller.
 */
#[Fillable([
    'revenue_category_id',
    'code',
    'name',
    'default_amount',
    'is_active',
    'sort_order',
])]
class RevenueSubcategory extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Master Kategori Pendapatan';

    /** Baris langganan bulanan rutin — dirakit `GenerateMonthlyInvoicesCommand`. */
    public const CODE_LANGGANAN_BULANAN = 'langganan_bulanan';

    /** Baris prorata bulan aktivasi — dirakit `InitialInvoiceService`. */
    public const CODE_PRORATA = 'prorata';

    /** Baris biaya aktivasi/registrasi PSB — dirakit `InitialInvoiceService`. */
    public const CODE_BIAYA_AKTIVASI = 'biaya_aktivasi';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RevenueCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RevenueCategory::class, 'revenue_category_id');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
