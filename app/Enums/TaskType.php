<?php

namespace App\Enums;

enum TaskType: string
{
    case SURVEY      = 'survey';
    case PEMASANGAN  = 'pemasangan';
    case MAINTENANCE = 'maintenance';
    case AMBIL_MODEM = 'ambil_modem';
    case RELOKASI    = 'relokasi';

    public function label(): string
    {
        return match ($this) {
            self::SURVEY      => 'Survey',
            self::PEMASANGAN  => 'Pemasangan',
            self::MAINTENANCE => 'Maintenance',
            self::AMBIL_MODEM => 'Ambil Modem',
            self::RELOKASI    => 'Relokasi',
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
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($t) => [
            'value' => $t->value,
            'label' => $t->label(),
        ])->toArray();
    }
}
