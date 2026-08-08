<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'feature_id',
    'action_id',
    'code',
    'name',
    'module',
    'description',
])]
class Permission extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Master Permission RBAC';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature_id' => 'integer',
            'action_id' => 'integer',
        ];
    }

    /**
     * Get the feature associated with the permission.
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * Get the action associated with the permission.
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }

    /**
     * Get the roles associated with the permission.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
