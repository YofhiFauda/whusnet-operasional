<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\EffectiveAccessService;
use Carbon\CarbonInterface;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function it_defaults_status_to_terpasang_and_links_inventory_serial(): void
    {
        $pop = $this->createPop('ADDSN');
        $customer = $this->createCustomer($pop, 'TEST-ADD-SN-001');
        $category = ItemCategory::create([
            'code' => 'ap_test_sn',
            'name' => 'AP Test SN',
            'default_unit' => 'pcs',
            'is_active' => true,
            'is_customer_extra_device' => true,
        ]);
        $item = Item::create([
            'code' => 'AP-TEST-SN',
            'name' => 'AP Test SN',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
        ]);
        $serial = InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => 'ADD-SN-0001',
            'status' => SerialStatus::DEPLOYED,
        ]);

        $device = CustomerAdditionalDevice::create([
            'customer_id' => $customer->id,
            'device_type' => 'AP',
            'inventory_serial_id' => $serial->id,
        ]);

        $device->refresh();
        $this->assertSame('terpasang', $device->status);
        $this->assertInstanceOf(InventorySerial::class, $device->inventorySerial);
        $this->assertSame($serial->id, $device->inventorySerial->id);
    }

    #[Test]
    public function it_casts_retrieved_at_as_datetime(): void
    {
        $pop = $this->createPop('ADDRT');
        $customer = $this->createCustomer($pop, 'TEST-ADD-RT-001');

        $device = CustomerAdditionalDevice::create([
            'customer_id' => $customer->id,
            'device_type' => 'STB',
            'status' => 'ditarik',
            'retrieved_at' => '2026-10-17 09:30:00',
        ]);

        $device->refresh();
        $this->assertInstanceOf(CarbonInterface::class, $device->retrieved_at);
        $this->assertSame('2026-10-17 09:30:00', $device->retrieved_at->format('Y-m-d H:i:s'));
        $this->assertSame('ditarik', $device->status);
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

    public function test_additional_device_destroy_rejects_device_belonging_to_another_customer(): void
    {
        $pop = $this->createPop('ADD7');
        $technician = $this->createUserWithRole('Teknisi');
        $customerA = $this->createCustomer($pop, 'TEST-ADD-007-A');
        $customerB = $this->createCustomer($pop, 'TEST-ADD-007-B');
        $deviceOfA = $customerA->additionalDevices()->create([
            'device_type' => 'AP',
            'brand' => 'TP-Link',
        ]);

        // URL dirakit dengan customer B, tapi device-nya milik customer A —
        // guard `$additionalDevice->customer_id === $customer->id` di
        // CustomerAdditionalDeviceController::destroy() wajib nolak ini
        // (404), bukan kehapus karena IDnya valid.
        $response = $this->actingAs($technician)->delete(route('customers.additional-device.destroy', [$customerB, $deviceOfA]));

        $response->assertStatus(404);
        $this->assertDatabaseHas('customer_additional_devices', ['id' => $deviceOfA->id]);
    }

    public function test_additional_device_store_rejects_customer_outside_pop_scope(): void
    {
        $allowedPop = $this->createPop('ADD8');
        $otherPop = $this->createPop('ADD9');
        $technician = User::factory()->create();
        $technician->role_id = Role::where('name', 'Teknisi')->firstOrFail()->id;
        $technician->save();

        UserRoleScope::create([
            'user_id' => $technician->id,
            'role_id' => $technician->role_id,
            'scope_type' => 'selected_pop',
        ])->targets()->create(['pop_id' => $allowedPop->id]);
        app(EffectiveAccessService::class)->clearCache($technician);

        $customerOutsideScope = $this->createCustomer($otherPop, 'TEST-ADD-008');

        $response = $this->actingAs($technician)->post(route('customers.additional-device.store', $customerOutsideScope), [
            'device_type' => 'AP',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('customer_additional_devices', 0);
    }

    public function test_additional_device_destroy_rejects_customer_outside_pop_scope(): void
    {
        $allowedPop = $this->createPop('ADD10');
        $otherPop = $this->createPop('ADD11');
        $technician = User::factory()->create();
        $technician->role_id = Role::where('name', 'Teknisi')->firstOrFail()->id;
        $technician->save();

        UserRoleScope::create([
            'user_id' => $technician->id,
            'role_id' => $technician->role_id,
            'scope_type' => 'selected_pop',
        ])->targets()->create(['pop_id' => $allowedPop->id]);
        app(EffectiveAccessService::class)->clearCache($technician);

        $customerOutsideScope = $this->createCustomer($otherPop, 'TEST-ADD-009');
        $device = $customerOutsideScope->additionalDevices()->create([
            'device_type' => 'AP',
            'brand' => 'TP-Link',
        ]);

        $response = $this->actingAs($technician)->delete(route('customers.additional-device.destroy', [$customerOutsideScope, $device]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('customer_additional_devices', ['id' => $device->id]);
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
