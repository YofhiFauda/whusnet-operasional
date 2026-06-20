<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'survey_status',
    'survey_date',
    'start_time',
    'end_time',
    'technician_id',
    'surveyor_2_id',
    'surveyor_3_id',
    'required_tools',
    'cable_estimation_meter',
    'nearest_odp',
    'survey_photo',
    'house_photo',
    'survey_note',
    'end_date',
    'duration_minutes',
    'surveyors',
    'assigned_at',
    'fop_id',
])]
class CustomerSurvey extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Data Teknis';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    protected array $auditHidden = [
        'survey_photo',
        'house_photo',
    ];

    protected function casts(): array
    {
        return [
            'survey_date' => 'date',
            'end_date'    => 'date',
            'assigned_at' => 'datetime',
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
    public function surveyor2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_2_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function surveyor3(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_3_id');
    }
}
