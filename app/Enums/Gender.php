<?php

namespace App\Enums;

enum Gender: string
{
    case LAKI_LAKI = 'Laki-laki';
    case PEREMPUAN = 'Perempuan';

    public function label(): string
    {
        return $this->value;
    }
}
