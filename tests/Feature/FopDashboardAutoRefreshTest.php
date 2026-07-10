<?php

namespace Tests\Feature;

use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FopDashboardAutoRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected User $fopUser;
    protected Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\TaskFeatureSeeder::class);

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
        $ownerRole = Role::firstOrCreate(['code' => 'owner'], ['name' => 'Owner']);
        $this->fopUser->role_id = $ownerRole->id;
        $this->fopUser->save();

        app(\App\Services\EffectiveAccessService::class)->clearCache($this->fopUser);

        $this->fopUser->roleScopes()->create([
            'role_id' => $ownerRole->id,
            'scope_type' => \App\Enums\ScopeType::ALL_POP->value,
        ]);
    }

    public function test_fop_dashboard_view_contains_auto_refresh_containers_and_echo_listener(): void
    {
        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('stat-cards-container');
        $response->assertSee('kanban-pipeline-container');
        $response->assertSee('antrian-survey-container');
        $response->assertSee('status-teknisi-container');
        $response->assertSee('initEchoListeners');
        $response->assertSee('refreshDashboardContainers');
    }
}
