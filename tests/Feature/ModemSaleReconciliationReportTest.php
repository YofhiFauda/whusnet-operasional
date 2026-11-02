<?php

namespace Tests\Feature;

use App\Enums\CReqVerificationStatus;
use App\Enums\FopTaskPriority;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\FopTask;
use App\Models\InternetPackage;
use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\RevenueCategory;
use App\Models\RevenueSubcategory;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskBillingItem;
use App\Models\TaskCreqDetail;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan "SN Terjual Belum Ditagih" — rancangan 2026-10-31, `docs/plan/
 * warehouse/rancangan-modem-terjual-visibilitas-dan-rekonsiliasi.md` §5/§7.
 */
class ModemSaleReconciliationReportTest extends TestCase
{
    use RefreshDatabase;

    private Item $modem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $category = ItemCategory::create(['code' => 'modem_ont_msr_test', 'name' => 'Modem/ONT MSR Test', 'default_unit' => 'pcs']);
        $this->modem = Item::create([
            'code' => 'ONT-MSR-TEST', 'name' => 'Modem ONT MSR Test', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
    }

    private function adminUser(): User
    {
        $role = Role::where('code', 'admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        return $user;
    }

    private function customerWithStatus(string $status, string $code): Customer
    {
        return Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '0812340006',
            'status' => $status,
            'pop_id' => $this->popOrCreate()->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);
    }

    private function internetPackage(): InternetPackage
    {
        return InternetPackage::firstOrCreate(
            ['package_code' => 'MSR-PKG'],
            ['name' => 'Paket MSR Test', 'category' => 'Home', 'package_group' => 'Basic', 'bandwidth_label' => '10 Mbps', 'monthly_price' => 150000]
        );
    }

    private function popOrCreate()
    {
        return Pop::firstOrCreate(
            ['code' => 'MSR-POP'],
            ['pop_code' => 'MSRP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang MSR Test', 'type' => 'cabang', 'status' => 'active']
        );
    }

    private function createSale(Customer $customer, string $serialNumber, User $actor, ?FopTask $fopTask = null): InventorySerialSale
    {
        $serial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => $serialNumber,
            'status' => 'installed',
            'ownership' => 'customer',
        ]);

        return InventorySerialSale::create([
            'inventory_serial_id' => $serial->id,
            'customer_id' => $customer->id,
            'fop_task_id' => $fopTask?->id,
            'price' => 150000,
            'created_by' => $actor->id,
        ]);
    }

    #[Test]
    public function tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $user = User::factory()->create(['role_id' => $teknisiRole->id]);

        $response = $this->actingAs($user)->get(route('reports.modem-sale-reconciliation.index'));

        $response->assertForbidden();
    }

    #[Test]
    public function sale_pelanggan_menunggu_verifikasi_cs_tampil_wajar_bukan_anomali(): void
    {
        $actor = $this->adminUser();
        $customer = $this->customerWithStatus('verification_admin', 'MSR-CS-001');
        $this->createSale($customer, 'SN-MSR-CS-001', $actor);

        $response = $this->actingAs($actor)->get(route('reports.modem-sale-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('SN-MSR-CS-001');
        $response->assertSee('Menunggu verifikasi CS');
        $response->assertViewHas('summary', fn ($summary) => $summary['total'] === 1 && $summary['anomaly'] === 0);
    }

    #[Test]
    public function sale_pelanggan_menunggu_bd_tampil_wajar_bukan_anomali(): void
    {
        $actor = $this->adminUser();
        $customer = $this->customerWithStatus('waiting_business_development_verification', 'MSR-BD-001');
        $this->createSale($customer, 'SN-MSR-BD-001', $actor);

        $response = $this->actingAs($actor)->get(route('reports.modem-sale-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Menunggu BD');
        $response->assertViewHas('summary', fn ($summary) => $summary['total'] === 1 && $summary['anomaly'] === 0);
    }

    #[Test]
    public function sale_tanpa_penaut_dan_tanpa_status_wajar_jadi_anomali(): void
    {
        $actor = $this->adminUser();
        $customer = $this->customerWithStatus('active', 'MSR-ANOM-001');
        $this->createSale($customer, 'SN-MSR-ANOM-001', $actor);

        $response = $this->actingAs($actor)->get(route('reports.modem-sale-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Anomali');
        $response->assertViewHas('summary', fn ($summary) => $summary['total'] === 1 && $summary['anomaly'] === 1);
    }

    #[Test]
    public function sale_yang_sudah_punya_invoice_item_tidak_tampil(): void
    {
        $actor = $this->adminUser();
        $customer = $this->customerWithStatus('active', 'MSR-BILLED-001');
        $sale = $this->createSale($customer, 'SN-MSR-BILLED-001', $actor);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'package_name_snapshot' => 'Paket MSR Test',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'billing_cycle' => 'monthly',
            'service_status' => 'aktif',
            'billing_status' => 'active',
            'contract_type' => 'beli',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'AWAL-MSR-TEST-001',
            'invoice_type' => 'awal',
            'customer_id' => $customer->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->internetPackage()->id,
            'pop_id' => $customer->pop_id,
            'billing_period' => now()->format('Y-m'),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'remaining_amount' => 150000,
            'paid_amount' => 0,
            'invoice_status' => 'belum_dibayar',
            'created_by' => $actor->id,
        ]);
        $category = RevenueCategory::where('code', RevenueCategory::CODE_JASA_PERBAIKAN)->firstOrFail();
        $subcategory = RevenueSubcategory::where('code', 'jual_modem')->firstOrFail();
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'revenue_category_id' => $category->id,
            'revenue_subcategory_id' => $subcategory->id,
            'category_name_snapshot' => $category->name,
            'subcategory_name_snapshot' => $subcategory->name,
            'description' => 'Pembelian modem test · SN SN-MSR-BILLED-001',
            'inventory_serial_sale_id' => $sale->id,
            'amount' => 150000,
            'sort_order' => 10,
        ]);

        $response = $this->actingAs($actor)->get(route('reports.modem-sale-reconciliation.index'));

        $response->assertOk();
        $response->assertDontSee('SN-MSR-BILLED-001');
        $response->assertViewHas('summary', fn ($summary) => $summary['total'] === 0);
    }

    #[Test]
    public function sale_creq_menunggu_approve_cs_tampil_wajar(): void
    {
        $actor = $this->adminUser();
        $customer = $this->customerWithStatus('active', 'MSR-CREQ-001');

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-MSR-001',
            'task_date' => now(),
            'category' => TaskType::CREQ->value,
            'tugas' => 'TEST_MSR_Pelanggan',
            'pop_id' => $customer->pop_id,
            'customer_id' => $customer->id,
            'status' => TaskStatus::SELESAI->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
        $task = Task::create([
            'task_number' => 'TASK-MSR-001',
            'task_type' => TaskType::CREQ->value,
            'title' => 'Customer Request MSR Test',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'status' => TaskStatus::SELESAI->value,
            'fop_task_id' => $fopTask->id,
        ]);
        $fopTask->update(['task_id' => $task->id]);
        TaskCreqDetail::create([
            'task_id' => $task->id,
            'category' => 'tambah_modem',
            'is_billable' => true,
            'verification_status' => CReqVerificationStatus::PENDING->value,
        ]);

        $sale = $this->createSale($customer, 'SN-MSR-CREQ-001', $actor, $fopTask);

        $response = $this->actingAs($actor)->get(route('reports.modem-sale-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Menunggu approve CS');
        $response->assertViewHas('summary', fn ($summary) => $summary['total'] === 1 && $summary['anomaly'] === 0);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'package_name_snapshot' => 'Paket MSR Test',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'billing_cycle' => 'monthly',
            'service_status' => 'aktif',
            'billing_status' => 'active',
            'contract_type' => 'beli',
        ]);

        // Begitu sudah ditagih lewat task_billing_items, baris lenyap dari laporan.
        TaskBillingItem::create([
            'task_id' => $task->id,
            'invoice_id' => Invoice::create([
                'invoice_number' => 'MANUAL-MSR-TEST-001',
                'invoice_type' => 'manual',
                'customer_id' => $customer->id,
                'customer_service_id' => $service->id,
                'internet_package_id' => $this->internetPackage()->id,
                'pop_id' => $customer->pop_id,
                'billing_period' => now()->format('Y-m'),
                'issue_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'subtotal' => 150000,
                'discount' => 0,
                'ppn' => 0,
                'total_amount' => 150000,
                'remaining_amount' => 150000,
                'paid_amount' => 0,
                'invoice_status' => 'belum_dibayar',
                'created_by' => $actor->id,
            ])->id,
            'name' => 'Jual Modem',
            'amount' => 150000,
            'inventory_serial_sale_id' => $sale->id,
            'created_by' => $actor->id,
        ]);

        $response = $this->actingAs($actor)->get(route('reports.modem-sale-reconciliation.index'));
        $response->assertDontSee('SN-MSR-CREQ-001');
    }
}
