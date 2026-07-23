<?php

namespace App\Enums;

enum ScopeType: string
{
    case ALL_POP = 'all_pop';
    case SELECTED_POP = 'selected_pop';
    /**
     * POP_TREE dipertahankan untuk backward compatibility data lama.
     * Tidak lagi ditampilkan sebagai pilihan di form tambah/edit user.
     * Perilakunya sama dengan SELECTED_POP (resolve hierarki penuh).
     */
    case POP_TREE = 'pop_tree';

    public function label(): string
    {
        return match ($this) {
            self::ALL_POP => 'Seluruh POP',
            self::SELECTED_POP => 'Cabang POP',
            self::POP_TREE => 'Cabang POP',  // label sama, legacy
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ALL_POP => 'Akses ke seluruh POP tanpa batasan wilayah.',
            self::SELECTED_POP => 'Akses ke Cabang POP yang dipilih beserta Mini POP dan semua distribusi di bawahnya.',
            self::POP_TREE => 'Akses ke Cabang POP yang dipilih beserta Mini POP dan semua distribusi di bawahnya.',
        };
    }
}
