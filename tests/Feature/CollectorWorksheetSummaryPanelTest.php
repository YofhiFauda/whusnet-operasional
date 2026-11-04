<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CollectorWorksheetSummaryPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createAdmin(Pop $pop): User
    {
        $role = Role::where('name', 'POP Admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->pops()->attach($pop->id);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C'.substr($code, -1),
            'cid_prefix' => 'D'.substr($code, -1),
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function summary_panel_endpoint_returns_kas_and_technician_containers(): void
    {
        $pop = $this->createPop('SMY');
        $admin = $this->createAdmin($pop);

        $response = $this->actingAs($admin)->get(route('collector-worksheet.summary-panel'));

        $response->assertOk();
        $response->assertSee('id="kas-admin-panel"', false);
        $response->assertSee('id="staff-list-technicians"', false);
    }

    #[Test]
    public function summary_panel_endpoint_rejects_role_without_collector_worksheet_permission(): void
    {
        $pop = $this->createPop('SMZ');
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);
        $teknisi->pops()->attach($pop->id);

        $response = $this->actingAs($teknisi)->get(route('collector-worksheet.summary-panel'));

        $response->assertForbidden();
    }
}
