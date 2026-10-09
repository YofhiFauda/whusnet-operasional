<?php

namespace App\Models;

use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Rekening Bank (ADHOC-95) — rekening resmi perusahaan tujuan
 * pembayaran Transfer.
 *
 * GLOBAL, sengaja TANPA `HasPopScope`: satu daftar rekening dipakai semua
 * POP. Tidak ada hard delete — payment menyimpan FK + snapshot nama/nomor,
 * rekening yang tak dipakai lagi cukup dinonaktifkan.
 */
#[Fillable([
    'bank_name',
    'account_number',
    'account_holder_name',
    'label',
    'is_active',
])]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
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

    /**
     * @param  Builder<BankAccount>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Teks opsi dropdown di form bayar — satu sumber supaya modal Bayar
     * Cepat, Modal Hub pelanggan, dan halaman Catat Pembayaran menampilkan
     * rekening dengan format yang sama.
     */
    public function displayName(): string
    {
        $text = "{$this->bank_name} — {$this->account_number} (a.n. {$this->account_holder_name})";

        return $this->label ? "{$this->label}: {$text}" : $text;
    }

    /**
     * Daftar rekening aktif dalam bentuk siap pakai untuk dropdown yang
     * diisi lewat JSON (modal bayar) — cuma field yang dibutuhkan UI.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function activeOptions(): array
    {
        return static::query()
            ->active()
            ->orderBy('bank_name')
            ->orderBy('account_number')
            ->get()
            ->map(fn (BankAccount $account): array => [
                'id' => $account->id,
                'name' => $account->displayName(),
            ])
            ->all();
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
