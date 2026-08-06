<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Http\Controllers\TicketHistoryController;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Models\Village;
use Carbon\CarbonInterface;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * History Ticketing (docs/plan/analisa-halaman-history-ticketing.md) — halaman
 * arsip SEMUA tiket, pengganti sheet Excel Helpdesk.
 *
 * Halaman ini CUMA menampung tiket yang sudah LEPAS dari meja Ticketing:
 * Selesai, Dibatalkan, atau diserahkan ke FOP. Tiket yang masih dikerjakan
 * (open di tangan Helpdesk/NOC) rumahnya Worksheet Helpdesk / Worksheet NOC —
 * kalau ikut nongol di sini, History jadi duplikat antrean kerja.
 *
 * Tiket jalur FOP berhenti di status "Assign FOP": progres lapangan
 * (Terjadwal/In Progress/Selesai/Dibatalkan) TIDAK dicerminkan di sini, dan
 * `resolved_at` = waktu penyerahan, bukan waktu teknisi lapor.
 */
class TicketHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdeskUser;

    private User $nocUser;

    private User $teknisiUser;

    private Customer $customer;

    private Pop $pop;

    private Village $village;

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
        $this->teknisiUser = $this->makeUserWithAllPopScope('teknisi');

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);

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
            'village_id' => $this->village->id,
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

    /**
     * SENGAJA postJson, bukan post biasa: jalur non-JSON nge-flash pesan
     * 'success' yang isinya NYEBUT nomor tiket, dan flash itu ke-render di GET
     * berikutnya — bikin assertDontSee() gagal padahal filternya bener.
     * Jebakan yang sama sudah pernah kena di TicketCloseEscalateTest.
     */
    private function submitTicket(?Customer $customer = null, array $overrides = []): Ticket
    {
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), array_merge([
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => ($customer ?? $this->customer)->id,
            'detail_keluhan' => 'Internet mati total sejak pagi.',
            'priority' => 'High',
        ], $overrides))->assertCreated();

        return Ticket::latest('id')->firstOrFail();
    }

    /**
     * escalateToFop() cuma bikin FopTask (status Draft) — Task eksekusi teknisi
     * baru lahir waktu FOP menugaskan teknisi. Helper ini menyambungkan
     * keduanya supaya jalur "teknisi lapor selesai" bisa diuji.
     */
    private function attachExecutionTask(Ticket $ticket, ?CarbonInterface $completedAt = null): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-2026-'.str_pad((string) (Task::count() + 1), 4, '0', STR_PAD_LEFT),
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::MAINTENANCE->value,
            'title' => 'Perbaikan '.$ticket->ticket_number,
            'status' => TaskStatus::TERJADWAL->value,
            'scheduled_at' => now(),
            'created_by' => $this->helpdeskUser->id,
            'updated_by' => $this->helpdeskUser->id,
            'completed_at' => $completedAt,
        ]);

        $ticket->fopTask->forceFill(['task_id' => $task->id])->saveQuietly();

        return $task;
    }

    // ── Cakupan: apa yang masuk & apa yang TIDAK ────────────────

    public function test_history_only_holds_tickets_that_left_the_ticketing_desk(): void
    {
        // MASUK — selesai internal
        $closedInternal = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $closedInternal))->assertOk();

        // MASUK — dibatalkan pra-FOP
        $cancelled = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.cancel', $cancelled), ['reason' => 'Salah input.'])->assertOk();

        // MASUK — diserahkan ke FOP
        $atFop = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $atFop), ['target' => 'fop'])->assertOk();

        // TIDAK MASUK — masih di meja Helpdesk
        $atHelpdesk = $this->submitTicket();

        // TIDAK MASUK — sedang diproses NOC
        $atNoc = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $atNoc), ['target' => 'noc'])->assertOk();

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.history'));

        $response->assertOk();
        $response->assertSee($closedInternal->ticket_number);
        $response->assertSee($cancelled->ticket_number);
        $response->assertSee($atFop->ticket_number);
        $response->assertDontSee($atHelpdesk->ticket_number);
        $response->assertDontSee($atNoc->ticket_number);
    }

    /**
     * Tiket yang dikembalikan NOC ke Helpdesk kembali jadi pekerjaan berjalan —
     * harus KELUAR lagi dari History (bukan nyangkut karena pernah di NOC).
     */
    public function test_ticket_returned_to_helpdesk_leaves_history(): void
    {
        $ticket = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])->assertOk();
        $this->actingAs($this->nocUser)->postJson(route('tickets.return-to-helpdesk', $ticket))->assertOk();

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.history'));
        $response->assertOk();

        // assertDontSee() polos gak cukup di sini: helpdeskUser (pembuat
        // tiket) dapet lonceng notifikasi "Tiket Dikembalikan ke Anda" dari
        // TicketService::returnToHelpdesk() (docs/plan/analisa-status-
        // implementasi-notifikasi.md §5) yang JSON-nya ikut ke-embed di
        // <script> dropdown navbar tiap halaman — bukan berarti tiketnya
        // nyangkut di tabel History. Strip <script> dulu biar assert cuma
        // nyentuh konten tabel yang sebenarnya diuji.
        $bodyWithoutScripts = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $response->getContent());
        $this->assertStringNotContainsString($ticket->ticket_number, $bodyWithoutScripts);
    }

    // ── Status: jalur FOP berhenti di "Assign FOP" ──────────────

    public function test_fop_route_ticket_shows_assign_fop_regardless_of_field_progress(): void
    {
        $ticket = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])->assertOk();

        $fopTask = $ticket->refresh()->fopTask;

        foreach ([TaskStatus::TERJADWAL, TaskStatus::IN_PROGRESS, TaskStatus::SELESAI, TaskStatus::DIBATALKAN] as $status) {
            $fopTask->update(['status' => $status->value]);

            $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.history'));

            $response->assertOk();
            $response->assertSee('Assign FOP');
            // Label progres lapangan sengaja TIDAK muncul di History.
            $response->assertDontSee('In Progress');
            $response->assertDontSee('Terputus');
        }
    }

    public function test_orphan_fop_ticket_also_shows_assign_fop(): void
    {
        $ticket = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])->assertOk();
        $ticket->refresh()->fopTask->delete();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history'))
            ->assertOk()
            ->assertSee($ticket->ticket_number)
            ->assertSee('Assign FOP')
            ->assertDontSee('Terputus');
    }

    /**
     * Regresi: helper label pernah pakai ternary "bukan closed berarti
     * cancelled", jadi tiket yang MASIH dikerjakan kebaca "Dibatalkan (NOC)".
     * Barisnya memang gak pernah dirender di History, tapi labelnya bohong.
     */
    public function test_status_label_does_not_call_an_open_ticket_cancelled(): void
    {
        $ticket = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])->assertOk();

        $label = TicketHistoryController::statusLabelFor($ticket->fresh());

        $this->assertSame('Diproses NOC', $label);
        $this->assertStringNotContainsString('Dibatalkan', $label);
    }

    /**
     * Tiket yang ditangani di bawah satu menit — sering terjadi — gak boleh
     * tampil "0:00:00" seolah datanya rusak.
     */
    public function test_sub_minute_duration_shows_seconds(): void
    {
        $ticket = $this->submitTicket();

        $ticket->forceFill([
            'created_at' => now()->subSeconds(42),
            'resolved_at' => now(),
        ])->save();

        $this->assertSame('0:00:42', $ticket->fresh()->solvingTimeLabel());
    }

    // ── Kolom "Oleh" ────────────────────────────────────────────

    /**
     * Kolom ini menyesuaikan hasil akhir. Buat tiket Assign FOP yang berguna
     * dibaca adalah siapa yang MENGIRIM ke FOP — bukan teknisi yang mengerjakan
     * (itu urusan /fop-tasks), dan bukan pula kosong karena tiketnya belum
     * "selesai" dari sisi Ticketing.
     */
    public function test_actor_column_shows_sender_for_assign_fop(): void
    {
        $ticket = $this->submitTicket();

        // Kirim ke NOC harus dari Helpdesk (holderRoles), lalu NOC yang
        // meneruskan ke FOP — jadi kolom "Oleh" harus menunjuk NOC.
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])->assertOk();
        $this->actingAs($this->nocUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])->assertOk();

        $ticket = $ticket->fresh()->load('histories.actor');

        $this->assertSame($this->nocUser->name, TicketHistoryController::actorLabelFor($ticket));

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history'))
            ->assertOk()
            ->assertSee($this->nocUser->name);
    }

    public function test_actor_column_shows_closer_and_canceller(): void
    {
        $closed = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $closed), ['target' => 'noc'])->assertOk();
        $this->actingAs($this->nocUser)->postJson(route('tickets.close', $closed))->assertOk();

        $cancelled = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.cancel', $cancelled), ['reason' => 'Salah input.'])->assertOk();

        $this->assertSame(
            $this->nocUser->name,
            TicketHistoryController::actorLabelFor($closed->fresh()->load('histories.actor'))
        );
        $this->assertSame(
            $this->helpdeskUser->name,
            TicketHistoryController::actorLabelFor($cancelled->fresh()->load('histories.actor'))
        );
    }

    public function test_team_column_is_gone(): void
    {
        $ticket = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])->assertOk();

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.history'));

        $response->assertOk();
        $response->assertDontSee('>Tim<', false);
        $this->assertFalse(method_exists(TicketHistoryController::class, 'teamLabelFor'));
    }

    // ── Filter status ───────────────────────────────────────────

    public function test_status_filter_has_three_values(): void
    {
        $closed = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $closed))->assertOk();

        $cancelled = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.cancel', $cancelled), ['reason' => 'Salah input.'])->assertOk();

        $atFop = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $atFop), ['target' => 'fop'])->assertOk();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history', ['status' => 'selesai']))
            ->assertOk()
            ->assertSee($closed->ticket_number)
            ->assertDontSee($cancelled->ticket_number)
            ->assertDontSee($atFop->ticket_number);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history', ['status' => 'dibatalkan']))
            ->assertOk()
            ->assertSee($cancelled->ticket_number)
            ->assertDontSee($closed->ticket_number)
            ->assertDontSee($atFop->ticket_number);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history', ['status' => 'assign_fop']))
            ->assertOk()
            ->assertSee($atFop->ticket_number)
            ->assertDontSee($closed->ticket_number)
            ->assertDontSee($cancelled->ticket_number);
    }

    // ── POP scope ────────────────────────────────────────────────

    public function test_history_respects_pop_scope(): void
    {
        $mine = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $mine))->assertOk();

        $otherPop = Pop::create([
            'name' => 'POP Siman',
            'code' => 'POP-SMN',
            'type' => 'branch',
            'address' => 'Siman',
            'status' => 'active',
            'city_id' => $this->pop->city_id,
        ]);

        $otherCustomer = Customer::factory()->create([
            'pop_id' => $otherPop->id,
            'village_id' => $this->village->id,
            'full_name' => 'Pelanggan Cabang Lain',
        ]);

        $foreign = $this->submitTicket($otherCustomer);
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $foreign))->assertOk();

        $scopedRole = Role::where('code', 'helpdesk')->first();
        $scopedUser = User::factory()->create(['role_id' => $scopedRole->id, 'status' => 'active']);
        $scope = $scopedUser->roleScopes()->create([
            'role_id' => $scopedRole->id,
            'scope_type' => ScopeType::SELECTED_POP->value,
        ]);
        $scope->targets()->create(['pop_id' => $this->pop->id]);

        $this->actingAs($scopedUser)
            ->get(route('tickets.history'))
            ->assertOk()
            ->assertSee($mine->ticket_number)
            ->assertDontSee($foreign->ticket_number);
    }

    // ── RBAC ─────────────────────────────────────────────────────

    public function test_history_page_rbac(): void
    {
        $this->actingAs($this->helpdeskUser)->get(route('tickets.history'))->assertOk();
        $this->actingAs($this->nocUser)->get(route('tickets.history'))->assertOk();
        $this->actingAs($this->teknisiUser)->get(route('tickets.history'))->assertForbidden();
    }

    public function test_history_permission_is_separate_from_generic_tickets_view(): void
    {
        $role = Role::create(['name' => 'Pemantau', 'code' => 'pemantau', 'is_system' => false]);
        $role->permissions()->attach(
            Permission::where('code', 'tickets.view')->firstOrFail()->id
        );

        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->actingAs($user)->get(route('tickets.history'))->assertForbidden();
    }

    // ── resolved_at: dua arti, satu kolom ───────────────────────

    public function test_resolved_at_filled_when_ticket_closed_internally(): void
    {
        $ticket = $this->submitTicket();

        $this->assertNull($ticket->resolved_at);

        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $ticket))->assertOk();

        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    /**
     * Jalur FOP: `resolved_at` = waktu PENYERAHAN, bukan waktu teknisi lapor.
     * History Ticketing berhenti di titik itu.
     */
    public function test_resolved_at_uses_handover_time_for_fop_route(): void
    {
        $ticket = $this->submitTicket();

        $handoverAt = now()->startOfSecond();
        $this->travelTo($handoverAt);
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])->assertOk();
        $this->travelBack();

        $this->assertSame(
            $handoverAt->toDateTimeString(),
            $ticket->fresh()->resolved_at->toDateTimeString()
        );
    }

    /**
     * Progres lapangan setelah penyerahan TIDAK boleh mengubah `resolved_at` —
     * dulu FopTaskObserver menimpanya dari `tasks.completed_at`.
     */
    public function test_field_progress_does_not_touch_resolved_at(): void
    {
        $ticket = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])->assertOk();

        $handover = $ticket->fresh()->resolved_at;
        $this->assertNotNull($handover);

        $fopTask = $ticket->refresh()->fopTask;
        $task = $this->attachExecutionTask($ticket, now()->addDay());

        $fopTask->update(['status' => TaskStatus::SELESAI->value]);
        $this->assertSame($handover->toDateTimeString(), $ticket->fresh()->resolved_at->toDateTimeString());

        $fopTask->update(['status' => TaskStatus::DIBATALKAN->value]);
        $this->assertSame($handover->toDateTimeString(), $ticket->fresh()->resolved_at->toDateTimeString());

        $this->assertNotNull($task->completed_at);
    }

    /**
     * Dibatalkan bukan diselesaikan — gak dapat waktu selesai, jadi gak ikut
     * menyeret rata-rata durasi.
     */
    public function test_cancelled_ticket_has_no_resolved_at(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.cancel', $ticket), ['reason' => 'Salah input.'])
            ->assertOk();

        $this->assertNull($ticket->fresh()->resolved_at);
    }

    // ── Snapshot desa ────────────────────────────────────────────

    public function test_village_is_snapshotted_and_does_not_follow_customer_move(): void
    {
        $ticket = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $ticket))->assertOk();

        $this->assertSame('Polorejo', $ticket->fresh()->customer_village);

        $newVillage = Village::create([
            'district_id' => $this->village->district_id,
            'name' => 'Kertosari',
            'postal_code' => '63419',
        ]);
        $this->customer->update(['village_id' => $newVillage->id]);

        $this->assertSame('Polorejo', $ticket->fresh()->customer_village);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history'))
            ->assertOk()
            ->assertSee('Polorejo')
            ->assertDontSee('Kertosari');
    }

    // ── Durasi ───────────────────────────────────────────────────

    public function test_duration_is_null_while_on_desk_and_computed_after(): void
    {
        $ticket = $this->submitTicket();

        $this->assertNull($ticket->resolutionMinutes());
        $this->assertNull($ticket->solvingTimeLabel());

        $ticket->forceFill([
            'created_at' => now()->subMinutes(95),
            'resolved_at' => now(),
        ])->save();

        $this->assertSame(95, $ticket->fresh()->resolutionMinutes());
        $this->assertSame('1:35:00', $ticket->fresh()->solvingTimeLabel());
    }

    // ── Filter tanggal & ekspor ──────────────────────────────────

    public function test_date_range_filter_narrows_result(): void
    {
        $old = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $old))->assertOk();
        $old->forceFill(['created_at' => now()->subMonth()])->save();

        $recent = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $recent))->assertOk();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history', ['date_from' => now()->subDay()->toDateString()]))
            ->assertOk()
            ->assertSee($recent->ticket_number)
            ->assertDontSee($old->ticket_number);
    }

    public function test_export_requires_export_permission(): void
    {
        $ticket = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $ticket))->assertOk();

        $this->actingAs($this->teknisiUser)
            ->get(route('tickets.history.export'))
            ->assertForbidden();

        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('Ekstensi zip gak ada — SimpleExcelWriter butuh ZipArchive buat nulis xlsx.');
        }

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history.export'))
            ->assertOk()
            ->assertDownload();
    }

    // ── Ringkasan header ─────────────────────────────────────────

    public function test_summary_counts_per_outcome(): void
    {
        $closed = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $closed))->assertOk();

        $cancelled = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.cancel', $cancelled), ['reason' => 'Salah input.'])->assertOk();

        $atFop = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $atFop), ['target' => 'fop'])->assertOk();

        // Masih dikerjakan — gak boleh kehitung di ringkasan mana pun.
        $this->submitTicket();

        $summary = $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history'))
            ->assertOk()
            ->viewData('summary');

        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['selesai']);
        $this->assertSame(1, $summary['dibatalkan']);
        $this->assertSame(1, $summary['assign_fop']);
        $this->assertNotNull($summary['avg_minutes']);
    }

    // ── Kategori & handler filter ────────────────────────────────

    public function test_category_filter(): void
    {
        $category = TicketIssueCategory::create([
            'name' => 'Backbone CUT',
            'default_priority' => 'High',
            'sla_source' => 'prioritas',
            'is_active' => true,
        ]);

        $withCategory = $this->submitTicket(null, [
            'detail_keluhan' => 'Backbone putus.',
            'issue_category_id' => $category->id,
        ]);
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $withCategory))->assertOk();

        $withoutCategory = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $withoutCategory))->assertOk();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history', ['issue_category_id' => $category->id]))
            ->assertOk()
            ->assertSee($withCategory->ticket_number)
            ->assertDontSee($withoutCategory->ticket_number);
    }

    public function test_fop_tasks_not_born_from_a_ticket_are_not_listed(): void
    {
        // Task FOP mandiri gak punya baris `tickets` sama sekali — test ini
        // memegang keputusan itu secara eksplisit, bukan cuma mengandalkan
        // fakta bahwa halaman ini membaca tabel tickets.
        $ticket = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])->assertOk();

        $this->assertDatabaseCount('tickets', 1);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history'))
            ->assertOk()
            ->assertSee($ticket->ticket_number);
    }

    public function test_handler_column_is_not_used_as_progress_indicator(): void
    {
        $ticket = $this->submitTicket();
        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])->assertOk();

        $this->assertSame(TicketHandler::FOP, $ticket->fresh()->handler);
        $this->assertSame(TicketHandlingStatus::OPEN, $ticket->fresh()->status);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.history'))
            ->assertOk()
            ->assertSee('Assign FOP');
    }
}
