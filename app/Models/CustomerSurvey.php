<?php

namespace App\Models;

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
    'required_tools',
    'cable_estimation_meter',
    'nearest_odp',
    'survey_photo',
    'survey_note',
])]
class CustomerSurvey extends Model
{
    protected function casts(): array
    {
        return [
            'survey_date' => 'date',
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
}
