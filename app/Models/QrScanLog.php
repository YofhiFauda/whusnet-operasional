<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak SEMUA scan QR pelanggan, termasuk yang gagal (docs/plan/qr-code/
 * rancangan-qr-pelanggan-final.md §4.2). Scan gagal adalah sinyal, bukan
 * sampah — jangan hapus baris `result != success` demi "kebersihan" data.
 *
 * Kebijakan retensi (§4.2): detail 90 hari, lalu di-aggregate & baris detail
 * dihapus. Belum diimplementasikan di Fase 1 — dicatat sebagai TODO Fase
 * lanjutan supaya tidak terlupa.
 */
#[Fillable([
    'customer_qr_token_id', 'customer_id', 'user_id',
    'purpose', 'result', 'reason',
    'latitude', 'longitude', 'accuracy_meters', 'distance_meters',
    'task_id', 'ticket_id',
    'ip_address', 'user_agent',
    'scanned_at',
])]
class QrScanLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsTo<CustomerQrToken, $this>
     */
    public function qrToken(): BelongsTo
    {
        return $this->belongsTo(CustomerQrToken::class, 'customer_qr_token_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
