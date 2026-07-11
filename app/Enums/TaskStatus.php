<?php

namespace App\Enums;

enum TaskStatus: string
{
    case DRAFT                 = 'draft';
    case TERJADWAL             = 'terjadwal';
    case IN_PROGRESS           = 'in_progress';
    case SELESAI               = 'selesai';
    case DIBATALKAN            = 'dibatalkan';
    case PENDING               = 'pending';
    case RESCHEDULE            = 'reschedule';
    case WAITING_SURVEY        = 'waiting_survey';
    case WAITING_INSTALLATION  = 'waiting_installation';
    case WAITING_INSTALLATIONS = 'waiting_installations';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT                 => 'Draft',
            self::TERJADWAL             => 'Terjadwal',
            self::IN_PROGRESS           => 'In Progress',
            self::SELESAI               => 'Selesai',
            self::DIBATALKAN            => 'Dibatalkan',
            self::PENDING               => 'Pending',
            self::RESCHEDULE            => 'Reschedule',
            self::WAITING_SURVEY        => 'Waiting Survey',
            self::WAITING_INSTALLATION, 
            self::WAITING_INSTALLATIONS => 'Waiting Installation',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::DRAFT                 => 'bg-gray-100 text-gray-700',
            self::TERJADWAL             => 'bg-blue-100 text-blue-700',
            self::IN_PROGRESS           => 'bg-amber-100 text-amber-700',
            self::SELESAI               => 'bg-green-100 text-green-700',
            self::DIBATALKAN            => 'bg-red-100 text-red-700 line-through',
            self::PENDING,
            self::RESCHEDULE,
            self::WAITING_SURVEY,
            self::WAITING_INSTALLATION,
            self::WAITING_INSTALLATIONS => 'bg-yellow-100 text-yellow-700',
        };
    }

    /**
     * Apakah status ini masih bisa diubah (belum final).
     */
    public function isEditable(): bool
    {
        return in_array($this, [
            self::DRAFT, 
            self::TERJADWAL, 
            self::PENDING, 
            self::WAITING_SURVEY, 
            self::WAITING_INSTALLATION, 
            self::WAITING_INSTALLATIONS
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
