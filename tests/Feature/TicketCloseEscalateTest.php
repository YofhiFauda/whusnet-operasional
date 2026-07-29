<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
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
 * Mekanisme Close/Escalate (docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD) — 5
 * aksi eksplisit: Helpdesk close sendiri, Helpdesk kirim ke NOC, Helpdesk
 * kirim ke FOP, NOC close sendiri, NOC kirim ke FOP.
 */
class TicketCloseEscalateTest extends TestCase
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

    // ── Skenario A: Helpdesk close sendiri ───────────────────────

    public function test_helpdesk_can_close_own_ticket(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.close', $ticket), ['reason' => 'Sudah dibantu reset ONT.'])
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(TicketHandlingStatus::CLOSED, $ticket->status);
        $this->assertSame(TicketHandler::HELPDESK, $ticket->handler);
        $this->assertNull($ticket->fopTask);

        $entry = $ticket->histories()->where('action', TicketHistoryAction::DISELESAIKAN)->first();
        $this->assertNotNull($entry);
        $this->assertSame('Sudah dibantu reset ONT.', $entry->reason);
        $this->assertSame($this->helpdeskUser->id, $entry->actor_id);
    }

    // ── Skenario B: Helpdesk kirim ke NOC ────────────────────────

    public function test_helpdesk_can_escalate_to_noc(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(TicketHandler::NOC, $ticket->handler);
        $this->assertSame(TicketHandlingStatus::OPEN, $ticket->status);
        $this->assertNull($ticket->fopTask);

        $entry = $ticket->histories()->where('action', TicketHistoryAction::DIESKALASI)->first();
        $this->assertSame('helpdesk', $entry->from_status);
        $this->assertSame('noc', $entry->to_status);
    }

    // ── Skenario C: Helpdesk kirim ke FOP (skip NOC) ─────────────

    public function test_helpdesk_can_escalate_directly_to_fop(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh()->load('fopTask');
        $this->assertSame(TicketHandler::FOP, $ticket->handler);
        $this->assertNotNull($ticket->fopTask);
        $this->assertStringStartsWith('TFOP-', $ticket->fopTask->task_number);
    }

    // ── NOC close sendiri ─────────────────────────────────────────

    public function test_noc_can_close_ticket_handed_over_from_helpdesk(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        // NOC wajib Oncheck dulu sebelum bisa Selesaikan (window pending —
        // lihat TicketOnCheckNocTest untuk guard ini secara detail).
        $this->actingAs($this->nocUser)
            ->post(route('tickets.oncheck-noc', $ticket))
            ->assertRedirect();

        $this->actingAs($this->nocUser)
            ->post(route('tickets.close', $ticket), ['reason' => 'Berhasil dikonfigurasi ulang dari sisi NOC.'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(TicketHandlingStatus::CLOSED, $ticket->status);
        $this->assertSame(TicketHandler::NOC, $ticket->handler);
    }

    // ── NOC kirim ke FOP ──────────────────────────────────────────

    public function test_noc_can_escalate_to_fop(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        $this->actingAs($this->nocUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        $ticket->refresh()->load('fopTask');
        $this->assertSame(TicketHandler::FOP, $ticket->handler);
        $this->assertNotNull($ticket->fopTask);
    }

    // ── RBAC / state-machine guard ────────────────────────────────

    public function test_role_without_tickets_update_permission_gets_403(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->teknisiUser)
            ->post(route('tickets.close', $ticket))
            ->assertForbidden();
    }

    /**
     * NOC belum megang tiket ini (masih di Helpdesk) — gak boleh close/escalate
     * duluan walau punya permission tickets.update secara umum.
     */
    public function test_noc_cannot_act_on_ticket_still_held_by_helpdesk(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->nocUser)
            ->post(route('tickets.close', $ticket))
            ->assertSessionHasErrors('target');

        $this->assertSame(TicketHandlingStatus::OPEN, $ticket->fresh()->status);
    }

    /**
     * Window "pending NOC" — Helpdesk kirim ke NOC tapi NOC BELUM Oncheck.
     * Helpdesk MASIH boleh act (close/escalate) selama window ini.
     */
    public function test_helpdesk_can_still_act_during_pending_noc_window(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        $this->assertTrue($ticket->fresh()->isPendingNoc());

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.close', $ticket), ['reason' => 'Ternyata bisa dibenerin sendiri.'])
            ->assertRedirect();

        $this->assertSame(TicketHandlingStatus::CLOSED, $ticket->fresh()->status);
    }

    /**
     * Begitu NOC klik Oncheck NOC, Helpdesk kehilangan akses — tiket resmi
     * pindah tangan ke NOC.
     */
    public function test_helpdesk_cannot_act_after_noc_has_checked(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        $this->actingAs($this->nocUser)
            ->post(route('tickets.oncheck-noc', $ticket))
            ->assertRedirect();

        $this->assertTrue($ticket->fresh()->isOnCheckNoc());

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.close', $ticket))
            ->assertSessionHasErrors('target');
    }

    public function test_cannot_act_on_ticket_already_closed(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.close', $ticket))
            ->assertRedirect();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertSessionHasErrors('target');

        $this->assertNull($ticket->fresh()->fopTask);
    }

    public function test_cannot_act_on_ticket_already_escalated_to_fop(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        $this->actingAs($this->fopUser)
            ->post(route('tickets.close', $ticket))
            ->assertSessionHasErrors('target');
    }

    public function test_ajax_close_and_escalate_return_json(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertOk()
            ->assertJsonStructure(['message']);
    }

    /**
     * List Task Ticketing (panel worksheet) render tombol aksi dari card yang
     * dibalikin JSON — bentuknya WAJIB sama persis kayak worksheetTasks()
     * initial load, biar card gak "lompat" bentuk pas di-update in-place.
     */
    public function test_ajax_action_response_returns_updated_worksheet_card(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc']);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'ticket' => ['id', 'code', 'priority', 'title', 'desc', 'time', 'cid', 'bucket', 'handler', 'actions' => ['can_close', 'can_escalate_noc', 'can_escalate_fop']],
        ]);
        $response->assertJsonPath('ticket.handler', 'noc');
        $response->assertJsonPath('ticket.bucket', 'diproses');
        // Window "pending NOC" — Helpdesk yang eskalasi MASIH pegang kendali
        // sampai NOC resmi Oncheck (lihat TicketOnCheckNocTest), jadi
        // tombolnya belum lenyap di titik ini.
        $response->assertJsonPath('ticket.actions.can_close', true);
        $response->assertJsonPath('ticket.actions.can_escalate_noc', false);
        $response->assertJsonPath('ticket.actions.can_escalate_fop', true);
    }

    /**
     * Initial load worksheet (List Task Ticketing) juga ngirim flag actions
     * per card — dipakai buat nampilin/nyembunyiin tombol Selesai/Ke NOC/Ke FOP.
     */
    public function test_worksheet_initial_load_includes_action_flags_for_owned_ticket(): void
    {
        $this->submitTicket($this->helpdeskUser);

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('"can_close":true', false);
        $response->assertSee('"can_escalate_noc":true', false);
        $response->assertSee('"can_escalate_fop":true', false);
    }

    // ── Atribusi "siapa ngapain" ──────────────────────────────────

    /**
     * Model helper closedBy()/escalatedToNocBy()/escalatedToFopBy() — dari
     * ticket_histories, tanpa query baru (histories WAJIB eager-loaded).
     */
    public function test_ticket_tracks_who_did_each_action(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        $this->actingAs($this->nocUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        $ticket->refresh()->load('histories.actor', 'creator');

        $this->assertSame($this->helpdeskUser->name, $ticket->creator->name);
        $this->assertSame($this->helpdeskUser->id, $ticket->escalatedToNocBy()->id);
        $this->assertSame($this->nocUser->id, $ticket->escalatedToFopBy()->id);
        $this->assertNull($ticket->closedBy());
    }

    public function test_ticket_tracks_who_closed_it(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.close', $ticket))
            ->assertRedirect();

        $ticket->refresh()->load('histories.actor');

        $this->assertSame($this->helpdeskUser->id, $ticket->closedBy()->id);
    }

    /**
     * Worksheet card payload (JSON) juga ngirim atribusi ini — dipakai render
     * di panel List Task Ticketing tanpa perlu buka halaman detail.
     */
    public function test_ajax_action_response_includes_attribution(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc']);

        $response->assertOk();
        $response->assertJsonPath('ticket.created_by', $this->helpdeskUser->name);
        $response->assertJsonPath('ticket.escalated_noc_by', $this->helpdeskUser->name);
        $response->assertJsonPath('ticket.escalated_fop_by', null);
        $response->assertJsonPath('ticket.closed_by', null);
    }

    /**
     * Worksheet NOC (dipakai NOC kerja 1 halaman) nampilin tombol aksi +
     * atribusi langsung di baris, gak perlu buka detail satu-satu.
     */
    public function test_noc_worksheet_shows_action_buttons_and_attribution(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        $response = $this->actingAs($this->nocUser)->get(route('noc.worksheet.masuk'));

        $response->assertOk();
        $response->assertSee($ticket->ticket_number);
        // Tiket masih pending (belum di-Oncheck) — NOC lihat tombol Oncheck
        // NOC & Assign FOP, TAPI BELUM Selesai (wajib Oncheck dulu, lihat
        // test_helpdesk_cannot_act_after_noc_has_checked).
        $response->assertSee(route('tickets.oncheck-noc', $ticket), false);
        $response->assertSee(route('tickets.escalate', $ticket), false);
        $response->assertSee($this->helpdeskUser->name);
    }

    /**
     * Gap #1 KRITIS (docs/plan/analisa-efektivitas-worksheet-ticketing.md) —
     * tombol aksi di list WAJIB bukan native <form method="POST">, biar gak
     * nembak jalur non-JSON TicketController::close()/escalate() yang
     * redirect ke tickets.show (NOC ke-bounce dari list tiap klik). Regresi:
     * kalau ada yang balikin ke <form>, test ini nangkep duluan.
     */
    public function test_worksheet_action_buttons_are_not_native_forms(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        $response = $this->actingAs($this->nocUser)->get(route('noc.worksheet.masuk'));

        $response->assertOk();
        $response->assertSee('confirmTicketRowAction(this', false);
        $response->assertDontSee('action="'.route('tickets.close', $ticket).'"', false);
        $response->assertDontSee('action="'.route('tickets.escalate', $ticket).'"', false);
    }

    /**
     * Aksi dari index (via JSON, sama kayak yang dipanggil ticketRowAction())
     * WAJIB stay-on-page — respons JSON, BUKAN redirect ke tickets.show.
     */
    public function test_ajax_action_from_index_context_does_not_redirect(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.close', $ticket));

        $response->assertOk();
        $response->assertJsonStructure(['message', 'ticket']);
    }

    // ── Worksheet exclude Selesai/Dibatalkan dari akar query ─────

    /**
     * Panel List Task Ticketing (worksheet) gak lagi nampung tiket yang udah
     * kelar — exclude di query, BUKAN cuma disaring tab client-side (konsultasi
     * workflow: Helpdesk/NOC fokus cuma ke kerjaan yang masih jalan).
     */
    public function test_worksheet_excludes_closed_tickets(): void
    {
        $closed = $this->submitTicket($this->helpdeskUser);
        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.close', $closed))
            ->assertOk();

        $open = $this->submitTicket($this->helpdeskUser);

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.create'));

        $response->assertOk();
        $response->assertDontSee($closed->ticket_number);
        $response->assertSee($open->ticket_number);
    }

    public function test_worksheet_excludes_cancelled_fop_tickets(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        // postJson — hindari flash session 'success' (isinya nyebut
        // ticket_number) nyampur ke assertDontSee() di GET berikutnya.
        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertOk();

        $ticket->refresh()->load('fopTask');
        $ticket->fopTask->update(['status' => TaskStatus::DIBATALKAN]);

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.create'));

        $response->assertOk();
        $response->assertDontSee($ticket->ticket_number);
    }

    public function test_worksheet_still_shows_active_fop_ticket(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertOk();

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee($ticket->ticket_number);
    }

    // ── Gap #3: auto-refresh (broadcast Reverb) ──────────────────

    /**
     * TicketQueueUpdated dispatch dari create/close/escalateToNoc/escalateToFop
     * setelah transaksi commit — sinyal buat worksheet panel & index bucket
     * auto-refresh (docs/plan/analisa-efektivitas-worksheet-ticketing.md Gap #3).
     */
    public function test_creating_ticket_dispatches_queue_updated_event(): void
    {
        Event::fake([TicketQueueUpdated::class]);

        $ticket = $this->submitTicket($this->helpdeskUser);

        Event::assertDispatched(
            TicketQueueUpdated::class,
            fn ($event) => $event->popId === $ticket->pop_id
        );
    }

    public function test_close_dispatches_queue_updated_event(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        Event::fake([TicketQueueUpdated::class]);

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.close', $ticket))
            ->assertOk();

        Event::assertDispatched(
            TicketQueueUpdated::class,
            fn ($event) => $event->popId === $ticket->pop_id
        );
    }

    public function test_escalate_dispatches_queue_updated_event(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        Event::fake([TicketQueueUpdated::class]);

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertOk();

        Event::assertDispatched(
            TicketQueueUpdated::class,
            fn ($event) => $event->popId === $ticket->pop_id
        );
    }

    /**
     * Endpoint refresh worksheet (dipanggil Alpine pas nerima broadcast atau
     * klik tombol Refresh manual) — bentuk respons WAJIB sama kayak initial
     * load, plus total count TANPA kena cap (Gap #4).
     */
    public function test_worksheet_json_endpoint_returns_tasks_and_total(): void
    {
        $this->submitTicket($this->helpdeskUser);

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.worksheet-tasks'));

        $response->assertOk();
        $response->assertJsonStructure([
            'tasks' => [['id', 'code', 'priority', 'title', 'desc', 'time', 'cid', 'bucket', 'handler', 'actions']],
            'total',
        ]);
        $response->assertJsonPath('total', 1);
    }

    public function test_worksheet_json_endpoint_requires_permission(): void
    {
        $this->actingAs($this->teknisiUser)
            ->getJson(route('tickets.worksheet-tasks'))
            ->assertForbidden();
    }

    // ── Gap #4: cap 30, indikator "masih ada lagi" ────────────────

    /**
     * Cap panel (30) TETAP ada, tapi sekarang ada worksheetTotalCount yang gak
     * kena cap — kalau lebih dari yang ditampilkan, Blade nampilin indikator
     * "+N lainnya, Lihat Semua" (sebelumnya diem-diem ilang tanpa indikator).
     */
    public function test_worksheet_shows_more_indicator_when_over_display_cap(): void
    {
        // 31 tiket aktif > cap 30.
        for ($i = 0; $i < 31; $i++) {
            $this->submitTicket($this->helpdeskUser);
        }

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('worksheetTotalCount: 31', false);
    }

    public function test_worksheet_json_total_not_capped(): void
    {
        for ($i = 0; $i < 31; $i++) {
            $this->submitTicket($this->helpdeskUser);
        }

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.worksheet-tasks'));

        $response->assertOk();
        $response->assertJsonPath('total', 31);
        $this->assertCount(30, $response->json('tasks'));
    }

    // ── Gap #5: dupe-check server-side, gak kena cap panel ────────

    public function test_duplicates_endpoint_finds_open_ticket_regardless_of_worksheet_cap(): void
    {
        $firstTicket = $this->submitTicket($this->helpdeskUser);

        // 30 tiket LAIN buat customer BEDA — dorong $firstTicket keluar dari
        // cap 30 kalau dupe-check masih nyisir array lokal (Gap #5 lama).
        $otherCustomer = Customer::factory()->create(['pop_id' => $this->pop->id]);
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $otherCustomer->id,
                'detail_keluhan' => 'Keluhan lain.',
                'priority' => 'High',
            ])->assertRedirect();
        }

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.duplicates', ['customer_id' => $this->customer->id]));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.code', $firstTicket->ticket_number);
        $response->assertJsonPath('0.bucket', 'masuk');
    }

    public function test_duplicates_endpoint_excludes_closed_tickets(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $ticket))->assertOk();

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.duplicates', ['customer_id' => $this->customer->id]));

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    public function test_duplicates_endpoint_requires_permission(): void
    {
        $this->actingAs($this->teknisiUser)
            ->getJson(route('tickets.duplicates', ['customer_id' => $this->customer->id]))
            ->assertForbidden();
    }

    public function test_duplicates_endpoint_without_customer_id_returns_empty(): void
    {
        $response = $this->actingAs($this->helpdeskUser)->getJson(route('tickets.duplicates'));

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    // ── Gap #7: NOC kembalikan tiket ke Helpdesk ──────────────────

    public function test_noc_can_return_ticket_to_helpdesk(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        $this->actingAs($this->nocUser)
            ->post(route('tickets.return-to-helpdesk', $ticket), ['reason' => 'Salah kirim, ini bukan gangguan NOC.'])
            ->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(TicketHandler::HELPDESK, $ticket->handler);
        $this->assertSame(TicketHandlingStatus::OPEN, $ticket->status);

        $entry = $ticket->histories()->where('action', TicketHistoryAction::DIKEMBALIKAN)->first();
        $this->assertNotNull($entry);
        $this->assertSame('noc', $entry->from_status);
        $this->assertSame('helpdesk', $entry->to_status);
        $this->assertSame($this->nocUser->id, $entry->actor_id);
    }

    /**
     * Helpdesk sendiri gak bisa "kembalikan" — dia asal-usulnya, gak ada
     * yang lebih rendah buat dikembaliin ke (Ticket::actionFlagsFor()).
     */
    public function test_helpdesk_cannot_return_ticket_to_helpdesk(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.return-to-helpdesk', $ticket))
            ->assertSessionHasErrors('target');

        $this->assertSame(TicketHandler::HELPDESK, $ticket->fresh()->handler);
    }

    /**
     * Tiket yang belum pernah ke NOC (masih di Helpdesk langsung) gak bisa
     * "dikembalikan" — gak ada apa pun buat dikembaliin.
     */
    public function test_cannot_return_ticket_still_at_helpdesk(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->nocUser)
            ->post(route('tickets.return-to-helpdesk', $ticket))
            ->assertSessionHasErrors('target');
    }

    public function test_cannot_return_ticket_already_at_fop(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertOk();
        $this->actingAs($this->nocUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertOk();

        $this->actingAs($this->fopUser)
            ->post(route('tickets.return-to-helpdesk', $ticket))
            ->assertSessionHasErrors('target');
    }

    public function test_return_to_helpdesk_dispatches_queue_updated_event(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);
        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertOk();

        Event::fake([TicketQueueUpdated::class]);

        $this->actingAs($this->nocUser)
            ->postJson(route('tickets.return-to-helpdesk', $ticket))
            ->assertOk();

        Event::assertDispatched(
            TicketQueueUpdated::class,
            fn ($event) => $event->popId === $ticket->pop_id
        );
    }

    /**
     * Ticket::actionFlagsFor() — model helper tunggal dipakai worksheet/
     * index/detail. can_return_to_helpdesk cuma true buat NOC.
     */
    public function test_action_flags_expose_return_to_helpdesk_only_for_noc(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        $helpdeskFlags = $ticket->actionFlagsFor($this->helpdeskUser);
        $this->assertFalse($helpdeskFlags['can_return_to_helpdesk']);

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertOk();

        $ticket->refresh();
        $nocFlags = $ticket->actionFlagsFor($this->nocUser);
        $this->assertTrue($nocFlags['can_return_to_helpdesk']);
    }

    // ── Gap #8: pesan error submit lebih spesifik ─────────────────

    /**
     * Worksheet form gak clear attachments kalau submit gagal (resetForm()
     * cuma dipanggil pas sukses) — ini udah bener sebelumnya, cuma UX-nya
     * gak dikomunikasikan. Test ini pastiin behavior backend-nya konsisten:
     * validasi gagal (422) balikin pesan per-field yang bisa dipakai
     * frontend, bukan cuma status kode doang.
     */
    public function test_store_validation_failure_returns_specific_field_errors(): void
    {
        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.store'), [
                'type' => TaskType::MAINTENANCE->value,
                'customer_id' => $this->customer->id,
                'detail_keluhan' => '',
                'priority' => 'High',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['detail_keluhan']);
    }

    public function test_close_action_error_returns_message_field_for_frontend_display(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $ticket))->assertOk();

        // Tiket udah closed — coba close lagi harus balikin message yang jelas,
        // bukan cuma 422 kosong (dipakai submitForm()'s `body.message` fallback).
        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.close', $ticket));

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
        $this->assertNotEmpty($response->json('message'));
    }
}
