<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\MaterialKind;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerSurvey;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkTool;
use App\Services\TaskMaterialService;
use App\Services\TaskWorkToolService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Verifikasi Admin harus memperlihatkan data yang benar-benar diinput
 * teknisi. Dua gejala yang dijaga di sini:
 *
 * 1. Checklist alat kerja ditulis ke `task_work_tools` (bukan kolom survey /
 *    installation), jadi halaman verifikasi sempat tidak menampilkannya sama
 *    sekali — admin cuma lihat teks bebas `required_tools`.
 * 2. `customer_surveys.fop_id` menunjuk ke users, tapi dulu ditampilkan mentah
 *    dengan label "Kebutuhan FOP / Tiang" — label dan isinya tidak nyambung.
 */
class VerificationAdminInputDataVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Customer $customer;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $ownerRole = Role::where('name', 'Owner')->first();
        $this->actor = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);

        $this->pop = Pop::create([
            'code' => 'VA',
            'pop_code' => 'VA',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Verifikasi',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'VA-001',
            'full_name' => 'Pelanggan Verifikasi',
            'primary_phone' => '0812000111',
            'status' => 'verification_admin',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function makeFopTask(TaskType $category, string $number): FopTask
    {
        return FopTask::create([
            'task_number' => $number,
            'task_date' => now(),
            'category' => $category->value,
            'tugas' => 'Task Verifikasi',
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'issue' => 'Test verifikasi',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
    }

    public function test_checklist_alat_survey_dan_pemasangan_tampil_di_halaman_verifikasi(): void
    {
        $tangga = WorkTool::create(['code' => 'TANGGA', 'name' => 'Tangga', 'is_active' => true, 'sort_order' => 10]);
        $splicer = WorkTool::create(['code' => 'SPLICER', 'name' => 'Fusion Splicer', 'is_active' => true, 'sort_order' => 20]);

        CustomerSurvey::create([
            'customer_id' => $this->customer->id,
            'survey_status' => 'completed',
            'nearest_odp' => 'ODP-VA-02',
            'cable_estimation_meter' => 90,
        ]);

        $service = app(TaskWorkToolService::class);

        $service->sync($this->makeFopTask(TaskType::SURVEY, 'TFOP-VA-SRV'), [
            ['work_tool_id' => $tangga->id],
            ['work_tool_id' => null, 'tool_name' => 'Perahu karet', 'note' => 'Akses lewat sungai'],
        ]);

        $service->sync($this->makeFopTask(TaskType::PEMASANGAN, 'TFOP-VA-PSB'), [
            ['work_tool_id' => $splicer->id],
        ]);

        $response = $this->actingAs($this->actor)
            ->get(route('customers.verification.admin', $this->customer));

        $response->assertOk();
        $response->assertSee('Alat Kerja Dicatat Surveyor');
        $response->assertSee('Tangga');
        $response->assertSee('Perahu karet');
        $response->assertSee('Akses lewat sungai');
        $response->assertSee('Alat Kerja Dipakai Tim Pemasangan');
        $response->assertSee('Fusion Splicer');
    }

    public function test_material_estimasi_survey_dan_terpakai_pemasangan_tampil_terpisah(): void
    {
        // Tabel "Estimasi vs Terpakai" saja tidak cukup: dia mengagregasi per
        // barang dan membuang catatan per baris, jadi baris yang benar-benar
        // diinput teknisi tidak pernah kelihatan utuh.
        CustomerSurvey::create([
            'customer_id' => $this->customer->id,
            'survey_status' => 'completed',
            'nearest_odp' => 'ODP-VA-03',
            'cable_estimation_meter' => 150,
        ]);

        $service = app(TaskMaterialService::class);

        $service->sync($this->makeFopTask(TaskType::SURVEY, 'TFOP-VA-SRV3'), MaterialKind::ESTIMASI, [
            ['item_name' => 'Dropcore 1 Core', 'qty' => 150, 'unit' => 'meter', 'note' => 'Lewat atas atap tetangga'],
        ]);

        $service->sync($this->makeFopTask(TaskType::PEMASANGAN, 'TFOP-VA-PSB3'), MaterialKind::TERPAKAI, [
            ['item_name' => 'Pigtail SC/UPC', 'qty' => 2, 'unit' => 'pcs', 'note' => 'Satu gagal splice'],
        ]);

        $response = $this->actingAs($this->actor)
            ->get(route('customers.verification.admin', $this->customer));

        $response->assertOk();
        $response->assertSee('Estimasi Material Hasil Survey');
        $response->assertSee('Dropcore 1 Core');
        $response->assertSee('Lewat atas atap tetangga');
        $response->assertSee('Material Terpakai Saat Pemasangan');
        $response->assertSee('Pigtail SC/UPC');
        $response->assertSee('Satu gagal splice');
    }

    public function test_fop_survey_tampil_sebagai_nama_bukan_id_mentah(): void
    {
        $fop = User::factory()->create([
            'name' => 'Budi Penanggung Jawab',
            'role_id' => Role::where('name', 'FOP')->value('id'),
            'status' => 'active',
        ]);

        CustomerSurvey::create([
            'customer_id' => $this->customer->id,
            'survey_status' => 'completed',
            'nearest_odp' => 'ODP-VA-01',
            'cable_estimation_meter' => 120,
            'fop_id' => $fop->id,
        ]);

        $response = $this->actingAs($this->actor)
            ->get(route('customers.verification.admin', $this->customer));

        $response->assertOk();
        $response->assertSee('FOP Penanggung Jawab');
        $response->assertSee('Budi Penanggung Jawab');
        $response->assertDontSee('Kebutuhan FOP / Tiang');
    }
}
