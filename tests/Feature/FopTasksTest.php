<?php

namespace Tests\Feature;

use App\Models\FopTask;
use App\Models\Village;
use App\Models\District;
use App\Models\City;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FopTasksTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerUser;
    private User $fopUser;
    private User $unauthorizedUser;
    private Village $village;
    private Pop $pop;
    private User $technician1;
    private User $technician2;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate features, actions, roles and permissions first
        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // Fetch populated roles
        $ownerRole = Role::where('code', 'owner')->first();
        $fopRole = Role::where('code', 'fop')->first();
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $salesRole = Role::where('code', 'sales')->first(); // Unauthorised

        // Create Users
        $this->ownerUser = User::factory()->create(['role_id' => $ownerRole->id]);
        $this->fopUser = User::factory()->create(['role_id' => $fopRole->id]);
        $this->unauthorizedUser = User::factory()->create(['role_id' => $salesRole->id]);
        
        $this->technician1 = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);
        $this->technician2 = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        // Create location hierarchy for Area/Desa
        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $this->village = Village::create([
            'district_id' => $district->id,
            'name' => 'Polorejo',
            'postal_code' => '63491'
        ]);
        
        $this->pop = Pop::create([
            'name' => 'POP Polorejo',
            'code' => 'POP-PLR',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
            'city_id' => $city->id
        ]);
    }

    public function test_guests_cannot_access_fop_tasks(): void
    {
        $response = $this->get(route('fop-tasks.index'));
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_users_cannot_access_fop_tasks(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('fop-tasks.index'));
        $response->assertStatus(403);
    }

    public function test_authorized_users_can_view_fop_tasks(): void
    {
        $task = FopTask::create([
            'task_number' => 'TFOP-2026-0001',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Perbaikan FO Cut',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'FO CUT di tiang 5',
            'status' => 'Proses',
            'priority' => 'High'
        ]);

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
        $response->assertStatus(200);
        $response->assertSee('TFOP-2026-0001');
        $response->assertSee('Perbaikan FO Cut');
        $response->assertSee('Polorejo');
    }

    public function test_can_create_fop_task(): void
    {
        $response = $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'C-REQ',
            'task_date' => now()->format('Y-m-d H:i:s'),
            'tugas' => 'Instalasi Jalur Baru Kantor',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Request khusus',
            'status' => 'Proses',
            'priority' => 'Medium',
            'technicians' => [$this->technician1->id, $this->technician2->id]
        ]);

        $response->assertRedirect(route('fop-tasks.index'));
        $this->assertDatabaseHas('fop_tasks', [
            'category' => 'C-REQ',
            'tugas' => 'Instalasi Jalur Baru Kantor',
            'status' => 'Proses'
        ]);

        $task = FopTask::where('tugas', 'Instalasi Jalur Baru Kantor')->first();
        $this->assertCount(2, $task->technicians);
    }

    public function test_cannot_create_pending_task_without_reason_or_date(): void
    {
        $response = $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN',
            'task_date' => now()->format('Y-m-d H:i:s'),
            'tugas' => 'Perbaikan Backbone',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Backbone LOS',
            'status' => 'Pending',
            'priority' => 'Urgent',
            'technicians' => [$this->technician1->id]
        ]);

        $response->assertSessionHasErrors(['pending_reason', 'client_request_date']);
    }

    public function test_can_create_pending_task_with_reason_and_date(): void
    {
        $reqDate = Carbon::today()->addDays(2)->format('Y-m-d');
        $response = $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN',
            'task_date' => now()->format('Y-m-d H:i:s'),
            'tugas' => 'Perbaikan Backbone',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Backbone LOS',
            'status' => 'Pending',
            'priority' => 'Urgent',
            'pending_reason' => 'Menunggu perizinan warga',
            'client_request_date' => $reqDate,
            'technicians' => [$this->technician1->id]
        ]);

        $response->assertRedirect(route('fop-tasks.index'));
        $this->assertDatabaseHas('fop_tasks', [
            'status' => 'Pending',
            'pending_reason' => 'Menunggu perizinan warga',
            'client_request_date' => $reqDate . ' 00:00:00'
        ]);
    }

    public function test_updating_status_to_cancel_sets_cancelled_at(): void
    {
        $task = FopTask::create([
            'task_number' => 'TFOP-2026-0002',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'FO Cut',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'LOS',
            'status' => 'Proses',
            'priority' => 'High'
        ]);

        $response = $this->actingAs($this->fopUser)->put(route('fop-tasks.update', $task->id), [
            'status' => 'Cancel'
        ]);

        $task->refresh();
        $this->assertEquals('Cancel', $task->status->value);
        $this->assertNotNull($task->cancelled_at);
    }

    public function test_console_command_resets_cancelled_tasks_the_next_day(): void
    {
        // 1. Task cancelled yesterday (before today)
        $yesterdayTask = FopTask::create([
            'task_number' => 'TFOP-2026-0003',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'FO Cut 1',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'LOS',
            'status' => 'Cancel',
            'priority' => 'High',
            'cancelled_at' => Carbon::yesterday()
        ]);

        // 2. Task cancelled today
        $todayTask = FopTask::create([
            'task_number' => 'TFOP-2026-0004',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'FO Cut 2',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'LOS',
            'status' => 'Cancel',
            'priority' => 'High',
            'cancelled_at' => Carbon::now()
        ]);

        $this->artisan('fop:reset-cancelled-tasks')->assertExitCode(0);

        $yesterdayTask->refresh();
        $todayTask->refresh();

        // Yesterday's cancelled task must be reset to Proses
        $this->assertEquals('Proses', $yesterdayTask->status->value);
        $this->assertNull($yesterdayTask->cancelled_at);

        // Today's cancelled task must remain Cancel
        $this->assertEquals('Cancel', $todayTask->status->value);
        $this->assertNotNull($todayTask->cancelled_at);
    }

    public function test_can_delete_fop_task(): void
    {
        $task = FopTask::create([
            'task_number' => 'TFOP-2026-0005',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'FO Cut',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'LOS',
            'status' => 'Proses',
            'priority' => 'High'
        ]);

        $response = $this->actingAs($this->fopUser)->delete(route('fop-tasks.destroy', $task->id));
        $response->assertRedirect(route('fop-tasks.index'));
        $this->assertDatabaseMissing('fop_tasks', ['id' => $task->id]);
    }

    public function test_guests_cannot_access_fop_tasks_history(): void
    {
        $response = $this->get(route('fop-tasks.history'));
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_users_cannot_access_fop_tasks_history(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('fop-tasks.history'));
        $response->assertStatus(403);
    }

    public function test_authorized_users_can_view_fop_tasks_history(): void
    {
        $task = FopTask::create([
            'task_number' => 'TFOP-2026-9999',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Perbaikan FO Selesai',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Selesai',
            'status' => 'Selesai',
            'priority' => 'High'
        ]);

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.history'));
        $response->assertStatus(200);
        $response->assertSee('TFOP-2026-9999');
        $response->assertSee('Perbaikan FO Selesai');
    }

    public function test_fop_tasks_index_does_not_show_completed_or_cancelled_tasks(): void
    {
        $completedTask = FopTask::create([
            'task_number' => 'TFOP-2026-1000',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Sudah Selesai',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Selesai',
            'status' => 'Selesai',
            'priority' => 'High'
        ]);

        $cancelledTask = FopTask::create([
            'task_number' => 'TFOP-2026-2000',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Sudah Cancel',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Cancelled',
            'status' => 'Cancel',
            'priority' => 'High'
        ]);

        $activeTask = FopTask::create([
            'task_number' => 'TFOP-2026-3000',
            'task_date' => now(),
            'category' => 'MTN',
            'tugas' => 'Masih Proses',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Proses',
            'status' => 'Proses',
            'priority' => 'High'
        ]);

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
        $response->assertStatus(200);
        $response->assertDontSee('TFOP-2026-1000');
        $response->assertDontSee('TFOP-2026-2000');
        $response->assertSee('TFOP-2026-3000');
    }

    public function test_team_conflict_still_shows_after_modal_closed_and_page_reloaded(): void
    {
        $joko = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id, 'status' => 'active', 'name' => 'Joko']);
        $cagak = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id, 'status' => 'active', 'name' => 'Cagak']);
        $tri = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id, 'status' => 'active', 'name' => 'Tri']);
        $suci = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id, 'status' => 'active', 'name' => 'Suci']);

        $date = now()->format('Y-m-d') . ' 08:00:00';

        $post = fn (array $techs, string $num) => $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN', 'task_date' => $date, 'tugas' => $num,
            'village_id' => $this->village->id, 'pop_id' => $this->pop->id, 'issue' => 'i',
            'status' => 'Proses', 'priority' => 'Medium', 'technicians' => $techs,
        ]);

        $post([$joko->id, $cagak->id], 'A');
        $post([$tri->id, $suci->id], 'B');
        $post([$cagak->id, $suci->id], 'C');

        // Request pertama: session flash masih ada, modal muncul.
        $first = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
        $first->assertStatus(200);
        $first->assertSee('Konflik Team');

        // Simulasikan user nge-close modal lalu refresh halaman lagi — session flash
        // dari request pertama udah abis dibaca, tapi konfliknya harus tetap kedeteksi
        // ulang dari state DB (task_id null + >=2 teknisi), bukan cuma dari flash sekali pakai.
        $second = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));
        $second->assertStatus(200);
        $second->assertSee('Konflik Team');

        $taskC = FopTask::where('tugas', 'C')->firstOrFail();
        $second->assertSee($taskC->task_number);
    }
}
