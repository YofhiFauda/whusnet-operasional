<?php

namespace App\Models;

use App\Enums\ScopeType;
use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserRoleScope extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'User Role Scope';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    protected $fillable = [
        'user_id',
        'role_id',
        'scope_type',
    ];

    protected $casts = [
        'scope_type' => ScopeType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(UserRoleScopeTarget::class);
    }

    /**
     * Get target POP IDs as array
     */
    public function getTargetPopIds(): array
    {
        return $this->targets()->pluck('pop_id')->toArray();
    }
}
