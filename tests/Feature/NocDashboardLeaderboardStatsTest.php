<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NocDashboardLeaderboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(TicketFeatureSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeUserWithAllPopScope(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->first();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        return $user;
    }

    #[Test]
    public function leaderboard_stats_endpoint_returns_leaderboard_partial_html(): void
    {
        $noc = $this->makeUserWithAllPopScope('noc');

        $response = $this->actingAs($noc)->get(route('noc.dashboard.leaderboard-stats'));

        $response->assertOk();
        $response->assertSee('id="leaderboard-container"', false);
        $response->assertSee('Leaderboard Performa Individu');
    }

    #[Test]
    public function leaderboard_stats_endpoint_rejects_role_without_noc_dashboard_permission(): void
    {
        $teknisi = $this->makeUserWithAllPopScope('teknisi');

        $response = $this->actingAs($teknisi)->get(route('noc.dashboard.leaderboard-stats'));

        $response->assertForbidden();
    }
}
