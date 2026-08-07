<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Events\FopTaskUpdated;
use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Models\Village;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * App\Events\FopTaskUpdated — broadcast dari 3 titik aksi user di
 * FopTaskController (switchTechnician/assignToTeam/update), ganti
 * setTimeout(reload) lama di fop_tasks/index.blade.php (docs/plan/analisa-
 * realtime-spa-operasional.md §2.2 no. 13). Dispatch manual, BUKAN dari
 * observer — FopTaskController::index() jalanin autoSyncAndCalculatePriority()
 * yang diam-diam update() puluhan row tiap page load, observer bakal
 * broadcast storm kalau dipasang di situ.
 */
class FopTaskUpdatedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private User $tech;

    private User $tech2;

    private Village $village;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $fopRole = Role::where('code', 'fop')->first();
        $teknisiRole = Role::where('code', 'teknisi')->first();

        $this->fopUser = User::factory()->create(['role_id' => $fopRole->id]);
        $this->giveAllPopScope($this->fopUser);
        $this->tech = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Budi']);
        $this->tech2 = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active', 'name' => 'Wati']);

        $city = City::create(['name' => 'Kota']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Distrik']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Desa', 'postal_code' => '11111']);
        $this->pop = Pop::create(['name' => 'POP', 'code' => 'POP-X', 'type' => 'branch', 'address' => 'x', 'status' => 'active', 'city_id' => $city->id]);
    }

    private function createFopTask(string $tugas, array $technicianIds, ?string $taskDate = null): FopTask
    {
        $response = $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN',
            'task_date' => $taskDate ?? now()->format('Y-m-d').' 08:00:00',
            'tugas' => $tugas,
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'i',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => $technicianIds,
        ]);

        $response->assertRedirect(route('fop-tasks.index'));

        return FopTask::where('tugas', $tugas)->firstOrFail();
    }

    public function test_switch_technician_dispatches_event_for_both_tasks(): void
    {
        $taskA = $this->createFopTask('Task A', [$this->tech->id]);
        $taskB = $this->createFopTask('Task B', [$this->tech2->id]);

        Event::fake([FopTaskUpdated::class]);

        $this->actingAs($this->fopUser)->postJson('/fop-tasks/switch-technician', [
            'technician_id' => $this->tech->id,
            'from_task_id' => $taskA->id,
            'to_task_id' => $taskB->id,
            'replacement_technician_id' => $this->tech2->id,
        ])->assertOk();

        Event::assertDispatched(FopTaskUpdated::class, fn ($e) => $e->fopTask->id === $taskA->id);
        Event::assertDispatched(FopTaskUpdated::class, fn ($e) => $e->fopTask->id === $taskB->id);
    }

    public function test_assign_to_team_dispatches_event(): void
    {
        $task = $this->createFopTask('Task Solo', [$this->tech->id]);

        Event::fake([FopTaskUpdated::class]);

        $this->actingAs($this->fopUser)->postJson(route('fop-tasks.assign-to-team', $task), [])
            ->assertOk();

        Event::assertDispatched(FopTaskUpdated::class, fn ($e) => $e->fopTask->id === $task->id);
    }

    public function test_cancel_dispatches_event(): void
    {
        $task = $this->createFopTask('Task Cancel', [$this->tech->id]);

        Event::fake([FopTaskUpdated::class]);

        $this->actingAs($this->fopUser)->putJson(route('fop-tasks.update', $task), [
            'status' => 'dibatalkan',
            'cancel_reason' => 'Salah input.',
        ])->assertOk();

        Event::assertDispatched(FopTaskUpdated::class, fn ($e) => $e->fopTask->id === $task->id);
    }

    public function test_row_endpoint_returns_cells_for_active_task(): void
    {
        $task = $this->createFopTask('Task Aktif', [$this->tech->id]);

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.row', $task));

        $response->assertOk();
        $response->assertSee('tech-cell-'.$task->id, false);
        $response->assertSee('status-cell-'.$task->id, false);
    }

    public function test_row_endpoint_returns_no_content_for_cancelled_task(): void
    {
        $task = $this->createFopTask('Task Batal', [$this->tech->id]);
        $this->actingAs($this->fopUser)->putJson(route('fop-tasks.update', $task), [
            'status' => 'dibatalkan',
            'cancel_reason' => 'Salah input.',
        ])->assertOk();

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.row', $task));

        $response->assertNoContent();
    }

    public function test_row_endpoint_denies_user_without_fop_tasks_permission(): void
    {
        $task = $this->createFopTask('Task Terlarang', [$this->tech->id]);

        $salesRole = Role::where('code', 'sales')->first();
        $noPermUser = User::factory()->create(['role_id' => $salesRole->id]);

        $response = $this->actingAs($noPermUser)->get(route('fop-tasks.row', $task));

        $response->assertForbidden();
    }

    /**
     * Sama seperti InvoiceStatusUpdatedBroadcastTest — phpunit.xml set
     * BROADCAST_CONNECTION=null, jadi otorisasi channel dites langsung lewat
     * EffectiveAccessService (persis yang dicek closure fop-tasks.{popId}),
     * bukan lewat HTTP POST /broadcasting/auth (NullBroadcaster::auth() kosong,
     * selalu 200 tanpa manggil closure-nya).
     */
    public function test_channel_grants_access_for_user_with_permission_and_matching_pop_scope(): void
    {
        $role = Role::where('code', 'fop')->first();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

        $access = app(EffectiveAccessService::class);
        $access->clearCache($user);

        $this->assertTrue($user->hasPermission('fop_tasks.view'));
        $this->assertFalse($access->hasAllPopAccess($user));
        $this->assertContains($this->pop->id, $access->getAllowedPopIds($user));
    }

    public function test_channel_denies_access_for_user_without_fop_tasks_view_permission(): void
    {
        $salesRole = Role::where('code', 'sales')->first();
        $user = User::factory()->create(['role_id' => $salesRole->id, 'status' => 'active']);

        $this->assertFalse($user->hasPermission('fop_tasks.view'));
    }
}
