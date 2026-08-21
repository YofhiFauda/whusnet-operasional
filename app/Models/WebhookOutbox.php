<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'destination',
    'event',
    'event_id',
    'idempotency_key',
    'customer_id',
    'payload',
    'status',
    'attempts',
    'next_attempt_at',
    'response_status',
    'last_error',
    'delivered_at',
])]
class WebhookOutbox extends Model
{
    protected $table = 'webhook_outbox';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * Nomor aktivasi berikutnya untuk pelanggan+event ini —
     * `installation:{customer_id}:activation:{n}`, `n` naik tiap penekanan
     * Aktivasi. Dihitung di PHP (bukan raw SQL MySQL-only seperti
     * SUBSTRING_INDEX/CAST AS UNSIGNED) supaya portable ke sqlite yang
     * dipakai test — CLAUDE.md: DB default repo ini sqlite.
     */
    public static function nextActivationNumber(?int $customerId, string $event): int
    {
        if ($customerId === null) {
            return 1;
        }

        $max = static::where('customer_id', $customerId)
            ->where('event', $event)
            ->pluck('idempotency_key')
            ->map(fn ($key) => static::activationNumberFromKey($key))
            ->filter()
            ->max();

        return ($max ?? 0) + 1;
    }

    /**
     * Nomor aktivasi TERTINGGI yang sudah `delivered` untuk pelanggan+event
     * ini, DI TUJUAN YANG SAMA. Dipakai job kirim buat guard urutan: event
     * dengan nomor lebih rendah yang datang belakangan (retry yang lolos
     * duluan lalu baru yang lama diproses) harus dibuang, bukan menimpa yang
     * sudah delivered.
     *
     * `destination` WAJIB ikut difilter. Tanpa itu keberhasilan satu tujuan
     * menyensor tujuan lain: satu penekanan Aktivasi menulis DUA baris dengan
     * `idempotency_key` yang sama, jadi begitu Website B delivered #7, semua
     * baris Telegram bernomor < 7 ikut dianggap basi lalu di-`skipped` —
     * padahal Telegram belum pernah menerima apa pun. Persis itu yang terjadi
     * 2026-08-20 (baris outbox #8/#10/#12 hilang diam-diam waktu kredensial
     * Telegram masih salah). Urutan cuma bermakna relatif terhadap apa yang
     * sudah diterima OLEH TUJUAN ITU SENDIRI.
     */
    public static function maxDeliveredActivationNumber(?int $customerId, string $event, string $destination): int
    {
        if ($customerId === null) {
            return 0;
        }

        $max = static::where('customer_id', $customerId)
            ->where('event', $event)
            ->where('destination', $destination)
            ->where('status', 'delivered')
            ->pluck('idempotency_key')
            ->map(fn ($key) => static::activationNumberFromKey($key))
            ->filter()
            ->max();

        return $max ?? 0;
    }

    public static function activationNumberFromKey(?string $key): ?int
    {
        if (! $key) {
            return null;
        }

        $suffix = explode(':', $key);
        $last = end($suffix);

        return is_numeric($last) ? (int) $last : null;
    }
}
