<?php

namespace App\Enums;

/**
 * Pengelompokan tiket buat submenu Ticketing. Tiap bucket dipetakan ke
 * sekumpulan TaskStatus milik FopTask hasil sync — tiket sendiri gak punya
 * kolom status (lihat Ticket::resolveStatus()).
 *
 * Empat bucket ini WAJIB saling lepas dan menutupi seluruh TaskStatus, biar
 * gak ada tiket yang ilang dari semua submenu. Dijaga oleh
 * TicketingTest::test_buckets_cover_every_task_status_exactly_once() — kalau
 * nambah case di TaskStatus, test itu gagal sampai bucket-nya ditentuin.
 */
enum TicketBucket: string
{
    case MASUK      = 'masuk';
    case DIPROSES   = 'diproses';
    case SELESAI    = 'selesai';
    case DIBATALKAN = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::MASUK      => 'Ticket Masuk',
            self::DIPROSES   => 'Ticket di Proses',
            self::SELESAI    => 'Ticket Selesai',
            self::DIBATALKAN => 'Ticket Dibatalkan',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MASUK      => 'Ticket baru yang belum ditugaskan FOP ke teknisi.',
            self::DIPROSES   => 'Sudah dijadwalkan, sedang dikerjakan, pending, atau lapor nanti.',
            self::SELESAI    => 'Pekerjaan teknisi sudah selesai.',
            self::DIBATALKAN => 'Ticket dibatalkan, atau Task FOP-nya sudah dihapus.',
        };
    }

    /**
     * Status FopTask yang masuk bucket ini.
     *
     * NB: "Lapor Nanti" bukan status tersendiri — itu FopTask ber-status
     * `pending` yang Task-nya `report_deferred` (lihat TaskStatus::displayLabel()),
     * jadi udah otomatis ketarik lewat PENDING di bucket DIPROSES.
     *
     * @return TaskStatus[]
     */
    public function statuses(): array
    {
        return match ($this) {
            self::MASUK      => [TaskStatus::DRAFT],
            self::DIPROSES   => [TaskStatus::TERJADWAL, TaskStatus::IN_PROGRESS, TaskStatus::PENDING],
            self::SELESAI    => [TaskStatus::SELESAI],
            self::DIBATALKAN => [TaskStatus::DIBATALKAN],
        };
    }

    /**
     * @return string[]
     */
    public function statusValues(): array
    {
        return array_map(fn (TaskStatus $s) => $s->value, $this->statuses());
    }

    /**
     * Tiket "Terputus" (fop_task_id null karena FopTask-nya dihapus FOP) gak
     * punya status buat dicocokin, jadi ikut ditampung bucket Dibatalkan —
     * kalau gak, tiketnya gak muncul di submenu mana pun.
     */
    public function includesOrphans(): bool
    {
        return $this === self::DIBATALKAN;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::MASUK      => 'bg-sky-50 border-sky-200 text-sky-700',
            self::DIPROSES   => 'bg-amber-50 border-amber-200 text-amber-700',
            self::SELESAI    => 'bg-emerald-50 border-emerald-200 text-emerald-700',
            self::DIBATALKAN => 'bg-slate-50 border-slate-200 text-slate-600',
        };
    }

    /**
     * Aksen border-kiri kartu ticket di daftar inbox — palet warnanya sengaja
     * senada sama badgeClasses() (sky/amber/emerald/slate) biar bucket yang
     * sama kebaca konsisten di seluruh halaman.
     */
    public function borderAccentClasses(): string
    {
        return match ($this) {
            self::MASUK      => 'border-l-sky-500',
            self::DIPROSES   => 'border-l-amber-500',
            self::SELESAI    => 'border-l-emerald-500',
            self::DIBATALKAN => 'border-l-slate-400',
        };
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
