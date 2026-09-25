<?php

namespace App\Enums;

/**
 * Jenis di dalam Tagihan Manual (ADHOC-70) — bukan `InvoiceType` sendiri,
 * cuma sub-klasifikasi di dalam `InvoiceType::MANUAL`. Tiga nilai ini FINAL
 * sesuai studi kasus user (docs/plan/billing/analisa-rancangan-tagihan-manual.md
 * §3.2) — Aktivasi/Bulanan/Reaktivasi BUKAN bagian dari Tagihan Manual.
 */
enum ManualInvoiceCategory: string
{
    case PERBAIKAN = 'perbaikan';
    case LAINNYA = 'lainnya';
    case PINDAH_LOKASI = 'pindah_lokasi';

    public function label(): string
    {
        return match ($this) {
            self::PERBAIKAN => 'Tagihan Perbaikan',
            self::LAINNYA => 'Tagihan Lainnya',
            self::PINDAH_LOKASI => 'Tagihan Pindah Lokasi',
        };
    }

    /** Lainnya wajib diisi nama sub-nya (over kabel, Pendapatan A, dst — ketikan bebas). */
    public function requiresSubtypeName(): bool
    {
        return $this === self::LAINNYA;
    }
}
