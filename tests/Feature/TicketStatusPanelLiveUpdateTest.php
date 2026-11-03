<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketStatusPanelLiveUpdateTest extends TestCase
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

    private function makeHelpdeskAllPop(): User
    {
        $role = Role::where('code', 'helpdesk')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        return $user;
    }

    private function makeHelpdeskScopedToPop(Pop $pop): User
    {
        $role = Role::where('code', 'helpdesk')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

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
        $city = City::create(['name' => "Kota {$code}"]);
        $district = District::create(['city_id' => $city->id, 'name' => "Kec {$code}"]);
        Village::create([
            'district_id' => $district->id,
            'name' => "Desa {$code}",
            'postal_code' => '63400',
        ]);

        return Pop::create([
            'name' => "POP {$code}",
            'code' => $code,
            'type' => 'branch',
            'address' => $code,
            'status' => 'active',
            'city_id' => $city->id,
        ]);
    }

    private function createTicketViaHelpdesk(User $helpdesk, Pop $pop): Ticket
    {
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        $this->actingAs($helpdesk)
            ->post(route('tickets.store'), [
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $customer->id,
                'detail_keluhan' => 'Internet mati total.',
                'catatan_teknis' => 'Redaman tinggi.',
                'priority' => 'High',
            ])
            ->assertRedirect();

        return Ticket::where('customer_id', $customer->id)->firstOrFail();
    }

    #[Test]
    public function status_panel_endpoint_returns_current_ticket_status(): void
    {
        $helpdesk = $this->makeHelpdeskAllPop();
        $pop = $this->createPop('PLR');
        $ticket = $this->createTicketViaHelpdesk($helpdesk, $pop);

        $response = $this->actingAs($helpdesk)->get(route('tickets.status-panel', $ticket));

        $response->assertOk();
        $response->assertSee('id="ticket-status-panel"', false);
        $response->assertSee($ticket->fresh()->statusLabel());
    }

    #[Test]
    public function status_panel_endpoint_respects_pop_scope(): void
    {
        $helpdeskAllPop = $this->makeHelpdeskAllPop();
        $ticketPop = $this->createPop('PLR');
        $otherPop = $this->createPop('OTR');
        $ticket = $this->createTicketViaHelpdesk($helpdeskAllPop, $ticketPop);

        $scopedOutsider = $this->makeHelpdeskScopedToPop($otherPop);

        $response = $this->actingAs($scopedOutsider)->get(route('tickets.status-panel', $ticket));

        $response->assertForbidden();
    }
}
