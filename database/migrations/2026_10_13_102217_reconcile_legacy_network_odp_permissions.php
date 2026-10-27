<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repair databases where the old reshape migration ran after the node type
     * seeder had already created network_nodes alongside network_odps.
     */
    public function up(): void
    {
        $reconciled = DB::transaction(function (): bool {
            $oldFeatureId = DB::table('features')->where('code', 'network_odps')->value('id');
            $newFeatureId = DB::table('features')->where('code', 'network_nodes')->value('id');

            if ($oldFeatureId === null || $newFeatureId === null) {
                return false;
            }

            foreach (DB::table('permissions')->where('feature_id', $oldFeatureId)->get() as $oldPermission) {
                $newPermissionId = DB::table('permissions')
                    ->where('feature_id', $newFeatureId)
                    ->where('action_id', $oldPermission->action_id)
                    ->value('id');

                if ($newPermissionId === null) {
                    $newPermissionId = DB::table('permissions')->insertGetId([
                        'feature_id' => $newFeatureId,
                        'action_id' => $oldPermission->action_id,
                        'code' => 'network_nodes.'.substr($oldPermission->code, strlen('network_odps.')),
                        'name' => null,
                        'module' => $oldPermission->module,
                        'description' => $oldPermission->description,
                        'created_at' => $oldPermission->created_at,
                        'updated_at' => $oldPermission->updated_at,
                    ]);
                }

                foreach (DB::table('role_permissions')->where('permission_id', $oldPermission->id)->pluck('role_id') as $roleId) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $newPermissionId,
                    ]);
                }
            }

            DB::table('features')->where('id', $oldFeatureId)->delete();

            return true;
        });

        if ($reconciled) {
            DB::table('users')->orderBy('id')->pluck('id')->each(
                fn ($userId) => Cache::forget("user.{$userId}.permissions")
            );
        }
    }

    /**
     * Grant reconciliation is a one-way data repair.
     */
    public function down(): void {}
};
