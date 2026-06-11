<?php

namespace Tests\Feature;

use App\Models\Permission;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_seeder_inserts_expected_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->assertSame(24, Permission::query()->count());

        $expectedPermissions = [
            'manage_pop',
            'view_pop',
            'manage_users',
            'view_users',
            'manage_roles',
            'view_roles',
            'manage_packages',
            'view_packages',
            'create_customers',
            'import_customers',
            'edit_customers',
            'view_customers',
            'validate_customer_data',
            'create_invoices',
            'view_invoices',
            'create_payments',
            'view_payments',
            'edit_payments',
            'fill_survey',
            'fill_installation',
            'fill_device',
            'view_reports_all',
            'view_reports_own_pop',
            'view_audit_logs',
        ];

        foreach ($expectedPermissions as $name) {
            $this->assertDatabaseHas('permissions', [
                'name' => $name,
            ]);
        }
    }

    public function test_permissions_are_correctly_grouped_by_module(): void
    {
        $this->seed(PermissionSeeder::class);

        $popPermission = Permission::where('name', 'manage_pop')->firstOrFail();
        $this->assertEquals('POP/Cabang', $popPermission->module);

        $invoicePermission = Permission::where('name', 'create_invoices')->firstOrFail();
        $this->assertEquals('Billing/Tagihan', $invoicePermission->module);

        $devicePermission = Permission::where('name', 'fill_device')->firstOrFail();
        $this->assertEquals('Data Teknis', $devicePermission->module);
    }
}
