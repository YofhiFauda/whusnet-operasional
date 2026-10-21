<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Saklar tunggal penutupan jalur tulis representasi lama (§16.4 #1, #4).
 * Semua penulis lama memanggil `assertLegacyWriteAllowed()` — jangan cek
 * config langsung di tempat lain supaya satu saklar benar-benar menutup
 * semua jalur sekaligus.
 */
final class InfrastructureCutover
{
    /** @var list<string> */
    public const LEGACY_NODE_TYPES = ['pon_port', 'olt_card', 'aggregation_switch'];

    public static function legacyWritesEnabled(): bool
    {
        return (bool) config('infrastructure.legacy_writes_enabled', true);
    }

    public static function assertLegacyWriteAllowed(string $field, string $what): void
    {
        if (! self::legacyWritesEnabled()) {
            throw ValidationException::withMessages([
                $field => "{$what} adalah representasi lama yang sudah ditutup. Gunakan struktur Slot/PON/Port dan dropdown lokasi bertingkat.",
            ]);
        }
    }
}
