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

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::TERJADWAL => 'Terjadwal',
            self::IN_PROGRESS => 'Sedang Dikerjakan',
            self::SELESAI => 'Selesai',
            self::DIBATALKAN => 'Dibatalkan',
            self::PENDING => 'Pending',
        };
    }

    /**
     * Label yang ditampilin ke user (dipake seragam di /tasks-saya, /fop-tasks,
     * /fop-tasks/history) — satu-satunya pengecualian dari `label()` polos:
     * `pending` + `report_deferred=true` (Lapor Nanti, tim TETAP nempel) beda
     * kejadian dari `pending` biasa (tim dilepas, balik ke antrian). Lihat
     * docs/project_status_label_unifikasi.md § DESAIN FINAL.
     */
    public function displayLabel(bool $reportDeferred = false): string
    {
        return match (true) {
            $this === self::PENDING && $reportDeferred => 'Lapor Nanti',
            default => $this->label(),
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
        };
    }

    /**
     * Tailwind classes (border + bg + text) buat badge status di tabel FOP Task,
     * Riwayat, dan /tasks-saya — sinkron sama `displayLabel()`. Satu-satunya
     * cabang: `pending` + `report_deferred=true` (Lapor Nanti) dapet warna violet
     * biar beda visual dari Pending biasa (kuning).
     */
    public function displayBadgeClasses(bool $reportDeferred = false): string
    {
        if ($this === self::PENDING && $reportDeferred) {
            return 'border-violet-200 dark:border-violet-800/50 text-violet-700 dark:text-violet-400 bg-violet-50 dark:bg-violet-900/20';
        }

        return match ($this) {
            self::DRAFT => 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50',
            self::TERJADWAL => 'border-blue-200 dark:border-blue-800/50 text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20',
            self::IN_PROGRESS => 'border-amber-200 dark:border-amber-800/50 text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20',
            self::SELESAI => 'border-green-200 dark:border-green-800/50 text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20',
            self::DIBATALKAN => 'border-red-200 dark:border-red-800/50 text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20',
            self::PENDING => 'border-yellow-200 dark:border-yellow-800/50 text-yellow-700 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20',
        };
    }

    /**
     * Apakah status ini masih bisa diubah (belum final).
     *
     * `PENDING` sengaja DIKELUARIN (2026-07-15): status ini sekarang SELALU
     * berarti tim udah dilepas & task balik ke antrian nunggu di-assign ulang
     * (lihat TaskController::reschedule()/pending()) — task kayak gini gak
     * boleh diedit langsung, harus di-assign ulang dulu (balik ke
     * terjadwal/in_progress) baru bisa diedit.
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
