<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskType;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Enums\TicketHistoryAction;
use App\Events\TicketQueueUpdated;
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
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Batalkan tiket yang masih ditangani Helpdesk/NOC (belum pernah ke FOP) —
 * kembaran TicketCloseEscalateTest tapi buat aksi cancel(). Pembatalan tiket
 * yang UDAH di FOP tetap lewat modul FOP (lihat TicketCancellationTest),
 * bukan di sini.
 */
class TicketPreFopCancelTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdeskUser;

    private User $nocUser;

    private User $fopUser;

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

        $this->helpdeskUser = $this->makeUserWithAllPopScope('helpdesk');
        $this->nocUser = $this->makeUserWithAllPopScope('noc');
        $this->fopUser = $this->makeUserWithAllPopScope('fop');
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

    private function submitTicket(User $actor): Ticket
    {
        $this->actingAs($actor)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total sejak pagi.',
            'priority' => 'High',
        ])->assertRedirect();

        return Ticket::latest('id')->firstOrFail();
    }

    public function test_helpdesk_can_cancel_own_ticket(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.cancel', $ticket), ['reason' => 'Pelanggan minta batal, sudah teratasi sendiri.'])
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(TicketHandlingStatus::CANCELLED, $ticket->status);
        $this->assertSame(TicketHandler::HELPDESK, $ticket->handler);
        $this->assertSame('dibatalkan', $ticket->bucket()->value);

        $entry = $ticket->histories()->where('action', TicketHistoryAction::DIBATALKAN)->first();
        $this->assertNotNull($entry);
        $this->assertSame('Pelanggan minta batal, sudah teratasi sendiri.', $entry->reason);
        $this->assertSame($this->helpdeskUser->id, $entry->actor_id);
    }

    public function test_noc_can_cancel_ticket_handed_over_from_helpdesk(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        $this->actingAs($this->nocUser)
            ->post(route('tickets.cancel', $ticket), ['reason' => 'Duplikat tiket lain.'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(TicketHandlingStatus::CANCELLED, $ticket->status);
        $this->assertSame(TicketHandler::NOC, $ticket->handler);
    }

    public function test_cancel_requires_reason(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.cancel', $ticket))
            ->assertSessionHasErrors('reason');

        $this->assertSame(TicketHandlingStatus::OPEN, $ticket->fresh()->status);
    }

    public function test_cannot_cancel_ticket_already_at_fop(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertOk();

        $this->actingAs($this->fopUser)
            ->post(route('tickets.cancel', $ticket), ['reason' => 'Coba batalin.'])
            ->assertSessionHasErrors('target');
    }

    public function test_noc_cannot_cancel_ticket_still_held_by_helpdesk(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->nocUser)
            ->post(route('tickets.cancel', $ticket), ['reason' => 'Coba batalin.'])
            ->assertSessionHasErrors('target');

        $this->assertSame(TicketHandlingStatus::OPEN, $ticket->fresh()->status);
    }

    public function test_cannot_cancel_ticket_already_cancelled(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.cancel', $ticket), ['reason' => 'Batal pertama.'])
            ->assertOk();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.cancel', $ticket), ['reason' => 'Batal lagi.'])
            ->assertSessionHasErrors('target');
    }

    public function test_role_without_tickets_cancel_permission_gets_403(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->teknisiUser)
            ->post(route('tickets.cancel', $ticket), ['reason' => 'Coba batalin.'])
            ->assertForbidden();
    }

    public function test_ajax_cancel_returns_json(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.cancel', $ticket), ['reason' => 'Batal via AJAX.']);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'ticket']);
        $response->assertJsonPath('ticket.bucket', 'dibatalkan');
    }

    public function test_cancel_dispatches_queue_updated_event(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        Event::fake([TicketQueueUpdated::class]);

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.cancel', $ticket), ['reason' => 'Batal.'])
            ->assertOk();

        Event::assertDispatched(
            TicketQueueUpdated::class,
            fn ($event) => $event->popId === $ticket->pop_id
        );
    }

    public function test_cancelled_ticket_shows_in_dibatalkan_bucket(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.cancel', $ticket), ['reason' => 'Batal.'])
            ->assertOk();

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.dibatalkan'));

        $response->assertOk();
        $response->assertSee($ticket->ticket_number);
    }

    public function test_action_flags_expose_can_cancel_for_holder_only(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $helpdeskFlags = $ticket->actionFlagsFor($this->helpdeskUser);
        $this->assertTrue($helpdeskFlags['can_cancel']);

        $nocFlags = $ticket->actionFlagsFor($this->nocUser);
        $this->assertFalse($nocFlags['can_cancel']);
    }
}
