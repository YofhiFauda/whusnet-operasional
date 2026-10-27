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

class FopUnifiedDashboardTest extends TestCase
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
            'code' => 'JTS',
            'pop_code' => 'JTS',
            'registration_prefix' => 'J',
            'cid_prefix' => 'J',
            'name' => 'POP Jetis',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $roleFop = Role::firstOrCreate(['code' => 'fop'], ['name' => 'FOP']);
        $this->fopUser = User::factory()->create([
            'role_id' => $roleFop->id,
        ]);

        app(EffectiveAccessService::class)->clearCache($this->fopUser);

        $this->fopUser->roleScopes()->create([
            'role_id' => $roleFop->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);
    }

    #[Test]
    public function fop_user_dapat_mengakses_dashboard_dengan_payload_modul_lengkap(): void
    {
        $response = $this->actingAs($this->fopUser)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertViewHasAll([
            'surveyQueue',
            'stats',
            'gudangStats',
            'teknisiList',
            'activeTeams',
            'activeFopTeams',
            'pops',
            'pipelineSummary',
            'heroMetrics',
            'fieldTickets',
            'warehouseTelemetry',
            'odpSummary',
            'emergencyAlerts',
        ]);
    }
}
