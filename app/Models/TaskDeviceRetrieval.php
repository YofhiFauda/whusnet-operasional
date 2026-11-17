<?php

namespace App\Models;

use App\Enums\DeviceRetrievalOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Laporan lapangan task Ambil Modem (DEAC) — lihat migrasi
 * `create_task_device_retrievals_table` dan
 * docs/plan/warehouse/analisa-ambil-modem-deac-ke-gudang.md.
 */
#[Fillable([
    'task_id',
    'outcome',
    'condition_photo',
    'accessories',
    'notes',
])]
class TaskDeviceRetrieval extends Model
{
    /**
     * Pilihan kelengkapan di form + whitelist validasi — kunci disimpan di `accessories`.
     *
     * Cuma adaptor: patchcord, kabel LAN, remote/STB dicabut dari form (2026-11-17)
     * karena bukan kelengkapan yang dilacak saat penarikan. Jangan tambah lagi di
     * sini kalau cuma butuh label tampilan — pakai ACCESSORY_LABELS.
     *
     * @var array<string, string>
     */
    public const ACCESSORY_OPTIONS = [
        'adaptor' => 'Adaptor / power',
    ];

    /**
     * Label tampilan untuk SEMUA kunci yang pernah tersimpan, termasuk yang sudah
     * dicabut dari form. Laporan lama masih menyimpan `patchcord`/`kabel_lan`/`remote`
     * di JSON `accessories` — tanpa label ini mereka tampil sebagai kunci mentah.
     *
     * @var array<string, string>
     */
    public const ACCESSORY_LABELS = self::ACCESSORY_OPTIONS + [
        'patchcord' => 'Patchcord',
        'kabel_lan' => 'Kabel LAN',
        'remote' => 'Remote / STB',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => DeviceRetrievalOutcome::class,
            'accessories' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
