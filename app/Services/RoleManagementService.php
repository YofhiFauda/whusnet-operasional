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

        // S6: Auto-grant parent view permissions bottom-up for any checked child permission
        if (!empty($sanitizedPermissions)) {
            $allPermissions = \App\Models\Permission::with('feature')->get();
            $permissionsMap = $allPermissions->keyBy('id');
            $allFeatures = \App\Models\Feature::all()->keyBy('id');
            $viewOverrides = config('rbac.view_permission_overrides', []);

            $addedIds = [];
            foreach ($sanitizedPermissions as $permId) {
                $perm = $permissionsMap->get($permId);
                if (!$perm || !$perm->feature) {
                    continue;
                }

                // Cek fitur MILIK PERMISSION ITU SENDIRI dulu (sibling-view),
                // baru jalan ke induknya (ancestor-view). Contoh sibling-view:
                // task.manage & task.view.all sama-sama nempel di feature
                // 'tasks.fop' (flat, gak ada nesting) — cek versi lama cuma
                // jalanin loop mulai dari PARENT, jadi gak pernah nyentuh
                // 'tasks.fop' punya sendiri kalau permission yg dicentang
                // emang langsung anggota 'tasks.fop'.
                $currentFeature = $perm->feature;
                while ($currentFeature !== null) {
                    // Kode permission "view" ikut konvensi "{feature_code}.view",
                    // kecuali fitur yang didaftarkan di config/rbac.php > view_permission_overrides.
                    $viewCode = $viewOverrides[$currentFeature->code] ?? "{$currentFeature->code}.view";

                    if ($viewCode !== $perm->code) {
                        $viewPerm = $allPermissions->first(fn($p) => $p->code === $viewCode);
                        if ($viewPerm) {
                            $addedIds[] = $viewPerm->id;
                        }
                    }

                    $currentFeature = $currentFeature->parent_id !== null
                        ? $allFeatures->get($currentFeature->parent_id)
                        : null;
                }
            }
            if (!empty($addedIds)) {
                $sanitizedPermissions = array_values(array_unique(array_merge($sanitizedPermissions, $addedIds)));
            }
        }

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
