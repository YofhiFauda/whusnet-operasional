<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Dynamically generate permissions first from config/rbac.php
        app(\App\Services\PermissionGeneratorService::class)->generate();

        $permissionsByRole = [
            'owner' => ['*'], // Owner gets all permissions

            'atasan' => [
                'dashboard.view',
                'pops.view',
                'users.view',
                'roles.view',
                'packages.view',
                'sla_timeline.view',
                'customers.view',
                'customers.detail.survey.view',
                'customers.detail.installation.view',
                'customers.detail.devices.view',
                'invoices.view',
                'invoices.print',
                'payments.view',
                'payments.print',
                'reports.view',
                'reports.export',
                'audit_logs.view',
                'audit_logs.export', // assuming audit_logs has export
                'fop_tasks.view',
            ],

            'admin' => [
                'dashboard.view',
                'pops.*',
                'users.*',
                'roles.*',
                'packages.*',
                'sla_timeline.*',
                'customers.view',
                'customers.create',
                'customers.update',
                'customers.delete',
                'customers.import.*',
                'customers.detail.*', // Access to all detail sections
                'invoices.*', // Ex: view, create, update, delete, cancel, print
                'payments.*', // Ex: view, create, update, validate, reject, print
                'reports.*',
                'audit_logs.view',
                'audit_logs.export',
                'fop_tasks.*',
                'task.lookup', // dipakai modal /fop-tasks (autocomplete pelanggan + cek konflik)
            ],

            'noc' => [
                'dashboard.view',
                'pops.view',
                'packages.view',
                'sla_timeline.view',
                'customers.view',
                'customers.detail.identity.view',
                'customers.detail.address.view',
                'customers.detail.packages.view',
                'customers.detail.survey.view',
                'customers.detail.survey.update',
                'customers.detail.survey.validate',
                'customers.detail.survey.reject',
                'customers.detail.installation.view',
                'customers.detail.installation.update',
                'customers.detail.installation.validate',
                'customers.detail.installation.activate',
                'customers.detail.devices.view',
                'customers.detail.devices.update',
                'customers.detail.devices.view_sensitive',
                'customers.detail.devices.update_sensitive',
                'customers.detail.documents.view',
                'customers.detail.documents.download',
                'invoices.view',
                'invoices.print',
                'payments.print', // Based on matrix
            ],

            'helpdesk' => [
                'dashboard.view',
                'pops.view',
                'packages.view',
                'sla_timeline.view',
                'customers.view',
                'customers.create',
                'customers.update',
                'customers.detail.identity.view',
                'customers.detail.identity.update',
                'customers.detail.address.view',
                'customers.detail.address.update',
                'customers.detail.packages.view',
                'customers.detail.packages.update',
                'customers.detail.survey.view',
                'customers.detail.installation.view',
                'customers.detail.devices.view',
                'customers.detail.documents.view',
                'customers.detail.documents.upload',
                'customers.detail.documents.download',
                'invoices.view',
                'invoices.create',
                'invoices.print',
                'payments.view',
                'payments.create',
                'payments.print',
                'reports.view',
                'reports.export',
            ],

            'fop' => [
                'dashboard.view',
                'customers.view',
                'customers.detail.identity.view',
                'customers.detail.address.view',
                'customers.detail.packages.view',
                'customers.detail.survey.view',
                'customers.detail.survey.update',
                'customers.detail.survey.validate',
                'customers.detail.survey.reject',
                'customers.detail.installation.view',
                'customers.detail.installation.update',
                'customers.detail.installation.activate',
                'customers.detail.devices.view',
                'customers.detail.devices.update',
                'customers.detail.documents.view',
                'customers.detail.documents.upload',
                'customers.detail.documents.download',
                'fop_tasks.*',
            ],

            'teknisi' => [
                'dashboard.view',
                'customers.view',
                'customers.detail.identity.view',
                'customers.detail.address.view',
                'customers.detail.packages.view',
                'customers.detail.survey.view',
                'customers.detail.survey.update',
                'customers.detail.installation.view',
                'customers.detail.installation.update',
                'customers.detail.installation.activate',
                'customers.detail.devices.view',
                'customers.detail.devices.update',
                'customers.detail.devices.view_sensitive',
                'customers.detail.devices.update_sensitive',
                'customers.detail.documents.view',
                'customers.detail.documents.upload',
                'customers.detail.documents.download',
            ],

            'sales' => [
                'dashboard.view',
                'customers.view',
                'customers.create',
                'customers.update',
                'customers.detail.identity.view',
                'customers.detail.identity.update',
                'customers.detail.address.view',
                'customers.detail.address.update',
                'customers.detail.packages.view',
                'customers.detail.packages.update',
                'customers.detail.documents.view',
                'customers.detail.documents.upload',
                'customers.detail.documents.download',
            ],

            'pop_admin' => [
                'dashboard.view',
                'pops.view',
                'packages.view',
                'sla_timeline.view',
                'customers.view',
                'customers.create',
                'customers.update',
                'customers.import.*',
                'customers.detail.*', // Except sensitive devices, we will subtract below
                'invoices.view',
                'invoices.create',
                'invoices.print',
                'payments.view',
                'payments.create',
                'payments.validate',
                'payments.reject',
                'payments.print',
                'reports.view',
                'reports.export',
            ],
        ];

        // Retrieve all available permissions grouped by feature
        $allPermissions = Permission::all();
        $allPermissionCodes = $allPermissions->pluck('code')->toArray();

        foreach ($permissionsByRole as $roleCode => $wantedPermissions) {
            $role = Role::where('code', $roleCode)->first();
            if (!$role) {
                continue;
            }

            $finalPermissionCodes = [];

            if (in_array('*', $wantedPermissions)) {
                $finalPermissionCodes = $allPermissionCodes;
            } else {
                foreach ($wantedPermissions as $wp) {
                    if (str_ends_with($wp, '.*')) {
                        $prefix = substr($wp, 0, -2);
                        // Add all permissions starting with prefix
                        foreach ($allPermissionCodes as $ap) {
                            if (str_starts_with($ap, $prefix)) {
                                $finalPermissionCodes[] = $ap;
                            }
                        }
                    } else {
                        if (in_array($wp, $allPermissionCodes)) {
                            $finalPermissionCodes[] = $wp;
                        } else {
                            Log::warning("Seeder: Permission {$wp} not found in database for role {$roleCode}");
                        }
                    }
                }
            }

            // Remove sensitive permissions for pop_admin based on matrix
            if ($roleCode === 'pop_admin') {
                $finalPermissionCodes = array_filter($finalPermissionCodes, function($code) {
                    return !in_array($code, [
                        'customers.detail.devices.view_sensitive',
                        'customers.detail.devices.update_sensitive'
                    ]);
                });
            }

            // Also for admin, matrix says no sensitive access, except if specified.
            if ($roleCode === 'admin') {
                $finalPermissionCodes = array_filter($finalPermissionCodes, function($code) {
                    return !in_array($code, [
                        'customers.detail.devices.view_sensitive',
                        'customers.detail.devices.update_sensitive'
                    ]);
                });
            }

            // FOP tidak boleh ubah Tipe Task lewat wildcard fop_tasks.* — harus di-grant eksplisit.
            if ($roleCode === 'fop') {
                $finalPermissionCodes = array_filter($finalPermissionCodes, function($code) {
                    return $code !== 'fop_tasks.update_sensitive';
                });
            }

            $finalPermissionCodes = array_unique($finalPermissionCodes);
            $permissionIds = $allPermissions->whereIn('code', $finalPermissionCodes)->pluck('id')->toArray();

            // Sync efficiently
            $role->permissions()->sync($permissionIds);
        }
    }
}
