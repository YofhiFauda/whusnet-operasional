<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\MaterialType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Item;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMaterial;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Alur material lewat form: estimasi dari Laporan Survey, realisasi dari
 * Laporan Pemasangan.
 */
class MaterialReportFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Customer $customer;

    private Pop $pop;

    private Item $dropcore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $ownerRole = Role::where('name', 'Owner')->first();
        $this->actor = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'MATFLOW-001',
            'full_name' => 'Pelanggan Material Flow',
            'primary_phone' => '0812345678',
            'status' => 'survey_in_progress',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $this->dropcore = Item::create([
            'code' => 'DC-1C',
            'name' => 'Kabel Dropcore 1 Core',
            'type' => MaterialType::KABEL_DROPCORE->value,
            'unit' => 'meter',
            'is_active' => true,
        ]);

        Task::create([
            'task_number' => 'TASK-MATFLOW-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Material Flow',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
        ]);

        // Anchor baris material — di produksi dibuat auto-sync papan FOP.
        FopTask::create([
            'task_number' => 'TFOP-2026-MATFLOW-1',
            'task_date' => now(),
            'category' => TaskType::SURVEY->value,
            'tugas' => 'Survey Material Flow',
            'village_id' => null,
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'issue' => 'Auto-Sync dari antrean survey',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
    }

    public function test_estimasi_material_tersimpan_dari_laporan_survey(): void
    {
        $response = $this->actingAs($this->actor)
            ->post(route('customers.survey.store', $this->customer->id), [
                'survey_status' => 'pending',
                'nearest_odp' => 'ODP-01',
                'cable_estimation_meter' => 0,
                'materials' => [
                    ['item_id' => $this->dropcore->id, 'qty' => 120],
                    ['item_id' => null, 'item_name' => 'Klem Kabel', 'item_type' => MaterialType::AKSESORIS_PASANG->value, 'qty' => 10, 'unit' => 'pcs'],
                ],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $rows = TaskMaterial::where('customer_id', $this->customer->id)->estimasi()->get();

        $this->assertCount(2, $rows);
        $this->assertSame('Kabel Dropcore 1 Core', $rows->first()->item_name);
        $this->assertSame('meter', $rows->first()->unit);
    }

    public function test_estimasi_kabel_survey_jadi_baris_dropcore_otomatis(): void
    {
        $response = $this->actingAs($this->actor)
            ->post(route('customers.survey.store', $this->customer->id), [
                'survey_status' => 'pending',
                'nearest_odp' => 'ODP-01',
                'cable_estimation_meter' => 85,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $rows = TaskMaterial::where('customer_id', $this->customer->id)->estimasi()->get();

        $this->assertCount(1, $rows);
        $this->assertSame(MaterialType::KABEL_DROPCORE, $rows->first()->item_type);
        $this->assertSame('85.00', $rows->first()->qty);
    }

    public function test_estimasi_kabel_tidak_menggandakan_baris_dropcore_manual(): void
    {
        $response = $this->actingAs($this->actor)
            ->post(route('customers.survey.store', $this->customer->id), [
                'survey_status' => 'pending',
                'nearest_odp' => 'ODP-01',
                'cable_estimation_meter' => 85,
                'materials' => [
                    ['item_id' => $this->dropcore->id, 'qty' => 90],
                ],
            ]);

        $response->assertStatus(302);

        $rows = TaskMaterial::where('customer_id', $this->customer->id)->estimasi()->get();

        $this->assertCount(1, $rows);
        $this->assertSame('90.00', $rows->first()->qty);
    }

    public function test_pemasangan_selesai_wajib_punya_material(): void
    {
        $this->customer->update(['status' => 'installation_in_progress']);

        // Foto-foto wajib ikut dikirim karena guard-nya berjalan lebih dulu.
        // Tidak ada file yang benar-benar ditulis: request berhenti di guard
        // material sebelum tahap upload.
        $response = $this->actingAs($this->actor)
            ->post(route('customers.installation.store', $this->customer->id), [
                'device_type' => 'ont',
                'installation_status' => 'completed',
                'installation_photo' => UploadedFile::fake()->image('pasang.jpg'),
                'contract_photo' => UploadedFile::fake()->image('kontrak.jpg'),
                'signature_photo' => UploadedFile::fake()->image('ttd.jpg'),
                'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
                'materials' => [],
            ]);

        $response->assertSessionHasErrors('materials');
    }

    public function test_pemasangan_gagal_tidak_wajib_material(): void
    {
        $this->customer->update(['status' => 'installation_in_progress']);

        $response = $this->actingAs($this->actor)
            ->post(route('customers.installation.store', $this->customer->id), [
                'device_type' => 'ont',
                'installation_status' => 'failed',
                'installation_note' => 'Tiang belum siap',
            ]);

        $response->assertSessionHasNoErrors();
    }
}
