<?php

namespace Tests\Feature;

use App\Enums\CReqVerificationStatus;
use App\Enums\ManualInvoiceCategory;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMaintenanceBillingDetail;
use App\Models\User;
use App\Services\ManualCategoryInvoiceService;
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
 * `TaskCreqBillingController::approve()`/`reject()` juga menangani task MTN
 * polos berbayar (`TaskMaintenanceBillingDetail`, Task 1-3 dalam
 * docs/plan/task-teknisi/rancangan-verifikasi-biaya-creq-mtn.md). Kategori
 * tagihan untuk MTN selalu di-hardcode `ManualInvoiceCategory::PERBAIKAN`
 * (bukan diturunkan dari kategori C-REQ — task MTN tidak punya kategori
 * seperti itu). Guard lockForUpdate()+cek PENDING harus berlaku juga di jalur
 * ini, bukan cuma C-REQ — kalau detail row-nya salah target (masih baca
 * TaskCreqDetail), approve kedua pada task MTN yang sudah verified akan lolos
 * tanpa guard sama sekali.
 */
class TaskMaintenanceBillingApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Customer $customer;

    protected User $helpdesk;

    protected Task $task;

    protected TaskMaintenanceBillingDetail $detail;

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

        $this->pop = Pop::create(['code' => 'MTNAPR-TEST', 'pop_code' => 'MTA', 'registration_prefix' => 'M', 'cid_prefix' => 'G', 'name' => 'Cabang MTN Approval Test', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-MTNAPR-001',
            'full_name' => 'Pelanggan MTN Approval',
            'primary_phone' => '0812340021',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $package = InternetPackage::create([
            'package_code' => 'PKT-MTNAPR',
            'name' => 'Paket MTN Approval',
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
            'task_number' => 'TASK-MTNAPR-0001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Approval Test',
            'status' => TaskStatus::SELESAI->value,
            'completed_at' => now(),
            'created_by' => $this->helpdesk->id,
            'updated_by' => $this->helpdesk->id,
        ]);

        $this->detail = TaskMaintenanceBillingDetail::create([
            'task_id' => $this->task->id,
            'is_billable' => true,
            'billing_note' => 'Ganti konektor, Rp25.000.',
            'verification_status' => CReqVerificationStatus::PENDING->value,
        ]);
    }

    #[Test]
    public function approve_task_mtn_berbayar_terbitkan_invoice_kategori_perbaikan(): void
    {
        $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $this->task), [
            'items' => [['name' => 'Ganti Konektor', 'amount' => '25.000']],
        ]);

        $response->assertSessionHasNoErrors();

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::VERIFIED, $this->detail->verification_status);

        $invoiceDetail = $this->detail->invoice;
        $this->assertNotNull($invoiceDetail);
        $this->assertSame(ManualInvoiceCategory::PERBAIKAN->value, $invoiceDetail->manual_category->value);
        $this->assertEquals(25000, (float) $invoiceDetail->total_amount);
        $this->assertDatabaseCount('task_billing_items', 1);
    }

    #[Test]
    public function approve_task_mtn_yang_sudah_verified_ditolak_422(): void
    {
        $this->detail->update([
            'verification_status' => CReqVerificationStatus::VERIFIED->value,
            'verified_by' => $this->helpdesk->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $this->task), [
            'items' => [['name' => 'Ganti Konektor', 'amount' => '25.000']],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('task_billing_items', 0);
    }

    #[Test]
    public function reject_task_mtn_berbayar_tulis_rejection_reason_ke_tabel_mtn(): void
    {
        $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.reject', $this->task), [
            'reason' => 'Biaya tidak sesuai tarif resmi.',
        ]);

        $response->assertSessionHasNoErrors();

        $this->detail->refresh();
        $this->assertSame(CReqVerificationStatus::REJECTED, $this->detail->verification_status);
        $this->assertSame('Biaya tidak sesuai tarif resmi.', $this->detail->rejection_reason);
    }

    /**
     * Regresi Fix Round 1 — show.blade.php sebelumnya SELALU baca
     * `$task->creqDetail` di top-level @php block (null untuk task MTN),
     * jadi akses properti berikutnya ($detail->verification_status dst)
     * fatal. approve()'s success redirect mengarah PERSIS ke halaman ini,
     * jadi bug ini membuat seluruh alur approve MTN gagal di titik akhir
     * walau datanya sendiri sudah benar tersimpan. Detail diset VERIFIED
     * langsung lewat model (bukan lewat approve()) supaya test ini murni
     * menguji rendering show(), terlepas dari approve() itu sendiri.
     */
    #[Test]
    public function show_task_mtn_verified_render_sukses_bukan_fatal(): void
    {
        $customer = Customer::query()->with('customerService')->findOrFail($this->customer->id);

        $invoice = app(ManualCategoryInvoiceService::class)->issue(
            $customer,
            ManualInvoiceCategory::PERBAIKAN,
            null,
            'Ganti Konektor: Rp25.000',
            25000,
            $this->helpdesk->id,
        );

        $this->detail->update([
            'verification_status' => CReqVerificationStatus::VERIFIED->value,
            'verified_by' => $this->helpdesk->id,
            'verified_at' => now(),
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->actingAs($this->helpdesk)->get(route('tasks.creq-billing.show', $this->task));

        $response->assertOk();
        $response->assertSee('Diverifikasi');
        $response->assertSee($invoice->invoice_number);
        $response->assertSee('25.000', false);
    }

    /**
     * Sama kasusnya tapi lewat jalur end-to-end asli — approve() beneran
     * dipanggil, lalu redirect-nya diikuti. Ini persis skenario yang bocor
     * sebelum Fix Round 1: approve() berhasil (DB benar), tapi halaman
     * tujuan redirect-nya sendiri fatal.
     */
    #[Test]
    public function approve_task_mtn_lalu_redirect_target_render_sukses(): void
    {
        $approveResponse = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $this->task), [
            'items' => [['name' => 'Ganti Konektor', 'amount' => '25.000']],
        ]);

        $approveResponse->assertRedirect(route('tasks.creq-billing.show', $this->task));

        $showResponse = $this->actingAs($this->helpdesk)->get(route('tasks.creq-billing.show', $this->task));

        $showResponse->assertOk();
        $showResponse->assertSee('Diverifikasi');
    }
}
