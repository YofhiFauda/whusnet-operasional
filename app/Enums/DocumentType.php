<?php

namespace App\Enums;

enum DocumentType: string
{
    case RUMAH = 'rumah';
    case KONTRAK = 'kontrak';
    case SURVEY = 'survey';
    case PEMASANGAN = 'pemasangan';

    public function label(): string
    {
        return match ($this) {
            self::RUMAH => 'Foto Rumah',
            self::KONTRAK => 'Dokumen Kontrak',
            self::SURVEY => 'Foto Survey',
            self::PEMASANGAN => 'Foto Pemasangan',
        };
    }
}
