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
            self::DRAFT => 'bg-gray-100 text-gray-700',
            self::TERJADWAL => 'bg-blue-100 text-blue-700',
            self::IN_PROGRESS => 'bg-amber-100 text-amber-700',
            self::SELESAI => 'bg-green-100 text-green-700',
            self::DIBATALKAN => 'bg-red-100 text-red-700 line-through',
            self::PENDING => 'bg-yellow-100 text-yellow-700',
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
            return 'border-violet-200 text-violet-700 bg-violet-50';
        }

        return match ($this) {
            self::DRAFT => 'border-slate-200 text-slate-600 bg-slate-50',
            self::TERJADWAL => 'border-blue-200 text-blue-700 bg-blue-50',
            self::IN_PROGRESS => 'border-amber-200 text-amber-700 bg-amber-50',
            self::SELESAI => 'border-green-200 text-green-700 bg-green-50',
            self::DIBATALKAN => 'border-red-200 text-red-700 bg-red-50',
            self::PENDING => 'border-yellow-200 text-yellow-700 bg-yellow-50',
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
