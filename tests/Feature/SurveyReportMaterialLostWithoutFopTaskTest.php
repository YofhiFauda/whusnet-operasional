<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkTool;
use App\Services\FopTaskProvisioningService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresi: estimasi material & checklist alat hilang senyap kalau FopTask
 * belum ada.
 *
 * `task_materials` dan `task_work_tools` wajib punya `fop_task_id`, sedangkan
 * FopTask kategori SURVEY/PSB dulu cuma lahir saat papan `/fop-tasks` dibuka
 * (`FopTaskController::autoSyncAndCalculatePriority()`). Teknisi yang mengisi
 * laporan sebelum itu kehilangan seluruh isian material & alat tanpa satu pun
 * pesan error — di produksi: 1791 survey, 0 baris task_materials.
 */
class SurveyReportMaterialLostWithoutFopTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $ownerRole = Role::where('name', 'Owner')->first();
        $this->actor = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);

        $this->pop = Pop::create([
            'code' => 'FP',
            'pop_code' => 'FP',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Provisioning',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeCustomer(string $code, string $status): Customer
    {
        return Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '0812000222',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    public function test_laporan_survey_menyimpan_material_walau_papan_fop_belum_pernah_dibuka(): void
    {
        $customer = $this->makeCustomer('FP-001', 'survey_in_progress');

        $task = Task::create([
            'task_number' => 'TASK-FP-001',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->actor->id, 'role_in_task' => 'lead']);

        $tangga = WorkTool::create(['code' => 'TANGGA', 'name' => 'Tangga', 'is_active' => true, 'sort_order' => 10]);

        // Sengaja TIDAK ada FopTask sama sekali — kondisi persis produksi.
        $this->assertSame(0, FopTask::count());

        $response = $this->actingAs($this->actor)
            ->post(route('customers.survey.store', $customer->id), [
                'survey_status' => 'pending',
                'nearest_odp' => 'ODP-FP-01',
                'cable_estimation_meter' => 0,
                'materials' => [
                    ['item_id' => null, 'item_name' => 'Dropcore 1 Core', 'item_type' => 'lainnya', 'qty' => 120, 'unit' => 'meter'],
                ],
                'work_tools_ids' => [$tangga->id],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $fopTask = FopTask::where('customer_id', $customer->id)
            ->where('category', TaskType::SURVEY->value)
            ->first();

        $this->assertNotNull($fopTask, 'FopTask anchor harus dibuat otomatis saat laporan disimpan.');
        $this->assertStringStartsWith('TFOP-', $fopTask->task_number);
        $this->assertSame(1, $fopTask->materials()->estimasi()->count());
        $this->assertSame('Dropcore 1 Core', $fopTask->materials()->estimasi()->first()->item_name);
        $this->assertSame(1, $fopTask->workTools()->count());
        $this->assertSame('Tangga', $fopTask->workTools()->first()->tool_name);
    }

    public function test_ensure_idempoten_tidak_bikin_tfop_dobel(): void
    {
        $customer = $this->makeCustomer('FP-002', 'waiting_survey');

        $service = app(FopTaskProvisioningService::class);

        $first = $service->ensureForCustomer($customer, TaskType::SURVEY);
        $second = $service->ensureForCustomer($customer, TaskType::SURVEY);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, FopTask::where('customer_id', $customer->id)->count());
    }

    public function test_kategori_selain_survey_dan_psb_tidak_pernah_auto_dibuat(): void
    {
        // MTN & C-REQ lahir lewat Ticketing (escalateToFop), bukan dari antrean
        // pelanggan — service ini tidak boleh jadi pintu belakangnya.
        $customer = $this->makeCustomer('FP-003', 'active');

        $result = app(FopTaskProvisioningService::class)
            ->ensureForCustomer($customer, TaskType::MAINTENANCE);

        $this->assertNull($result);
        $this->assertSame(0, FopTask::count());
    }
}
