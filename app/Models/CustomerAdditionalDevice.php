<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perangkat tambahan pelanggan (AP, STB, dll.) di luar perangkat utama.
 *
 * Sengaja TIDAK memakai boot()/recalculateCompleteness() seperti CustomerDevice:
 * device tambahan tidak mempengaruhi data_completeness_status pelanggan.
 */
#[Fillable([
    'customer_id',
    'device_type',
    'brand',
    'model',
    'serial_number',
    'mac_address',
    'technical_note',
    'inventory_serial_id',
    'status',
    'retrieved_at',
])]
class CustomerAdditionalDevice extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Data Teknis';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retrieved_at' => 'datetime',
        ];
    }

    /**
     * Perangkat tambahan dari stok Gudang yang SEDANG terpasang di pelanggan
     * (ADHOC-190). Dipakai untuk menghitung perangkat yang sudah terpenuhi
     * pada langkah Perangkat Tambahan Laporan Pemasangan. Baris v1 teks
     * bebas (tanpa `inventory_serial_id`) sengaja tidak dihitung.
     */
    public function scopeInstalledFromWarehouse($query)
    {
        return $query->whereNotNull('inventory_serial_id')->where('status', 'terpasang');
    }

    /**
     * Semua perangkat yang masih terpasang, termasuk baris teks bebas
     * (legacy/`Tanpa Stok`) — kandidat "yang diganti" di Maintenance/C-REQ.
     */
    public function scopeTerpasang($query)
    {
        return $query->where('status', 'terpasang');
    }

    /**
     * Perangkat yang MASIH ada di pelanggan — baris 'ditarik' cuma riwayat.
     * Dipakai untuk semua hitungan jatah paket: tanpa ini, tiap penggantian
     * perangkat (baris lama 'ditarik' + baris baru) menggerus jatah dan
     * memblok Tambah Perangkat/ganti paket padahal jumlah fisiknya tetap.
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'ditarik');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<InventorySerial, $this>
     */
    public function inventorySerial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class);
    }
}
