<?php

namespace App\Models;

use App\Enums\OwnershipMode;
use App\Enums\SerialStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris per unit fisik barang SERIALIZED (modem, ONT, router, OTDR).
 * `status` SATU-SATUNYA acuan (`SerialStatus`) — jangan bikin status lain di
 * tempat lain (§16.6 warehouse_inventory_asset_traceability_analysis_advanced).
 *
 * `current_pop_id`/`current_technician_id`/`customer_id` — cuma SATU yang
 * relevan tergantung `status`. Konsistensinya ditegakkan Service (Fase
 * berikutnya), BUKAN model ini atau DB constraint.
 *
 * `customer_id` cuma NUNJUK ke pelanggan buat traceability — device fisik
 * (SN modem, dst) tetap `customer_technical_details` sebagai sumber
 * kebenaran instalasi (§29.3 analisa pertama), TIDAK disalin ke sini.
 */
#[Fillable([
    'item_id',
    'serial_number',
    'mac_address',
    'status',
    'current_pop_id',
    'current_technician_id',
    'issued_from_pop_id',
    'customer_id',
    'fop_task_id',
    'installed_at',
])]
class InventorySerial extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SerialStatus::class,
            'installed_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function currentPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'current_pop_id');
    }

    public function currentTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_technician_id');
    }

    /**
     * Gudang cabang asal ISSUE terakhir — dipakai scoping POP halaman Custody
     * (§2.6 rancangan-ui.md). Beda dari `currentPop` ("lokasi sekarang kalau
     * lagi di gudang") — kolom ini gak pernah null-balik begitu pernah
     * diissue, walau SN-nya sekarang udah pindah ke custody/terpasang.
     */
    public function issuedFromPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'issued_from_pop_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fopTask(): BelongsTo
    {
        return $this->belongsTo(FopTask::class);
    }

    public function scopeStatus($query, SerialStatus $status)
    {
        return $query->where('status', $status->value);
    }

    /**
     * Predikat doang — BUKAN enforcement. Guard transisi sungguhan (nolak
     * `INSTALLED` buat `OwnershipMode::COMPANY_ASSET`) wajib ditulis di
     * Service pas Fase Service, bukan cuma dicek di sini lalu diabaikan.
     * Lihat §16.2 warehouse_inventory_asset_traceability_analysis_advanced.
     */
    public function isEligibleForInstall(): bool
    {
        return $this->item?->ownership_mode === OwnershipMode::INSTALLABLE;
    }
}
