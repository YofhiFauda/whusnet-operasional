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
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Worksheet NOC — dua HALAMAN mandiri (route + permission sendiri):
 * `/noc/worksheet/masuk` (pending, belum di-Oncheck) dan
 * `/noc/worksheet/diproses` (udah di-Oncheck). Terpisah dari panel "List Task
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

    public function test_noc_can_access_both_worksheet_tabs(): void
    {
        $this->actingAs($this->nocUser)->get(route('noc.worksheet.masuk'))->assertOk();
        $this->actingAs($this->nocUser)->get(route('noc.worksheet.diproses'))->assertOk();
    }

    /**
     * Entry point tunggal (yang ditunjuk sidebar) — buka tab pertama yang
     * user emang boleh akses.
     */
    public function test_worksheet_entry_point_opens_first_permitted_tab(): void
    {
        $this->actingAs($this->nocUser)
            ->get(route('noc.worksheet'))
            ->assertRedirect(route('noc.worksheet.masuk'));
    }

    public function test_worksheet_entry_point_is_forbidden_without_any_tab_permission(): void
    {
        $this->actingAs($this->teknisiUser)
            ->get(route('noc.worksheet'))
            ->assertForbidden();
    }

    /**
     * Dua tab itu ada DI DALAM satu halaman — halaman tab Masuk tetap
     * nampilin link ke tab Diproses, bukan halaman lepas tanpa navigasi.
     */
    public function test_both_tabs_are_navigable_from_within_the_page(): void
    {
        $response = $this->actingAs($this->nocUser)->get(route('noc.worksheet.masuk'));

        $response->assertOk();
        $response->assertSee(route('noc.worksheet.masuk'), false);
        $response->assertSee(route('noc.worksheet.diproses'), false);
        $response->assertSee('Ticket Masuk');
        $response->assertSee('Ticket Diproses');
    }

    /**
     * Dua halaman ini permission-nya KEPISAH — role tanpa akses ditolak di
     * dua-duanya (bukan cuma disembunyiin tombolnya).
     */
    public function test_role_without_noc_worksheet_permission_gets_403(): void
    {
        $this->actingAs($this->teknisiUser)->get(route('noc.worksheet.masuk'))->assertForbidden();
        $this->actingAs($this->teknisiUser)->get(route('noc.worksheet.diproses'))->assertForbidden();
    }

    public function test_helpdesk_cannot_access_noc_worksheet(): void
    {
        $this->actingAs($this->helpdeskUser)->get(route('noc.worksheet.masuk'))->assertForbidden();
    }

    public function test_pending_ticket_shows_in_masuk_page_only(): void
    {
        $ticket = $this->submitTicketToNoc();

        $masuk = $this->actingAs($this->nocUser)->get(route('noc.worksheet.masuk'));
        $masuk->assertOk()->assertSee($ticket->ticket_number);

        $diproses = $this->actingAs($this->nocUser)->get(route('noc.worksheet.diproses'));
        $diproses->assertOk()->assertDontSee($ticket->ticket_number);
    }

    public function test_checked_ticket_shows_in_diproses_page_only(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->nocUser)->postJson(route('tickets.oncheck-noc', $ticket))->assertOk();

        $masuk = $this->actingAs($this->nocUser)->get(route('noc.worksheet.masuk'));
        $masuk->assertOk()->assertDontSee($ticket->ticket_number);

        $diproses = $this->actingAs($this->nocUser)->get(route('noc.worksheet.diproses'));
        $diproses->assertOk()->assertSee($ticket->ticket_number);
    }

    public function test_masuk_page_shows_oncheck_but_not_close_button(): void
    {
        $ticket = $this->submitTicketToNoc();

        $response = $this->actingAs($this->nocUser)->get(route('noc.worksheet.masuk'));

        $response->assertOk();
        $response->assertSee(route('tickets.oncheck-noc', $ticket), false);
        $response->assertDontSee(route('tickets.close', $ticket), false);
    }

    public function test_diproses_page_shows_close_but_not_oncheck_button(): void
    {
        $ticket = $this->submitTicketToNoc();
        $this->actingAs($this->nocUser)->postJson(route('tickets.oncheck-noc', $ticket))->assertOk();

        $response = $this->actingAs($this->nocUser)->get(route('noc.worksheet.diproses'));

        $response->assertOk();
        $response->assertSee(route('tickets.close', $ticket), false);
        $response->assertDontSee(route('tickets.oncheck-noc', $ticket), false);
    }
}
