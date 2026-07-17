<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerDevice;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogGeneralTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_core_model_changes_are_recorded_in_audit_log(): void
    {
        $this->actingAs($this->owner);

        $pop = Pop::create([
            'code' => 'POP-AUDIT-GENERAL',
            'pop_code' => 'PAG',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Audit General',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $pop->update(['name' => 'POP Audit General Updated']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'module' => 'POP/Cabang',
            'action' => 'update',
            'auditable_type' => Pop::class,
            'auditable_id' => $pop->id,
        ]);

        $this->package->update(['name' => 'Paket Audit Updated']);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Master Paket',
            'action' => 'update',
            'auditable_type' => InternetPackage::class,
            'auditable_id' => $this->package->id,
        ]);

        $customer = $this->createCustomer($pop);
        $customer->update(['full_name' => 'Customer Audit Updated']);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Data Pelanggan',
            'action' => 'update',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
        ]);

        $customerLog = AuditLog::where('auditable_type', Customer::class)
            ->where('auditable_id', $customer->id)
            ->where('action', 'update')
            ->where('module', 'Data Pelanggan')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Customer Audit', $customerLog->old_values['full_name']);
        $this->assertSame('Customer Audit Updated', $customerLog->new_values['full_name']);

        $role = Role::where('name', 'Helpdesk')->firstOrFail();
        $role->update(['description' => 'Role audit updated']);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Role Management',
            'action' => 'update',
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
        ]);
    }

    public function test_invoice_user_assignment_and_technical_data_changes_are_recorded(): void
    {
        $this->actingAs($this->owner);

        $pop = Pop::create([
            'code' => 'POP-AUDIT-TECH',
            'pop_code' => 'PAT',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Audit Technical',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = $this->createCustomer($pop);
        $service = $customer->customerService()->firstOrFail();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-202606-9901',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
            'created_by' => $this->owner->id,
        ]);

        $invoice->update([
            'paid_amount' => 50000,
            'remaining_amount' => 100000,
            'invoice_status' => 'sebagian',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Tagihan',
            'action' => 'update',
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
        ]);

        $user = User::factory()->create(['status' => 'active']);

        $response = $this->put(route('users.pops.update', $user->id), [
            'pop_ids' => [$pop->id],
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'User Management',
            'action' => 'assign_pop',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $device = CustomerDevice::create([
            'customer_id' => $customer->id,
            'device_type' => 'ONT',
            'brand' => 'ZTE',
            'model' => 'F609',
            'serial_number' => 'SN-AUDIT-001',
            'mac_address' => 'AA:BB:CC:DD:EE:11',
            'pppoe_username' => 'audit-user',
            'pppoe_password' => 'secret-pppoe',
            'wifi_ssid' => 'Audit WiFi',
            'wifi_password' => 'secret-wifi',
        ]);

        $deviceLog = AuditLog::where('auditable_type', CustomerDevice::class)
            ->where('auditable_id', $device->id)
            ->where('action', 'create')
            ->firstOrFail();

        $this->assertSame('Data Teknis', $deviceLog->module);
        $this->assertArrayNotHasKey('pppoe_password', $deviceLog->new_values);
        $this->assertArrayNotHasKey('wifi_password', $deviceLog->new_values);
    }

    public function test_owner_and_admin_pusat_can_view_audit_log_page(): void
    {
        $adminPusat = User::factory()->create([
            'role_id' => Role::where('name', 'Admin')->firstOrFail()->id,
            'status' => 'active',
        ]);

        AuditLog::create([
            'user_id' => $this->owner->id,
            'module' => 'Data Pelanggan',
            'action' => 'update',
            'auditable_type' => Customer::class,
            'auditable_id' => 1,
            'old_values' => ['full_name' => 'Nama Lama'],
            'new_values' => ['full_name' => 'Nama Baru'],
            'created_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Riwayat Perubahan Penting')
            ->assertSee('Data Pelanggan')
            ->assertSee('Nama Baru');

        $this->actingAs($adminPusat)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Riwayat Perubahan Penting');
    }

    public function test_non_owner_admin_pusat_cannot_view_audit_log_page(): void
    {
        $finance = User::factory()->create([
            'role_id' => Role::where('name', 'Helpdesk')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->actingAs($finance)
            ->get(route('audit-logs.index'))
            ->assertForbidden();
    }

    private function createCustomer(Pop $pop): Customer
    {
        $customer = Customer::create([
            'customer_code' => 'C-AUDIT-' . $pop->pop_code,
            'full_name' => 'Customer Audit',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'customer_status' => 'aktif',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Audit Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Audit Test',
            'village' => 'Desa Audit',
            'district' => 'Kecamatan Audit',
            'city' => 'Kota Audit',
            'province' => 'Jawa Timur',
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return $customer->refresh();
    }
}
