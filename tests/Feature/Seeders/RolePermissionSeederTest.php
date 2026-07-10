<?php

namespace Tests\Feature\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ActionSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed required prerequisites
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_it_assigns_all_permissions_to_owner(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = Role::where('code', 'owner')->firstOrFail();
        $totalPermissions = Permission::count();

        $this->assertGreaterThan(0, $totalPermissions);
        $this->assertEquals($totalPermissions, $owner->permissions()->count());
    }

    public function test_it_does_not_assign_payment_permission_to_teknisi(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $teknisi = Role::where('code', 'teknisi')->firstOrFail();
        
        $hasPaymentPermission = $teknisi->permissions()
            ->where('code', 'like', 'payments.%')
            ->exists();

        $this->assertFalse($hasPaymentPermission, 'Teknisi should not have payment permissions');
    }

    public function test_it_does_not_assign_sensitive_device_view_to_pop_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $popAdmin = Role::where('code', 'pop_admin')->firstOrFail();

        $hasSensitiveView = $popAdmin->permissions()
            ->where('code', 'customers.detail.devices.view_sensitive')
            ->exists();

        $this->assertFalse($hasSensitiveView, 'POP Admin should not have sensitive device view permission');
    }

    public function test_it_does_not_assign_sensitive_device_view_to_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = Role::where('code', 'admin')->firstOrFail();

        $hasSensitiveView = $admin->permissions()
            ->where('code', 'customers.detail.devices.view_sensitive')
            ->exists();

        $this->assertFalse($hasSensitiveView, 'Admin should not have sensitive device view permission');
    }

    public function test_it_does_not_assign_invoice_update_to_helpdesk(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $helpdesk = Role::where('code', 'helpdesk')->firstOrFail();

        $hasInvoiceUpdate = $helpdesk->permissions()
            ->where('code', 'invoices.update')
            ->exists();

        $this->assertFalse($hasInvoiceUpdate, 'Helpdesk should not have invoices.update permission');
    }
}
