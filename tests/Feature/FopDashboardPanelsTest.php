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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FopDashboardPanelsTest extends TestCase
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

    #[Test]
    public function panels_endpoint_returns_only_requested_containers(): void
    {
        $response = $this->actingAs($this->fopUser)
            ->get(route('fop.dashboard.panels', ['targets' => 'stat-cards']));

        $response->assertOk();
        $response->assertSee('id="stat-cards-container"', false);
        $response->assertDontSee('id="status-teknisi-container"', false);
    }

    #[Test]
    public function panels_endpoint_returns_both_targets_when_requested(): void
    {
        $response = $this->actingAs($this->fopUser)
            ->get(route('fop.dashboard.panels', ['targets' => 'stat-cards,status-teknisi']));

        $response->assertOk();
        $response->assertSee('id="stat-cards-container"', false);
        $response->assertSee('id="status-teknisi-container"', false);
    }

    #[Test]
    public function panels_endpoint_rejects_user_without_task_view_all(): void
    {
        $teknisiRole = Role::firstOrCreate(['code' => 'teknisi'], ['name' => 'Teknisi']);
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $response = $this->actingAs($teknisi)
            ->get(route('fop.dashboard.panels', ['targets' => 'stat-cards']));

        $response->assertForbidden();
    }
}
