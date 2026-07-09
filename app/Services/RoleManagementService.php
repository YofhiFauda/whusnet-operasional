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
        // Sanitize to unique integers to avoid duplicate inserts and exceptions
        $sanitizedPermissions = array_values(array_unique(array_map('intval', $permissions)));

        DB::transaction(function () use ($role, $sanitizedPermissions, $permissions) {
            // Lock row Role — cegah race condition kalau ada 2 request PUT matrix
            // nyaris bersamaan (mis. double-klik submit) yang bisa bikin sync()
            // di kedua request sama-sama baca state lama lalu tabrakan insert
            // pivot yang sama (unique constraint role_permissions).

            $role = Role::where('id', $role->id)->lockForUpdate()->firstOrFail();

            $oldPermissions = $role->permissions()->pluck('permissions.id')->toArray();

            // 1. Eksekusi Relasi Utama
            $role->permissions()->sync($sanitizedPermissions);

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
