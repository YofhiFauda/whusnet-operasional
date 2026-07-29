<?php

namespace App\Enums;

enum TaskType: string
{
    case SURVEY = 'SURVEY';
    case PEMASANGAN = 'PSB';
    case MAINTENANCE = 'MTN';
    case AMBIL_MODEM = 'DEAC';
    case CREQ = 'C-REQ';
    case OREQ = 'O-REQ';
    case INFR = 'INFR REQ';

    public function label(): string
    {
        return match ($this) {
            self::SURVEY => 'Survey Pelanggan',
            self::PEMASANGAN => 'Pemasangan Baru',
            self::MAINTENANCE => 'Maintenance',
            self::AMBIL_MODEM => 'Ambil Modem',
            self::CREQ => 'Customer Request',
            self::OREQ => 'Office Request',
            self::INFR => 'Infrastruktur Request',
        };
    }

    /**
     * SLA dalam menit per tipe task.
     */
    public function slaMinutes(): int
    {
        return match ($this) {
            self::SURVEY => 120,   // 2 jam
            self::PEMASANGAN => 240,   // 4 jam
            self::MAINTENANCE => 180,   // 3 jam
            self::AMBIL_MODEM => 60,    // 1 jam
            self::CREQ => 120,   // 2 jam
            self::OREQ => 240,   // 4 jam
            self::INFR => 480,   // 8 jam
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
            self::SURVEY => 24,   // 1x24 jam
            self::PEMASANGAN => 72,   // 3x24 jam
            self::MAINTENANCE => 24,
            self::AMBIL_MODEM => 24,
            self::CREQ => 24,
            self::OREQ => 48,
            self::INFR => 72,
        };
    }

    /**
     * Warna badge/card untuk kalender UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::SURVEY => 'blue',
            self::PEMASANGAN => 'green',
            self::MAINTENANCE => 'orange',
            self::AMBIL_MODEM => 'pink',
            self::CREQ => 'teal',
            self::OREQ => 'gray',
            self::INFR => 'red',
        };
    }

    /**
     * Tailwind CSS classes untuk card kalender.
     */
    public function cardClasses(): string
    {
        return match ($this) {
            self::SURVEY => 'bg-blue-100 dark:bg-blue-900/40 border-blue-300 dark:border-blue-800/70 text-blue-800 dark:text-blue-300',
            self::PEMASANGAN => 'bg-green-100 dark:bg-green-900/40 border-green-300 dark:border-green-800/70 text-green-800 dark:text-green-300',
            self::MAINTENANCE => 'bg-orange-100 dark:bg-orange-900/40 border-orange-300 dark:border-orange-800/70 text-orange-800 dark:text-orange-300',
            self::AMBIL_MODEM => 'bg-pink-100 dark:bg-pink-900/40 border-pink-300 dark:border-pink-800/70 text-pink-800 dark:text-pink-300',
            self::CREQ => 'bg-teal-100 dark:bg-teal-900/40 border-teal-300 dark:border-teal-800/70 text-teal-800 dark:text-teal-300',
            self::OREQ => 'bg-gray-100 dark:bg-slate-700/50 border-gray-300 dark:border-slate-600 text-gray-800 dark:text-slate-200',
            self::INFR => 'bg-red-100 dark:bg-red-900/40 border-red-300 dark:border-red-800/70 text-red-800',
        };
    }

    /**
     * Tailwind CSS classes untuk badge (lebih soft dari card).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::SURVEY => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800/50 text-blue-700 dark:text-blue-400',
            self::PEMASANGAN => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800/50 text-green-700 dark:text-green-400',
            self::MAINTENANCE => 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800/50 text-orange-700 dark:text-orange-400',
            self::AMBIL_MODEM => 'bg-pink-50 dark:bg-pink-900/20 border-pink-200 dark:border-pink-800/50 text-pink-700 dark:text-pink-400',
            self::CREQ => 'bg-teal-50 dark:bg-teal-900/20 border-teal-200 dark:border-teal-800/50 text-teal-700 dark:text-teal-400',
            self::OREQ => 'bg-gray-50 dark:bg-slate-800/50 border-gray-200 dark:border-slate-700 text-gray-700 dark:text-slate-300',
            self::INFR => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800/50 text-red-700 dark:text-red-400',
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
     * Tipe yang hanya boleh muncul via auto-sync — gak boleh dipilih manual
     * saat buat/edit task (baik di /tasks maupun /fop-tasks). SURVEY/PEMASANGAN
     * datang dari Registrasi Pelanggan, AMBIL_MODEM (DEAC) datang dari tombol
     * "Ambil Alat" di List Putus Langganan (CustomerController::retrieveDevice()).
     */
    public static function autoOnlyValues(): array
    {
        return [self::SURVEY->value, self::PEMASANGAN->value, self::AMBIL_MODEM->value];
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
