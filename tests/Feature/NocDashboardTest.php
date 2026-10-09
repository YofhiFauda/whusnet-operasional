<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Models\Village;
use App\Services\EffectiveAccessService;
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

    public function test_dashboard_renders_new_analytic_sections(): void
    {
        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $response->assertSee('Tren Harian Volume Tiket');
        $response->assertSee('SLA Compliance');
        $response->assertSee('Distribusi Aging Tiket Aktif NOC');
    }

    public function test_role_without_performance_permission_does_not_see_leaderboard(): void
    {
        // Helpdesk gak diberi noc_dashboard.performance.view di RolePermissionSeeder
        // (dia bukan pemegang halaman ini), tapi test ini butuh akses ke
        // /noc/dashboard buat memverifikasi — pinjam permission noc_dashboard.view
        // langsung ke role via RoleManagementService supaya gak nyalain seluruh
        // noc_dashboard.* yang bakal ikut nyalain performance.view juga.
        $role = Role::where('code', 'helpdesk')->first();
        $role->permissions()->attach(Permission::where('code', 'noc_dashboard.view')->firstOrFail());
        app(EffectiveAccessService::class)->clearCache($this->helpdeskUser);

        $response = $this->actingAs($this->helpdeskUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Leaderboard Performa Individu');
        $this->assertFalse($response->viewData('canViewPerformance'));
        $this->assertNull($response->viewData('leaderboard'));
    }

    public function test_leaderboard_attributes_actions_to_correct_actor_and_role(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet lambat.',
            'priority' => 'High',
        ])->assertRedirect();
        $ticket = Ticket::latest('id')->firstOrFail();

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertOk();

        $this->actingAs($this->nocUser)
            ->postJson(route('tickets.close', $ticket), ['reason' => 'Selesai ditangani NOC'])
            ->assertOk();

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard', ['date_preset' => 'all_time']));
        $response->assertOk();

        $leaderboard = $response->viewData('leaderboard');

        $this->assertNotNull($leaderboard);
        $this->assertCount(1, $leaderboard['helpdesk']);
        $this->assertEquals($this->helpdeskUser->id, $leaderboard['helpdesk'][0]['user_id']);
        $this->assertEquals(1, $leaderboard['helpdesk'][0]['jumlah_eskalasi_noc']);
        $this->assertEquals(0, $leaderboard['helpdesk'][0]['jumlah_selesai']);

        $this->assertCount(1, $leaderboard['noc']);
        $this->assertEquals($this->nocUser->id, $leaderboard['noc'][0]['user_id']);
        $this->assertEquals(1, $leaderboard['noc'][0]['jumlah_selesai']);
    }

    public function test_delta_stats_null_when_all_time_preset(): void
    {
        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard', ['date_preset' => 'all_time']));

        $response->assertOk();
        $this->assertNull($response->viewData('deltaStats'));
    }

    public function test_delta_stats_computed_for_month_to_date(): void
    {
        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $delta = $response->viewData('deltaStats');
        $this->assertIsArray($delta);
        $this->assertArrayHasKey('total_ticket', $delta);
    }

    public function test_aging_buckets_not_limited_to_thirty_active_tickets(): void
    {
        for ($i = 0; $i < 32; $i++) {
            Ticket::create([
                'ticket_number' => "TK-AGE-{$i}",
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $this->customer->id,
                'pop_id' => $this->pop->id,
                'detail_keluhan' => 'Keluhan aging',
                'priority' => 'High',
                'handler' => 'noc',
                'status' => 'open',
                'created_by' => $this->helpdeskUser->id,
                'created_at' => now()->subHours(1),
            ]);
        }

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $aging = $response->viewData('agingBuckets');
        $this->assertEquals(32, $aging['0_8']);
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

    public function test_monthly_complaint_trend_renders_and_spans_twelve_months(): void
    {
        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $response->assertSee('Tren Bulanan Komplain per Daerah');
        $response->assertSee('Tren Bulanan Komplain per Kategori Issue');

        $trend = $response->viewData('monthlyComplaintTrend');
        $this->assertCount(12, $trend['labels']);
        $this->assertArrayHasKey('series', $trend['by_region']);
        $this->assertArrayHasKey('series', $trend['by_issue']);
    }

    public function test_monthly_complaint_trend_only_counts_maintenance_type_and_ignores_pop_filter(): void
    {
        $city2 = City::create(['name' => 'Kota Lain']);
        $district2 = District::create(['city_id' => $city2->id, 'name' => 'Kecamatan Lain']);
        $village2 = Village::create(['district_id' => $district2->id, 'name' => 'Desa Lain', 'postal_code' => '11111']);
        $pop2 = Pop::create([
            'name' => 'POP Lain',
            'code' => 'POP-LAIN',
            'type' => 'branch',
            'address' => 'Alamat Lain',
            'status' => 'active',
            'city_id' => $city2->id,
        ]);
        $customer2 = Customer::factory()->create(['pop_id' => $pop2->id, 'village_id' => $village2->id, 'full_name' => 'Pelanggan POP Lain']);

        // Komplain (MTN) di POP lain, bulan berjalan — harus ikut kehitung
        // walau nanti filter pop_id diarahkan ke $this->pop.
        Ticket::create([
            'ticket_number' => 'TK-MTN-POP2',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $customer2->id,
            'pop_id' => $pop2->id,
            'detail_keluhan' => 'Internet mati di POP lain',
            'priority' => 'High',
            'handler' => 'noc',
            'status' => 'open',
            'created_by' => $this->helpdeskUser->id,
            'created_at' => now(),
        ]);

        // C-REQ (bukan komplain) — jangan ikut kehitung sama sekali.
        Ticket::create([
            'ticket_number' => 'TK-CREQ',
            'type' => TaskType::CREQ->value,
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Minta ganti paket',
            'priority' => 'High',
            'handler' => 'noc',
            'status' => 'open',
            'created_by' => $this->helpdeskUser->id,
            'created_at' => now(),
        ]);

        // Filter ke $this->pop doang — chart komplain bulanan tetap harus
        // ikutkan tiket POP lain (SENGAJA independen dari filter pop_id).
        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard', ['pop_id' => $this->pop->id]));

        $response->assertOk();
        $trend = $response->viewData('monthlyComplaintTrend');
        $this->assertEquals(1, $trend['total']);
    }

    public function test_per_pop_analytics_renders_bar_line_combo_for_pop_with_data(): void
    {
        Ticket::create([
            'ticket_number' => 'TK-POP-1',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Internet lemot.',
            'priority' => 'High',
            'handler' => 'noc',
            'status' => 'open',
            'created_by' => $this->helpdeskUser->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $response->assertSee('Performa & Analisa per POP');
        $response->assertSee($this->pop->name);

        $perPop = $response->viewData('perPopAnalytics');
        $this->assertNotEmpty($perPop);

        $entry = collect($perPop)->firstWhere('pop_id', $this->pop->id);
        $this->assertNotNull($entry);
        $this->assertCount(12, $entry['labels']);
        $this->assertCount(12, $entry['complaints']);
        $this->assertCount(12, $entry['customers']);
        $this->assertEquals(1, array_sum($entry['complaints']));
        // $this->customer dibuat CustomerFactory (registration_date = now()) —
        // minimal bulan berjalan harus kehitung ≥1 pelanggan.
        $this->assertGreaterThanOrEqual(1, end($entry['customers']));
        $this->assertNotEmpty($entry['analysis']);
        $this->assertStringNotContainsString('NaN', $entry['analysis']);
    }

    public function test_pop_without_any_customer_is_skipped_from_per_pop_analytics(): void
    {
        $emptyCity = City::create(['name' => 'Kota Kosong']);
        $emptyPop = Pop::create([
            'name' => 'POP Tanpa Pelanggan',
            'code' => 'POP-KOSONG',
            'type' => 'branch',
            'address' => 'Alamat Kosong',
            'status' => 'active',
            'city_id' => $emptyCity->id,
        ]);

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();

        // 'POP Tanpa Pelanggan' SAH muncul di dropdown filter "POP / Cabang"
        // (daftar semua POP dalam scope, terlepas ada data atau tidak) —
        // yang diverifikasi di sini murni section Performa & Analisa per POP.
        $perPop = $response->viewData('perPopAnalytics');
        $this->assertNull(collect($perPop)->firstWhere('pop_id', $emptyPop->id));
    }
}
