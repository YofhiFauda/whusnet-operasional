<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all permissions
        $allPermissions = Permission::all();

        // 1. Owner & Admin Pusat (Full Access)
        $ownerRole = Role::where('name', 'Owner')->first();
        $adminPusatRole = Role::where('name', 'Admin Pusat')->first();

        if ($ownerRole) {
            $ownerRole->permissions()->sync($allPermissions->pluck('id'));
        }

        if ($adminPusatRole) {
            $adminPusatRole->permissions()->sync($allPermissions->pluck('id'));
        }

        // 2. Admin Cabang Permissions
        $adminCabangRole = Role::where('name', 'Admin Cabang')->first();
        if ($adminCabangRole) {
            $adminCabangPermissions = [
                'view_pop',
                'view_users',
                'view_roles',
                'view_packages',
                'create_customers',
                'edit_customers',
                'view_customers',
                'validate_customer_data',
                'create_invoices',
                'view_invoices',
                'view_payments',
                'fill_survey',
                'fill_installation',
                'fill_device',
                'view_customer_documents',
                'upload_customer_documents',
                'view_reports_own_pop',
                'view_audit_logs',
            ];
            $permissionIds = Permission::whereIn('name', $adminCabangPermissions)->pluck('id');
            $adminCabangRole->permissions()->sync($permissionIds);
        }

        // 3. Finance/Kasir Permissions
        $financeRole = Role::where('name', 'Finance/Kasir')->first();
        if ($financeRole) {
            $financePermissions = [
                'view_pop',
                'view_packages',
                'view_customers',
                'create_invoices',
                'view_invoices',
                'create_payments',
                'view_payments',
                'edit_payments',
                'view_reports_own_pop',
            ];
            $permissionIds = Permission::whereIn('name', $financePermissions)->pluck('id');
            $financeRole->permissions()->sync($permissionIds);
        }

        // 4. Teknisi Permissions
        $teknisiRole = Role::where('name', 'Teknisi')->first();
        if ($teknisiRole) {
            $teknisiPermissions = [
                'view_pop',
                'view_packages',
                'view_customers',
                'fill_survey',
                'fill_installation',
                'fill_device',
                'view_customer_documents',
                'upload_customer_documents',
            ];
            $permissionIds = Permission::whereIn('name', $teknisiPermissions)->pluck('id');
            $teknisiRole->permissions()->sync($permissionIds);
        }

        // 5. Customer Service Permissions
        $csRole = Role::where('name', 'Customer Service')->first();
        if ($csRole) {
            $csPermissions = [
                'view_pop',
                'view_packages',
                'create_customers',
                'edit_customers',
                'view_customers',
                'view_customer_documents',
            ];
            $permissionIds = Permission::whereIn('name', $csPermissions)->pluck('id');
            $csRole->permissions()->sync($permissionIds);
        }
    }
}
