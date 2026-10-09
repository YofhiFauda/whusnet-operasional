<?php

namespace App\Enums;

/**
 * Sumber pencatat pembayaran dari jalur lapangan (batch kolektor/teknisi).
 *
 * Disimpan sebagai SNAPSHOT di `payments.collected_by_role` saat pembayaran
 * dicatat, BUKAN diturunkan dari role user saat ini: user yang merangkap dua
 * role atau role-nya berubah kemudian tidak boleh membuat badge sejarah
 * pembayaran ikut berubah.
 *
 * docs/plan/kolektor/rancangan-pembayaran-teknisi.md §6.
 */
enum CollectorRole: string
{
    case KOLEKTOR = 'kolektor';
    case TEKNISI = 'teknisi';

    public function label(): string
    {
        return match ($this) {
            self::KOLEKTOR => 'Kolektor',
            self::TEKNISI => 'Teknisi',
        };
    }
}
