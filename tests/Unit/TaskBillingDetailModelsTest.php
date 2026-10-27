<?php

namespace Tests\Unit;

use App\Enums\CReqVerificationStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Task;
use App\Models\TaskBillingItem;
use App\Models\TaskMaintenanceBillingDetail;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Skema & relasi antrean billing MTN + breakdown per-item (Task 1 rancangan
 * verifikasi biaya C-REQ/MTN). Task & Invoice belum punya factory, jadi record
 * dibuat langsung; invoice butuh paket internet dari seeder seperti test sibling.
 */
class TaskBillingDetailModelsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function task_maintenance_billing_detail_dan_billing_item_relasi_lengkap(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create();
        $pop = Pop::factory()->create();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $task = Task::create([
            'task_number' => 'TASK-MTN-UNIT-0001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $detail = TaskMaintenanceBillingDetail::create(['task_id' => $task->id, 'is_billable' => true]);

        $this->assertTrue($task->refresh()->maintenanceBillingDetail->is($detail));
        $this->assertSame(CReqVerificationStatus::PENDING, $detail->refresh()->verification_status);
        $this->assertTrue($detail->is_billable);

        $package = InternetPackage::query()->firstOrFail();
        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => 'Paket Test',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-UNIT-BILLING-0001',
            'invoice_type' => 'manual',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-10',
            'due_date' => '2026-10-24',
            'subtotal' => 50000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 50000,
            'paid_amount' => 0,
            'remaining_amount' => 50000,
            'invoice_status' => 'belum_dibayar',
        ]);

        $item = TaskBillingItem::create([
            'task_id' => $task->id,
            'invoice_id' => $invoice->id,
            'name' => 'Biaya Jasa',
            'amount' => 50000,
            'created_by' => $user->id,
        ]);

        $this->assertTrue($task->refresh()->billingItems->first()->is($item));
        $this->assertTrue($item->task->is($task));
        $this->assertTrue($item->invoice->is($invoice));
        $this->assertNull($item->inventorySerialSale);
        $this->assertTrue($item->createdBy->is($user));
        $this->assertSame('50000.00', $item->refresh()->amount);
    }
}
