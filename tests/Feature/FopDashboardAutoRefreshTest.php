<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
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

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);

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

        app(EffectiveAccessService::class)->clearCache($this->fopUser);

        $this->fopUser->roleScopes()->create([
            'role_id' => $ownerRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);
    }

    public function test_fop_dashboard_view_contains_auto_refresh_containers_and_echo_listener(): void
    {
        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('stat-cards-container');
        $response->assertSee('antrian-survey-container');
        $response->assertSee('status-teknisi-container');
        $response->assertSee('initEchoListeners');
        $response->assertSee('refreshDashboardContainers');
    }
}
