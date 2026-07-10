<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkflowTransitionPermission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'from_status',
        'to_status',
        'permission_name',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_workflow_transition');
    }
}
