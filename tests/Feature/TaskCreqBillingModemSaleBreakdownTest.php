<?php

namespace Tests\Feature;

use App\Enums\CReqVerificationStatus;
use App\Enums\FopTaskPriority;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\FopTask;
use App\Models\InternetPackage;
use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\Item;
use App\Models\ItemCategory;
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
 * Breakdown Harga Modem + Jasa di Verifikasi C-REQ (ownership modem
 * sewa/beli, Task 6, 2026-10-09).
 *
 * Kalau laporan teknisi "Beli dari Gudang" sudah menulis InventorySerialSale
 * (Task 1-3) buat task C-REQ ini, CS mengisi DUA komponen nominal terpisah
 * (harga modem + jasa) di halaman Verifikasi Biaya C-REQ — bukan satu
 * `amount` tunggal seperti kategori C-REQ lain — supaya tagihan menunjukkan
 * rincian ke pelanggan dan harga modem final (yang mungkin dikoreksi CS)
 * ditulis balik ke InventorySerialSale::price (sumber Laporan Barang
 * Terjual). Task C-REQ TANPA sale row (mayoritas kategori lain) tetap pakai
 * flow `amount` tunggal lama — regresi flow itu dicakup
 * `CreqBillingVerificationTest`, test ini cuma menambah satu kasus untuk
 * memastikan cabang baru TIDAK pernah tersentuh kalau sale row tidak ada.
 */
class TaskCreqBillingModemSaleBreakdownTest extends TestCase
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

        $this->pop = Pop::create(['code' => 'CREQMS-TEST', 'pop_code' => 'CQM', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Modem Sale Test', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-CREQMS-001',
            'full_name' => 'Pelanggan Modem Sale',
            'primary_phone' => '0812340099',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $helpdeskRole = Role::where('code', 'helpdesk')->firstOrFail();
        $this->helpdesk = User::factory()->create(['role_id' => $helpdeskRole->id]);
        $this->helpdesk->roleScopes()->create(['role_id' => $helpdeskRole->id, 'scope_type' => ScopeType::ALL_POP]);

        $this->task = Task::create([
            'task_number' => 'TASK-CREQMS-0001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::CREQ->value,
            'title' => 'Customer Request Modem Sale Test',
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => now(),
            'created_by' => $this->helpdesk->id,
            'updated_by' => $this->helpdesk->id,
        ]);

        $this->detail = TaskCreqDetail::create([
            'task_id' => $this->task->id,
            'category' => 'tambah_modem',
            'is_billable' => true,
            'billing_note' => 'Beli modem dari gudang, Rp150.000 + jasa Rp50.000.',
            'verification_status' => CReqVerificationStatus::PENDING->value,
        ]);
    }

    private function beriLayanan(): CustomerService
    {
        $package = InternetPackage::create([
            'package_code' => 'PKT-CREQMS',
            'name' => 'Paket Modem Sale',
            'category' => 'Home Broadband',
            'package_group' => 'Net',
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => 150000,
            'is_active' => true,
        ]);

        return CustomerService::create([
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
    }

    /**
     * Membuat InventorySerialSale yang nyambung ke $this->task lewat
     * FopTask (fop_tasks.task_id -> tasks.id, inventory_serial_sales.fop_task_id
     * -> fop_tasks.id) — rantai yang sama dengan yang ditulis
     * InventoryService::recordSerialSale() saat teknisi lapor "Beli dari
     * Gudang" (Task 2/3).
     */
    private function buatSaleUntukTask(float $price): InventorySerialSale
    {
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-CREQMS-0001',
            'task_date' => now(),
            'category' => TaskType::CREQ->value,
            'task_id' => $this->task->id,
            'tugas' => 'TEST-CREQMS-001_Pelanggan Modem Sale',
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'issue' => 'Beli modem dari gudang',
            'status' => TaskStatus::SELESAI->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        $category = ItemCategory::create(['code' => 'modem_ont_ms', 'name' => 'Modem/ONT Pelanggan', 'default_unit' => 'pcs']);
        $item = Item::create([
            'code' => 'ONT-CREQMS-TEST', 'name' => 'Modem ONT Beli Gudang', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
        $serial = InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => 'SN-CREQMS-001',
            'status' => SerialStatus::INSTALLED->value,
            'customer_id' => $this->customer->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        return InventorySerialSale::create([
            'inventory_serial_id' => $serial->id,
            'customer_id' => $this->customer->id,
            'fop_task_id' => $fopTask->id,
            'price' => $price,
            'created_by' => $this->helpdesk->id,
        ]);
    }

    #[Test]
    public function approve_dengan_sale_row_items_termasuk_baris_modem_jadi_satu_invoice(): void
    {
        $this->beriLayanan();
        // Harga lama dari laporan teknisi — CS mengoreksinya jadi 150.000 di
        // form ini, jadi assert di bawah juga membuktikan nilainya BENAR
        // DITULIS ULANG, bukan cuma "sudah sama dari awal".
        $sale = $this->buatSaleUntukTask(100000);

        $response = $this->actingAs($this->helpdesk)
            ->put(route('tasks.creq-billing.approve', $this->task), [
                'items' => [
                    ['name' => 'Jual Modem', 'amount' => '150.000', 'inventory_serial_sale_id' => $sale->id],
                    ['name' => 'Biaya Jasa', 'amount' => '50.000'],
                ],
            ]);

        $response->assertRedirect(route('tasks.creq-billing.show', $this->task));
        $response->assertSessionHas('success');

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::VERIFIED, $this->detail->verification_status);

        $invoice = $this->detail->invoice;
        $this->assertNotNull($invoice);
        $this->assertEquals(200000.0, (float) $invoice->total_amount);
        $this->assertStringContainsString('Jual Modem: Rp150.000', $invoice->description);
        $this->assertStringContainsString('Biaya Jasa: Rp50.000', $invoice->description);
        $this->assertDatabaseCount('task_billing_items', 2);

        $sale->refresh();
        $this->assertEquals(150000.0, (float) $sale->price);
    }

    #[Test]
    public function approve_dengan_sale_row_tanpa_baris_modem_ditolak(): void
    {
        $this->beriLayanan();
        $sale = $this->buatSaleUntukTask(100000);

        $this->actingAs($this->helpdesk)
            ->put(route('tasks.creq-billing.approve', $this->task), [
                'items' => [['name' => 'Biaya Jasa', 'amount' => '50.000']],
            ])
            ->assertSessionHasErrors('items');

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::PENDING, $this->detail->verification_status);
        $this->assertDatabaseCount('task_billing_items', 0);
        $sale->refresh();
        $this->assertNotEquals(150000.0, (float) $sale->price); // tidak tersentuh
        $this->assertEquals(100000.0, (float) $sale->price);
    }

    #[Test]
    public function approve_dengan_harga_modem_nol_ditolak(): void
    {
        $this->beriLayanan();
        $sale = $this->buatSaleUntukTask(100000);

        // Modem yang sudah terjual tidak boleh dikoreksi jadi Rp0 — invarian
        // sama dengan InventoryService::recordSerialSale() (harga > 0) DAN
        // aturan umum items.*.amount min:0.01 (berlaku untuk SEMUA baris,
        // termasuk baris modem yang auto-seed — tidak ada baris bebas Rp0 di
        // breakdown generik ini, beda dari `service_fee` lama yang boleh nol).
        $this->actingAs($this->helpdesk)
            ->put(route('tasks.creq-billing.approve', $this->task), [
                'items' => [
                    ['name' => 'Jual Modem', 'amount' => '0', 'inventory_serial_sale_id' => $sale->id],
                    ['name' => 'Biaya Jasa', 'amount' => '50.000'],
                ],
            ])
            ->assertSessionHasErrors('items.0.amount');

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::PENDING, $this->detail->verification_status);
        $sale->refresh();
        $this->assertEquals(100000.0, (float) $sale->price);

        // Begitu baris modemnya > 0, breakdown lolos.
        $this->actingAs($this->helpdesk)
            ->put(route('tasks.creq-billing.approve', $this->task), [
                'items' => [
                    ['name' => 'Jual Modem', 'amount' => '150.000', 'inventory_serial_sale_id' => $sale->id],
                    ['name' => 'Biaya Jasa', 'amount' => '50.000'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::VERIFIED, $this->detail->verification_status);
    }

    #[Test]
    public function approve_tanpa_sale_row_flow_items_generik_tetap_berjalan(): void
    {
        $this->beriLayanan();

        $response = $this->actingAs($this->helpdesk)
            ->put(route('tasks.creq-billing.approve', $this->task), [
                'items' => [
                    ['name' => 'Pasang modem tambahan, biaya sesuai tarif.', 'amount' => '150.000'],
                ],
            ]);

        $response->assertRedirect(route('tasks.creq-billing.show', $this->task));
        $response->assertSessionHas('success');

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::VERIFIED, $this->detail->verification_status);

        $invoice = $this->detail->invoice;
        $this->assertNotNull($invoice);
        $this->assertEquals(150000.0, (float) $invoice->total_amount);
        $this->assertStringContainsString('Pasang modem tambahan, biaya sesuai tarif.: Rp150.000', $invoice->description);
    }
}
