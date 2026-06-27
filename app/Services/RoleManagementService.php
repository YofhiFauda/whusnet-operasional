<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class RoleManagementService
{
    /**
     * Sync permissions to a role and record audit log.
     *
     * @param Role $role
     * @param array $permissions
     * @return void
     */
    public function syncPermissions(Role $role, array $permissions): void
    {
        DB::transaction(function () use ($role, $permissions) {
            $oldPermissions = $role->permissions()->pluck('permissions.id')->toArray();
            
            // 1. Eksekusi Relasi Utama
            $role->permissions()->sync($permissions);

            // Clear cache for all users with this role
            $users = $role->users()->get();
            $effectiveAccessService = app(\App\Services\EffectiveAccessService::class);
            foreach ($users as $user) {
                $effectiveAccessService->clearCache($user);
            }

            // 2. Pencatatan Audit Log Secara Terpusat
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Role Management',
                'action' => 'sync_permissions',
                'auditable_type' => Role::class,
                'auditable_id' => $role->id,
                'old_values' => ['permissions' => $oldPermissions],
                'new_values' => ['permissions' => $permissions],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        });
    }
}
