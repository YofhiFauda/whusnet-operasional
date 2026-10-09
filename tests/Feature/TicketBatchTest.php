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
use App\Models\TicketIssueCategory;
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
 * Revisi Worksheet Helpdesk — kategori issue ber-checklist Batch (mis. "ODP
 * LOS") support Parent/Child banyak pelanggan sekaligus, + No. HP Pelapor.
 * Lihat docs/ticketing/business-logic.md.
 */
class TicketBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdeskUser;

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

        $role = Role::where('code', 'helpdesk')->first();
        $this->helpdeskUser = User::factory()->create(['role_id' => $role->id]);
        $this->helpdeskUser->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);

        $this->pop = Pop::create([
            'name' => 'POP Jetis',
            'code' => 'POP-JTS',
            'type' => 'branch',
            'address' => 'Jetis',
            'status' => 'active',
            'city_id' => $city->id,
        ]);

        $this->customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $village->id,
            'full_name' => 'Pelanggan A',
        ]);
    }

    private function makeBatchCategory(): TicketIssueCategory
    {
        return TicketIssueCategory::create([
            'name' => 'ODP LOS',
            'default_priority' => 'High',
            'sla_source' => 'prioritas',
            'is_active' => true,
            'is_batch' => true,
        ]);
    }

    public function test_batch_category_ticket_can_be_created_without_customer_using_manual_pop(): void
    {
        $category = $this->makeBatchCategory();

        $response = $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'issue_category_id' => $category->id,
            'search_label' => 'ODP JTS 13 LOS',
            'pop_id' => $this->pop->id,
            'reporter_phone' => '081200001111',
            'detail_keluhan' => 'Terdapat Pelanggan A sampai C yang terdampak',
            'priority' => 'High',
        ]);

        $response->assertCreated();

        $ticket = Ticket::firstWhere('issue_category_id', $category->id);
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->customer_id);
        $this->assertSame($this->pop->id, $ticket->pop_id);
        $this->assertSame('ODP JTS 13 LOS', $ticket->customer_name);
        $this->assertSame('081200001111', $ticket->reporter_phone);
        $this->assertTrue($ticket->isBatch());
    }

    public function test_batch_ticket_without_pop_or_label_is_rejected(): void
    {
        $category = $this->makeBatchCategory();

        $response = $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'issue_category_id' => $category->id,
            'detail_keluhan' => 'Terdapat pelanggan terdampak',
            'priority' => 'High',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pop_id', 'search_label']);
    }

    public function test_non_batch_category_still_requires_customer_id(): void
    {
        $response = $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'High',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id']);
    }

    public function test_reporter_phone_falls_back_to_customer_phone_when_empty(): void
    {
        $this->customer->update(['primary_phone' => '081399998888']);

        $response = $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'High',
        ]);

        $response->assertCreated();

        $ticket = Ticket::latest('id')->first();
        $this->assertNull($ticket->reporter_phone);
        $this->assertSame('081399998888', $ticket->contactPhone());
    }

    public function test_reporter_phone_overrides_display_when_filled(): void
    {
        $this->customer->update(['primary_phone' => '081399998888']);

        $response = $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'reporter_phone' => '087700001234',
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'High',
        ]);

        $response->assertCreated();

        $ticket = Ticket::latest('id')->first();
        $this->assertSame('087700001234', $ticket->reporter_phone);
        $this->assertSame('087700001234', $ticket->contactPhone());
    }

    public function test_helpdesk_can_add_batch_member_to_batch_ticket(): void
    {
        $category = $this->makeBatchCategory();

        $ticket = new Ticket;
        $ticket->ticket_number = 'TKT-2026-0100';
        $ticket->type = TaskType::MAINTENANCE;
        $ticket->issue_category_id = $category->id;
        $ticket->pop_id = $this->pop->id;
        $ticket->customer_name = 'ODP JTS 13 LOS';
        $ticket->detail_keluhan = 'Terdapat pelanggan terdampak';
        $ticket->priority = 'High';
        $ticket->created_by = $this->helpdeskUser->id;
        $ticket->handler = TicketHandler::HELPDESK;
        $ticket->status = TicketHandlingStatus::OPEN;
        $ticket->save();

        // Anggota dari lookup CID (customer_id ada), nama/HP gak dikirim —
        // fallback ke data master.
        $response = $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            ['customer_id' => $this->customer->id]
        );

        $response->assertCreated();
        $this->assertDatabaseHas('ticket_batch_members', [
            'ticket_id' => $ticket->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->full_name,
        ]);

        // Anggota input manual (belum ketemu lewat lookup).
        $response = $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            ['customer_name' => 'Pelanggan Manual', 'phone' => '081211112222']
        );

        $response->assertCreated();
        $this->assertDatabaseHas('ticket_batch_members', [
            'ticket_id' => $ticket->id,
            'customer_id' => null,
            'customer_name' => 'Pelanggan Manual',
            'phone' => '081211112222',
        ]);

        $this->assertCount(2, $ticket->fresh()->batchMembers);
    }

    /**
     * Pick CID auto-isi nama/HP di modal (frontend), TAPI staf tetap bisa
     * edit sebelum submit — nilai yang diketik WAJIB menang atas data master
     * pelanggan, bukan sebaliknya. Lihat TicketService::addBatchMember().
     */
    public function test_edited_name_and_phone_override_customer_master_data(): void
    {
        $category = $this->makeBatchCategory();

        $ticket = new Ticket;
        $ticket->ticket_number = 'TKT-2026-0102';
        $ticket->type = TaskType::MAINTENANCE;
        $ticket->issue_category_id = $category->id;
        $ticket->pop_id = $this->pop->id;
        $ticket->customer_name = 'ODP JTS 13 LOS';
        $ticket->detail_keluhan = 'Terdapat pelanggan terdampak';
        $ticket->priority = 'High';
        $ticket->created_by = $this->helpdeskUser->id;
        $ticket->handler = TicketHandler::HELPDESK;
        $ticket->status = TicketHandlingStatus::OPEN;
        $ticket->save();

        $this->customer->update(['primary_phone' => '081399998888']);

        $response = $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            [
                'customer_id' => $this->customer->id,
                'customer_name' => 'Nama Panggilan Beda',
                'phone' => '087788889999',
            ]
        );

        $response->assertCreated();
        $this->assertDatabaseHas('ticket_batch_members', [
            'ticket_id' => $ticket->id,
            'customer_id' => $this->customer->id,
            'customer_name' => 'Nama Panggilan Beda',
            'phone' => '087788889999',
        ]);
    }

    public function test_adding_batch_member_to_non_batch_ticket_is_rejected(): void
    {
        $ticket = new Ticket;
        $ticket->ticket_number = 'TKT-2026-0101';
        $ticket->type = TaskType::MAINTENANCE;
        $ticket->customer_id = $this->customer->id;
        $ticket->pop_id = $this->pop->id;
        $ticket->customer_name = $this->customer->full_name;
        $ticket->detail_keluhan = 'Internet mati total.';
        $ticket->priority = 'High';
        $ticket->created_by = $this->helpdeskUser->id;
        $ticket->handler = TicketHandler::HELPDESK;
        $ticket->status = TicketHandlingStatus::OPEN;
        $ticket->save();

        $response = $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            ['customer_name' => 'Pelanggan Manual']
        );

        $response->assertStatus(422);
    }

    /**
     * Teknisi yang ditugaskan ke Task hasil tiket batch WAJIB lihat daftar
     * pelanggan terdampak di Detail Task-nya sendiri — satu Task/FopTask
     * mewakili SEMUA pelanggan, teknisi gak boleh cuma lihat label ODP tanpa
     * tau siapa aja yang harus dicek (laporan user: "teknisi gak lihat
     * daftar 30 pelanggan terdampak"). Lihat tasks/show.blade.php.
     */
    public function test_technician_sees_affected_customers_on_assigned_task(): void
    {
        $category = $this->makeBatchCategory();

        $fopRole = Role::where('code', 'fop')->first();
        $fopUser = User::factory()->create(['role_id' => $fopRole->id]);
        $fopUser->roleScopes()->create(['role_id' => $fopRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $teknisiUser = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'issue_category_id' => $category->id,
            'search_label' => 'ODP JTS 13 LOS',
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Terdapat Pelanggan A sampai C yang terdampak',
            'priority' => 'High',
        ])->assertCreated();

        $ticket = Ticket::firstWhere('issue_category_id', $category->id);

        $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            ['customer_name' => 'Pelanggan A', 'phone' => '081200000001']
        )->assertCreated();
        $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            ['customer_name' => 'Pelanggan B', 'phone' => '081200000002']
        )->assertCreated();

        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])->assertOk();

        $fopTask = $ticket->fresh()->fopTask;

        $this->actingAs($fopUser)->put(route('fop-tasks.update', $fopTask), [
            'task_date' => now()->addDay()->toDateString(),
            'issue' => $fopTask->issue,
            'notes' => $fopTask->notes,
            'status' => 'draft',
            'priority' => $fopTask->priority->value,
            'technicians' => [$teknisiUser->id],
        ])->assertRedirect();

        $task = $fopTask->fresh()->task;
        $this->assertNotNull($task);

        $response = $this->actingAs($teknisiUser)->get(route('tasks.show', $task));

        $response->assertOk();
        $response->assertSee('Pelanggan A');
        $response->assertSee('Pelanggan B');
        $response->assertSee('2 Pelanggan');
    }

    /**
     * FOP yang buka Detail Task dari papan /fop-tasks (fop_tasks.history_detail,
     * BEDA halaman dari tasks/show.blade.php teknisi) juga WAJIB lihat
     * daftar pelanggan terdampak — "Data Pelanggan" versi single-customer di
     * halaman itu mubazir (dash semua) buat tiket batch.
     */
    public function test_fop_history_detail_page_shows_affected_customers(): void
    {
        $category = $this->makeBatchCategory();

        $fopRole = Role::where('code', 'fop')->first();
        $fopUser = User::factory()->create(['role_id' => $fopRole->id]);
        $fopUser->roleScopes()->create(['role_id' => $fopRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'issue_category_id' => $category->id,
            'search_label' => 'ODP JTS 13 LOS',
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Terdapat Pelanggan A sampai C yang terdampak',
            'priority' => 'High',
        ])->assertCreated();

        $ticket = Ticket::firstWhere('issue_category_id', $category->id);

        $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            ['customer_name' => 'Pelanggan C', 'phone' => '081200000003']
        )->assertCreated();

        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])->assertOk();

        $fopTask = $ticket->fresh()->fopTask;

        $response = $this->actingAs($fopUser)->get(route('fop-tasks.history.show', $fopTask));

        $response->assertOk();
        $response->assertSee('Pelanggan C');
        $response->assertSee('Pelanggan Terdampak (1)');
    }

    /**
     * Assign ke NOC/FOP cuma ngirim tiket PARENT — child (batchMembers) gak
     * pernah nyentuh FopTask/composeFopNotes/histories NOC-FOP sama sekali
     * (lihat CLAUDE.md § Sinkronisasi Ticket ↔ FopTask ↔ Task). Diverifikasi
     * dari sisi FopTask yang lahir: notes-nya cuma pointer pendek ke tiket,
     * gak menyebut satu pun child.
     */
    public function test_escalating_batch_ticket_sends_only_parent_not_children(): void
    {
        $category = $this->makeBatchCategory();

        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'issue_category_id' => $category->id,
            'search_label' => 'ODP JTS 13 LOS',
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Terdapat Pelanggan A sampai C yang terdampak',
            'priority' => 'High',
        ])->assertCreated();

        $ticket = Ticket::firstWhere('issue_category_id', $category->id);

        $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            ['customer_name' => 'Pelanggan A', 'phone' => '081200000001']
        )->assertCreated();
        $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            ['customer_name' => 'Pelanggan B', 'phone' => '081200000002']
        )->assertCreated();

        $response = $this->actingAs($this->helpdeskUser)->postJson(route('tickets.escalate', $ticket), ['target' => 'fop']);
        $response->assertOk();

        $fopTask = $ticket->fresh()->fopTask;
        $this->assertNotNull($fopTask);
        $this->assertStringContainsString($ticket->ticket_number, $fopTask->notes);
        $this->assertStringNotContainsString('Pelanggan A', $fopTask->notes);
        $this->assertStringNotContainsString('Pelanggan B', $fopTask->notes);
        // customer_id tiket parent tetap null — FopTask gak "mewakilkan" satu
        // child pun jadi pelanggan tunggal si FopTask.
        $this->assertNull($fopTask->customer_id);

        // Kedua child tetap utuh di ticket_batch_members, gak ikut kepindah/
        // dihapus/disalin ke mana pun akibat eskalasi.
        $this->assertCount(2, $ticket->fresh()->batchMembers);
    }

    /**
     * Ticket Selesai & Dibatalkan (arsip) nampilin Parent + SEMUA Child
     * sekaligus — beda dari Worksheet Helpdesk yang cuma ngirim Parent ke
     * NOC/FOP. Lihat TicketArchiveController + partials/archive.blade.php.
     */
    public function test_archive_page_shows_parent_and_children_of_closed_batch_ticket(): void
    {
        $category = $this->makeBatchCategory();

        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'issue_category_id' => $category->id,
            'search_label' => 'ODP JTS 13 LOS',
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Terdapat Pelanggan A sampai C yang terdampak',
            'priority' => 'High',
        ])->assertCreated();

        $ticket = Ticket::firstWhere('issue_category_id', $category->id);

        $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            ['customer_name' => 'Pelanggan Terdampak Satu', 'phone' => '081200000009']
        )->assertCreated();

        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $ticket))->assertOk();

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.selesai'));

        $response->assertOk();
        $response->assertSee($ticket->ticket_number);
        $response->assertSee('ODP JTS 13 LOS');
        $response->assertSee('Pelanggan Terdampak Satu');
    }

    /**
     * History Ticketing (arsip semua tiket) — Parent + Child juga wajib
     * kebaca, sama seperti Ticket Selesai/Dibatalkan.
     */
    public function test_history_page_shows_parent_and_children_of_batch_ticket(): void
    {
        $category = $this->makeBatchCategory();

        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'issue_category_id' => $category->id,
            'search_label' => 'ODP JTS 13 LOS',
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Terdapat Pelanggan A sampai C yang terdampak',
            'priority' => 'High',
        ])->assertCreated();

        $ticket = Ticket::firstWhere('issue_category_id', $category->id);

        $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            ['customer_name' => 'Pelanggan Terdampak Dua', 'phone' => '081200000008']
        )->assertCreated();

        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.close', $ticket))->assertOk();

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.history'));

        $response->assertOk();
        $response->assertSee($ticket->ticket_number);
        $response->assertSee('Pelanggan Terdampak Dua');
    }

    public function test_worksheet_payload_flags_batch_ticket_and_lists_members(): void
    {
        $category = $this->makeBatchCategory();

        $this->actingAs($this->helpdeskUser)->postJson(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'issue_category_id' => $category->id,
            'search_label' => 'ODP JTS 13 LOS',
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Terdapat pelanggan terdampak',
            'priority' => 'High',
        ])->assertCreated();

        $ticket = Ticket::firstWhere('issue_category_id', $category->id);

        $this->actingAs($this->helpdeskUser)->postJson(
            route('tickets.batch-members.store', $ticket),
            ['customer_id' => $this->customer->id]
        )->assertCreated();

        $response = $this->actingAs($this->helpdeskUser)->getJson(route('tickets.worksheet-tasks'));

        $response->assertOk();
        $payload = collect($response->json('tasks'))->firstWhere('id', $ticket->id);

        $this->assertNotNull($payload);
        $this->assertTrue($payload['is_batch']);
        $this->assertCount(1, $payload['batch_members']);
        $this->assertSame($this->customer->full_name, $payload['batch_members'][0]['customer_name']);
    }
}
