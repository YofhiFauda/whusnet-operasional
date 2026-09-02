<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Token pertukaran satu-arah staf/kolektor → Portal. Lihat docblock migrasi
 * pembentuknya (`create_staff_portal_tokens_table`) dan
 * `docs/plan/qr-code/analisa-unifikasi-qr-staff-portal.md` §4 untuk alasan
 * lengkap kenapa ini tabel terpisah dari `CustomerPortalToken`.
 */
#[Fillable([
    'user_id', 'customer_id', 'purpose', 'token_hash',
    'expires_at', 'consumed_at', 'ip_address',
])]
class StaffPortalToken extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Plaintext dikembalikan SEKALI, tidak pernah disimpan mentah — pola
     * sama `CustomerPortalToken::issue()`.
     *
     * @return array{model: self, plaintext: string}
     */
    public static function issue(int $userId, int $customerId, string $purpose, int $ttlMinutes, ?string $ip): array
    {
        $plaintext = Str::random(64);

        $model = static::create([
            'user_id' => $userId,
            'customer_id' => $customerId,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $plaintext),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'ip_address' => $ip,
        ]);

        return ['model' => $model, 'plaintext' => $plaintext];
    }

    public static function resolveActive(string $plaintext, string $purpose): ?self
    {
        return static::query()
            ->where('purpose', $purpose)
            ->where('token_hash', hash('sha256', $plaintext))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Konsumsi token — HANYA dipanggil caller setelah aksi PENULISAN
     * (create tiket / catat pembayaran kolektor) berhasil. Baca (worklist,
     * dedup check) tidak memanggil ini — lihat docblock migrasi.
     */
    public function consume(): void
    {
        $this->forceFill(['consumed_at' => now()])->saveQuietly();
    }
}
