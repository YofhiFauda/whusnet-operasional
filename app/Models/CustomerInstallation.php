<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'installation_status',
    'scheduled_date',
    'scheduled_time',
    'technician_id',
    'technician_2_id',
    'technician_3_id',
    'finished_date',
    'installation_photo',
    'contract_photo',
    'signature_photo',
    'installation_note',
    'start_time',
    'end_time',
    'started_at',
    'completed_at',
    'technicians',
    'assigned_at',
    'fop_id',
    'selected_inventory_serial_id',
    'selected_inventory_roll_id',
    'roll_meters_used',
    'modem_acquisition_choice',
    'manual_device_brand',
    'manual_device_model',
    'manual_device_serial_number',
    'modem_sale_price',
    'extra_device_shortage',
    'extra_device_shortage_reason',
    'extra_devices_reported_at',
    'extra_device_serial_ids',
])]
class CustomerInstallation extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Data Teknis';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    protected array $auditHidden = [
        'installation_photo',
    ];

    /**
     * Nominal "Beli dari Gudang" yang ikut Invoice Awal. SATU sumber untuk
     * CustomerVerificationController::finalVerify() (yang menagih) DAN
     * halaman Verifikasi Admin (yang menampilkan) — dulu cuma dihitung di
     * controller, jadi admin gak bisa lihat modemnya dijual/disewa sebelum
     * menyetujui (laporan user 2026-11-14). 'beli_sendiri'/'sewa_gudang'/
     * pelanggan kontrak sewa = 0.
     */
    public function modemSaleAmountForInvoice(): float
    {
        return $this->modem_acquisition_choice === 'beli_gudang'
            ? (float) $this->modem_sale_price
            : 0.0;
    }

    /**
     * Label cara pemilikan modem untuk tampilan. Null = belum dipilih
     * (pelanggan kontrak sewa memang tidak pernah memilih — modem sewa).
     */
    public function modemAcquisitionLabel(): ?string
    {
        return match ($this->modem_acquisition_choice) {
            'beli_gudang' => 'Beli dari Gudang',
            'beli_sendiri' => 'Beli Sendiri (milik pelanggan)',
            'sewa_gudang' => 'Sewa Gudang',
            default => null,
        };
    }

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'finished_date' => 'date',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'roll_meters_used' => 'decimal:2',
            'modem_sale_price' => 'decimal:2',
            'extra_device_shortage' => 'boolean',
            'extra_devices_reported_at' => 'datetime',
            'extra_device_serial_ids' => 'array',
        ];
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
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technician2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_2_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technician3(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_3_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fop(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fop_id');
    }

    /**
     * Pointer DRAFT (ADHOC-54) — lihat komentar migration
     * `add_selected_inventory_serial_id_to_customer_installations_table`.
     *
     * @return BelongsTo<InventorySerial, $this>
     */
    public function selectedInventorySerial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class, 'selected_inventory_serial_id');
    }

    /**
     * Pointer DRAFT roll kabel — sama pola `selectedInventorySerial()`.
     *
     * @return BelongsTo<InventoryRoll, $this>
     */
    public function selectedInventoryRoll(): BelongsTo
    {
        return $this->belongsTo(InventoryRoll::class, 'selected_inventory_roll_id');
    }
}
