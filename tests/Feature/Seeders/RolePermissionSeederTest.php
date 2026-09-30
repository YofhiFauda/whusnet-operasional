<?php

namespace Tests\Feature\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_it_assigns_sensitive_device_view_to_admin_per_ui_matrix(): void
    {
        // Dibalik 2026-09-29: Role Matrix UI memberi Admin view_sensitive,
        // seeder menyalin UI.
        $this->seed(RolePermissionSeeder::class);

        $admin = Role::where('code', 'admin')->firstOrFail();

        $hasSensitiveView = $admin->permissions()
            ->where('code', 'customers.detail.devices.view_sensitive')
            ->exists();

        $this->assertTrue($hasSensitiveView, 'Admin should have sensitive device view permission');
    }

    public function test_customer_service_role_is_seeded_with_its_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $customerService = Role::where('code', 'customer_service')->firstOrFail();
        $codes = $customerService->permissions()->pluck('code');

        // Permission verifikasi registrasi/C-REQ datang dari feature seeder
        // yang tak dijalankan di setUp — cukup cek yang dari PermissionSeeder.
        $this->assertContains('customers.view', $codes);
        $this->assertContains('users.create', $codes);
        $this->assertNotContains('payments.create', $codes);
    }

    public function test_all_seeded_roles_are_system_roles_so_their_code_is_locked(): void
    {
        // Role code dirujuk langsung oleh kode aplikasi & seeder — role
        // non-sistem bisa diganti code-nya di UI dan fiturnya rusak diam-diam.
        $unlocked = Role::where('is_system', false)->pluck('code')->all();

        $this->assertSame([], $unlocked, 'Role berikut belum is_system: '.implode(', ', $unlocked));
    }

    public function test_reseeding_roles_does_not_merge_customer_service_into_helpdesk(): void
    {
        // Regresi: mapping lama 'Customer Service' -> 'Helpdesk' di RoleSeeder
        // memindah user CS ke Helpdesk lalu menghapus role-nya tiap db:seed.
        $this->seed(RoleSeeder::class);

        $this->assertTrue(Role::where('code', 'customer_service')->exists());
        $this->assertTrue(Role::where('code', 'helpdesk')->exists());
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
