<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cascade cancel /fop-tasks → Task eksekusi wajib nembus TaskPolicy
 * (cancelViaFopTask), bukan manggil TaskService langsung.
 */
class FopTaskCancelCascadeAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private User $adminUser;

    private User $tech;

    private Village $village;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->fopUser = User::factory()->create(['role_id' => Role::where('code', 'fop')->first()->id]);
        $this->giveAllPopScope($this->fopUser);
        $this->adminUser = User::factory()->create(['role_id' => Role::where('code', 'admin')->first()->id]);
        $this->giveAllPopScope($this->adminUser);
        $this->tech = User::factory()->create([
            'role_id' => Role::where('code', 'teknisi')->first()->id,
            'status' => 'active',
        ]);

        $city = City::create(['name' => 'Kota']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Distrik']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Desa', 'postal_code' => '11111']);
        $this->pop = Pop::create([
            'name' => 'POP', 'code' => 'POP-X', 'type' => 'branch',
            'address' => 'x', 'status' => 'active', 'city_id' => $city->id,
        ]);
    }

    private function createFopTask(string $tugas): FopTask
    {
        $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN',
            'task_date' => now()->format('Y-m-d').' 08:00:00',
            'tugas' => $tugas,
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'FO Cut',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => [$this->tech->id],
        ])->assertRedirect();

        return FopTask::where('tugas', $tugas)->firstOrFail();
    }

    private function cancel(User $actor, FopTask $fopTask)
    {
        return $this->actingAs($actor)->putJson(route('fop-tasks.update', $fopTask), [
            'status' => TaskStatus::DIBATALKAN->value,
            'cancel_reason' => 'Alasan pembatalan',
        ]);
    }

    /**
     * Regresi: role fop tetap bisa cancel — policy baru gak boleh mempersempit
     * kewenangan yang udah ada.
     */
    public function test_fop_can_still_cancel_and_cascade(): void
    {
        $fopTask = $this->createFopTask('Task FOP');

        $this->cancel($this->fopUser, $fopTask)->assertOk();

        $this->assertSame(TaskStatus::DIBATALKAN, $fopTask->refresh()->status);
        $this->assertSame(TaskStatus::DIBATALKAN, Task::find($fopTask->task_id)->status);
    }

    /**
     * Regresi paling penting: role `admin` punya `fop_tasks.*` TAPI gak punya
     * `task.cancel`. Kalau cascade dipaksa lewat `task.cancel`, admin bakal
     * kehilangan kewenangan membatalkan tiket yang selama ini dia punya.
     */
    public function test_admin_without_task_cancel_permission_can_still_cancel(): void
    {
        $this->assertFalse(
            $this->adminUser->hasPermission('task.cancel'),
            'Prasyarat test berubah: admin sekarang punya task.cancel.'
        );
        $this->assertTrue($this->adminUser->hasPermission('fop_tasks.cancel'));

        $fopTask = $this->createFopTask('Task Admin');

        $this->cancel($this->adminUser, $fopTask)->assertOk();

        $this->assertSame(TaskStatus::DIBATALKAN, Task::find($fopTask->task_id)->status);
    }

    /**
     * Invarian yang ditutup: guard SRV/PSB di controller baca `FopTask.category`,
     * sedangkan yang dibatalin `Task`. Kalau dua kolom itu menyimpang, tiket MTN
     * gak boleh jadi jalan pintas buat membatalkan Task SURVEY — jalur sah SRV/PSB
     * tetap cuma halaman Pelanggan.
     */
    public function test_cascade_refuses_when_linked_task_is_survey(): void
    {
        $fopTask = $this->createFopTask('Task Menyimpang');

        $linkedTask = Task::find($fopTask->task_id);
        $linkedTask->task_type = TaskType::SURVEY->value;
        $linkedTask->save();

        $this->cancel($this->fopUser, $fopTask)->assertForbidden();

        // Transaksi ke-rollback — tiket DAN task-nya dua-duanya gak berubah.
        $this->assertNotSame(TaskStatus::DIBATALKAN, $fopTask->refresh()->status);
        $this->assertNotSame(TaskStatus::DIBATALKAN, $linkedTask->refresh()->status);
    }

    public function test_cascade_refuses_when_linked_task_is_pemasangan(): void
    {
        $fopTask = $this->createFopTask('Task Menyimpang PSB');

        $linkedTask = Task::find($fopTask->task_id);
        $linkedTask->task_type = TaskType::PEMASANGAN->value;
        $linkedTask->save();

        $this->cancel($this->fopUser, $fopTask)->assertForbidden();

        $this->assertNotSame(TaskStatus::DIBATALKAN, $linkedTask->refresh()->status);
    }

    /**
     * Owner ber-permission '*' pun gak boleh nembus invarian SRV/PSB —
     * TaskPolicy::before() harus ngecualiin cancelViaFopTask dari bypass wildcard.
     */
    public function test_owner_wildcard_cannot_bypass_survey_invariant(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->first()->id]);
        $this->assertTrue($owner->hasPermission('*'));

        $fopTask = $this->createFopTask('Task Owner');

        $linkedTask = Task::find($fopTask->task_id);
        $linkedTask->task_type = TaskType::SURVEY->value;
        $linkedTask->save();

        $this->cancel($owner, $fopTask)->assertForbidden();

        $this->assertNotSame(TaskStatus::DIBATALKAN, $linkedTask->refresh()->status);
    }

    /**
     * Policy baru gak boleh melebarkan pintu /tasks: `fop_tasks.cancel` cuma
     * berlaku buat cascade dari tiket, bukan tombol Cancel di halaman Task.
     */
    public function test_fop_tasks_cancel_does_not_grant_direct_task_cancel(): void
    {
        $fopTask = $this->createFopTask('Task Langsung');
        $linkedTask = Task::find($fopTask->task_id);

        // Jalur /tasks tetap dijaga TaskPolicy::cancel() (butuh task.cancel +
        // aturan workflow), yang di env ini gak keseed — jadi tetap ditolak.
        $this->actingAs($this->adminUser)
            ->post(route('tasks.cancel', $linkedTask), ['cancel_reason' => 'Coba tembus'])
            ->assertForbidden();

        $this->assertNotSame(TaskStatus::DIBATALKAN, $linkedTask->refresh()->status);
    }
}
