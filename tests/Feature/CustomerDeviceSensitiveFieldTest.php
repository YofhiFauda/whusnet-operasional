<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\InternetPackage;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDeviceSensitiveFieldTest extends TestCase
{
    use RefreshDatabase;

    protected $pop;

    protected $customer;

    protected $device;

    protected $financeRole;

    protected $helpdeskRole;

    protected $teknisiRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'P-TEST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::create([
            'name' => 'Test Package',
            'package_code' => 'PKG-TEST',
            'category' => 'broadband',
            'package_group' => 'basic',
            'bandwidth_label' => '10 Mbps',
            'download_speed' => 10,
            'upload_speed' => 10,
            'monthly_price' => 150000,
            'status' => 'active',
        ]);

        // Setup Customer
        $this->customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'internet_package_id' => $package->id,
            'status' => 'active',
        ]);

        $this->device = CustomerDevice::create([
            'customer_id' => $this->customer->id,
            'device_type' => 'router',
            'pppoe_username' => 'john_pppoe',
            'pppoe_password' => 'secret_pppoe',
            'wifi_ssid' => 'john_wifi',
            'wifi_password' => 'secret_wifi',
            'ip_address' => '192.168.1.100',
            'vlan_id' => 100,
        ]);

        $this->financeRole = Role::where('name', 'Helpdesk')->firstOrFail();
        $this->attachPermission($this->financeRole, 'customers.view');
        $this->attachPermission($this->financeRole, 'customers.detail.devices.view');

        $this->helpdeskRole = Role::where('name', 'Helpdesk')->firstOrFail();
        $this->attachPermission($this->helpdeskRole, 'customers.view');
        $this->attachPermission($this->helpdeskRole, 'customers.detail.devices.view');
        $this->attachPermission($this->helpdeskRole, 'customers.detail.devices.create');
        $this->attachPermission($this->helpdeskRole, 'customers.detail.devices.update');

        $this->teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->attachPermission($this->teknisiRole, 'customers.view');
        $this->attachPermission($this->teknisiRole, 'customers.detail.devices.view');
        $this->attachPermission($this->teknisiRole, 'customers.detail.devices.create');
        $this->attachPermission($this->teknisiRole, 'customers.detail.devices.update');
        $this->attachPermission($this->teknisiRole, 'customers.detail.devices.view_sensitive');
        $this->attachPermission($this->teknisiRole, 'customers.detail.devices.update_sensitive');
    }

    private function attachPermission(Role $role, string $code)
    {
        $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'feature_id' => null, 'action_id' => null, 'module' => 'test', 'description' => 'test']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    private function createUserWithRole($role)
    {
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);

        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $this->pop->id,
        ]);

        return $user;
    }

    public function test_finance_cannot_see_sensitive_fields_in_ui()
    {
        $finance = $this->createUserWithRole($this->financeRole);

        $response = $this->actingAs($finance)->get('/customers/'.$this->customer->id);

        $response->assertStatus(200);
        // It should render ******** instead of secret_pppoe
        $response->assertSee('********');
        $response->assertDontSee('secret_pppoe');
        $response->assertDontSee('secret_wifi');
        $response->assertDontSee('192.168.1.100');
    }

    public function test_helpdesk_cannot_update_sensitive_fields()
    {
        $helpdesk = $this->createUserWithRole($this->helpdeskRole);

        $response = $this->actingAs($helpdesk)->post('/customers/'.$this->customer->id.'/device', [
            'device_type' => 'router',
            'pppoe_password' => 'hacked_password',
            'wifi_password' => 'hacked_wifi',
            'ip_address' => '10.0.0.1',
        ]);

        $response->assertRedirect();

        // Refresh and check DB, should not be changed
        $this->device->refresh();
        $this->assertEquals('secret_pppoe', $this->device->pppoe_password);
        $this->assertEquals('secret_wifi', $this->device->wifi_password);
        $this->assertEquals('192.168.1.100', $this->device->ip_address);
    }

    public function test_teknisi_can_see_and_update_sensitive_fields()
    {
        $teknisi = $this->createUserWithRole($this->teknisiRole);

        // Can see — teknisi gak punya customers.detail.view (Detail Pelanggan
        // diblok), tab Perangkat diakses lewat halaman terpisah
        // customers.fieldwork (lihat CustomerFieldworkController).
        $response = $this->actingAs($teknisi)->get(route('customers.fieldwork', $this->customer->id));
        $response->assertStatus(200);
        $response->assertSee('secret_pppoe');
        $response->assertSee('secret_wifi');
        $response->assertSee('192.168.1.100');

        // Can update
        $response = $this->actingAs($teknisi)->post('/customers/'.$this->customer->id.'/device', [
            'device_type' => 'router',
            'pppoe_username' => 'new_pppoe',
            'pppoe_password' => 'new_secret_pppoe',
            'wifi_ssid' => 'new_wifi',
            'wifi_password' => 'new_secret_wifi',
            'ip_address' => '10.10.10.10',
        ]);

        $response->assertRedirect();

        // Refresh and check DB
        $this->device->refresh();
        $this->assertEquals('new_secret_pppoe', $this->device->pppoe_password);
        $this->assertEquals('new_secret_wifi', $this->device->wifi_password);
        $this->assertEquals('10.10.10.10', $this->device->ip_address);
    }
}
