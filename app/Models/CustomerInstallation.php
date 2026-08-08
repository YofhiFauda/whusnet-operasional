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
])]
class CustomerInstallation extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Data Teknis';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    protected array $auditHidden = [
        'installation_photo',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'finished_date' => 'date',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
}
