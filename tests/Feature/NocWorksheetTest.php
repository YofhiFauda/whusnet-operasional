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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Worksheet NOC — dua HALAMAN mandiri (route + permission sendiri):
 * SATU halaman tanpa tab (ADHOC-06) — dua tab lama (`/noc/worksheet/masuk`
 * pending & `/noc/worksheet/diproses`) dilebur. Terpisah dari panel "List Task
 * Ticketing" di /tickets/new (worksheet bersama Helpdesk & NOC).
 */
class NocWorksheetTest extends TestCase
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

    private function submitTicketToNoc(): Ticket
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total sejak pagi.',
            'priority' => 'High',
        ])->assertRedirect();

        $ticket = Ticket::latest('id')->firstOrFail();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        return $ticket->fresh();
    }

    public function test_noc_can_open_worksheet(): void
    {
        $this->actingAs($this->nocUser)->get(route('noc.worksheet'))->assertOk();
    }

    /**
     * Dua route tab lama dihapus (ADHOC-06) — link/bookmark lama diarahkan
     * balik ke halaman utama, bukan jadi 404.
     */
    public function test_legacy_tab_urls_redirect_to_single_page(): void
    {
        $this->actingAs($this->nocUser)->get('/noc/worksheet/masuk')->assertRedirect('/noc/worksheet');
        $this->actingAs($this->nocUser)->get('/noc/worksheet/diproses')->assertRedirect('/noc/worksheet');
    }

    public function test_worksheet_is_forbidden_without_permission(): void
    {
        $this->actingAs($this->teknisiUser)->get(route('noc.worksheet'))->assertForbidden();
    }

    public function test_helpdesk_cannot_access_noc_worksheet(): void
    {
        $this->actingAs($this->helpdeskUser)->get(route('noc.worksheet'))->assertForbidden();
    }

    /**
     * Tiket yang baru diassign ke NOC LANGSUNG nongol di worksheet — gak ada
     * lagi tab "Masuk" yang harus di-Oncheck dulu sebelum kelihatan sebagai
     * pekerjaan berjalan.
     */
    public function test_ticket_appears_right_after_assignment_to_noc(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->nocUser)
            ->get(route('noc.worksheet'))
            ->assertOk()
            ->assertSee($ticket->ticket_number);
    }

    /**
     * Halaman ini PUNYA tab lagi (ADHOC-09: Tiket Masuk / Assign FOP) — tapi
     * bukan tab yang dihapus ADHOC-06. Yang harus tetap absen: window Pending
     * NOC beserta aksi Oncheck. Jangan kendorkan assertion ini; kalau label
     * Oncheck/Pending NOC nongol lagi, artinya window itu dihidupkan sebagian.
     */
    public function test_worksheet_has_no_pending_noc_window(): void
    {
        $this->submitTicketToNoc();

        $response = $this->actingAs($this->nocUser)->get(route('noc.worksheet'));

        $response->assertOk();
        $response->assertDontSee('Oncheck NOC');
        $response->assertDontSee('OnCheck NOC');
        $response->assertDontSee('Pending NOC');
        $response->assertDontSee('oncheck-noc', false);
    }

    /**
     * Tombol aksi pindah ke drawer baris terpilih; URL endpoint-nya dirender
     * sebagai data-attribute baris dan CUMA kalau flag aksinya nyala.
     */
    public function test_worksheet_row_carries_action_urls_for_drawer(): void
    {
        $ticket = $this->submitTicketToNoc();

        $response = $this->actingAs($this->nocUser)->get(route('noc.worksheet'));

        $response->assertOk();
        $response->assertSee(route('tickets.close', $ticket), false);
        $response->assertSee(route('tickets.escalate', $ticket), false);
        $response->assertSee(route('tickets.return-to-helpdesk', $ticket), false);
    }

    public function test_closed_ticket_leaves_the_worksheet(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->nocUser)
            ->postJson(route('tickets.close', $ticket))
            ->assertOk();

        $this->actingAs($this->nocUser)
            ->get(route('noc.worksheet'))
            ->assertOk()
            ->assertDontSee($ticket->ticket_number);
    }

    // =========================================================================
    // ADHOC-09 — tabel padat, dua tab, cari & filter
    // =========================================================================

    public function test_default_tab_only_shows_open_noc_tickets(): void
    {
        $inbox = $this->submitTicketToNoc();
        $assigned = $this->escalateToFopAsNoc($this->submitTicketToNoc());

        $rows = $this->ticketNumbersOn(route('noc.worksheet'));

        $this->assertContains($inbox->ticket_number, $rows);
        $this->assertNotContains($assigned->ticket_number, $rows);
    }

    public function test_assign_fop_tab_shows_tickets_noc_forwarded_to_fop(): void
    {
        $inbox = $this->submitTicketToNoc();
        $assigned = $this->escalateToFopAsNoc($this->submitTicketToNoc());

        $rows = $this->ticketNumbersOn(route('noc.worksheet', ['tab' => 'assign_fop']));

        $this->assertContains($assigned->ticket_number, $rows);
        $this->assertNotContains($inbox->ticket_number, $rows);
    }

    /**
     * Tiket yang Helpdesk kirim LANGSUNG ke FOP bukan pekerjaan NOC — gak boleh
     * nyampur di worksheet NOC walaupun handler-nya sama-sama FOP.
     */
    public function test_assign_fop_tab_excludes_tickets_that_never_passed_noc(): void
    {
        $direct = $this->submitTicketDirectToFopByHelpdesk();

        $this->assertNotContains(
            $direct->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet', ['tab' => 'assign_fop']))
        );
    }

    public function test_assign_fop_tab_is_read_only(): void
    {
        $assigned = $this->escalateToFopAsNoc($this->submitTicketToNoc());

        $response = $this->actingAs($this->nocUser)->get(route('noc.worksheet', ['tab' => 'assign_fop']));

        $response->assertOk();
        $response->assertDontSee(route('tickets.close', $assigned), false);
        $response->assertDontSee(route('tickets.cancel', $assigned), false);
    }

    public function test_unknown_tab_falls_back_to_inbox(): void
    {
        $inbox = $this->submitTicketToNoc();

        $this->assertContains(
            $inbox->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet', ['tab' => 'ngawur']))
        );
    }

    #[DataProvider('searchTermProvider')]
    public function test_search_matches_ticket_number_customer_and_village(string $term, bool $shouldMatch): void
    {
        $ticket = $this->submitTicketToNoc();

        $rows = $this->ticketNumbersOn(route('noc.worksheet', [
            'q' => $term === '{ticket_number}' ? $ticket->ticket_number : $term,
        ]));

        $shouldMatch
            ? $this->assertContains($ticket->ticket_number, $rows)
            : $this->assertNotContains($ticket->ticket_number, $rows);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function searchTermProvider(): array
    {
        return [
            'nomor tiket' => ['{ticket_number}', true],
            'nama pelanggan' => ['Budi', true],
            'desa snapshot' => ['Polorejo', true],
            'potongan keluhan' => ['mati total', true],
            'tidak cocok' => ['zzz-tidak-ada', false],
        ];
    }

    public function test_pop_filter_narrows_the_list(): void
    {
        $ticket = $this->submitTicketToNoc();
        $otherPop = Pop::create([
            'name' => 'POP Siman',
            'code' => 'POP-SMN',
            'type' => 'branch',
            'address' => 'Siman',
            'status' => 'active',
            'city_id' => $this->pop->city_id,
        ]);

        $this->assertContains(
            $ticket->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet', ['pop_id' => $this->pop->id]))
        );

        $this->assertNotContains(
            $ticket->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet', ['pop_id' => $otherPop->id]))
        );
    }

    public function test_priority_filter_narrows_the_list(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->assertContains(
            $ticket->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet', ['priority' => 'High']))
        );

        $this->assertNotContains(
            $ticket->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet', ['priority' => 'Urgent']))
        );
    }

    public function test_issue_category_filter_narrows_the_list(): void
    {
        $ticket = $this->submitTicketToNoc();
        $category = TicketIssueCategory::create([
            'name' => 'Gangguan Fiber',
            'default_priority' => 'High',
            'is_active' => true,
        ]);

        $this->assertNotContains(
            $ticket->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet', ['issue_category_id' => $category->id]))
        );

        $ticket->update(['issue_category_id' => $category->id]);

        $this->assertContains(
            $ticket->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet', ['issue_category_id' => $category->id]))
        );
    }

    public function test_date_range_filter_narrows_the_list(): void
    {
        $ticket = $this->submitTicketToNoc();
        $today = now()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');

        $this->assertContains(
            $ticket->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet', ['date_from' => $today, 'date_to' => $today]))
        );

        $this->assertNotContains(
            $ticket->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet', ['date_from' => $tomorrow]))
        );
    }

    /**
     * Counter tab ikut filter aktif — kalau tidak, angka badge bohong waktu
     * user sedang menyaring.
     */
    public function test_tab_counters_follow_active_filters(): void
    {
        $this->submitTicketToNoc();
        $this->escalateToFopAsNoc($this->submitTicketToNoc());

        $unfiltered = $this->actingAs($this->nocUser)->get(route('noc.worksheet'));
        $unfiltered->assertOk();
        $this->assertSame(1, $unfiltered->viewData('tabCounts')['masuk']);
        $this->assertSame(1, $unfiltered->viewData('tabCounts')['assign_fop']);

        $filtered = $this->actingAs($this->nocUser)->get(route('noc.worksheet', ['q' => 'zzz-tidak-ada']));
        $filtered->assertOk();
        $this->assertSame(0, $filtered->viewData('tabCounts')['masuk']);
        $this->assertSame(0, $filtered->viewData('tabCounts')['assign_fop']);
    }

    /**
     * POP scope wajib jalan di KEDUA tab — tanpa itu halaman ini bocorin tiket
     * lintas cabang.
     */
    public function test_pop_scope_hides_tickets_from_other_pops_in_both_tabs(): void
    {
        $inbox = $this->submitTicketToNoc();
        $assigned = $this->escalateToFopAsNoc($this->submitTicketToNoc());

        $otherPop = Pop::create([
            'name' => 'POP Jetis',
            'code' => 'POP-JTS',
            'type' => 'branch',
            'address' => 'Jetis',
            'status' => 'active',
            'city_id' => $this->pop->city_id,
        ]);

        $scopedNoc = $this->makeUserWithSelectedPopScope('noc', $otherPop);

        $this->assertNotContains(
            $inbox->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet'), $scopedNoc)
        );

        $this->assertNotContains(
            $assigned->ticket_number,
            $this->ticketNumbersOn(route('noc.worksheet', ['tab' => 'assign_fop']), $scopedNoc)
        );
    }

    /**
     * Nomor tiket dibaca dari DATA paginator, bukan dari HTML: layout ikut
     * merender dropdown notifikasi yang juga menyebut nomor tiket, jadi
     * assertSee/assertDontSee atas HTML gak bisa membuktikan isi tabel.
     *
     * @return array<int, string>
     */
    private function ticketNumbersOn(string $url, ?User $user = null): array
    {
        $response = $this->actingAs($user ?? $this->nocUser)->get($url);
        $response->assertOk();

        return $response->viewData('tickets')->pluck('ticket_number')->all();
    }

    private function escalateToFopAsNoc(Ticket $ticket): Ticket
    {
        $this->actingAs($this->nocUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        return $ticket->fresh();
    }

    private function submitTicketDirectToFopByHelpdesk(): Ticket
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Redaman tinggi, minta langsung ke lapangan.',
            'priority' => 'High',
        ])->assertRedirect();

        $ticket = Ticket::latest('id')->firstOrFail();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        return $ticket->fresh();
    }

    private function makeUserWithSelectedPopScope(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->first();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP->value,
        ]);

        $scope->targets()->create(['pop_id' => $pop->id]);

        return $user;
    }
}
