<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master kategori barang/material — menggantikan peran enum `MaterialType`
 * sebagai daftar pilihan.
 *
 * Yang dipakai data lain adalah `code`, bukan `id`: `task_materials.item_type`
 * dan `customer_technical_details.passive_device_type` menyimpannya sebagai
 * string snapshot. Karena itu code kategori bawaan tidak boleh berubah — lihat
 * `is_system`.
 */
#[Fillable([
    'code',
    'name',
    'default_unit',
    'is_active',
    'sort_order',
])]
class ItemCategory extends Model
{
    /**
     * Code kategori kabel dropcore.
     *
     * Dipakai `CustomerSurveyController` untuk merakit baris dropcore otomatis
     * dari `cable_estimation_meter`. Sengaja konstanta, bukan query: kalau
     * kategorinya dinonaktifkan admin, baris otomatis itu tetap harus terbentuk
     * dengan code yang sama supaya laporan lama & baru bisa diagregasi.
     */
    public const CODE_KABEL_DROPCORE = 'kabel_dropcore';

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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function taskMaterials(): HasMany
    {
        return $this->hasMany(TaskMaterial::class);
    }

    /**
     * Kategori aktif untuk dropdown form. Di-cache per-request saja (static),
     * bukan Cache::remember — daftarnya dipakai berkali-kali dalam satu render
     * form repeatable, tapi jarang berubah antar deploy sehingga cache lintas
     * request cuma menambah satu tempat yang harus di-invalidate saat admin
     * menyunting master.
     *
     * @return Collection<int, self>
     */
    public static function options()
    {
        return static::active()->ordered()->get();
    }

    /**
     * Label untuk satu code snapshot. Fallback ke code mentah supaya baris
     * material lama tetap tampil walau kategorinya sudah dihapus admin —
     * "kabel_dropcore" jelek dibaca, tapi jauh lebih baik daripada kolom kosong
     * di laporan pemakaian.
     *
     * Peta code→nama diambil sekali per request: pemanggilnya adalah tabel
     * material yang memanggil ini sekali per baris, dan query per baris di situ
     * langsung jadi N+1 di halaman verifikasi yang menampilkan estimasi +
     * terpakai sekaligus. Termasuk kategori nonaktif — baris lama tetap butuh
     * namanya.
     */
    public static function labelFor(?string $code): string
    {
        if ($code === null || $code === '') {
            return '-';
        }

        static::$labelMap ??= static::query()->pluck('name', 'code')->all();

        return static::$labelMap[$code] ?? $code;
    }

    /**
     * @var array<string, string>|null
     */
    private static ?array $labelMap = null;

    /**
     * Buang memo label — dipanggil setelah master disunting, dan oleh test yang
     * membuat kategori baru di tengah request yang sama.
     */
    public static function flushLabelCache(): void
    {
        static::$labelMap = null;
    }
}
