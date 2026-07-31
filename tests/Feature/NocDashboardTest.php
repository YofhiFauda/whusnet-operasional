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
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard NOC (`/noc/dashboard`) — stat counter, list aktif+aging, feed
 * aktivitas, statistik issue & daerah, serta trend matrix daerah x issue.
 */
class NocDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $nocUser;

    private User $helpdeskUser;

    private User $teknisiUser;

    private Customer $customer;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(TicketFeatureSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->nocUser = $this->makeUserWithAllPopScope('noc');
        $this->helpdeskUser = $this->makeUserWithAllPopScope('helpdesk');
        $this->teknisiUser = $this->makeUserWithAllPopScope('teknisi');

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);

        $this->pop = Pop::create([
            'name' => 'POP Polorejo',
            'code' => 'POP-PLR',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
            'city_id' => $city->id,
        ]);

        $this->customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $village->id,
            'full_name' => 'Budi Santoso',
        ]);
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

    public function test_noc_can_access_dashboard(): void
    {
        $this->actingAs($this->nocUser)->get(route('noc.dashboard'))->assertOk();
    }

    public function test_role_without_noc_dashboard_permission_gets_403(): void
    {
        $this->actingAs($this->teknisiUser)->get(route('noc.dashboard'))->assertForbidden();
    }

    public function test_dashboard_shows_active_pending_ticket(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total sejak pagi.',
            'priority' => 'High',
        ])->assertRedirect();
        $ticket = Ticket::latest('id')->firstOrFail();

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertOk();

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $response->assertSee($ticket->ticket_number);
        $response->assertSee('Diproses NOC');
    }

    public function test_dashboard_renders_all_sections(): void
    {
        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $response->assertSee('Tiket Aktif NOC');
        $response->assertSee('Aktivitas Terbaru');
        $response->assertSee('Statistik Tiket per Kategori Issue');
        $response->assertSee('Statistik Tiket per Daerah');
        $response->assertSee('Tren Matriks: Daerah dengan Issue Terbanyak');
    }

    public function test_dashboard_filters_by_preset_and_pop(): void
    {
        $category = TicketIssueCategory::create([
            'name' => 'Kabel Putus',
            'default_type' => TaskType::MAINTENANCE->value,
            'default_priority' => 'High',
            'is_active' => true,
        ]);

        $ticket1 = Ticket::create([
            'ticket_number' => 'TK-TEST-001',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'issue_category_id' => $category->id,
            'detail_keluhan' => 'Kabel FO Putus',
            'priority' => 'High',
            'handler' => 'noc',
            'status' => 'open',
            'created_by' => $this->helpdeskUser->id,
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard', [
            'date_preset' => '7_days',
            'pop_id' => $this->pop->id,
        ]));

        $response->assertOk();
        $stats = $response->viewData('stats');
        $this->assertEquals(1, $stats['total_ticket']);
        $this->assertEquals(1, $stats['diproses_noc']);
    }

    public function test_active_tickets_are_sorted_by_aging_oldest_first(): void
    {
        $olderTicket = Ticket::create([
            'ticket_number' => 'TK-OLD',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Keluhan Lama',
            'priority' => 'High',
            'handler' => 'noc',
            'status' => 'open',
            'created_by' => $this->helpdeskUser->id,
            'created_at' => now()->subHours(10),
        ]);

        $newerTicket = Ticket::create([
            'ticket_number' => 'TK-NEW',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Keluhan Baru',
            'priority' => 'High',
            'handler' => 'noc',
            'status' => 'open',
            'created_by' => $this->helpdeskUser->id,
            'created_at' => now()->subHours(1),
        ]);

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $activeTickets = $response->viewData('activeTickets');

        $this->assertGreaterThanOrEqual(2, $activeTickets->count());
        $firstTicket = $activeTickets->first();
        $this->assertEquals($olderTicket->id, $firstTicket->id);
    }
}
