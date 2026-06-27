<?php

namespace App\Services;

use App\Models\Action;
use App\Models\Feature;
use App\Models\Permission;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermissionGeneratorService
{
    /**
     * Generate permissions based on config/rbac.php mapping.
     *
     * @return array Summary of generation process
     */
    public function generate(): array
    {
        $allowedActions = Config::get('rbac.allowed_actions', []);
        
        $summary = [
            'total_features_processed' => 0,
            'permissions_created' => 0,
            'permissions_skipped' => 0,
            'errors' => []
        ];

        DB::beginTransaction();

        try {
            // Load all features and actions into memory keyed by code for quick lookup
            $features = Feature::all()->keyBy('code');
            $actions = Action::all()->keyBy('code');

            foreach ($allowedActions as $featureCode => $actionCodes) {
                // Ensure feature exists
                if (!$features->has($featureCode)) {
                    $summary['errors'][] = "Feature code '{$featureCode}' not found in database.";
                    continue;
                }

                $feature = $features->get($featureCode);
                $summary['total_features_processed']++;

                foreach ($actionCodes as $actionCode) {
                    // Ensure action exists
                    if (!$actions->has($actionCode)) {
                        $summary['errors'][] = "Action code '{$actionCode}' not found in database.";
                        continue;
                    }

                    $action = $actions->get($actionCode);
                    $permissionCode = "{$featureCode}.{$actionCode}";

                    // Check if it already exists
                    $existing = Permission::where('feature_id', $feature->id)
                        ->where('action_id', $action->id)
                        ->first();

                    if (!$existing) {
                        // Create new permission
                        Permission::create([
                            'feature_id' => $feature->id,
                            'action_id' => $action->id,
                            'code' => $permissionCode,
                            // To maintain backward compatibility, name and module can be null
                            'name' => null,
                            'module' => null,
                        ]);
                        $summary['permissions_created']++;
                    } else {
                        // If it exists but code is different (unlikely), update it
                        if ($existing->code !== $permissionCode) {
                            $existing->update(['code' => $permissionCode]);
                        }
                        $summary['permissions_skipped']++;
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate permissions: ' . $e->getMessage());
            throw $e;
        }

        return $summary;
    }
}
