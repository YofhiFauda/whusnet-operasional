<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\MaterialKind;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMaterial;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Laporan yang diisi teknisi lewat form Laporan Maintenance (kendala teknis,
 * material terpakai, foto OPM/Speedtest) sebelumnya cuma tersimpan di DB
 * tanpa pernah tampil lagi di halaman Detail Task — teknisi & FOP tidak bisa
 * mengecek ulang apa yang sudah dikerjakan lewat /tasks/{id}.
 */
class TaskShowMaintenanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $fopUser;

    protected Pop $pop;

    protected Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $this->village = Village::create([
            'district_id' => $district->id,
            'name' => 'Polorejo',
            'postal_code' => '63491',
        ]);

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->fopUser = User::factory()->create();
        $fopRole = Role::where('code', 'fop')->firstOrFail();
        $this->fopUser->role_id = $fopRole->id;
        $this->fopUser->save();

        $this->fopUser->roleScopes()->create([
            'role_id' => $fopRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }
    }

    public function test_task_show_page_menampilkan_laporan_maintenance_dan_material_terpakai(): void
    {
        $task = Task::create([
            'task_number' => 'TASK-TEST-9902',
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Maintenance Report Test',
            'status' => TaskStatus::SELESAI->value,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
            'completed_at' => now(),
        ]);

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-2026-9902',
            'task_date' => now(),
            'category' => TaskType::MAINTENANCE->value,
            'task_id' => $task->id,
            'tugas' => 'Maintenance Report Test',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Koneksi putus-putus',
            'status' => TaskStatus::SELESAI->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        TaskMaterial::create([
            'fop_task_id' => $fopTask->id,
            'kind' => MaterialKind::TERPAKAI,
            'item_id' => null,
            'item_name' => 'Patch Cord 1 Meter',
            'item_type' => 'lainnya',
            'qty' => 2,
            'unit' => 'pcs',
            'recorded_by' => $this->fopUser->id,
        ]);

        $task->maintenanceReport()->create([
            'kendala_teknis' => 'Redaman tinggi akibat patch cord rusak, sudah diganti baru.',
        ]);

        $response = $this->actingAs($this->fopUser)
            ->get(route('tasks.show', $task->id));

        $response->assertOk();
        $response->assertSee('Laporan Pekerjaan Teknisi');
        $response->assertSee('Redaman tinggi akibat patch cord rusak, sudah diganti baru.');
        $response->assertSee('Patch Cord 1 Meter');
    }

    public function test_task_show_page_menampilkan_laporan_survey_dan_pemasangan(): void
    {
        $customer = Customer::create([
            'customer_code' => 'C009911',
            'registration_number' => 'REG-9911',
            'registration_date' => now()->toDateString(),
            'full_name' => 'Budi Sudarsono',
            'primary_phone' => '081234567899',
            'address' => 'Jl. Merdeka No 12',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'status' => 'waiting_acc',
        ]);

        $surveyTask = Task::create([
            'task_number' => 'TASK-SURVEY-9911',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Pelanggan Baru',
            'status' => TaskStatus::SELESAI->value,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $customer->surveys()->create([
            'survey_status' => 'completed',
            'cable_estimation_meter' => 120,
            'nearest_odp' => 'ODP-BDD-01/04',
            'survey_note' => 'Tiang listrik tersedia, jarak aman.',
            'technician_id' => $this->fopUser->id,
        ]);

        $responseSurvey = $this->actingAs($this->fopUser)
            ->get(route('tasks.show', $surveyTask->id));

        $responseSurvey->assertOk();
        $responseSurvey->assertSee('Laporan Result Survey Lapangan');
        $responseSurvey->assertSee('LAYAK PASANG (Selesai)');
        $responseSurvey->assertSee('120 Meter');
        $responseSurvey->assertSee('ODP-BDD-01/04');

        $installTask = Task::create([
            'task_number' => 'TASK-PSB-9922',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Baru PSB',
            'status' => TaskStatus::SELESAI->value,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $customer->installations()->create([
            'installation_status' => 'completed',
            'completed_at' => now(),
            'installation_note' => 'Pemasangan sukses, sinyal RX -19dBm jernih.',
            'technician_id' => $this->fopUser->id,
        ]);

        $responseInstall = $this->actingAs($this->fopUser)
            ->get(route('tasks.show', $installTask->id));

        $responseInstall->assertOk();
        $responseInstall->assertSee('Laporan Hasil Pemasangan (PSB)');
        $responseInstall->assertSee('PEMASANGAN SELESAI');
        $responseInstall->assertSee('Pemasangan sukses, sinyal RX -19dBm jernih.');
    }
}
