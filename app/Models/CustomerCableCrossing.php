<?php

namespace App\Models;

use App\Enums\CableCrossingStatus;
use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rute kabel melintang jalan (ADHOC-205). Dicatat saat Pemasangan, wajib
 * diselesaikan (ditarik / tidak bisa ditarik) saat task DEAC.
 * Rancangan: docs/customer-lifecycle/rancangan-kabel-melintang.md.
 */
#[Fillable([
    'customer_id',
    'customer_installation_id',
    'inventory_roll_id',
    'nama_jalan',
    'jenis_jalan',
    'meter_terpakai',
    'status',
    'foto_bukti',
    'dicatat_oleh',
    'dicatat_at',
    'deact_task_id',
    'diselesaikan_at',
    'foto_penarikan',
    'alasan_tidak_ditarik',
])]
class CustomerCableCrossing extends Model
{
    use HasFactory, RecordsAuditLogs;

    protected string $auditModule = 'Pemasangan';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    protected array $auditHidden = [
        'foto_bukti',
        'foto_penarikan',
    ];

    protected function casts(): array
    {
        return [
            'status' => CableCrossingStatus::class,
            'meter_terpakai' => 'decimal:2',
            'dicatat_at' => 'datetime',
            'diselesaikan_at' => 'datetime',
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
     * @return BelongsTo<CustomerInstallation, $this>
     */
    public function installation(): BelongsTo
    {
        return $this->belongsTo(CustomerInstallation::class, 'customer_installation_id');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function deactTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'deact_task_id');
    }

    /**
     * @return HasMany<CustomerCableCrossingPoint, $this>
     */
    public function points(): HasMany
    {
        return $this->hasMany(CustomerCableCrossingPoint::class)->orderBy('urutan');
    }
}
