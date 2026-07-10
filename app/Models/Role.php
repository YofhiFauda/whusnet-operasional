<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'guard_name', 'description', 'is_system'])]
class Role extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Role Management';
    protected array $auditEvents = ['created', 'updated', 'deleted'];

    public function isFullAccessRole(): bool
    {
        return in_array($this->name, ['Owner', 'Admin', 'Admin Pusat'], true);
    }

    public function isTechnicianRole(): bool
    {
        return $this->name === 'Teknisi';
    }

    /**
     * Apakah role ini role Owner (role tertinggi yang dilindungi sistem).
     */
    public function isOwner(): bool
    {
        return $this->code === 'owner';
    }

    /**
     * Apakah role ini tidak boleh dihapus dari sistem.
     */
    public function isProtected(): bool
    {
        $protected = config('rbac.protected_roles', ['owner']);
        return in_array($this->code, $protected, true);
    }

    /**
     * Apakah user tertentu berhak mengelola (edit/delete/set permission) role ini.
     *
     * Aturan:
     * 1. Owner selalu bisa mengelola semua role.
     * 2. Role Owner hanya bisa dikelola oleh user Owner.
     * 3. Role lain: cek rbac.role_management_scope.
     *
     * @param \App\Models\User $user
     */
    public function canBeManagedBy(\App\Models\User $user): bool
    {
        $userRoleCode = $user->role?->code;

        // Owner dapat mengelola semua role
        if ($userRoleCode === 'owner') {
            return true;
        }

        // Role Owner hanya bisa dikelola oleh Owner
        if ($this->code === 'owner') {
            return false;
        }

        // Cek scope dari config
        $scope = config('rbac.role_management_scope', []);
        $allowedTargets = $scope[$userRoleCode] ?? [];

        return in_array($this->code, $allowedTargets, true);
    }

    /**
     * Apakah user tertentu berhak membuat role baru.
     *
     * Owner dapat membuat role apa saja.
     * Role lain tidak dapat membuat role baru (create = Owner only).
     *
     * @param \App\Models\User $user
     */
    public static function canBeCreatedBy(\App\Models\User $user): bool
    {
        return $user->role?->code === 'owner';
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

    public function userScopes()
    {
        return $this->hasMany(UserRoleScope::class);
    }

    public function workflowTransitions()
    {
        return $this->belongsToMany(WorkflowTransitionPermission::class, 'role_workflow_transition');
    }

    /**
     * Get the workflow transition permissions associated with the role (via permissions pivot).
     */
    public function workflowTransitionPermissions()
    {
        return $this->belongsToMany(
            WorkflowTransitionPermission::class,
            'role_permissions',
            'role_id',
            'permission_id',
            'id',
            'permission_name'
        )->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
         ->whereColumn('permissions.code', 'workflow_transition_permissions.permission_name');
    }
}
