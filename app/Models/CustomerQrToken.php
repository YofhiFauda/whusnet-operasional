<?php

namespace App\Models;

use App\Observers\CustomerQrTokenObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token QR pelanggan (docs/plan/qr-code/rancangan-qr-pelanggan-final.md,
 * Fase 1). SENGAJA TIDAK memakai RecordsAuditLogs — kolom `issued_by`,
 * `revoked_by`, `revoke_reason` sudah jadi jejak audit eksplisit di baris
 * ini sendiri, pola sama seperti CustomerPortalToken.
 *
 * Invariant "maksimal satu token aktif per pelanggan" ditegakkan di
 * {@see CustomerQrTokenObserver::creating()}, bukan di sini —
 * supaya jalur artisan/tinker/import ikut terkunci, bukan cuma Service.
 */
#[Fillable([
    'customer_id', 'token', 'signed_pop_id', 'signed_customer_code',
    'issued_display_id', 'issued_at', 'issued_by',
    'revoked_at', 'revoked_by', 'revoke_reason',
    'last_scanned_at', 'scan_count',
    'pin_hash', 'pin_issued_at', 'pin_issued_by', 'pin_version',
    'pin_must_change', 'pin_first_used_at', 'pin_expires_at',
    'pin_failed_attempts', 'pin_locked_until',
])]
class CustomerQrToken extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_scanned_at' => 'datetime',
            'pin_issued_at' => 'datetime',
            'pin_must_change' => 'boolean',
            'pin_first_used_at' => 'datetime',
            'pin_expires_at' => 'datetime',
            'pin_locked_until' => 'datetime',
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
     * @return BelongsTo<Pop, $this>
     */
    public function signedPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'signed_pop_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * PIN "aktif" = ada hash-nya DAN belum kedaluwarsa. Dipakai
     * QrBillingController buat pilih gerbang: PIN 6 digit kalau ini true,
     * jalur legacy 4-digit HP kalau false (§6.1).
     */
    public function hasActivePin(): bool
    {
        return $this->pin_hash !== null
            && ($this->pin_expires_at === null || $this->pin_expires_at->isFuture());
    }

    public function isPinLocked(): bool
    {
        return $this->pin_locked_until !== null && $this->pin_locked_until->isFuture();
    }
}
