<?php

namespace App\Enums;

enum TaskStatus: string
{
    case DRAFT = 'draft';
    case TERJADWAL = 'terjadwal';
    case IN_PROGRESS = 'in_progress';
    case SELESAI = 'selesai';
    case DIBATALKAN = 'dibatalkan';
    case PENDING = 'pending';

    /**
     * Kerja lapangan SUDAH selesai, laporannya menyusul (2026-09-26).
     *
     * Dulu ini bukan status sendiri — cuma `pending` + flag `report_deferred`.
     * Akibatnya tiap kode yang cuma lihat `status` (policy, tombol, papan FOP,
     * backlog) memperlakukannya sebagai Pending biasa: label bener "Lapor
     * Nanti", tapi tombol "Lanjutkan Laporan" hilang untuk MTN/C-REQ/DEAC dan
     * FOP bisa reject/jadwal ulang. Dua kejadian yang perilakunya beda wajib
     * punya status beda:
     *   - PENDING     = kerja berhenti, tim dilepas, balik ke antrian FOP.
     *   - LAPOR_NANTI = kerja beres, tim TETAP nempel, TERKUNCI untuk teknisi
     *                   (FOP gak boleh reject/reschedule/batal/ganti tim) —
     *                   satu-satunya jalan keluar: teknisi kirim laporan.
     */
    case LAPOR_NANTI = 'lapor_nanti';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::TERJADWAL => 'Terjadwal',
            self::IN_PROGRESS => 'Sedang Dikerjakan',
            self::SELESAI => 'Selesai',
            self::DIBATALKAN => 'Dibatalkan',
            self::PENDING => 'Pending',
            self::LAPOR_NANTI => 'Lapor Nanti',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-gray-100 dark:bg-slate-700/50 text-gray-700 dark:text-slate-300',
            self::TERJADWAL => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400',
            self::IN_PROGRESS => 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400',
            self::SELESAI => 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400',
            self::DIBATALKAN => 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 line-through',
            self::PENDING => 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-400',
            self::LAPOR_NANTI => 'bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-400',
        };
    }

    /**
     * Tailwind classes (border + bg + text) buat badge status di tabel FOP Task,
     * Riwayat, dan /tasks-saya. Lapor Nanti violet biar beda visual dari
     * Pending (kuning).
     */
    public function displayBadgeClasses(): string
    {
        return match ($this) {
            self::DRAFT => 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50',
            self::TERJADWAL => 'border-blue-200 dark:border-blue-800/50 text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20',
            self::IN_PROGRESS => 'border-amber-200 dark:border-amber-800/50 text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20',
            self::SELESAI => 'border-green-200 dark:border-green-800/50 text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20',
            self::DIBATALKAN => 'border-red-200 dark:border-red-800/50 text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20',
            self::PENDING => 'border-yellow-200 dark:border-yellow-800/50 text-yellow-700 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20',
            self::LAPOR_NANTI => 'border-violet-200 dark:border-violet-800/50 text-violet-700 dark:text-violet-400 bg-violet-50 dark:bg-violet-900/20',
        };
    }

    /**
     * SATU-SATUNYA sumber "task di status ini boleh menerima laporan teknisi".
     *
     * Dipakai policy `statusComplete`, tombol laporan di /tasks-saya & Detail
     * Task, dan semua controller laporan (survey, pemasangan, maintenance,
     * ambil alat). Jangan tulis ulang daftar `[in_progress, ...]` di tempat
     * lain — bug "label Lapor Nanti tapi tombol laporan hilang" (2026-09-26)
     * lahir persis dari daftar yang ditulis ulang di banyak tempat lalu satu
     * di antaranya lupa diperbarui.
     *
     * Sengaja `match` tanpa `default`: nambah case baru di enum ini bikin
     * method ini meledak (UnhandledMatchError) sampai diputuskan sadar.
     */
    public function acceptsReport(): bool
    {
        return match ($this) {
            self::IN_PROGRESS, self::LAPOR_NANTI => true,
            self::DRAFT, self::TERJADWAL, self::SELESAI, self::DIBATALKAN, self::PENDING => false,
        };
    }

    /**
     * Nilai string dari status yang `acceptsReport()` — buat `whereIn()`.
     *
     * @return string[]
     */
    public static function reportableValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->acceptsReport())
        ));
    }

    /**
     * Status yang TERKUNCI dari aksi sisi FOP (reject, pending/reschedule,
     * batal, edit, ganti tim/teknisi, hapus) — keputusan user 2026-09-26:
     * Lapor Nanti dikunci ke teknisi, laporannya pasti diisi. `match` tanpa
     * `default` dengan alasan yang sama kayak `acceptsReport()`.
     */
    public function isLockedFromFop(): bool
    {
        return match ($this) {
            self::LAPOR_NANTI => true,
            self::DRAFT, self::TERJADWAL, self::IN_PROGRESS, self::SELESAI, self::DIBATALKAN, self::PENDING => false,
        };
    }

    /**
     * Apakah status ini masih bisa diubah (belum final).
     *
     * `PENDING` sengaja DIKELUARIN (2026-07-15): status ini sekarang SELALU
     * berarti tim udah dilepas & task balik ke antrian nunggu di-assign ulang
     * (lihat TaskController::reschedule()/pending()) — task kayak gini gak
     * boleh diedit langsung, harus di-assign ulang dulu (balik ke
     * terjadwal/in_progress) baru bisa diedit. `LAPOR_NANTI` juga di luar —
     * terkunci ke teknisi (lihat isLockedFromFop()).
     */
    public function isEditable(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::TERJADWAL,
        ]);
    }

    /**
     * Apakah status ini dianggap "aktif".
     */
    public function isActive(): bool
    {
        return in_array($this, [self::TERJADWAL, self::IN_PROGRESS]);
    }
}
