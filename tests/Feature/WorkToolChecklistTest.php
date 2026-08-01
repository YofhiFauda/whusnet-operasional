<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskWorkTool;
use App\Models\User;
use App\Models\WorkTool;
use App\Services\TaskWorkToolService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Checklist alat kerja: master, penulisan lewat tiga form laporan, dan
 * pembacaannya di halaman Task teknisi.
 *
 * Yang paling dijaga di sini: checklist menempel ke FopTask, bukan ke survey.
 * Rancangan awal (pivot ke customer_surveys) bikin MTN/C-REQ — yang tidak
 * pernah lewat survey — tidak akan pernah bisa punya daftar alat.
 */
class WorkToolChecklistTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Customer $customer;

    private Pop $pop;

    private WorkTool $tangga;

    private WorkTool $splicer;

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
            'code' => 'WT',
            'pop_code' => 'WT',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Alat',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'WT-001',
            'full_name' => 'Pelanggan Alat',
            'primary_phone' => '0812345678',
            'status' => 'survey_in_progress',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $this->tangga = WorkTool::create(['code' => 'TANGGA', 'name' => 'Tangga', 'is_active' => true, 'sort_order' => 10]);
        $this->splicer = WorkTool::create(['code' => 'SPLICER', 'name' => 'Fusion Splicer', 'is_active' => true, 'sort_order' => 20]);
    }

    private function makeFopTask(TaskType $category, string $number, ?Task $task = null): FopTask
    {
        return FopTask::create([
            'task_number' => $number,
            'task_date' => now(),
            'category' => $category->value,
            'tugas' => 'Task Alat',
            'village_id' => null,
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'task_id' => $task?->id,
            'issue' => 'Test alat kerja',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
    }

    private function makeTask(TaskType $type, string $number): Task
    {
        $task = Task::create([
            'task_number' => $number,
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => $type->value,
            'title' => 'Task '.$type->value,
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
        ]);

        $task->teamMembers()->create(['user_id' => $this->actor->id, 'role_in_task' => 'lead']);

        return $task;
    }

    public function test_checklist_tersimpan_dari_laporan_survey(): void
    {
        $this->makeTask(TaskType::SURVEY, 'TASK-WT-SRV');
        $fopTask = $this->makeFopTask(TaskType::SURVEY, 'TFOP-WT-SRV');

        $response = $this->actingAs($this->actor)
            ->post(route('customers.survey.store', $this->customer->id), [
                'survey_status' => 'pending',
                'nearest_odp' => 'ODP-01',
                'cable_estimation_meter' => 0,
                'work_tools_ids' => [$this->tangga->id, $this->splicer->id],
                'work_tools_manual' => [
                    ['tool_name' => 'Perahu karet', 'note' => 'Akses lewat sungai'],
                ],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $rows = $fopTask->workTools()->orderBy('id')->get();

        $this->assertCount(3, $rows);
        $this->assertSame('Tangga', $rows[0]->tool_name);
        $this->assertSame($this->tangga->id, $rows[0]->work_tool_id);
        // Alat di luar master tersimpan tanpa FK, nama apa adanya.
        $this->assertNull($rows[2]->work_tool_id);
        $this->assertSame('Perahu karet', $rows[2]->tool_name);
        $this->assertSame('Akses lewat sungai', $rows[2]->note);
    }

    public function test_checklist_maintenance_menempel_ke_fop_task_sendiri(): void
    {
        // Inti keputusan desain: MTN tidak lewat survey, tapi tetap harus bisa
        // punya daftar alat. Anchor-nya fop_tasks.task_id.
        $task = $this->makeTask(TaskType::MAINTENANCE, 'TASK-WT-MTN');
        $fopTask = $this->makeFopTask(TaskType::MAINTENANCE, 'TFOP-WT-MTN', $task);

        $response = $this->actingAs($this->actor)
            ->post(route('tasks.maintenance.store', $task), [
                'kendala_teknis' => 'Kabel putus kena ranting, disambung ulang.',
                'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
                'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
                'work_tools_ids' => [$this->splicer->id],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $rows = $fopTask->workTools()->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Fusion Splicer', $rows->first()->tool_name);
    }

    public function test_material_tersimpan_dari_laporan_maintenance(): void
    {
        // Sebelumnya material maintenance cuma lima kolom teks bebas
        // (kabel/modem/patchcord/sleeve/lainnya) yang tak bisa diagregasi.
        $task = $this->makeTask(TaskType::MAINTENANCE, 'TASK-WT-MTN2');
        $fopTask = $this->makeFopTask(TaskType::MAINTENANCE, 'TFOP-WT-MTN2', $task);

        $response = $this->actingAs($this->actor)
            ->post(route('tasks.maintenance.store', $task), [
                'kendala_teknis' => 'Patch cord rusak, diganti baru.',
                'opm_photo' => UploadedFile::fake()->image('opm.jpg'),
                'speedtest_photo' => UploadedFile::fake()->image('speed.jpg'),
                'materials' => [
                    ['item_id' => null, 'item_name' => 'Patch Cord SC/UPC', 'item_type' => 'patch_cord', 'qty' => 1, 'unit' => 'pcs'],
                ],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $rows = $fopTask->materials()->terpakai()->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Patch Cord SC/UPC', $rows->first()->item_name);
        $this->assertSame('patch_cord', $rows->first()->item_type);
    }

    public function test_task_pemasangan_membaca_checklist_survey_sebagai_fallback(): void
    {
        // Alasan utama fitur ini ada: teknisi PSB tahu harus bawa apa sebelum
        // berangkat, padahal yang menilai medan adalah surveyor.
        $surveyFopTask = $this->makeFopTask(TaskType::SURVEY, 'TFOP-WT-SRV2');
        app(TaskWorkToolService::class)->sync($surveyFopTask, [
            ['work_tool_id' => $this->tangga->id],
        ]);

        $installTask = $this->makeTask(TaskType::PEMASANGAN, 'TASK-WT-PSB');
        $this->makeFopTask(TaskType::PEMASANGAN, 'TFOP-WT-PSB', $installTask);

        $rows = app(TaskWorkToolService::class)->displayRowsForTask($installTask);

        $this->assertCount(1, $rows);
        $this->assertSame('Tangga', $rows->first()->tool_name);
    }

    public function test_checklist_task_sendiri_menang_atas_fallback_survey(): void
    {
        $surveyFopTask = $this->makeFopTask(TaskType::SURVEY, 'TFOP-WT-SRV3');
        $service = app(TaskWorkToolService::class);
        $service->sync($surveyFopTask, [['work_tool_id' => $this->tangga->id]]);

        $installTask = $this->makeTask(TaskType::PEMASANGAN, 'TASK-WT-PSB2');
        $installFopTask = $this->makeFopTask(TaskType::PEMASANGAN, 'TFOP-WT-PSB2', $installTask);
        $service->sync($installFopTask, [['work_tool_id' => $this->splicer->id]]);

        $rows = $service->displayRowsForTask($installTask);

        $this->assertCount(1, $rows);
        $this->assertSame('Fusion Splicer', $rows->first()->tool_name);
    }

    public function test_baris_duplikat_dan_kosong_dibuang(): void
    {
        // Checklist tidak punya qty — dua baris "Tangga" bukan berarti dua
        // tangga, cuma bikin daftar yang dibaca teknisi jadi berulang.
        $fopTask = $this->makeFopTask(TaskType::SURVEY, 'TFOP-WT-DUP');

        app(TaskWorkToolService::class)->sync($fopTask, [
            ['work_tool_id' => $this->tangga->id],
            ['work_tool_id' => $this->tangga->id],
            ['work_tool_id' => null, 'tool_name' => ''],
            ['work_tool_id' => null, 'tool_name' => 'Perahu karet'],
            ['work_tool_id' => null, 'tool_name' => 'perahu karet'],
        ]);

        $this->assertSame(2, $fopTask->workTools()->count());
    }

    public function test_sync_mengganti_bukan_menggandakan(): void
    {
        $fopTask = $this->makeFopTask(TaskType::SURVEY, 'TFOP-WT-SYNC');
        $service = app(TaskWorkToolService::class);
        $rows = [['work_tool_id' => $this->tangga->id]];

        $service->sync($fopTask, $rows);
        $service->sync($fopTask, $rows);

        $this->assertSame(1, $fopTask->workTools()->count());
    }

    public function test_checklist_lama_tetap_terbaca_walau_alatnya_dihapus(): void
    {
        $fopTask = $this->makeFopTask(TaskType::SURVEY, 'TFOP-WT-DEL');
        app(TaskWorkToolService::class)->sync($fopTask, [['work_tool_id' => $this->tangga->id]]);

        $this->tangga->delete();

        $row = TaskWorkTool::where('fop_task_id', $fopTask->id)->first();

        // FK di-null-kan (nullOnDelete), snapshot nama bertahan.
        $this->assertNull($row->work_tool_id);
        $this->assertSame('Tangga', $row->tool_name);
    }

    public function test_alat_di_luar_master_ditolak_lewat_id_palsu(): void
    {
        $this->makeTask(TaskType::SURVEY, 'TASK-WT-SRV9');
        $this->makeFopTask(TaskType::SURVEY, 'TFOP-WT-SRV9');

        $this->actingAs($this->actor)
            ->post(route('customers.survey.store', $this->customer->id), [
                'survey_status' => 'pending',
                'nearest_odp' => 'ODP-01',
                'cable_estimation_meter' => 0,
                'work_tools_ids' => [99999],
            ])
            ->assertSessionHasErrors('work_tools_ids.0');
    }

    public function test_halaman_master_alat_kerja_butuh_permission(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->first();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $this->actingAs($teknisi)
            ->get(route('master.work-tools.index'))
            ->assertForbidden();

        $this->actingAs($this->actor)
            ->get(route('master.work-tools.index'))
            ->assertOk();
    }
}
