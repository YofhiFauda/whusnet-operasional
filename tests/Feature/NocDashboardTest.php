<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskType;
use App\Enums\TicketHandler;
use App\Enums\TicketHistoryAction;
use App\Http\Controllers\NocDashboardController;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Models\Village;
use App\Services\EffectiveAccessService;
use App\Services\NocAnalyticsService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
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

    public function test_cold_monthly_trend_waits_for_existing_scope_lock(): void
    {
        $this->actingAs($this->nocUser);

        $popIds = Pop::forUser($this->nocUser)->pluck('id')->sort()->values()->implode(',');
        $cacheKey = sprintf(
            'dashboard:noc:monthly-complaint:%d:%s',
            $this->nocUser->id,
            hash('sha256', $popIds)
        );
        $lock = Cache::lock($cacheKey.':lock', 2);
        $this->assertTrue($lock->get());

        try {
            $started = microtime(true);
            $this->get(route('noc.dashboard'))->assertOk();
            $this->assertTrue(Cache::has($cacheKey), implode(', ', array_keys(Cache::getStore()->all())));
            $this->assertGreaterThan(1.2, microtime(true) - $started);
        } finally {
            $lock->release();
        }
    }

    public function test_cold_telemetry_waits_for_existing_scope_lock(): void
    {
        $this->actingAs($this->nocUser);

        $popIds = Pop::forUser($this->nocUser)->pluck('id')->sort()->values()->implode(',');
        $cacheKey = sprintf(
            'dashboard:noc:telemetry:%d:%s:%s:%s',
            $this->nocUser->id,
            hash('sha256', $popIds),
            'month_to_date',
            ''
        );
        $lock = Cache::lock($cacheKey.':lock', 2);
        $this->assertTrue($lock->get());

        try {
            $started = microtime(true);
            $this->get(route('noc.dashboard'))->assertOk();
            $this->assertTrue(Cache::has($cacheKey), implode(', ', array_keys(Cache::getStore()->all())));
            $this->assertGreaterThan(1.2, microtime(true) - $started);
        } finally {
            $lock->release();
        }
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

    public function test_custom_date_ranges_do_not_share_cached_stats(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TK-CUSTOM-RANGE',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Keluhan Januari',
            'priority' => 'High',
            'handler' => 'noc',
            'status' => 'open',
            'created_by' => $this->helpdeskUser->id,
        ]);
        Ticket::query()->whereKey($ticket->id)->update(['created_at' => '2026-01-10 10:00:00']);

        $january = $this->actingAs($this->nocUser)->get(route('noc.dashboard', [
            'date_preset' => 'custom',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]));
        $february = $this->actingAs($this->nocUser)->get(route('noc.dashboard', [
            'date_preset' => 'custom',
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-28',
        ]));

        $january->assertOk();
        $february->assertOk();
        $this->assertSame(1, $january->viewData('stats')['total_ticket']);
        $this->assertSame(0, $february->viewData('stats')['total_ticket']);
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

    public function test_leaderboard_counts_histories_without_hydrating_non_completion_actions(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet lambat.',
            'priority' => 'High',
        ])->assertRedirect();

        $ticket = Ticket::latest('id')->firstOrFail();
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'action' => TicketHistoryAction::DIESKALASI->value,
            'to_status' => TicketHandler::NOC->value,
            'actor_id' => $this->helpdeskUser->id,
            'happened_at' => now(),
        ]);
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'action' => TicketHistoryAction::DIKEMBALIKAN->value,
            'actor_id' => $this->helpdeskUser->id,
            'happened_at' => now(),
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'action' => TicketHistoryAction::DIESKALASI->value,
            'to_status' => TicketHandler::HELPDESK->value,
            'actor_id' => $this->nocUser->id,
            'happened_at' => now(),
        ]);

        $hydratedHistories = 0;
        TicketHistory::retrieved(function () use (&$hydratedHistories): void {
            $hydratedHistories++;
        });

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard.leaderboard-stats', ['date_preset' => 'all_time']));

        $response->assertOk();
        $response->assertSee($this->helpdeskUser->name);
        $response->assertDontSee($this->nocUser->name);
        $this->assertSame(0, $hydratedHistories);
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

    public function test_monthly_complaint_window_reads_compact_rows_without_hydrating_tickets(): void
    {
        $category = TicketIssueCategory::create([
            'name' => 'Kabel Putus',
            'default_priority' => 'High',
            'is_active' => true,
        ]);
        Customer::query()->whereKey($this->customer->id)->update([
            'district_id' => Village::findOrFail($this->customer->village_id)->district_id,
        ]);
        Ticket::create([
            'ticket_number' => 'TK-COMPACT-TREND',
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'issue_category_id' => $category->id,
            'detail_keluhan' => 'Kabel putus.',
            'priority' => 'High',
            'handler' => 'noc',
            'status' => 'open',
            'created_by' => $this->helpdeskUser->id,
        ]);

        $hydratedTickets = 0;
        Ticket::retrieved(function () use (&$hydratedTickets): void {
            $hydratedTickets++;
        });

        $this->actingAs($this->nocUser);
        $window = (new ReflectionMethod(NocDashboardController::class, 'loadComplaintWindow'))
            ->invoke(app(NocDashboardController::class));

        $this->assertSame(0, $hydratedTickets);
        $this->assertCount(1, $window['tickets']);
        $this->assertSame('Babadan', $window['tickets']->first()->region_name);
        $this->assertSame('Kabel Putus', $window['tickets']->first()->issue_name);
    }

    public function test_monthly_complaint_window_keeps_selected_pop_scope(): void
    {
        $otherPop = Pop::create([
            'name' => 'POP Luar Scope',
            'code' => 'POP-LUAR-NOC',
            'type' => 'branch',
            'address' => 'Luar scope',
            'status' => 'active',
        ]);
        $otherCustomer = Customer::factory()->create([
            'pop_id' => $otherPop->id,
            'village_id' => $this->customer->village_id,
        ]);

        foreach ([$this->customer, $otherCustomer] as $index => $customer) {
            Ticket::create([
                'ticket_number' => "TK-SCOPE-TREND-{$index}",
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $customer->id,
                'pop_id' => $customer->pop_id,
                'detail_keluhan' => 'Uji scope.',
                'priority' => 'High',
                'handler' => 'noc',
                'status' => 'open',
                'created_by' => $this->helpdeskUser->id,
            ]);
        }

        $scope = $this->nocUser->roleScopes()->firstOrFail();
        $scope->update(['scope_type' => ScopeType::SELECTED_POP->value]);
        $scope->targets()->create(['pop_id' => $this->pop->id]);
        app(EffectiveAccessService::class)->clearCache($this->nocUser);

        $this->actingAs($this->nocUser);
        $window = (new ReflectionMethod(NocDashboardController::class, 'loadComplaintWindow'))
            ->invoke(app(NocDashboardController::class));

        $this->assertCount(1, $window['tickets']);
        $this->assertSame($this->pop->id, (int) $window['tickets']->first()->pop_id);
    }

    public function test_dashboard_cache_isolated_when_user_pop_scope_shrinks(): void
    {
        $otherPop = Pop::create([
            'name' => 'POP Cache Luar',
            'code' => 'POP-CACHE-LUAR',
            'type' => 'branch',
            'address' => 'Luar scope',
            'status' => 'active',
        ]);
        $otherCustomer = Customer::factory()->create([
            'pop_id' => $otherPop->id,
            'village_id' => $this->customer->village_id,
        ]);

        foreach ([$this->customer, $otherCustomer] as $index => $customer) {
            Ticket::create([
                'ticket_number' => "TK-CACHE-SCOPE-{$index}",
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $customer->id,
                'pop_id' => $customer->pop_id,
                'detail_keluhan' => 'Uji cache POP.',
                'priority' => 'High',
                'handler' => 'noc',
                'status' => 'open',
                'created_by' => $this->helpdeskUser->id,
            ]);
        }

        $before = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));
        $before->assertOk();
        $this->assertSame(2, $before->viewData('stats')['total_ticket']);
        $this->assertSame(2, $before->viewData('issueStats')['Lainnya']['count']);
        $this->assertSame(2, $before->viewData('monthlyComplaintTrend')['total']);

        $scope = $this->nocUser->roleScopes()->firstOrFail();
        $scope->update(['scope_type' => ScopeType::SELECTED_POP->value]);
        $scope->targets()->create(['pop_id' => $this->pop->id]);
        app(EffectiveAccessService::class)->clearCache($this->nocUser);

        $after = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));
        $after->assertOk();
        $this->assertSame(1, $after->viewData('stats')['total_ticket']);
        $this->assertSame(1, $after->viewData('issueStats')['Lainnya']['count']);
        $this->assertSame(1, $after->viewData('monthlyComplaintTrend')['total']);
        $this->assertCount(1, $after->viewData('perPopAnalytics'));
        $this->assertCount(1, $after->viewData('deepAnalytics')['regional_complaint_rates']);
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

    public function test_chronic_customer_analytics_does_not_hydrate_every_ticket(): void
    {
        $category = TicketIssueCategory::create([
            'name' => 'Koneksi Putus',
            'default_priority' => 'High',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 2; $i++) {
            Ticket::create([
                'ticket_number' => "TK-CHRONIC-{$i}",
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $this->customer->id,
                'pop_id' => $this->pop->id,
                'issue_category_id' => $category->id,
                'detail_keluhan' => 'Koneksi putus.',
                'priority' => 'High',
                'handler' => 'noc',
                'status' => 'open',
                'created_by' => $this->helpdeskUser->id,
            ]);
        }

        $hydratedTickets = 0;
        Ticket::retrieved(function () use (&$hydratedTickets): void {
            $hydratedTickets++;
        });

        $this->actingAs($this->nocUser);
        $rows = app(NocAnalyticsService::class)->getChronicCustomers(Ticket::query()->applyUserScope());

        $this->assertSame(0, $hydratedTickets);
        $this->assertCount(1, $rows);
        $this->assertSame($this->customer->full_name, $rows[0]['name']);
        $this->assertSame('Koneksi Putus', $rows[0]['top_issue']);
        $this->assertSame(2, $rows[0]['count']);
    }

    public function test_issue_region_and_matrix_stats_do_not_hydrate_period_tickets(): void
    {
        $category = TicketIssueCategory::create([
            'name' => 'Kabel Putus',
            'default_priority' => 'High',
            'is_active' => true,
        ]);
        $districtId = Village::findOrFail($this->customer->village_id)->district_id;
        Customer::query()->whereKey($this->customer->id)->update(['district_id' => $districtId]);
        $customerWithoutDistrict = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'district_id' => null,
        ]);

        for ($i = 0; $i < 40; $i++) {
            Ticket::create([
                'ticket_number' => "TK-AGGREGATE-{$i}",
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $i < 25 ? $this->customer->id : $customerWithoutDistrict->id,
                'pop_id' => $this->pop->id,
                'issue_category_id' => $i < 30 ? $category->id : null,
                'detail_keluhan' => 'Keluhan statistik.',
                'priority' => 'High',
                'handler' => TicketHandler::HELPDESK->value,
                'status' => 'cancelled',
                'created_by' => $this->helpdeskUser->id,
                'created_at' => now()->subDay(),
            ]);
        }

        $hydratedTickets = 0;
        Ticket::retrieved(function () use (&$hydratedTickets): void {
            $hydratedTickets++;
        });

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $this->assertSame(30, $response->viewData('issueStats')['Kabel Putus']['count']);
        $this->assertSame(10, $response->viewData('issueStats')['Lainnya']['count']);
        $this->assertSame(25, $response->viewData('regionStats')['Babadan']['count']);
        $this->assertSame(15, $response->viewData('regionStats')[$this->pop->name]['count']);
        $this->assertSame(25, $response->viewData('trendMatrix')['matrix']['Babadan']['Kabel Putus']);
        $this->assertSame(5, $response->viewData('trendMatrix')['matrix'][$this->pop->name]['Kabel Putus']);
        $this->assertSame(10, $response->viewData('trendMatrix')['matrix'][$this->pop->name]['Lainnya']);
        $this->assertLessThan(130, $hydratedTickets);
    }

    public function test_average_duration_uses_resolved_time_without_loading_period_tickets(): void
    {
        $createdAt = now()->subDay()->startOfHour();
        for ($i = 0; $i < 40; $i++) {
            $ticket = Ticket::create([
                'ticket_number' => "TK-DURATION-{$i}",
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $this->customer->id,
                'pop_id' => $this->pop->id,
                'detail_keluhan' => 'Keluhan durasi.',
                'priority' => 'High',
                'handler' => TicketHandler::HELPDESK->value,
                'status' => 'closed',
                'created_by' => $this->helpdeskUser->id,
                'resolved_at' => $createdAt->copy()->addMinutes(90),
            ]);
            Ticket::query()->whereKey($ticket->id)->update(['created_at' => $createdAt]);
        }

        $hydratedTickets = 0;
        Ticket::retrieved(function () use (&$hydratedTickets): void {
            $hydratedTickets++;
        });

        $response = $this->actingAs($this->nocUser)->get(route('noc.dashboard'));

        $response->assertOk();
        $this->assertSame(90, $response->viewData('stats')['avg_duration_minutes']);
        $this->assertSame('1 Jam 30 Mns', $response->viewData('stats')['avg_duration_label']);
        $this->assertLessThan(180, $hydratedTickets);
    }

    public function test_operator_workload_uses_latest_history_without_hydrating_all_open_tickets(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $ticket = Ticket::create([
                'ticket_number' => "TK-WORKLOAD-{$i}",
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $this->customer->id,
                'pop_id' => $this->pop->id,
                'detail_keluhan' => 'Keluhan operator.',
                'priority' => 'High',
                'handler' => TicketHandler::NOC->value,
                'status' => 'open',
                'created_by' => $this->helpdeskUser->id,
            ]);
            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'action' => TicketHistoryAction::DIESKALASI->value,
                'actor_id' => $this->nocUser->id,
                'happened_at' => now()->subSeconds(40 - $i),
            ]);
        }

        $hydratedTickets = 0;
        $hydratedHistories = 0;
        Ticket::retrieved(function () use (&$hydratedTickets): void {
            $hydratedTickets++;
        });
        TicketHistory::retrieved(function () use (&$hydratedHistories): void {
            $hydratedHistories++;
        });

        $this->actingAs($this->nocUser);
        $rows = (new ReflectionMethod(NocAnalyticsService::class, 'buildOperatorWorkloads'))
            ->invoke(app(NocAnalyticsService::class), Ticket::query()->applyUserScope());

        $this->assertCount(1, $rows);
        $this->assertSame($this->nocUser->id, $rows[0]['user_id']);
        $this->assertSame(40, $rows[0]['active_count']);
        $this->assertSame('TK-WORKLOAD-39', $rows[0]['latest_ticket']);
        $this->assertSame(0, $hydratedTickets);
        $this->assertSame(0, $hydratedHistories);
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
