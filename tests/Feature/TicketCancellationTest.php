<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketHistoryAction;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pembatalan ticket: RBAC (cuma pihak yang bisa akses Task FOP) + dua riwayat
 * (sisi FOP dan sisi Ticketing).
 */
class TicketCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;
    private User $helpdeskUser;
    private User $teknisiUser;
    private Customer $customer;
    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\TicketFeatureSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->fopUser = $this->makeUserWithAllPopScope('fop');
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
            'scope_type' => \App\Enums\ScopeType::ALL_POP->value,
        ]);

        return $user;
    }

    private function submitTicket(): Ticket
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total sejak pagi.',
            'catatan_teknis' => 'Redaman -28 dBm.',
            'priority' => 'High',
        ])->assertRedirect();

        return Ticket::latest('id')->firstOrFail();
    }

    private function cancel(User $actor, Ticket $ticket, string $reason = 'Pelanggan sudah normal sendiri')
    {
        return $this->actingAs($actor)->putJson(route('fop-tasks.update', $ticket->fopTask), [
            'status' => TaskStatus::DIBATALKAN->value,
            'cancel_reason' => $reason,
        ]);
    }

    // ── RBAC ────────────────────────────────────────────────────

    /**
     * Pengirim ticket (helpdesk) gak punya akses modul Task FOP, jadi gak boleh
     * batalin ticket-nya sendiri — pembatalan wewenang pihak Task FOP.
     */
    public function test_ticket_sender_without_fop_access_cannot_cancel(): void
    {
        $ticket = $this->submitTicket();

        $this->cancel($this->helpdeskUser, $ticket)->assertForbidden();

        $this->assertNotSame(TaskStatus::DIBATALKAN, $ticket->fopTask->refresh()->status);
        $this->assertDatabaseCount('ticket_histories', 1); // cuma 'dibuat'
    }

    public function test_teknisi_cannot_cancel_ticket(): void
    {
        $ticket = $this->submitTicket();

        $this->cancel($this->teknisiUser, $ticket)->assertForbidden();

        $this->assertNotSame(TaskStatus::DIBATALKAN, $ticket->fopTask->refresh()->status);
    }

    public function test_fop_can_cancel_ticket(): void
    {
        $ticket = $this->submitTicket();

        $this->cancel($this->fopUser, $ticket)->assertOk();

        $fopTask = $ticket->fopTask->refresh();
        $this->assertSame(TaskStatus::DIBATALKAN, $fopTask->status);
        $this->assertSame('Pelanggan sudah normal sendiri', $fopTask->cancel_reason);
        $this->assertNotNull($fopTask->cancelled_at);
    }

    public function test_cancel_without_reason_is_rejected(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->fopUser)
            ->putJson(route('fop-tasks.update', $ticket->fopTask), [
                'status' => TaskStatus::DIBATALKAN->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cancel_reason');
    }

    /**
     * Modul Ticketing sengaja gak nyediain endpoint cancel sendiri — satu-satunya
     * pintu pembatalan adalah Task FOP.
     */
    public function test_ticketing_module_exposes_no_cancel_endpoint(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->fopUser)
            ->post("/tickets/{$ticket->id}/cancel", ['cancel_reason' => 'x'])
            ->assertNotFound();
    }

    // ── Dua riwayat ─────────────────────────────────────────────

    /**
     * Inti kebutuhan: satu pembatalan menampung DUA riwayat.
     */
    public function test_cancelling_writes_both_fop_and_ticket_histories(): void
    {
        $ticket = $this->submitTicket();

        $this->cancel($this->fopUser, $ticket)->assertOk();

        // 1. Riwayat sisi FOP
        $fopHistories = $ticket->fopTask->statusHistories()->get();
        $this->assertCount(1, $fopHistories);
        $this->assertSame('dibatalkan', $fopHistories->first()->to_status);
        $this->assertSame(TaskStatus::DRAFT->value, $fopHistories->first()->from_status);
        $this->assertSame($this->fopUser->id, $fopHistories->first()->changed_by);

        // 2. Riwayat sisi Ticketing
        $ticketHistories = $ticket->histories()->get();
        $this->assertCount(2, $ticketHistories); // dibuat + dibatalkan

        $cancelEntry = $ticketHistories->firstWhere('action', TicketHistoryAction::DIBATALKAN);
        $this->assertNotNull($cancelEntry);
        $this->assertSame('Pelanggan sudah normal sendiri', $cancelEntry->reason);
        $this->assertSame($this->fopUser->id, $cancelEntry->actor_id);
        $this->assertSame(TaskStatus::DRAFT->value, $cancelEntry->from_status);
        $this->assertSame(TaskStatus::DIBATALKAN->value, $cancelEntry->to_status);
    }

    public function test_ticket_creation_is_recorded_in_ticket_history(): void
    {
        $ticket = $this->submitTicket();

        $entry = $ticket->histories()->first();

        $this->assertSame(TicketHistoryAction::DIBUAT, $entry->action);
        $this->assertSame($this->helpdeskUser->id, $entry->actor_id);
        $this->assertSame(TaskStatus::DRAFT->value, $entry->to_status);
    }

    /**
     * Cancel tiket yang udah ditugaskan (punya Task eksekusi) tetap nulis tepat
     * satu riwayat per sisi — jangan sampai dobel gara-gara TaskObserver dan
     * FopTaskObserver dua-duanya kepicu.
     */
    public function test_assigned_ticket_cancellation_does_not_duplicate_histories(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->fopUser)
            ->putJson(route('fop-tasks.update', $ticket->fopTask), [
                'technicians' => [$this->teknisiUser->id],
            ])
            ->assertOk();

        $this->assertNotNull($ticket->fopTask->refresh()->task_id);

        $this->cancel($this->fopUser, $ticket)->assertOk();

        $this->assertCount(
            1,
            $ticket->fopTask->refresh()->statusHistories()->where('to_status', 'dibatalkan')->get(),
            'Riwayat cancel sisi FOP ketulis dobel.'
        );

        $this->assertCount(
            1,
            $ticket->histories()->where('action', TicketHistoryAction::DIBATALKAN->value)->get(),
            'Riwayat cancel sisi Ticketing ketulis dobel.'
        );
    }

    public function test_ticket_detail_shows_both_histories(): void
    {
        $ticket = $this->submitTicket();
        $this->cancel($this->fopUser, $ticket)->assertOk();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Riwayat Ticketing')
            ->assertSee('Riwayat Task FOP')
            ->assertSee('Ticket dibatalkan')
            ->assertSee('Pelanggan sudah normal sendiri')
            ->assertSee($this->fopUser->name);
    }

    public function test_cancelled_ticket_lands_in_dibatalkan_bucket(): void
    {
        $ticket = $this->submitTicket();
        $this->cancel($this->fopUser, $ticket)->assertOk();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.bucket', 'dibatalkan'))
            ->assertOk()
            ->assertSee($ticket->ticket_number);
    }

    /**
     * FopTask non-ticket (dibuat langsung dari /fop-tasks) gak boleh bikin baris
     * ticket_histories nyasar.
     */
    public function test_cancelling_non_ticket_fop_task_writes_no_ticket_history(): void
    {
        $village = Village::first();

        $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN',
            'task_date' => now()->format('Y-m-d') . ' 08:00:00',
            'tugas' => 'Task murni FOP',
            'village_id' => $village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'FO Cut',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => [$this->teknisiUser->id],
        ])->assertRedirect();

        $fopTask = \App\Models\FopTask::where('tugas', 'Task murni FOP')->firstOrFail();

        $this->actingAs($this->fopUser)
            ->putJson(route('fop-tasks.update', $fopTask), [
                'status' => TaskStatus::DIBATALKAN->value,
                'cancel_reason' => 'Salah input',
            ])
            ->assertOk();

        $this->assertDatabaseCount('ticket_histories', 0);
        $this->assertCount(1, $fopTask->refresh()->statusHistories()->where('to_status', 'dibatalkan')->get());
    }
}
