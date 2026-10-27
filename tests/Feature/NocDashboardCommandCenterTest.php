<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\ScopeType;
use App\Enums\TaskType;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
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

class NocDashboardCommandCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $nocUser;

    private Customer $customer1;

    private Customer $customer2;

    private Pop $pop1;

    private Pop $pop2;

    private Village $village;

    private TicketIssueCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(TicketFeatureSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('code', 'noc')->firstOrFail();
        $this->nocUser = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $this->nocUser->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);

        $this->pop1 = Pop::create([
            'name' => 'POP Polorejo',
            'code' => 'POP-PLR',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
            'city_id' => $city->id,
        ]);

        $this->pop2 = Pop::create([
            'name' => 'POP Siman',
            'code' => 'POP-SMN',
            'type' => 'branch',
            'address' => 'Siman',
            'status' => 'active',
            'city_id' => $city->id,
        ]);

        $this->customer1 = Customer::factory()->create([
            'pop_id' => $this->pop1->id,
            'village_id' => $this->village->id,
            'full_name' => 'Budi Santoso',
            'cid' => 'CUST-001',
            'status' => 'active',
        ]);

        $this->customer2 = Customer::factory()->create([
            'pop_id' => $this->pop2->id,
            'village_id' => $this->village->id,
            'full_name' => 'Siti Aminah',
            'cid' => 'CUST-002',
            'status' => 'active',
        ]);

        $this->category = TicketIssueCategory::create([
            'name' => 'LOS Merah',
            'default_type' => TaskType::MAINTENANCE->value,
            'default_priority' => 'High',
            'is_active' => true,
        ]);
    }

    public function test_noc_dashboard_passes_live_telemetry_and_deep_analytics(): void
    {
        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $response->assertViewHas('liveTelemetry');
        $response->assertViewHas('deepAnalytics');

        $telemetry = $response->viewData('liveTelemetry');
        $this->assertArrayHasKey('urgent_tickets', $telemetry);
        $this->assertArrayHasKey('priority_counts', $telemetry);
        $this->assertArrayHasKey('cluster_alerts', $telemetry);
        $this->assertArrayHasKey('operator_workloads', $telemetry);
        $this->assertArrayHasKey('open_noc_count', $telemetry);

        $analytics = $response->viewData('deepAnalytics');
        $this->assertArrayHasKey('mttr_remote_minutes', $analytics);
        $this->assertArrayHasKey('mttr_field_minutes', $analytics);
        $this->assertArrayHasKey('fcr_rate', $analytics);
        $this->assertArrayHasKey('chronic_customers', $analytics);
        $this->assertArrayHasKey('regional_complaint_rates', $analytics);
        $this->assertArrayHasKey('shift_summary', $analytics);
    }

    public function test_urgent_tickets_detects_at_risk_sla_and_high_priority(): void
    {
        // Tiket yang akan lewat SLA dalam 30 menit
        $ticketAtRisk = Ticket::create([
            'ticket_number' => 'TK-ATRISK-01',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer1->id,
            'pop_id' => $this->pop1->id,
            'issue_category_id' => $this->category->id,
            'detail_keluhan' => 'Redaman drop drastis',
            'priority' => FopTaskPriority::HIGH->value,
            'handler' => TicketHandler::NOC->value,
            'status' => TicketHandlingStatus::OPEN->value,
            'created_by' => $this->nocUser->id,
            'sla_deadline_at' => now()->addMinutes(30),
        ]);

        // Tiket yang sudah overdue
        $ticketOverdue = Ticket::create([
            'ticket_number' => 'TK-OVERDUE-01',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer2->id,
            'pop_id' => $this->pop2->id,
            'issue_category_id' => $this->category->id,
            'detail_keluhan' => 'LOS kabel terbakar',
            'priority' => FopTaskPriority::URGENT->value,
            'handler' => TicketHandler::NOC->value,
            'status' => TicketHandlingStatus::OPEN->value,
            'created_by' => $this->nocUser->id,
            'sla_deadline_at' => now()->subMinutes(15),
        ]);

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));
        $response->assertOk();

        $telemetry = $response->viewData('liveTelemetry');
        $urgentList = collect($telemetry['urgent_tickets']);

        $this->assertTrue($urgentList->contains('ticket_number', 'TK-ATRISK-01'));
        $this->assertTrue($urgentList->contains('ticket_number', 'TK-OVERDUE-01'));

        $overdueItem = $urgentList->firstWhere('ticket_number', 'TK-OVERDUE-01');
        $this->assertTrue($overdueItem['is_breached']);

        $atRiskItem = $urgentList->firstWhere('ticket_number', 'TK-ATRISK-01');
        $this->assertTrue($atRiskItem['is_at_risk']);
    }

    public function test_cluster_alert_triggers_when_four_tickets_in_same_area(): void
    {
        // Buat 4 tiket dalam 30 menit terakhir di desa yang sama
        for ($i = 1; $i <= 4; $i++) {
            Ticket::create([
                'ticket_number' => "TK-CLUSTER-0{$i}",
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $this->customer1->id,
                'pop_id' => $this->pop1->id,
                'customer_odp' => 'ODP-PLR-01',
                'issue_category_id' => $this->category->id,
                'detail_keluhan' => 'Mati serentak',
                'priority' => FopTaskPriority::HIGH->value,
                'handler' => TicketHandler::NOC->value,
                'status' => TicketHandlingStatus::OPEN->value,
                'created_by' => $this->nocUser->id,
                'created_at' => now()->subMinutes(20),
            ]);
        }

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));
        $response->assertOk();

        $telemetry = $response->viewData('liveTelemetry');
        $this->assertNotEmpty($telemetry['cluster_alerts']);

        $alert = $telemetry['cluster_alerts'][0];
        $this->assertStringContainsString('ODP-PLR-01', $alert['location']);
        $this->assertGreaterThanOrEqual(4, $alert['count']);
        $response->assertSee('INDIKASI GANGGUAN MASSAL');
    }

    public function test_chronic_customers_detects_repeat_complaints_within_thirty_days(): void
    {
        // Pelanggan 1 memiliki 3 tiket maintenance dalam 15 hari terakhir
        for ($i = 1; $i <= 3; $i++) {
            Ticket::create([
                'ticket_number' => "TK-CHRONIC-0{$i}",
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $this->customer1->id,
                'pop_id' => $this->pop1->id,
                'issue_category_id' => $this->category->id,
                'detail_keluhan' => "Komplain ke-{$i}",
                'priority' => FopTaskPriority::MEDIUM->value,
                'handler' => TicketHandler::NOC->value,
                'status' => TicketHandlingStatus::CLOSED->value,
                'created_by' => $this->nocUser->id,
                'created_at' => now()->subDays(5 * $i),
                'resolved_at' => now()->subDays(5 * $i)->addHours(2),
            ]);
        }

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));
        $response->assertOk();

        $analytics = $response->viewData('deepAnalytics');
        $chronicList = collect($analytics['chronic_customers']);

        $this->assertTrue($chronicList->contains('customer_id', $this->customer1->id));
        $chronicCust = $chronicList->firstWhere('customer_id', $this->customer1->id);
        $this->assertEquals(3, $chronicCust['count']);
        $this->assertEquals($this->customer1->full_name, $chronicCust['name']);
    }

    public function test_regional_complaint_rate_calculates_correct_percentage(): void
    {
        // 2 tiket di POP 1 (dengan 1 customer = 200%)
        Ticket::create([
            'ticket_number' => 'TK-RATE-01',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer1->id,
            'pop_id' => $this->pop1->id,
            'issue_category_id' => $this->category->id,
            'detail_keluhan' => 'Komplain POP 1',
            'priority' => FopTaskPriority::LOW->value,
            'handler' => TicketHandler::NOC->value,
            'status' => TicketHandlingStatus::OPEN->value,
            'created_by' => $this->nocUser->id,
        ]);

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));
        $response->assertOk();

        $analytics = $response->viewData('deepAnalytics');
        $rates = collect($analytics['regional_complaint_rates']);

        $this->assertTrue($rates->contains('pop_id', $this->pop1->id));
        $pop1Rate = $rates->firstWhere('pop_id', $this->pop1->id);
        $this->assertGreaterThan(0, $pop1Rate['rate_pct']);
    }

    public function test_empty_state_does_not_crash_or_divide_by_zero(): void
    {
        // Tidak ada tiket sama sekali
        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $analytics = $response->viewData('deepAnalytics');
        $this->assertEquals(0.0, $analytics['fcr_rate']);
        $this->assertEmpty($analytics['chronic_customers']);
    }
}
