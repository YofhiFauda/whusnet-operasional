<?php

namespace Tests\Feature;

use App\Enums\CReqVerificationStatus;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskCreqDetail;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan C-REQ — dropdown Kategori C-REQ + field kondisional (tikor,
 * modem, nama kategori bebas) + checkbox "Task ini berbayar" masuk antrean
 * verifikasi CS. Form yang sama persis dengan Laporan Maintenance
 * (`TaskMaintenanceController`), jadi test ini fokus ke perilaku KHUSUS
 * task_type CREQ — regresi MTN dicakup test lain (TaskMaintenanceModemInstallTest dkk).
 *
 * docs/plan/task-teknisi/rancangan-biaya-creq-verifikasi-cs.md
 */
class TaskCreqBillingReportTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected Customer $customer;

    protected User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'CREQ-TEST', 'pop_code' => 'CRQ', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang C-REQ Test', 'type' => 'cabang', 'status' => 'active']);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-CREQ-001',
            'full_name' => 'Pelanggan C-REQ',
            'primary_phone' => '0812340009',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);

        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->technician->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP]);
    }

    private function makeCreqTask(): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-CREQ-'.random_int(1000, 9999),
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::CREQ->value,
            'title' => 'Customer Request Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        return $task;
    }

    private function basePayload(): array
    {
        return [
            'kendala_teknis' => 'Permintaan pelanggan diproses di lokasi.',
            'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
        ];
    }

    #[Test]
    public function kategori_creq_wajib_diisi(): void
    {
        $task = $this->makeCreqTask();

        $response = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload());

        $response->assertSessionHasErrors('creq_category');
        $this->assertNull($task->fresh()->creqDetail);
    }

    #[Test]
    public function pindah_lokasi_wajib_tikor_lama_dan_baru(): void
    {
        $task = $this->makeCreqTask();

        $response = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'pindah_lokasi',
            ]);

        $response->assertSessionHasErrors([
            'creq_tikor_lama_lat', 'creq_tikor_lama_lng', 'creq_tikor_baru_lat', 'creq_tikor_baru_lng',
        ]);
    }

    #[Test]
    public function pindah_lokasi_dengan_tikor_lengkap_tersimpan(): void
    {
        $task = $this->makeCreqTask();

        $response = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'pindah_lokasi',
                'creq_tikor_lama_lat' => '-7.8654321',
                'creq_tikor_lama_lng' => '111.4567890',
                'creq_tikor_baru_lat' => '-7.8600000',
                'creq_tikor_baru_lng' => '111.4500000',
            ]);

        $response->assertSessionHasNoErrors();
        $task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $task->status);

        $detail = $task->creqDetail;
        $this->assertNotNull($detail);
        $this->assertSame('pindah_lokasi', $detail->category->value);
        $this->assertEquals(-7.8654321, (float) $detail->tikor_lama_lat);
        $this->assertFalse($detail->is_billable);
        $this->assertSame(CReqVerificationStatus::PENDING, $detail->verification_status);
    }

    #[Test]
    public function lainnya_wajib_nama_kategori(): void
    {
        $task = $this->makeCreqTask();

        $response = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'lainnya',
            ]);

        $response->assertSessionHasErrors('creq_category_custom_name');
    }

    #[Test]
    public function tambah_modem_wajib_pilih_sn_dari_custody(): void
    {
        $task = $this->makeCreqTask();

        $response = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'tambah_modem',
            ]);

        $response->assertSessionHasErrors('selected_inventory_serial_id');
    }

    #[Test]
    public function tambah_modem_dengan_sn_custody_tersimpan(): void
    {
        $task = $this->makeCreqTask();

        $category = ItemCategory::create(['code' => 'modem_ont', 'name' => 'Modem/ONT Pelanggan', 'default_unit' => 'pcs']);
        $modem = Item::create([
            'code' => 'ONT-CREQ-TEST', 'name' => 'Modem ONT Tambahan', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
        $serial = InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'SN-CREQ-001',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $response = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'tambah_modem',
                'selected_inventory_serial_id' => $serial->id,
            ]);

        $response->assertSessionHasNoErrors();
        $task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $task->status);
        $this->assertSame('tambah_modem', $task->creqDetail->category->value);
    }

    #[Test]
    public function task_berbayar_wajib_catatan_biaya_dan_masuk_antrean_verifikasi(): void
    {
        $task = $this->makeCreqTask();

        // Tanpa catatan biaya — gagal.
        $gagal = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'lainnya',
                'creq_category_custom_name' => 'Pasang Repeater',
                'creq_is_billable' => '1',
            ]);
        $gagal->assertSessionHasErrors('creq_billing_note');

        $ok = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'lainnya',
                'creq_category_custom_name' => 'Pasang Repeater',
                'creq_is_billable' => '1',
                'creq_billing_note' => 'Pasang repeater tambahan atas permintaan pelanggan, Rp150.000.',
            ]);

        $ok->assertSessionHasNoErrors();
        $detail = $task->fresh()->creqDetail;
        $this->assertTrue($detail->is_billable);
        $this->assertSame(CReqVerificationStatus::PENDING, $detail->verification_status);
        $this->assertSame('Pasang Repeater', $detail->category_custom_name);
    }

    #[Test]
    public function task_tidak_berbayar_tidak_masuk_antrean_tapi_tetap_tersimpan(): void
    {
        $task = $this->makeCreqTask();

        $response = $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
                'creq_category' => 'lainnya',
                'creq_category_custom_name' => 'Cek Redaman',
            ]);

        $response->assertSessionHasNoErrors();
        $detail = $task->fresh()->creqDetail;
        $this->assertFalse($detail->is_billable);
    }

    /**
     * Regresi 2026-09-28: laporan C-REQ dikirim ulang setelah FOP menolak
     * (review reject → in_progress) dulu menambah baris detail kedua. CS
     * memproses baris lama (hasOne), baris baru nyangkut di antrean selamanya.
     */
    #[Test]
    public function kirim_ulang_laporan_memperbarui_detail_yang_sama_dan_kembali_menunggu_verifikasi(): void
    {
        $task = $this->makeCreqTask();
        $payload = [
            'creq_category' => 'lainnya',
            'creq_category_custom_name' => 'Pasang Repeater',
            'creq_is_billable' => '1',
            'creq_billing_note' => 'Biaya awal Rp150.000.',
        ];

        $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + $payload)
            ->assertSessionHasNoErrors();

        // CS sudah memverifikasi, lalu FOP menolak laporan → teknisi kirim ulang.
        $task->fresh()->creqDetail->update([
            'verification_status' => CReqVerificationStatus::VERIFIED->value,
            'verified_by' => $this->technician->id,
            'verified_at' => now(),
        ]);
        $task->refresh()->update(['status' => TaskStatus::IN_PROGRESS->value, 'fop_review_status' => 'rejected']);

        $this->actingAs($this->technician)
            ->post(route('tasks.maintenance.store', $task), $this->basePayload() + array_merge($payload, [
                'creq_billing_note' => 'Revisi biaya Rp175.000.',
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame(1, TaskCreqDetail::where('task_id', $task->id)->count());
        $detail = $task->fresh()->creqDetail;
        $this->assertSame('Revisi biaya Rp175.000.', $detail->billing_note);
        $this->assertSame(CReqVerificationStatus::PENDING, $detail->verification_status);
        $this->assertNull($detail->verified_by);
        $this->assertNull($detail->verified_at);
    }
}
