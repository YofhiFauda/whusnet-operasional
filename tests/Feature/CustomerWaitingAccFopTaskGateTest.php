<?php

namespace Tests\Feature;

use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\CustomerRegistrationVerificationFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test: Pelanggan yang berstatus 'waiting_acc' (setelah survey selesai dilaporkan)
 * tidak boleh masuk ke dalam Task FOP (Pemasangan) sebelum diverifikasi oleh CS
 * dan status berubah menjadi 'waiting_installation'.
 */
class CustomerWaitingAccFopTaskGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(CustomerRegistrationVerificationFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pelanggan_waiting_acc_tidak_masuk_ke_task_fop_pemasangan_saat_buka_fop_tasks(): void
    {
        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $admin = User::factory()->create(['status' => 'active', 'role_id' => $adminRole->id]);

        $pop = Pop::factory()->create(['type' => 'cabang']);
        $package = InternetPackage::create([
            'package_code' => 'TEST10',
            'name' => 'Test 10 Mbps',
            'category' => 'Paket Home Broadband',
            'package_group' => 'Test',
            'bandwidth_label' => '10 Mbps',
            'monthly_price' => 100000,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create([
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'status' => 'waiting_acc',
        ]);

        $this->actingAs($admin);

        // Buka /fop-tasks yang menjalankan autoSyncAndCalculatePriority()
        $this->get(route('fop-tasks.index'))->assertOk();

        // Pastikan TIDAK ADA FopTask PEMASANGAN yang terbentuk untuk customer berstatus waiting_acc
        $this->assertDatabaseMissing('fop_tasks', [
            'customer_id' => $customer->id,
            'category' => TaskType::PEMASANGAN->value,
        ]);
    }

    public function test_pelanggan_waiting_acc_masuk_ke_task_fop_pemasangan_setelah_diverifikasi_cs(): void
    {
        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $admin = User::factory()->create(['status' => 'active', 'role_id' => $adminRole->id]);

        $pop = Pop::factory()->create(['type' => 'cabang']);
        $package = InternetPackage::create([
            'package_code' => 'TEST10',
            'name' => 'Test 10 Mbps',
            'category' => 'Paket Home Broadband',
            'package_group' => 'Test',
            'bandwidth_label' => '10 Mbps',
            'monthly_price' => 100000,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create([
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'status' => 'waiting_acc',
        ]);

        $this->actingAs($admin);

        // CS memproses survey ke Tim (verifikasi ACC)
        $response = $this->post(route('customers.verification.process-to-team', $customer));
        $response->assertSessionDoesntHaveErrors();

        $customer->refresh();
        $this->assertSame('waiting_installation', $customer->status);

        // Task dan FopTask PEMASANGAN otomatis dibuat
        $this->assertDatabaseHas('tasks', [
            'customer_id' => $customer->id,
            'task_type' => TaskType::PEMASANGAN->value,
        ]);
        $this->assertDatabaseHas('fop_tasks', [
            'customer_id' => $customer->id,
            'category' => TaskType::PEMASANGAN->value,
        ]);
    }
}
