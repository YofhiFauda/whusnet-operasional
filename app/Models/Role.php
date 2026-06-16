<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'guard_name', 'description'])]
class Role extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Role Management';

    public function isFullAccessRole(): bool
    {
        return in_array($this->name, ['Owner', 'Admin', 'Admin Pusat'], true);
    }

    public function isTechnicianRole(): bool
    {
        return $this->name === 'Teknisi';
    }

    /**
     * Get the permissions associated with the role.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Get the users associated with the role.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
