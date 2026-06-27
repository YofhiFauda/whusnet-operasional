<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserRoleScope;
use Illuminate\Support\Facades\DB;

class UserScopeManagementService
{
    /**
     * Sync user role scope and pop targets, and record audit log if changed.
     *
     * @param User $user
     * @param string $scopeType
     * @param array $popIds
     * @return void
     */
    public function syncUserRoleScope(User $user, string $scopeType, array $popIds): void
    {
        DB::transaction(function () use ($user, $scopeType, $popIds) {
            $oldRoleScope = UserRoleScope::where('user_id', $user->id)->where('role_id', $user->role_id)->first();
            $oldScopeType = $oldRoleScope ? $oldRoleScope->scope_type->value : null;
            $oldPopIds = $oldRoleScope ? $oldRoleScope->getTargetPopIds() : [];

            $roleScope = UserRoleScope::updateOrCreate(
                ['user_id' => $user->id, 'role_id' => $user->role_id],
                ['scope_type' => $scopeType]
            );
            
            UserRoleScope::where('user_id', $user->id)
                ->where('role_id', '!=', $user->role_id)
                ->delete();

            $roleScope->targets()->delete();
            if ($scopeType === 'selected_pop') {
                $targets = collect($popIds)->map(fn($id) => ['pop_id' => $id]);
                $roleScope->targets()->createMany($targets->all());
            }
            
            // Update backward compatibility table user_pops
            $syncPops = $scopeType === 'selected_pop' ? $popIds : [];
            $user->pops()->sync($syncPops);

            // Clear access cache
            app(\App\Services\EffectiveAccessService::class)->clearCache($user);

            // Normalisasi array untuk komparasi (sorting string/int)
            sort($oldPopIds);
            sort($popIds);

            $isChanged = $oldScopeType !== $scopeType || $oldPopIds !== $popIds;

            if ($isChanged) {
                AuditLog::create([
                    'user_id' => auth()->id() ?? $user->id,
                    'module' => 'User Role Scope',
                    'action' => 'sync_user_scope',
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'old_values' => [
                        'scope_type' => $oldScopeType,
                        'pop_ids' => $oldPopIds
                    ],
                    'new_values' => [
                        'scope_type' => $scopeType,
                        'pop_ids' => $popIds
                    ],
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'created_at' => now(),
                ]);
            }
        });
    }
}
