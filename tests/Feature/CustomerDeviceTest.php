<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
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

class CustomerDeviceTest extends TestCase
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

        // RolePermissionSeeder does not reliably attach the device permissions used
        // by this test suite (same gap worked around in CustomerDeviceSensitiveFieldTest),
        // so attach them explicitly to keep these tests deterministic.
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->attachPermission($teknisiRole, 'customers.view');
        $this->attachPermission($teknisiRole, 'customers.detail.devices.view');
        $this->attachPermission($teknisiRole, 'customers.detail.devices.create');
        $this->attachPermission($teknisiRole, 'customers.detail.devices.update');
        $this->attachPermission($teknisiRole, 'customers.detail.devices.view_sensitive');
        $this->attachPermission($teknisiRole, 'customers.detail.devices.update_sensitive');

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

    public function test_technician_can_fill_customer_device(): void
    {
        $pop = $this->createPop('DEV1');
        $technician = $this->createUserWithRole('Teknisi');
        $customer = $this->createCustomer($pop, 'TEST-DEV-001');

        $response = $this->actingAs($technician)
            ->post(route('customers.device.store', $customer->id), [
                'device_type' => 'ont',
                'brand' => 'Huawei',
                'model' => 'HG8245H',
                'serial_number' => 'SN123456',
                'mac_address' => 'AA:BB:CC:DD:EE:FF',
                'pppoe_username' => 'user001@whusnet',
                'pppoe_password' => 'secret-pppoe',
                'wifi_ssid' => 'WHUSNET-001',
                'wifi_password' => 'secret-wifi',
                'ip_address' => '192.168.1.10',
                'vlan_id' => 100,
                'odp' => 'ODP-DEV-01',
                'odp_port' => '1',
                'signal_rx_power' => -18.75,
                'connection_mode' => 'pppoe',
                'technical_note' => 'Perangkat terpasang dan koneksi normal.',
                'passive_device' => 'Antena Grid 25dBi',
                'passive_device_type' => 'antena_radio',
                'passive_device_qty' => '1',
                'passive_device_note' => 'Dipasang di tiang belakang rumah',
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('customer_devices', [
            'customer_id' => $customer->id,
            'device_type' => 'ont',
            'brand' => 'Huawei',
            'serial_number' => 'SN123456',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'pppoe_password' => 'secret-pppoe',
            'wifi_password' => 'secret-wifi',
        ]);
        $this->assertDatabaseHas('customer_technical_details', [
            'customer_id' => $customer->id,
            'passive_device_type' => 'antena_radio',
            'passive_device_qty' => '1',
            'passive_device_note' => 'Dipasang di tiang belakang rumah',
        ]);
    }

    public function test_invalid_passive_device_type_is_rejected(): void
    {
        $pop = $this->createPop('DEV6');
        $technician = $this->createUserWithRole('Teknisi');
        $customer = $this->createCustomer($pop, 'TEST-DEV-006');

        $response = $this->actingAs($technician)
            ->from(route('customers.show', $customer->id))
            ->post(route('customers.device.store', $customer->id), [
                'device_type' => 'ont',
                'passive_device_type' => 'not_a_real_category',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['passive_device_type']);
    }

    public function test_device_data_is_visible_on_customer_detail(): void
    {
        $pop = $this->createPop('DEV2');
        $technician = $this->createUserWithRole('Teknisi');
        $customer = $this->createCustomer($pop, 'TEST-DEV-002');

        $customer->customerDevice()->create([
            'device_type' => 'router',
            'brand' => 'MikroTik',
            'model' => 'hAP ac2',
            'serial_number' => 'RTR001',
            'mac_address' => '11:22:33:44:55:66',
            'pppoe_username' => 'router002@whusnet',
            'pppoe_password' => 'router-secret',
            'wifi_ssid' => 'WHUSNET-ROUTER',
            'wifi_password' => 'wifi-secret',
            'ip_address' => '10.10.10.2',
            'vlan_id' => 200,
            'odp' => 'ODP-DEV-02',
            'odp_port' => '2',
            'signal_rx_power' => -19.50,
            'connection_mode' => 'router',
            'technical_note' => 'Router pelanggan sudah dikonfigurasi manual.',
        ]);

        $response = $this->actingAs($technician)
            ->get(route('customers.show', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('Data Perangkat Pelanggan');
        $response->assertSee('MikroTik');
        $response->assertSee('router002@whusnet');
        $response->assertSee('router-secret');
        $response->assertSee('wifi-secret');
        $response->assertSee('Router pelanggan sudah dikonfigurasi manual.');
    }

    public function test_finance_cannot_fill_customer_device(): void
    {
        $pop = $this->createPop('DEV3');
        $finance = $this->createUserWithRole('Helpdesk');
        $customer = $this->createCustomer($pop, 'TEST-DEV-003');

        $response = $this->actingAs($finance)
            ->post(route('customers.device.store', $customer->id), [
                'device_type' => 'ont',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('customer_devices', [
            'customer_id' => $customer->id,
        ]);
    }

    public function test_customer_service_cannot_see_sensitive_device_fields(): void
    {
        $pop = $this->createPop('DEV4');
        $customerService = $this->createUserWithRole('Helpdesk');
        $customer = $this->createCustomer($pop, 'TEST-DEV-004');

        $customer->customerDevice()->create([
            'device_type' => 'ont',
            'brand' => 'ZTE',
            'model' => 'F609',
            'serial_number' => 'ZTE001',
            'mac_address' => 'AA:11:BB:22:CC:33',
            'pppoe_username' => 'zte004@whusnet',
            'pppoe_password' => 'hidden-pppoe',
            'wifi_ssid' => 'WHUSNET-CS',
            'wifi_password' => 'hidden-wifi',
            'ip_address' => '192.168.100.1',
            'connection_mode' => 'bridge',
        ]);

        $response = $this->actingAs($customerService)
            ->get(route('customers.show', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('Data Perangkat Pelanggan');
        $response->assertSee('ZTE');
        $response->assertSee('zte004@whusnet');
        $response->assertDontSee('hidden-pppoe');
        $response->assertDontSee('hidden-wifi');
        $response->assertSee('********');
        $response->assertDontSee('Isi / Ubah Perangkat');
    }

    public function test_device_tab_falls_back_to_migrated_technical_detail(): void
    {
        $pop = $this->createPop('DEV4B');
        $technician = $this->createUserWithRole('Teknisi');
        $customer = $this->createCustomer($pop, 'TEST-DEV-004B');

        CustomerTechnicalDetail::create([
            'customer_id' => $customer->id,
            'old_report_id' => 'RPT-004B',
            'old_customer_id' => $customer->customer_code,
            'old_request_id' => 'RQ000716',
            'connection_type' => 'KABEL',
            'router_or_ont_serial' => 'ZICG10237307',
            'ip_address' => 'SMN_RQ000716@PurnamaAyuLestari',
            'odp_number' => '236 sandia',
            'odp_port' => '6 sandia',
            'olt_port' => 'Olt sandia',
            'passive_device' => 'KABEL',
            'note' => 'Purnama Ayu Lestari Putri',
        ]);

        $response = $this->actingAs($technician)
            ->get(route('customers.show', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('Data Perangkat Migrasi');
        $response->assertSee('ZICG10237307');
        $response->assertSee('SMN_RQ000716@PurnamaAyuLestari');
        $response->assertSee('Data ini tampil dari detail teknis migrasi karena tabel perangkat pelanggan belum terisi.');
        $response->assertDontSee('Belum ada data perangkat');
    }

    public function test_invalid_mac_and_ip_are_rejected(): void
    {
        $pop = $this->createPop('DEV5');
        $technician = $this->createUserWithRole('Teknisi');
        $customer = $this->createCustomer($pop, 'TEST-DEV-005');

        $response = $this->actingAs($technician)
            ->from(route('customers.show', $customer->id))
            ->post(route('customers.device.store', $customer->id), [
                'device_type' => 'ont',
                'mac_address' => 'invalid-mac',
                'ip_address' => '999.999.999.999',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['mac_address', 'ip_address']);
        $this->assertDatabaseMissing('customer_devices', [
            'customer_id' => $customer->id,
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
            'phone' => '0812345678',
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

        return $user;
    }
}
