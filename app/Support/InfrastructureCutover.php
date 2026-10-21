<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Saklar tunggal penutupan jalur tulis representasi lama (§16.4 #1, #4).
 * Penutupan normal tersimpan di audit setelah tujuh bukti diverifikasi;
 * konfigurasi false hanya penghentian darurat yang dicatat terpisah.
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
        if (AuditLog::query()->where('module', 'InfrastructureCutover')->where('action', 'legacy_writes_closed')->exists()) {
            return false;
        }
        if (! (bool) config('infrastructure.legacy_writes_enabled', true)) {
            $reason = (string) config('infrastructure.legacy_emergency_reason', 'Alasan darurat belum dicatat di konfigurasi');
            if (! AuditLog::query()->where('module', 'InfrastructureCutover')->where('action', 'legacy_writes_emergency_disabled')
                ->where('request_hash', hash('sha256', $reason))->exists()) {
                AuditLog::create([
                    'user_id' => auth()->id(), 'module' => 'InfrastructureCutover', 'action' => 'legacy_writes_emergency_disabled',
                    'auditable_type' => User::class, 'auditable_id' => auth()->id() ?? 0,
                    'old_values' => ['legacy_writes_enabled' => true],
                    'new_values' => ['legacy_writes_enabled' => false, 'source' => 'INFRA_LEGACY_WRITES', 'reason' => $reason, 'cutover_complete' => false],
                    'request_hash' => hash('sha256', $reason), 'created_at' => now(),
                ]);
            }

            return false;
        }

        return true;
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
