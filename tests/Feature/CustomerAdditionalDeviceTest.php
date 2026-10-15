<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAdditionalDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        // Pola yang sama dengan CustomerDeviceTest: RolePermissionSeeder tidak
        // reliably nge-attach permission device ke role Teknisi, jadi di-attach
        // manual supaya test ini deterministik.
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->attachPermission($teknisiRole, 'customers.view');
        $this->attachPermission($teknisiRole, 'customers.detail.devices.view');
        $this->attachPermission($teknisiRole, 'customers.detail.devices.create');
        $this->attachPermission($teknisiRole, 'customers.detail.devices.update');

        $helpdeskRole = Role::where('name', 'Helpdesk')->firstOrFail();
        $this->attachPermission($helpdeskRole, 'customers.view');
        $this->attachPermission($helpdeskRole, 'customers.detail.devices.view');
    }

    private function attachPermission(Role $role, string $code): void
    {
        $permission = Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'feature_id' => null, 'action_id' => null, 'module' => 'test', 'description' => 'test']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    public function test_additional_device_rejected_when_limit_reached(): void
    {
        $pop = $this->createPop('ADD2');
        $technician = $this->createUserWithRole('Teknisi');
        $customer = $this->createCustomer($pop, 'TEST-ADD-002');
        $package = InternetPackage::create([
            'package_code' => 'ADD-PKT-002',
            'name' => 'Paket Limit 1',
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '10 Mbps',
            'download_speed_mbps' => 10,
            'upload_speed_mbps' => 10,
            'monthly_price' => 150000,
            'max_additional_devices' => 1,
        ]);
        $this->createActiveCustomerService($customer, $package);

        $first = $this->actingAs($technician)->post(route('customers.additional-device.store', $customer), [
            'device_type' => 'AP', 'brand' => 'TP-Link',
        ]);
        $first->assertStatus(302);
        $this->assertDatabaseCount('customer_additional_devices', 1);

        $second = $this->actingAs($technician)->post(route('customers.additional-device.store', $customer), [
            'device_type' => 'AP',
        ]);
        $second->assertSessionHasErrors();
        $this->assertDatabaseCount('customer_additional_devices', 1);
    }

    public function test_additional_device_rejected_when_package_limit_is_zero(): void
    {
        $pop = $this->createPop('ADD3');
        $technician = $this->createUserWithRole('Teknisi');
        $customer = $this->createCustomer($pop, 'TEST-ADD-003');
        $package = InternetPackage::create([
            'package_code' => 'ADD-PKT-003',
            'name' => 'Paket Tanpa Device Tambahan',
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '10 Mbps',
            'download_speed_mbps' => 10,
            'upload_speed_mbps' => 10,
            'monthly_price' => 150000,
            // max_additional_devices default 0 — tidak diisi sengaja.
        ]);
        $this->createActiveCustomerService($customer, $package);

        $response = $this->actingAs($technician)->post(route('customers.additional-device.store', $customer), [
            'device_type' => 'AP',
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('customer_additional_devices', 0);
    }

    public function test_additional_device_create_requires_permission(): void
    {
        $pop = $this->createPop('ADD4');
        $helpdesk = $this->createUserWithRole('Helpdesk');
        $customer = $this->createCustomer($pop, 'TEST-ADD-004');

        $response = $this->actingAs($helpdesk)->post(route('customers.additional-device.store', $customer), [
            'device_type' => 'AP',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('customer_additional_devices', 0);
    }

    public function test_additional_device_can_be_deleted(): void
    {
        $pop = $this->createPop('ADD5');
        $technician = $this->createUserWithRole('Teknisi');
        $customer = $this->createCustomer($pop, 'TEST-ADD-005');
        $additional = $customer->additionalDevices()->create([
            'device_type' => 'AP',
            'brand' => 'TP-Link',
        ]);

        $customer->refresh();
        $completenessBefore = $customer->data_completeness_status;

        $response = $this->actingAs($technician)->delete(route('customers.additional-device.destroy', [$customer, $additional]));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('customer_additional_devices', ['id' => $additional->id]);
        $this->assertSame($completenessBefore, $customer->refresh()->data_completeness_status);
    }

    public function test_additional_device_delete_requires_permission(): void
    {
        $pop = $this->createPop('ADD6');
        $helpdesk = $this->createUserWithRole('Helpdesk');
        $customer = $this->createCustomer($pop, 'TEST-ADD-006');
        $additional = $customer->additionalDevices()->create([
            'device_type' => 'AP',
            'brand' => 'TP-Link',
        ]);

        $response = $this->actingAs($helpdesk)->delete(route('customers.additional-device.destroy', [$customer, $additional]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('customer_additional_devices', ['id' => $additional->id]);
    }

    private function createActiveCustomerService(Customer $customer, InternetPackage $package): CustomerService
    {
        return CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => $package->bandwidth_label,
            'upload_speed_snapshot' => $package->bandwidth_label,
            'monthly_price' => $package->monthly_price,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $package->monthly_price,
            'activation_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'billing_cycle' => 'monthly',
            'service_status' => 'aktif',
            'billing_status' => 'active',
            'contract_type' => 'beli',
        ]);
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createCustomer(Pop $pop, string $code): Customer
    {
        return Customer::create([
            'customer_code' => $code,
            'full_name' => 'Customer Device '.$code,
            'primary_phone' => '0812345678',
            'pop_id' => $pop->id,
            'status' => 'installed',
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::where('name', $roleName)->firstOrFail();
        $user->role_id = $role->id;
        $user->save();
        $this->giveAllPopScope($user);

        return $user;
    }
}
