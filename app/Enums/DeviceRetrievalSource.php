<?php

namespace App\Enums;

/**
 * Jalur masuk modem hasil pengambilan dari pelanggan (ADHOC-88).
 */
enum DeviceRetrievalSource: string
{
    /** Teknisi mencabut lewat task Ambil Modem (DEAC), transit dulu sebelum diterima gudang. */
    case DEAC = 'deac';

    /** Pelanggan mengantar sendiri ke gudang, tanpa task. Langsung diterima. */
    case WALK_IN = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::DEAC => 'Diambil teknisi (Task DEAC)',
            self::WALK_IN => 'Diantar pelanggan',
        };
    }
}
