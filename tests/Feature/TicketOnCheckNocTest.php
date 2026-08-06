<?php

namespace Tests\Feature;

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
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PENJAGA REGRESI — window "Pending NOC" + aksi "Oncheck NOC" SUDAH DIHAPUS
 * (ADHOC-06, 2026-07-29).
 *
 * Dulu file ini menguji mekanisme itu: Helpdesk kirim ke NOC → tiket "pending"
 * (Helpdesk masih pegang kendali) sampai NOC menekan Oncheck, baru Helpdesk
 * kehilangan akses. Pada praktiknya assign = mulai kerja, jadi langkah "terima
 * dulu" cuma bikin tiket menggantung.
 *
 * Isinya sekarang menjaga supaya mekanisme itu TIDAK diam-diam balik: tiket
 * yang dikirim ke NOC langsung "Diproses NOC", endpoint & kolomnya hilang, dan
 * Helpdesk tetap ikut memegang tiket. Nama file sengaja dipertahankan supaya
 * jejak kenapa fitur ini pernah ada tidak hilang dari repo.
 */
class TicketOnCheckNocTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdeskUser;

    private User $nocUser;

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

        $this->helpdeskUser = $this->makeUserWithAllPopScope('helpdesk');
        $this->nocUser = $this->makeUserWithAllPopScope('noc');

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

    private function escalatedToNocTicket(): Ticket
    {
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total sejak pagi.',
            'priority' => 'High',
        ])->assertCreated();

        $ticket = Ticket::latest('id')->firstOrFail();

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertOk();

        return $ticket->fresh();
    }

    public function test_ticket_sent_to_noc_is_immediately_processed(): void
    {
        $ticket = $this->escalatedToNocTicket();

        $this->assertSame(TicketHandler::NOC, $ticket->handler);
        $this->assertSame(TicketHandlingStatus::OPEN, $ticket->status);
        $this->assertSame('Diproses NOC', $ticket->statusLabel());
    }

    public function test_oncheck_endpoint_no_longer_exists(): void
    {
        $ticket = $this->escalatedToNocTicket();

        $this->assertFalse(app('router')->has('tickets.oncheck-noc'));

        $this->actingAs($this->nocUser)
            ->post("/tickets/{$ticket->id}/oncheck-noc")
            ->assertNotFound();
    }

    public function test_noc_checked_at_column_is_gone(): void
    {
        $this->assertFalse(Schema::hasColumn('tickets', 'noc_checked_at'));
    }

    public function test_action_flags_no_longer_expose_oncheck(): void
    {
        $ticket = $this->escalatedToNocTicket();

        $this->assertArrayNotHasKey('can_oncheck_noc', $ticket->actionFlagsFor($this->nocUser));
    }

    public function test_noc_can_close_immediately_without_accepting_first(): void
    {
        $ticket = $this->escalatedToNocTicket();

        $this->actingAs($this->nocUser)
            ->postJson(route('tickets.close', $ticket), ['reason' => 'Beres dari sisi NOC.'])
            ->assertOk();

        $this->assertSame(TicketHandlingStatus::CLOSED, $ticket->fresh()->status);
    }

    public function test_helpdesk_still_holds_ticket_after_assigning_to_noc(): void
    {
        $ticket = $this->escalatedToNocTicket();

        $this->assertSame(['helpdesk', 'noc', 'admin', 'admin_pop', 'atasan', 'owner'], $ticket->holderRoles());

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.close', $ticket), ['reason' => 'Ternyata bisa dibenerin sendiri.'])
            ->assertOk();

        $this->assertSame(TicketHandlingStatus::CLOSED, $ticket->fresh()->status);
    }

    public function test_noc_worksheet_shows_ticket_right_after_assignment(): void
    {
        $ticket = $this->escalatedToNocTicket();

        $this->actingAs($this->nocUser)
            ->get(route('noc.worksheet'))
            ->assertOk()
            ->assertSee($ticket->ticket_number)
            ->assertDontSee('Oncheck NOC');
    }
}
