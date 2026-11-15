<?php

namespace App\Models;

use App\Enums\PushDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PushDelivery extends Model
{
    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'notification_class',
        'status',
        'sent_at',
        'delivered_at',
        'escalated_at',
    ];

    protected $casts = [
        'status' => PushDeliveryStatus::class,
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
