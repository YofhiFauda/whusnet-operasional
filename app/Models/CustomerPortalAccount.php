<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kredensial login portal pelanggan (docs/api/api-portal-pelanggan/, Fase 2).
 *
 * SENGAJA TIDAK memakai trait `RecordsAuditLogs` — bukan lupa. Kalau dipasang,
 * `auditHidden` cuma menyaring kolom `password_hash` dari payload, tapi kolom
 * lain di tabel ini (`status`, `failed_attempts`, `last_login_at`) tetap
 * memicu satu baris `audit_logs` TIAP kali pelanggan login — membanjiri
 * riwayat yang staf pakai menelusuri perubahan data sungguhan
 * (database-schema.md §1). Model kredensial di repo ini yang aman justru
 * yang sama sekali tidak diaudit lewat jalur ini, bukan yang "difilter
 * sebagian" — lihat App\Models\WebhookOutbox sebagai pola pembanding.
 */
#[Fillable([
    'customer_id', 'login_id', 'password_hash', 'password_changed_at',
    'failed_attempts', 'locked_until', 'status', 'claimed_at', 'last_login_at',
])]
#[Hidden(['password_hash'])]
class CustomerPortalAccount extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Cast 'hashed' generik — jalan di kolom nama apa pun, tidak
            // harus literal `password`.
            'password_hash' => 'hashed',
            'password_changed_at' => 'datetime',
            'locked_until' => 'datetime',
            'claimed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Gagal login ke-N — dihitung DI DB, bukan cuma cache (cache bisa
     * di-flush, lockout ikut hilang bersamanya; alasan sama seperti PIN
     * §6.5.4). Ambang & durasi lewat config supaya gampang diubah satu
     * tempat tanpa grep kode.
     */
    public function registerFailedAttempt(): void
    {
        $threshold = config('customer_portal.lockout_threshold', 5);
        $minutes = config('customer_portal.lockout_minutes', 15);

        $attempts = $this->failed_attempts + 1;

        $this->forceFill([
            'failed_attempts' => $attempts,
            'locked_until' => $attempts >= $threshold
                ? now()->addMinutes($minutes)
                : $this->locked_until,
        ])->save();
    }

    public function resetFailedAttempts(): void
    {
        $this->forceFill([
            'failed_attempts' => 0,
            'locked_until' => null,
        ])->save();
    }
}
