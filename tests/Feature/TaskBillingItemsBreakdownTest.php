<?php

namespace Tests\Feature;

use App\Enums\CReqVerificationStatus;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskCreqDetail;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\CreqBillingVerificationFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Breakdown per-item generik di `TaskCreqBillingController::approve()` — task
 * C-REQ TANPA InventorySerialSale (mayoritas kategori C-REQ) mengisi N baris
 * {name, amount} bebas, bukan satu `amount`/`description` tunggal seperti
 * sebelum Task 4. Kasus dengan InventorySerialSale (baris "Jual Modem" wajib)
 * dicakup `TaskCreqBillingModemSaleBreakdownTest`; kasus task MTN polos
 * dicakup `TaskMaintenanceBillingApprovalTest`.
 */
class TaskBillingItemsBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Customer $customer;

    protected User $helpdesk;

    protected Task $task;

    protected TaskCreqDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(CreqBillingVerificationFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'CREQBI-TEST', 'pop_code' => 'CQB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Breakdown Test', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-CREQBI-001',
            'full_name' => 'Pelanggan Breakdown Item',
            'primary_phone' => '0812340098',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $package = InternetPackage::create([
            'package_code' => 'PKT-CREQBI',
            'name' => 'Paket Breakdown Item',
            'category' => 'Home Broadband',
            'package_group' => 'Net',
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => 150000,
            'is_active' => true,
        ]);

        CustomerService::create([
            'customer_id' => $this->customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => now()->subMonth()->toDateString(),
            'due_date' => now()->toDateString(),
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $helpdeskRole = Role::where('code', 'helpdesk')->firstOrFail();
        $this->helpdesk = User::factory()->create(['role_id' => $helpdeskRole->id]);
        $this->helpdesk->roleScopes()->create(['role_id' => $helpdeskRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $this->task = Task::create([
            'task_number' => 'TASK-CREQBI-0001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::CREQ->value,
            'title' => 'Customer Request Breakdown Item Test',
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => now(),
            'created_by' => $this->helpdesk->id,
            'updated_by' => $this->helpdesk->id,
        ]);

        $this->detail = TaskCreqDetail::create([
            'task_id' => $this->task->id,
            'category' => 'lainnya',
            'category_custom_name' => 'Pasang Kabel Ekstra',
            'is_billable' => true,
            'billing_note' => 'Pasang kabel ekstra 20m + ongkos jasa.',
            'verification_status' => CReqVerificationStatus::PENDING->value,
        ]);
    }

    #[Test]
    public function approve_creq_tanpa_sale_row_breakdown_n_baris_jadi_satu_invoice(): void
    {
        $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $this->task), [
            'manual_subtype_name' => 'Pasang Kabel Ekstra',
            'items' => [
                ['name' => 'Pasang Kabel Ekstra', 'amount' => '50.000'],
                ['name' => 'Ongkos Jasa', 'amount' => '30.000'],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::VERIFIED, $this->detail->verification_status);

        $invoice = $this->detail->invoice;
        $this->assertNotNull($invoice);
        $this->assertEquals(80000, (float) $invoice->total_amount);
        $this->assertStringContainsString('Pasang Kabel Ekstra: Rp50.000', $invoice->description);
        $this->assertStringContainsString('Ongkos Jasa: Rp30.000', $invoice->description);
        $this->assertDatabaseCount('task_billing_items', 2);
    }

    #[Test]
    public function approve_items_kosong_ditolak(): void
    {
        $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $this->task), [
            'manual_subtype_name' => 'Pasang Kabel Ekstra',
            'items' => [],
        ]);

        $response->assertSessionHasErrors('items');

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::PENDING, $this->detail->verification_status);
        $this->assertDatabaseCount('task_billing_items', 0);
    }

    #[Test]
    public function approve_item_amount_nol_ditolak(): void
    {
        $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $this->task), [
            'manual_subtype_name' => 'Pasang Kabel Ekstra',
            'items' => [['name' => 'Jasa', 'amount' => '0']],
        ]);

        $response->assertSessionHasErrors('items.0.amount');

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::PENDING, $this->detail->verification_status);
        $this->assertDatabaseCount('task_billing_items', 0);
    }

    #[Test]
    public function approve_item_name_kosong_ditolak(): void
    {
        $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $this->task), [
            'manual_subtype_name' => 'Pasang Kabel Ekstra',
            'items' => [['name' => '', 'amount' => '50.000']],
        ]);

        $response->assertSessionHasErrors('items.0.name');

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::PENDING, $this->detail->verification_status);
        $this->assertDatabaseCount('task_billing_items', 0);
    }
}
