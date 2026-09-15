<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Kategori Pendapatan — empat kategori tetap, tidak bisa ditambah
 * maupun dihapus admin (lihat migrasi `create_revenue_categories_table`).
 *
 * Yang jadi kontrak adalah `code`, bukan `id`: `InvoiceItemBuilder` membaca
 * `CODE_JASA_LAYANAN_INTERNET` untuk mengenali baris langganan (yang bikin
 * tagihannya kena guard satu-langganan-per-periode) dan `CODE_LAINNYA` untuk
 * mengenali baris berkategori ketikan bebas. Karena itu code kategori tidak
 * bisa diubah admin — `RevenueCategoryController::updateCategory()` membuang
 * field itu dari input.
 *
 * Yang bebas disunting admin adalah [[RevenueSubcategory]].
 */
#[Fillable([
    'code',
    'name',
    'description',
    'is_active',
    'sort_order',
])]
class RevenueCategory extends Model
{
    use RecordsAuditLogs;

    /**
     * Master ini memuat nominal (`RevenueSubcategory::$default_amount`) dan
     * isinya menentukan angka yang muncul di tagihan, jadi ikut pola
     * `InternetPackage` yang juga diaudit — bukan pola `ItemCategory` yang
     * tidak.
     */
    protected string $auditModule = 'Master Kategori Pendapatan';

    /**
     * Kategori langganan internet. Baris tagihan di kategori ini nominalnya
     * TIDAK diambil dari input admin melainkan dari `customer_services`, dan
     * keberadaannya menentukan `invoice_type` harus `awal|bulanan|reaktivasi`
     * (bukan `insidental`).
     */
    public const CODE_JASA_LAYANAN_INTERNET = 'jasa_layanan_internet';

    public const CODE_JASA_INSTALASI = 'jasa_instalasi';

    public const CODE_JASA_PERBAIKAN = 'jasa_perbaikan';

    /**
     * Kategori jatuh terakhir. Baris di kategori ini tidak punya sub kategori
     * master — namanya diketik admin dan disimpan sebagai snapshot.
     */
    public const CODE_LAINNYA = 'lainnya';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RevenueSubcategory, $this>
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(RevenueSubcategory::class);
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

    /**
     * Apakah baris di kategori ini memakai nama ketikan bebas (bukan sub
     * kategori master).
     */
    public function usesCustomName(): bool
    {
        return $this->code === self::CODE_LAINNYA;
    }

    /**
     * Apakah baris di kategori ini nominalnya diambil dari layanan pelanggan,
     * bukan dari ketikan admin.
     */
    public function isSubscription(): bool
    {
        return $this->code === self::CODE_JASA_LAYANAN_INTERNET;
    }

    /**
     * Kategori aktif beserta sub kategori aktifnya untuk form tagihan.
     * Eager load disengaja: form merender cascading kategori→sub sekaligus,
     * jadi lazy load di sini langsung jadi N+1 di halaman create.
     *
     * @return Collection<int, self>
     */
    public static function optionsWithSubcategories(): Collection
    {
        return static::active()
            ->with(['subcategories' => fn ($query) => $query->active()->ordered()])
            ->ordered()
            ->get();
    }
}
