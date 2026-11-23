<?php

namespace App\Models;

use App\Enums\FopTaskPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Issue/Kategori Keluhan — klasifikasi terstruktur untuk dropdown
 * "Kategori Issue" di form Create Service Ticket. Bukan pengganti
 * `detail_keluhan` (tetap wajib diisi, narasi bebas pelanggan).
 */
#[Fillable([
    'name',
    'default_priority',
    'sla_source',
    'is_active',
    'is_batch',
    'badge_color',
])]
class TicketIssueCategory extends Model
{
    /**
     * Warna teks di atas `badge_color` — putih atau gelap, dipilih dari
     * luminansi latar. Dihitung server (bukan disimpan) supaya admin cukup
     * milih satu warna dan teks badge gak pernah jadi gak kebaca, mis. latar
     * kuning muda + teks putih. Null kalau kategori belum punya warna.
     */
    public function badgeTextColor(): ?string
    {
        return self::textColorFor($this->badge_color);
    }

    public static function textColorFor(?string $hex): ?string
    {
        if (! is_string($hex) || ! preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
            return null;
        }

        [$r, $g, $b] = array_map(
            fn (string $pair): float => hexdec($pair) / 255,
            str_split(substr($hex, 1), 2)
        );

        // Luminansi relatif WCAG — ambang 0.45 kira-kira titik di mana teks
        // gelap mulai lebih kontras daripada teks putih.
        $linear = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);

        return $luminance > 0.45 ? '#0f172a' : '#ffffff';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_priority' => FopTaskPriority::class,
            'is_active' => 'boolean',
            'is_batch' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'issue_category_id');
    }
}
