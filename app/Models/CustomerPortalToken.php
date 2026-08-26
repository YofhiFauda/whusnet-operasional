<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Token akses & refresh portal pelanggan (docs/api/api-portal-pelanggan/,
 * Fase 2). BUKAN numpang Sanctum `personal_access_tokens` — lihat migrasi
 * pembentuknya buat alasan lengkap. SENGAJA TIDAK memakai `RecordsAuditLogs`,
 * sama alasannya seperti CustomerPortalAccount.
 */
#[Fillable([
    'customer_id', 'token_hash', 'type', 'parent_id',
    'expires_at', 'revoked_at', 'last_used_at', 'ip_address',
])]
class CustomerPortalToken extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CustomerPortalToken, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<CustomerPortalToken, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Terbitkan sepasang token baru (dipanggil terpisah utk access & refresh
     * — caller yang mengatur pemasangannya). Plaintext dikembalikan SEKALI,
     * tidak pernah disimpan mentah — cuma hash-nya yang masuk DB.
     *
     * @return array{model: self, plaintext: string}
     */
    public static function issue(
        int $customerId,
        string $type,
        ?int $parentId,
        int $ttlMinutes,
        ?string $ip,
    ): array {
        $plaintext = Str::random(64);

        $model = static::create([
            'customer_id' => $customerId,
            'token_hash' => hash('sha256', $plaintext),
            'type' => $type,
            'parent_id' => $parentId,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'ip_address' => $ip,
        ]);

        return ['model' => $model, 'plaintext' => $plaintext];
    }

    public static function resolveActiveAccessToken(string $plaintext): ?self
    {
        return static::query()
            ->where('type', 'access')
            ->where('token_hash', hash('sha256', $plaintext))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public static function resolveRefreshToken(string $plaintext): ?self
    {
        return static::query()
            ->where('type', 'refresh')
            ->where('token_hash', hash('sha256', $plaintext))
            ->first();
    }

    /**
     * Cabut SEMUA token pelanggan itu — dipakai logout, logout-all, ganti
     * password (lain kecuali sesi aktif), dan CustomerObserver saat
     * pelanggan terminated.
     */
    public static function revokeAllForCustomer(int $customerId, ?int $exceptTokenId = null): void
    {
        static::query()
            ->where('customer_id', $customerId)
            ->whereNull('revoked_at')
            ->when($exceptTokenId, fn ($query) => $query->whereNot('id', $exceptTokenId))
            ->update(['revoked_at' => now()]);
    }

    /**
     * Rotasi refresh selalu 1:1 (satu refresh lama → satu refresh baru),
     * rantainya linked-list linear, BUKAN tree bercabang — traversal
     * iteratif lewat `where parent_id` aman dan tidak butuh kolom family_id
     * baru yang tidak ada di skema dokumen (database-schema.md §2: kolom
     * cuma punya pointer ke predecessor, bukan ke depan).
     *
     * Dipanggil saat refresh token yang SUDAH revoked dipakai lagi —
     * indikasi pencurian (business-logic.md §Token). Di jalur caller
     * (PortalAuthController::refresh()), reuse detection sebenarnya memicu
     * revokeAllForCustomer() total — method ini tetap disediakan sebagai
     * primitif independen (mencabut cuma turunan satu token tertentu) untuk
     * kasus yang lebih sempit di luar reuse detection.
     */
    public function revokeDescendants(): int
    {
        $count = 0;
        $frontier = [$this->id];

        while ($frontier !== []) {
            $children = static::query()
                ->whereIn('parent_id', $frontier)
                ->whereNull('revoked_at')
                ->get();

            if ($children->isEmpty()) {
                break;
            }

            static::query()->whereIn('id', $children->pluck('id'))->update(['revoked_at' => now()]);
            $count += $children->count();
            $frontier = $children->pluck('id')->all();
        }

        return $count;
    }
}
