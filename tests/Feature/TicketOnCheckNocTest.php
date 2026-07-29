<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskType;
use App\Enums\TicketHandler;
use App\Enums\TicketHistoryAction;
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
 * Window "pending NOC" + aksi Oncheck NOC — Helpdesk kirim ke NOC, tiket
 * "pending" (Helpdesk MASIH pegang kendali) sampai NOC resmi Oncheck. Begitu
 * di-check, Helpdesk kehilangan akses, cuma NOC yang lanjut. Kembaran
 * TicketCloseEscalateTest, fokus khusus mekanisme baru ini.
 */
class TicketOnCheckNocTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdeskUser;

    private User $nocUser;

    private User $fopUser;

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

    public function test_ticket_is_pending_noc_right_after_escalation(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->assertTrue($ticket->isPendingNoc());
        $this->assertFalse($ticket->isOnCheckNoc());
        $this->assertNull($ticket->noc_checked_at);
    }

    public function test_noc_can_oncheck_pending_ticket(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->nocUser)
            ->post(route('tickets.oncheck-noc', $ticket), ['reason' => 'Mulai investigasi.'])
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertNotNull($ticket->noc_checked_at);
        $this->assertTrue($ticket->isOnCheckNoc());
        $this->assertFalse($ticket->isPendingNoc());

        $entry = $ticket->histories()->where('action', TicketHistoryAction::DICEK_NOC)->first();
        $this->assertNotNull($entry);
        $this->assertSame('Mulai investigasi.', $entry->reason);
        $this->assertSame($this->nocUser->id, $entry->actor_id);
    }

    public function test_helpdesk_cannot_oncheck(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.oncheck-noc', $ticket))
            ->assertSessionHasErrors('target');

        $this->assertNull($ticket->fresh()->noc_checked_at);
    }

    public function test_oncheck_rejected_if_already_checked(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->nocUser)->post(route('tickets.oncheck-noc', $ticket))->assertRedirect();

        $this->actingAs($this->nocUser)
            ->post(route('tickets.oncheck-noc', $ticket))
            ->assertSessionHasErrors('target');
    }

    public function test_oncheck_rejected_once_ticket_reaches_fop(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->nocUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertOk();

        $this->assertSame(TicketHandler::FOP, $ticket->fresh()->handler);

        $this->actingAs($this->nocUser)
            ->post(route('tickets.oncheck-noc', $ticket))
            ->assertSessionHasErrors('target');
    }

    // ── Window pending: Helpdesk masih pegang kendali ──────────────

    public function test_helpdesk_can_close_during_pending_window(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.close', $ticket), ['reason' => 'Ternyata bisa dibenerin sendiri.'])
            ->assertRedirect();

        $this->assertSame('closed', $ticket->fresh()->status->value);
    }

    public function test_helpdesk_can_escalate_to_fop_during_pending_window(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertOk();

        $this->assertSame(TicketHandler::FOP, $ticket->fresh()->handler);
    }

    public function test_helpdesk_can_cancel_during_pending_window(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.cancel', $ticket), ['reason' => 'Batal, pelanggan gak jadi komplain.'])
            ->assertOk();

        $this->assertSame('cancelled', $ticket->fresh()->status->value);
    }

    // ── NOC boleh Assign FOP / Kembalikan TANPA Oncheck dulu ───────

    public function test_noc_can_escalate_to_fop_without_checking_first(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->nocUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertOk();

        $this->assertSame(TicketHandler::FOP, $ticket->fresh()->handler);
    }

    public function test_noc_can_return_to_helpdesk_without_checking_first(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->nocUser)
            ->postJson(route('tickets.return-to-helpdesk', $ticket))
            ->assertOk();

        $this->assertSame(TicketHandler::HELPDESK, $ticket->fresh()->handler);
        $this->assertNull($ticket->fresh()->noc_checked_at);
    }

    // ── NOC WAJIB check dulu sebelum Selesaikan ─────────────────────

    public function test_noc_cannot_close_before_checking(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->nocUser)
            ->post(route('tickets.close', $ticket))
            ->assertSessionHasErrors('target');

        $this->assertSame('open', $ticket->fresh()->status->value);
    }

    public function test_noc_can_close_after_checking(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->nocUser)->post(route('tickets.oncheck-noc', $ticket))->assertRedirect();

        $this->actingAs($this->nocUser)
            ->post(route('tickets.close', $ticket), ['reason' => 'Selesai dikerjakan.'])
            ->assertRedirect();

        $this->assertSame('closed', $ticket->fresh()->status->value);
    }

    // ── Lockout setelah checked ─────────────────────────────────────

    public function test_helpdesk_locked_out_after_noc_checks(): void
    {
        $ticket = $this->submitTicketToNoc();

        $this->actingAs($this->nocUser)->post(route('tickets.oncheck-noc', $ticket))->assertRedirect();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.close', $ticket))
            ->assertSessionHasErrors('target');

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertSessionHasErrors('target');
    }

    // ── actionFlagsFor() konsisten sama guard Service ───────────────

    public function test_action_flags_expose_oncheck_only_when_pending_and_role_noc(): void
    {
        $ticket = $this->submitTicketToNoc();

        $nocFlags = $ticket->actionFlagsFor($this->nocUser);
        $this->assertTrue($nocFlags['can_oncheck_noc']);
        $this->assertFalse($nocFlags['can_close']); // wajib check dulu

        $helpdeskFlags = $ticket->actionFlagsFor($this->helpdeskUser);
        $this->assertFalse($helpdeskFlags['can_oncheck_noc']);
        $this->assertTrue($helpdeskFlags['can_close']); // masih window pending

        $this->actingAs($this->nocUser)->post(route('tickets.oncheck-noc', $ticket))->assertRedirect();
        $ticket->refresh()->load('histories');

        $nocFlagsAfter = $ticket->actionFlagsFor($this->nocUser);
        $this->assertFalse($nocFlagsAfter['can_oncheck_noc']);
        $this->assertTrue($nocFlagsAfter['can_close']);

        $helpdeskFlagsAfter = $ticket->actionFlagsFor($this->helpdeskUser);
        $this->assertFalse($helpdeskFlagsAfter['can_close']);
    }
}
