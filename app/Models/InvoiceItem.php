<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris rincian tagihan (ADHOC-60).
 *
 * Nominal di sini adalah komponen SUBTOTAL, bukan nominal akhir yang ditagih —
 * diskon & PPN berlaku di level tagihan. Invariannya:
 *
 *     SUM(invoice_items.amount) == invoices.subtotal
 *
 * Semua penulisan baris WAJIB lewat `InvoiceItemBuilder`; jangan bikin jalur
 * yang menyisipkan baris langsung, karena invariant di atas tidak akan
 * terjaga dan laporan pendapatan per kategori langsung menyimpang dari total
 * tagihan.
 */
#[Fillable([
    'invoice_id',
    'revenue_category_id',
    'revenue_subcategory_id',
    'category_name_snapshot',
    'subcategory_name_snapshot',
    'description',
    'amount',
    'sort_order',
])]
class InvoiceItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<RevenueCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RevenueCategory::class, 'revenue_category_id');
    }

    /**
     * @return BelongsTo<RevenueSubcategory, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(RevenueSubcategory::class, 'revenue_subcategory_id');
    }
}
