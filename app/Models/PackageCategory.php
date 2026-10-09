<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master Kategori Paket Internet — menggantikan `InternetPackage::CATEGORIES`
 * yang sebelumnya hardcode.
 *
 * Tidak ada kolom `code`: `internet_packages.category` menyimpan `name`
 * kategori ini apa adanya sebagai string, bukan sebuah code snapshot. Jadi
 * mengganti `name` di sini TIDAK otomatis mengubah paket yang sudah memakai
 * nama lama — cukup pastikan admin tidak mengubah nama kategori yang sedang
 * dipakai kalau tidak ingin paket lama "kehilangan" kategorinya di filter.
 */
#[Fillable([
    'name',
    'is_active',
    'sort_order',
    'installation_fee_approval_role_id',
])]
class PackageCategory extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    /**
     * Kategori aktif untuk dropdown form paket.
     *
     * @return Collection<int, self>
     */
    public static function options(): Collection
    {
        return static::active()->ordered()->get();
    }

    /**
     * Role yang wajib dipunyai user buat mengisi "Biaya Instalasi" pelanggan
     * di kategori ini (modul Busdev, `/customer-acquisitions`) — dipilih
     * admin lewat Master Kategori Paket, bukan hardcode di kode. NULL berarti
     * kategori ini tidak butuh validasi tambahan sama sekali.
     */
    public function installationFeeApprovalRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'installation_fee_approval_role_id');
    }

    /**
     * Kategori ini butuh "Biaya Instalasi" diisi Busdev atau tidak.
     */
    public function needsInstallationFeeValidation(): bool
    {
        return $this->installation_fee_approval_role_id !== null;
    }
}
