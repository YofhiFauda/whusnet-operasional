<?php

namespace App\Enums;

enum TaskType: string
{
    case SURVEY      = 'SURVEY';
    case PEMASANGAN  = 'PSB';
    case MAINTENANCE = 'MTN';
    case AMBIL_MODEM = 'DEAC';
    case RELOKASI    = 'RELOKASI';
    case CREQ        = 'C-REQ';
    case OREQ        = 'O-REQ';
    case INFR        = 'INFR REQ';

    public function label(): string
    {
        return match ($this) {
            self::SURVEY      => 'Survey Pelanggan',
            self::PEMASANGAN  => 'Pemasangan Baru',
            self::MAINTENANCE => 'Maintenance',
            self::AMBIL_MODEM => 'Ambil Modem',
            self::RELOKASI    => 'Relokasi/Pemindahan',
            self::CREQ        => 'Customer Request',
            self::OREQ        => 'Office Request',
            self::INFR        => 'Infrastruktur Request',
        };
    }

    /**
     * SLA dalam menit per tipe task.
     */
    public function slaMinutes(): int
    {
        return match ($this) {
            self::SURVEY      => 120,   // 2 jam
            self::PEMASANGAN  => 240,   // 4 jam
            self::MAINTENANCE => 180,   // 3 jam
            self::AMBIL_MODEM => 60,    // 1 jam
            self::RELOKASI    => 240,   // 4 jam
            self::CREQ        => 120,   // 2 jam
            self::OREQ        => 240,   // 4 jam
            self::INFR        => 480,   // 8 jam
        };
    }

    /**
     * Default batas waktu wajib mulai ditangani (Master Timeline SLA), dalam
     * jam. Dipakai sbg fallback kalau paket internet belum diatur admin di
     * halaman Master Timeline SLA. Beda konsep dari slaMinutes() di atas
     * (itu durasi pengerjaan, ini batas mulai ditangani).
     */
    public function defaultHandlingSlaHours(): int
    {
        return match ($this) {
            self::SURVEY      => 24,   // 1x24 jam
            self::PEMASANGAN  => 72,   // 3x24 jam
            self::MAINTENANCE => 24,
            self::AMBIL_MODEM => 24,
            self::RELOKASI    => 48,
            self::CREQ        => 24,
            self::OREQ        => 48,
            self::INFR        => 72,
        };
    }

    /**
     * Warna badge/card untuk kalender UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::SURVEY      => 'blue',
            self::PEMASANGAN  => 'green',
            self::MAINTENANCE => 'orange',
            self::AMBIL_MODEM => 'pink',
            self::RELOKASI    => 'purple',
            self::CREQ        => 'teal',
            self::OREQ        => 'gray',
            self::INFR        => 'red',
        };
    }

    /**
     * Tailwind CSS classes untuk card kalender.
     */
    public function cardClasses(): string
    {
        return match ($this) {
            self::SURVEY      => 'bg-blue-100 border-blue-300 text-blue-800',
            self::PEMASANGAN  => 'bg-green-100 border-green-300 text-green-800',
            self::MAINTENANCE => 'bg-orange-100 border-orange-300 text-orange-800',
            self::AMBIL_MODEM => 'bg-pink-100 border-pink-300 text-pink-800',
            self::RELOKASI    => 'bg-purple-100 border-purple-300 text-purple-800',
            self::CREQ        => 'bg-teal-100 border-teal-300 text-teal-800',
            self::OREQ        => 'bg-gray-100 border-gray-300 text-gray-800',
            self::INFR        => 'bg-red-100 border-red-300 text-red-800',
        };
    }

    /**
     * Tailwind CSS classes untuk badge (lebih soft dari card).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::SURVEY      => 'bg-blue-50 border-blue-200 text-blue-700',
            self::PEMASANGAN  => 'bg-green-50 border-green-200 text-green-700',
            self::MAINTENANCE => 'bg-orange-50 border-orange-200 text-orange-700',
            self::AMBIL_MODEM => 'bg-pink-50 border-pink-200 text-pink-700',
            self::RELOKASI    => 'bg-purple-50 border-purple-200 text-purple-700',
            self::CREQ        => 'bg-teal-50 border-teal-200 text-teal-700',
            self::OREQ        => 'bg-gray-50 border-gray-200 text-gray-700',
            self::INFR        => 'bg-red-50 border-red-200 text-red-700',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($t) => [
            'value' => $t->value,
            'label' => $t->label(),
        ])->toArray();
    }

    /**
     * Tipe yang hanya boleh muncul via auto-sync Registrasi Pelanggan —
     * gak boleh dipilih manual saat buat/edit task (baik di /tasks maupun /fop-tasks).
     */
    public static function autoOnlyValues(): array
    {
        return [self::SURVEY->value, self::PEMASANGAN->value];
    }

    /**
     * Tipe yang boleh dipilih manual (semua tipe kecuali autoOnlyValues()).
     */
    public static function manualValues(): array
    {
        return array_diff(array_column(self::cases(), 'value'), self::autoOnlyValues());
    }

    /**
     * Options (value+label) yang boleh dipilih manual — dipakai dropdown create/edit.
     */
    public static function manualOptions(): array
    {
        return collect(self::options())
            ->reject(fn ($t) => in_array($t['value'], self::autoOnlyValues()))
            ->values()
            ->all();
    }

    /**
     * Tipe yang boleh diajukan lewat modul Ticketing (internal perusahaan).
     * Tipe lain tetap dibuat langsung dari /fop-tasks (internal FOP) atau
     * lewat auto-sync Registrasi Pelanggan.
     */
    public static function ticketValues(): array
    {
        return [self::MAINTENANCE->value, self::CREQ->value];
    }

    /**
     * Options (value+label) buat dropdown Tipe Ticket.
     */
    public static function ticketOptions(): array
    {
        return collect(self::options())
            ->filter(fn ($t) => in_array($t['value'], self::ticketValues(), true))
            ->values()
            ->all();
    }
}
