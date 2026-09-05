<?php

namespace App\Models;

use App\Enums\CustodyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Custody barang QUANTITY/BATCH yang dipegang teknisi — TIDAK dilacak
 * `inventory_serials` (khusus serialized per-unit). `status` pakai
 * `CustodyStatus`, vocabulary SENGAJA terpisah dari `SerialStatus` (qty bisa
 * parsial, unit serial gak bisa) — lihat
 * docs/plan/warehouse/kontrol-anti-manipulasi.md §7.
 *
 * SATU teknisi bisa punya BEBERAPA baris aktif buat item+lot yang sama dari
 * ISSUE berbeda waktu — tiap ISSUE bikin baris baru (lihat komentar migration
 * `create_technician_custody_table`). Konsumsi FIFO lintas baris
 * (`qty_remaining > 0` diurutkan `lot_no` ASC) itu tugas
 * `InventoryService::consumeFromCustody()` (Fase Service), BUKAN model ini.
 */
#[Fillable([
    'technician_id',
    'issued_from_pop_id',
    'item_id',
    'lot_no',
    'qty_remaining',
    'unit_price_snapshot',
    'status',
    'issued_at',
])]
class TechnicianCustody extends Model
{
    /**
     * Nama tabel `technician_custody` SINGULAR (bukan "custodies" hasil
     * pluralisasi otomatis Eloquent dari "TechnicianCustody") — samain
     * dengan migration `create_technician_custody_table`.
     */
    protected $table = 'technician_custody';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_remaining' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
            'status' => CustodyStatus::class,
            'issued_at' => 'datetime',
        ];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Gudang cabang asal ISSUE — dipakai scoping POP halaman Custody (§2.6
     * rancangan-ui.md), BUKAN "lokasi sekarang" (barang ini emang lagi gak di
     * gudang mana pun, ada di tangan teknisi).
     */
    public function issuedFromPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'issued_from_pop_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function scopeActive($query)
    {
        return $query->where('qty_remaining', '>', 0);
    }

    /**
     * Badge durasi custody (§3 kontrol-anti-manipulasi.md) — MURNI
     * informasional, bukan alert ambang waktu otomatis. Jam kalau < 24 jam,
     * hari kalau >= 1 hari.
     */
    public function ageLabel(): string
    {
        // (int) — Carbon 3 balikin float presisi dari diffIn*() (mis.
        // "5.0000107783333"), bukan integer kayak Carbon 2. Dibulatin ke
        // bawah, angka jam/hari yang presisi sampai microsecond gak ada
        // gunanya buat badge di UI.
        $hours = (int) $this->issued_at->diffInHours(now());

        if ($hours < 24) {
            return $hours.' jam';
        }

        return ((int) $this->issued_at->diffInDays(now())).' hari';
    }
}
