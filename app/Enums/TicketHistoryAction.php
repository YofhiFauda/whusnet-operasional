<?php

namespace App\Enums;

/**
 * Jenis kejadian di riwayat Ticketing. Sengaja dipisah dari
 * `fop_task_status_history` (riwayat sisi FOP) — satu pembatalan menghasilkan
 * DUA jejak: satu di Task FOP (operasional teknisi) dan satu di sini (sisi
 * pengirim tiket). Lihat FopTaskObserver.
 */
enum TicketHistoryAction: string
{
    case DIBUAT = 'dibuat';
    case DIBATALKAN = 'dibatalkan';
    case DIESKALASI = 'dieskalasi';
    case DISELESAIKAN = 'diselesaikan';
    case DIKEMBALIKAN = 'dikembalikan';

    /**
     * USANG — aksi "Oncheck NOC" dihapus (ADHOC-06, 2026-07-29): tiket yang
     * diassign ke NOC langsung diproses, gak ada langkah terima.
     *
     * Case-nya SENGAJA dipertahankan. Baris `ticket_histories` lama masih
     * menyimpan nilai 'dicek_noc' dan kolomnya di-cast ke enum ini — hapus
     * case-nya, riwayat tiket lama langsung meledak waktu dibaca.
     */
    case DICEK_NOC = 'dicek_noc';

    public function label(): string
    {
        return match ($this) {
            self::DIBUAT => 'Ticket dikirim',
            self::DIBATALKAN => 'Ticket dibatalkan',
            self::DIESKALASI => 'Ticket dieskalasi',
            self::DISELESAIKAN => 'Ticket diselesaikan',
            self::DIKEMBALIKAN => 'Ticket dikembalikan ke Helpdesk',
            self::DICEK_NOC => 'NOC mulai menangani',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::DIBUAT => 'bg-sky-50 dark:bg-sky-900/20 border-sky-200 dark:border-sky-800/50 text-sky-700 dark:text-sky-400',
            self::DIBATALKAN => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800/50 text-red-700 dark:text-red-400',
            self::DIESKALASI => 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50 text-amber-700 dark:text-amber-400',
            self::DISELESAIKAN => 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-400',
            self::DIKEMBALIKAN => 'bg-slate-50 dark:bg-slate-900/20 border-slate-200 dark:border-slate-800/50 text-slate-600 dark:text-slate-400',
            self::DICEK_NOC => 'bg-amber-100 dark:bg-amber-900/30 border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-300',
        };
    }
}
