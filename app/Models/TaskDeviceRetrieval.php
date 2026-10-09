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
     * Pilihan kelengkapan standar di form — kunci disimpan di `accessories`.
     *
     * @var array<string, string>
     */
    public const ACCESSORY_OPTIONS = [
        'adaptor' => 'Adaptor / power',
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
