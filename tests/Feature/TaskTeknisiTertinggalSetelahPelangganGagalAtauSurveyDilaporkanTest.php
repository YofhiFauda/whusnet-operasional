<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use App\Services\CustomerWorkflowService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bug 2026-09-29 (uji manual Testing 7 & Testing 10):
 *
 *  - Testing 7: PSB sudah dijadwalkan FOP ke teknisi Bella, lalu superadmin
 *    menolak pelanggan (masuk Gagal) dari Verifikasi. Pelanggan masuk Gagal,
 *    tapi task Bella tetap `terjadwal` di Task Saya.
 *  - Testing 10: superadmin menekan Proses di Antrean Survey (task belum
 *    dijadwalkan), FOP lalu menjadwalkan survey ke Bella, superadmin mengisi
 *    laporan survey sampai selesai. Pelanggan lanjut, task Bella tertinggal.
 *
 * Plus akar yang ketemu di data Testing 7: tiap survey/PSB punya DUA Task —
 * satu `pending` dari antrean (yatim selamanya), satu dari papan FOP.
 */
class TaskTeknisiTertinggalSetelahPelangganGagalAtauSurveyDilaporkanTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private User $bella;

    private Pop $pop;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $this->fopUser = $this->userWithRole('fop');
        $this->bella = $this->userWithRole('teknisi');

        $city = City::create(['name' => 'Kota Uji Task Tertinggal']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Kecamatan Uji']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Desa Uji', 'postal_code' => '63491']);

        $this->pop = Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        return $user;
    }

    /**
     * Pelanggan masuk antrean lewat jalur nyata (transisi workflow), jadi Task
     * `pending` antrean + FopTask Draft ikut lahir persis seperti di produksi.
     */
    private function customerInQueue(string $fromStatus, WorkflowTransition $queueStatus): Customer
    {
        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
            'status' => $fromStatus,
        ]);

        $this->actingAs($this->fopUser);
        app(CustomerWorkflowService::class)->transition($customer, $queueStatus);

        return $customer->refresh();
    }

    private function fopTaskFor(Customer $customer, TaskType $type): FopTask
    {
        return FopTask::where('customer_id', $customer->id)->where('category', $type->value)->latest('id')->firstOrFail();
    }

    /** Payload modal Edit /fop-tasks — sama dengan FopTaskDraftAutoScheduleOnAssignTest. */
    private function scheduleToBella(FopTask $fopTask): TestResponse
    {
        return $this->actingAs($this->fopUser)->putJson(route('fop-tasks.update', $fopTask), [
            'category' => $fopTask->category->value,
            'task_date' => now()->format('Y-m-d H:i:s'),
            'tugas' => $fopTask->tugas,
            'village_id' => $fopTask->village_id,
            'pop_id' => $fopTask->pop_id,
            'issue' => $fopTask->issue,
            'priority' => $fopTask->priority->value,
            'status' => $fopTask->status->value,
            'technicians' => [$this->bella->id],
        ]);
    }

    private function openTasksOf(Customer $customer, TaskType $type)
    {
        return Task::where('customer_id', $customer->id)
            ->where('task_type', $type->value)
            ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
            ->get();
    }

    private function openTasksOfBella()
    {
        return Task::whereHas('teamMembers', fn ($q) => $q->where('user_id', $this->bella->id))
            ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
            ->get();
    }

    #[Test]
    public function menjadwalkan_survey_memakai_task_antrean_bukan_membuat_task_kedua(): void
    {
        $customer = $this->customerInQueue('registered', WorkflowTransition::WAITING_SURVEY);
        $queuedTask = Task::where('customer_id', $customer->id)->where('task_type', TaskType::SURVEY->value)->sole();
        $this->assertSame(TaskStatus::PENDING, $queuedTask->status);

        $fopTask = $this->fopTaskFor($customer, TaskType::SURVEY);
        $this->scheduleToBella($fopTask)->assertOk();

        $surveyTasks = Task::where('customer_id', $customer->id)->where('task_type', TaskType::SURVEY->value)->get();
        $this->assertCount(1, $surveyTasks, 'Menjadwalkan survey melahirkan Task kedua — Task antrean jadi yatim.');

        $task = $surveyTasks->first();
        $this->assertSame($queuedTask->id, $task->id);
        $this->assertSame(TaskStatus::TERJADWAL, $task->status);
        $this->assertSame([$this->bella->id], $task->teamMembers()->pluck('user_id')->all());
        $this->assertNotNull($task->sla_minutes);
        $this->assertSame($this->fopUser->id, $task->fop_id);
        $this->assertSame($task->id, $fopTask->refresh()->task_id);
        $this->assertSame(TaskStatus::TERJADWAL, $fopTask->status);
    }

    /**
     * Testing 7: PSB terjadwal ke Bella, superadmin tolak pelanggan di
     * Verifikasi saat pemasangan sudah berjalan.
     */
    #[Test]
    public function tolak_pelanggan_saat_pemasangan_membatalkan_task_psb_teknisi(): void
    {
        $customer = $this->customerInQueue('waiting_acc', WorkflowTransition::WAITING_INSTALLATION);
        $fopTask = $this->fopTaskFor($customer, TaskType::PEMASANGAN);
        $this->scheduleToBella($fopTask)->assertOk();

        app(CustomerWorkflowService::class)->transition($customer->refresh(), WorkflowTransition::INSTALLATION_IN_PROGRESS);

        $this->loginAsAdmin();
        $this->post(route('customers.verification.reject', $customer), ['reason' => 'Lokasi tidak memungkinkan'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('rejected', $customer->refresh()->status);
        $this->assertCount(0, $this->openTasksOfBella(), 'Task PSB Bella masih nyangkut setelah pelanggan masuk Gagal.');
        $this->assertCount(0, $this->openTasksOf($customer, TaskType::PEMASANGAN));

        $fopTask->refresh();
        $this->assertSame(TaskStatus::DIBATALKAN, $fopTask->status);
        $this->assertStringContainsString('Lokasi tidak memungkinkan', $fopTask->task->cancel_reason);
    }

    /**
     * Testing 10: Proses ditekan superadmin sebelum task dijadwalkan, FOP
     * menjadwalkan ke Bella, superadmin mengisi laporan survey sampai selesai.
     */
    #[Test]
    public function laporan_survey_oleh_admin_membatalkan_task_survey_teknisi_yang_ditinggal(): void
    {
        $customer = $this->customerInQueue('registered', WorkflowTransition::WAITING_SURVEY);

        $admin = $this->loginAsAdmin();
        $this->post(route('customers.survey.start', $customer))->assertRedirect();
        $this->assertSame('survey_in_progress', $customer->refresh()->status);

        $fopTask = $this->fopTaskFor($customer, TaskType::SURVEY);
        $this->scheduleToBella($fopTask)->assertOk();
        $this->assertCount(1, $this->openTasksOfBella());

        $this->actingAs($admin)->post(route('customers.survey.store', $customer), [
            'survey_status' => 'completed',
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-TEST-01',
            'difficulty_level' => 'SEDANG',
            'house_photo' => UploadedFile::fake()->image('house.jpg'),
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('waiting_acc', $customer->refresh()->status);
        $this->assertCount(0, $this->openTasksOfBella(), 'Task survey Bella masih nyangkut setelah survey dilaporkan admin.');

        // Dibatalkan, bukan diselesaikan: Bella tidak mengerjakan apa pun.
        $bellaTask = $fopTask->refresh()->task;
        $this->assertSame(TaskStatus::DIBATALKAN, $bellaTask->status);
        $this->assertStringContainsString($admin->name, $bellaTask->cancel_reason);
        $this->assertSame(TaskStatus::DIBATALKAN, $fopTask->status);
    }

    #[Test]
    public function pelanggan_gagal_sebelum_dijadwalkan_tidak_meninggalkan_task_maupun_fop_task_draft(): void
    {
        $customer = $this->customerInQueue('registered', WorkflowTransition::WAITING_SURVEY);
        $fopTask = $this->fopTaskFor($customer, TaskType::SURVEY);
        $this->assertSame(TaskStatus::DRAFT, $fopTask->status);

        $this->loginAsAdmin();
        $this->post(route('customers.survey.cancel', $customer), ['reason' => 'Di luar jangkauan'])
            ->assertRedirect();

        $this->assertSame('rejected', $customer->refresh()->status);
        $this->assertCount(0, $this->openTasksOf($customer, TaskType::SURVEY));

        $fopTask->refresh();
        $this->assertSame(TaskStatus::DIBATALKAN, $fopTask->status);
        $this->assertNotNull($fopTask->cancelled_at);
        $this->assertSame(1, $fopTask->statusHistories()->where('to_status', TaskStatus::DIBATALKAN->value)->count());
    }

    /**
     * Jalur normal tetap utuh: teknisi yang dijadwalkan melapor sendiri →
     * task-nya Selesai, bukan ikut dibatalkan penutup otomatis.
     */
    #[Test]
    public function laporan_survey_oleh_teknisi_yang_dijadwalkan_tetap_selesai(): void
    {
        $customer = $this->customerInQueue('registered', WorkflowTransition::WAITING_SURVEY);
        $fopTask = $this->fopTaskFor($customer, TaskType::SURVEY);
        $this->scheduleToBella($fopTask)->assertOk();

        $this->actingAs($this->bella)->post(route('customers.survey.start', $customer))->assertRedirect();
        $this->actingAs($this->bella)->post(route('customers.survey.store', $customer), [
            'survey_status' => 'completed',
            'cable_estimation_meter' => 50,
            'nearest_odp' => 'ODP-TEST-01',
            'difficulty_level' => 'SEDANG',
            'house_photo' => UploadedFile::fake()->image('house.jpg'),
            'survey_photo' => UploadedFile::fake()->image('survey.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('waiting_acc', $customer->refresh()->status);
        $this->assertSame(TaskStatus::SELESAI, $fopTask->refresh()->task->status);
        $this->assertCount(1, Task::where('customer_id', $customer->id)->where('task_type', TaskType::SURVEY->value)->get());
    }
}
