<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardExecutiveMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $this->owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $this->pop = Pop::first() ?? Pop::factory()->create(['name' => 'POP Pusat']);
    }

    private function createInvoice(array $attributes): Invoice
    {
        $package = InternetPackage::query()->first();
        if (! $package) {
            $package = InternetPackage::create([
                'package_code' => 'PKG-TEST-20',
                'name' => 'Paket Test 20M',
                'category' => 'Home Broadband',
                'price' => 150000,
                'speed_mbps' => 20,
                'is_active' => true,
            ]);
        }

        $service = CustomerService::where('customer_id', $attributes['customer_id'])->first();
        if (! $service) {
            $service = CustomerService::create([
                'customer_id' => $attributes['customer_id'],
                'internet_package_id' => $package->id,
                'package_name_snapshot' => $package->name,
                'download_speed_snapshot' => '20 Mbps',
                'upload_speed_snapshot' => '10 Mbps',
                'monthly_price' => $attributes['total_amount'] ?? 150000,
                'discount' => 0,
                'ppn' => 0,
                'total_monthly_bill' => $attributes['total_amount'] ?? 150000,
                'activation_date' => now()->subMonths(3)->toDateString(),
                'due_date' => now()->toDateString(),
                'service_status' => 'aktif',
                'billing_status' => 'active',
            ]);
        }

        return Invoice::create(array_merge([
            'invoice_number' => 'INV-'.uniqid(),
            'invoice_type' => 'bulanan',
            'customer_id' => $attributes['customer_id'],
            'pop_id' => $attributes['pop_id'] ?? $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => $attributes['billing_period'] ?? now()->format('Y-m'),
            'issue_date' => now()->toDateString(),
            'due_date' => $attributes['due_date'] ?? now()->addDays(10)->toDateString(),
            'subtotal' => $attributes['total_amount'] ?? 100000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $attributes['total_amount'] ?? 100000,
            'paid_amount' => $attributes['paid_amount'] ?? 0,
            'remaining_amount' => $attributes['remaining_amount'] ?? ($attributes['total_amount'] ?? 100000),
            'invoice_status' => $attributes['invoice_status'] ?? InvoiceStatus::BELUM_DIBAYAR->value,
        ], $attributes));
    }

    public function test_owner_dashboard_renders_successfully_with_all_executive_metrics_and_charts(): void
    {
        $response = $this->actingAs($this->owner)->get('/');

        $response->assertOk();
        $response->assertSee('Operational Command Center');
        $response->assertSee('Executive Operational Pulse');
        $response->assertSee('chart-financial-trend');
        $response->assertSee('chart-payment-channels');
        $response->assertSee('chart-aging-receivables');
        $response->assertSee('chart-pop-distribution');
        $response->assertSee('chart-customer-completeness');

        $stats = $response->viewData('stats');
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('financial_trend', $stats);
        $this->assertArrayHasKey('payment_channels', $stats);
        $this->assertArrayHasKey('aging_receivables', $stats);
        $this->assertArrayHasKey('customer_completeness', $stats);
        $this->assertArrayHasKey('operational_pulse', $stats);

        $chartData = $response->viewData('customersByPopChartData');
        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('data', $chartData);
    }

    public function test_financial_trend_aggregation_calculates_correct_monthly_values(): void
    {
        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'status' => 'active',
        ]);

        $currMonth = now()->format('Y-m');
        $prevMonth = now()->subMonth()->format('Y-m');

        $this->createInvoice([
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'billing_period' => $currMonth,
            'total_amount' => 500000,
            'remaining_amount' => 500000,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ]);

        $this->createInvoice([
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'billing_period' => $prevMonth,
            'total_amount' => 300000,
            'remaining_amount' => 0,
            'invoice_status' => InvoiceStatus::LUNAS->value,
        ]);

        $response = $this->actingAs($this->owner)->get('/?period_from='.$currMonth.'&period_to='.$currMonth);
        $response->assertOk();

        $trend = $response->viewData('stats')['financial_trend'];
        $this->assertCount(6, $trend['labels']);
        $this->assertCount(6, $trend['invoices']);
        $this->assertCount(6, $trend['payments']);

        $totalInvoicesInTrend = array_sum($trend['invoices']);
        $this->assertEquals(800000, $totalInvoicesInTrend);
    }

    public function test_payment_channels_distribution_categorizes_methods(): void
    {
        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'status' => 'active',
        ]);

        $invoice = $this->createInvoice([
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'billing_period' => now()->format('Y-m'),
            'total_amount' => 1000000,
            'remaining_amount' => 500000,
        ]);

        Payment::create([
            'payment_number' => 'PAY-TEST-001',
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'amount' => 300000,
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::TRANSFER->value,
            'payment_status' => PaymentStatus::VALID->value,
        ]);

        Payment::create([
            'payment_number' => 'PAY-TEST-002',
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH->value,
            'payment_status' => PaymentStatus::VALID->value,
        ]);

        $currMonth = now()->format('Y-m');
        $response = $this->actingAs($this->owner)->get('/?period_from='.$currMonth.'&period_to='.$currMonth);

        $response->assertOk();
        $channels = $response->viewData('stats')['payment_channels'];

        $this->assertEquals(300000, $channels['transfer']['amount']);
        $this->assertEquals(1, $channels['transfer']['count']);
        $this->assertEquals(200000, $channels['cash']['amount']);
        $this->assertEquals(1, $channels['cash']['count']);
    }

    public function test_aging_receivables_categorizes_overdue_brackets(): void
    {
        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'status' => 'active',
        ]);

        // Invoice periode lalu (piutang) dengan due date 20 hari lalu (bracket 1-30 hari)
        $this->createInvoice([
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'billing_period' => now()->subMonth()->format('Y-m'),
            'total_amount' => 400000,
            'remaining_amount' => 400000,
            'due_date' => now()->subDays(20)->toDateString(),
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ]);

        // Invoice periode lalu dengan due date 45 hari lalu (bracket 31-60 hari)
        $this->createInvoice([
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'billing_period' => now()->subMonths(2)->format('Y-m'),
            'total_amount' => 250000,
            'remaining_amount' => 250000,
            'due_date' => now()->subDays(45)->toDateString(),
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ]);

        $response = $this->actingAs($this->owner)->get('/');
        $response->assertOk();

        $aging = $response->viewData('stats')['aging_receivables'];
        $this->assertEquals(400000, $aging['days_1_30']['amount']);
        $this->assertEquals(1, $aging['days_1_30']['count']);
        $this->assertEquals(250000, $aging['days_31_60']['amount']);
        $this->assertEquals(1, $aging['days_31_60']['count']);
    }

    public function test_operational_pulse_reflects_status(): void
    {
        $response = $this->actingAs($this->owner)->get('/');
        $response->assertOk();

        $pulse = $response->viewData('stats')['operational_pulse'];
        $this->assertIsArray($pulse);
        $this->assertArrayHasKey('finance', $pulse);
        $this->assertArrayHasKey('network', $pulse);
        $this->assertArrayHasKey('field', $pulse);
        $this->assertArrayHasKey('warehouse', $pulse);
        $this->assertArrayHasKey('overall', $pulse);
        $this->assertContains($pulse['overall']['status'], ['good', 'warning', 'danger']);
    }
}
