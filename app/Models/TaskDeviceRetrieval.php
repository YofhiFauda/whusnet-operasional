<?php

namespace App\Models;

use App\Enums\DeviceRetrievalOutcome;
use App\Enums\DeviceRetrievalReviewStatus;
use App\Support\DeviceRetrievalPhoto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Laporan lapangan task Ambil Modem (DEAC) — lihat migrasi
 * `create_task_device_retrievals_table` dan
 * docs/plan/warehouse/analisa-ambil-modem-deac-ke-gudang.md.
 *
 * Laporan GAGAL (modem tidak berhasil diambil) = satu percobaan penarikan
 * yang direview FOP lewat `review_status` — lihat DeviceRetrievalReviewService
 * dan docs/plan/warehouse/analisa-review-penarikan-deac.md.
 */
#[Fillable([
    'task_id',
    'outcome',
    'condition_photo',
    'accessories',
    'notes',
    'review_status',
    'reviewed_by',
    'reviewed_at',
    'review_note',
    'photo_purged_at',
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
            'review_status' => DeviceRetrievalReviewStatus::class,
            'reviewed_at' => 'datetime',
            'photo_purged_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<DeviceRetrievalComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(DeviceRetrievalComment::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * URL foto lewat controller ber-permission (bukan URL publik) — null kalau
     * tidak ada foto, sudah dihapus retensi 30 hari, atau file-nya hilang.
     */
    public function photoUrl(): ?string
    {
        if ($this->photo_purged_at || ! DeviceRetrievalPhoto::exists($this->condition_photo)) {
            return null;
        }

        return route('device-retrievals.photo', $this);
    }
}
