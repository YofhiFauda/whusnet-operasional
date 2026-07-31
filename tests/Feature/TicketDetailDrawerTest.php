<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\FopTaskStatusHistory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAttachment;
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
 * Detail tiket di dua HALAMAN KERJA (Worksheet Helpdesk & Worksheet NOC) dibuka
 * lewat **drawer kanan**, bukan navigasi ke `/tickets/{id}`. Halaman penuh
 * disisakan buat halaman arsip (Ticket Selesai, Ticket Dibatalkan, History
 * Ticketing) yang memang berangkat dari daftar statis.
 *
 * Isi drawer datang dari endpoint `tickets.detail-json` — gerbangnya SAMA dengan
 * halaman detail (`tickets.view` + POP scope). Kalau gerbang itu longgar,
 * seluruh snapshot pelanggan bocor lintas cabang lewat satu request JSON.
 */
class TicketDetailDrawerTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdeskUser;

    private User $nocUser;

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

    public function test_detail_json_returns_ticket_snapshot_actions_and_history(): void
    {
        $ticket = $this->submitTicket();

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.detail-json', $ticket));

        $response->assertOk();
        $response->assertJsonPath('code', $ticket->ticket_number);
        $response->assertJsonPath('customer.name', 'Budi Santoso');
        $response->assertJsonPath('detail_keluhan', 'Internet mati total sejak pagi.');
        // Helpdesk yang megang: boleh selesaikan & eskalasi.
        $response->assertJsonPath('actions.can_close', true);
        $response->assertJsonPath('actions.can_escalate_noc', true);
        // Riwayat "dibuat" ikut — drawer nampilin blok Riwayat & Audit.
        $response->assertJsonPath('histories.0.label', 'Ticket dikirim');
        $response->assertJsonPath('histories.0.actor', $this->helpdeskUser->name);
        // Label tipe versi panjang — badge header drawer, biar gak cuma kode "MTN".
        $response->assertJsonPath('type_label', TaskType::MAINTENANCE->value.' — '.TaskType::MAINTENANCE->label());
        // Belum pernah ke FOP: bukan orphan, dan gak ada blok Task FOP.
        $response->assertJsonPath('fop_task', null);
        $response->assertJsonPath('fop_task_orphan', false);
    }

    /**
     * Blok "Task FOP Lapangan Terkait" + "Riwayat Task FOP" di drawer — bagian
     * yang bikin drawer setara halaman detail penuh buat tiket yang udah
     * diserahkan ke lapangan.
     */
    public function test_detail_json_carries_fop_task_technicians_and_field_history(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        $fopTask = $ticket->fresh()->fopTask;
        $fopTask->technicians()->attach($this->teknisiUser->id);

        FopTaskStatusHistory::create([
            'fop_task_id' => $fopTask->id,
            'from_status' => 'draft',
            'to_status' => 'terjadwal',
            'changed_by' => $this->helpdeskUser->id,
            'changed_at' => now(),
        ]);

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.detail-json', $ticket));

        $response->assertOk();
        $response->assertJsonPath('fop_task.number', $fopTask->task_number);
        $response->assertJsonPath('fop_task.technicians', $this->teknisiUser->name);
        $response->assertJsonPath('fop_task.can_view', false); // helpdesk gak punya fop_tasks.view
        $response->assertJsonPath('fop_task.histories.0.label', 'Terjadwal');
        $response->assertJsonPath('fop_task.histories.0.changed_by', $this->helpdeskUser->name);
        $response->assertJsonPath('fop_task_orphan', false);
    }

    /**
     * Tiket "Terputus" — FopTask-nya dihapus FOP. `fop_task` DAN
     * `fop_task_number` dua-duanya jadi null (turunan relasi yang sama), jadi
     * drawer butuh flag `fop_task_orphan` buat nampilin pesan "task sudah tidak
     * aktif" — bukan diam-diam menghilangkan blok FOP.
     */
    public function test_detail_json_flags_orphan_ticket_when_fop_task_deleted(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        $ticket->fresh()->fopTask->delete();

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.detail-json', $ticket));

        $response->assertOk();
        $response->assertJsonPath('fop_task', null);
        $response->assertJsonPath('fop_task_number', null);
        $response->assertJsonPath('fop_task_orphan', true);
    }

    /**
     * Metadata lampiran (ukuran + pengunggah) — dipakai baris kedua tiap item
     * di blok Lampiran, sama seperti halaman detail penuh.
     *
     * Lampirannya dibikin langsung sebagai baris DB, TANPA nyentuh filesystem:
     * payload-nya cuma baca kolom + generate URL endpoint, jadi test ini gak
     * perlu disk fake sama sekali.
     */
    public function test_detail_json_includes_attachment_metadata(): void
    {
        $ticket = $this->submitTicket();

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'file_path' => 'tickets/'.$ticket->id.'/bukti.jpg',
            'original_name' => 'bukti-redaman.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'uploaded_by' => $this->helpdeskUser->id,
        ]);

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.detail-json', $ticket));

        $response->assertOk();
        $response->assertJsonPath('attachments.0.name', 'bukti-redaman.jpg');
        $response->assertJsonPath('attachments.0.size', $attachment->humanSize());
        $response->assertJsonPath('attachments.0.uploader', $this->helpdeskUser->name);
        $response->assertJsonPath('attachments.0.is_image', true);
        // URL WAJIB endpoint bercek permission — disk lampiran privat.
        $response->assertJsonPath('attachments.0.url', route('tickets.attachments.download', $attachment));
    }

    /**
     * `handler=FOP` terminal buat sisi Ticketing — drawer gak boleh nawarin
     * tombol aksi apa pun lagi (pembatalan pasca-FOP lewat /fop-tasks).
     */
    public function test_detail_json_reports_no_actions_once_ticket_reaches_fop(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        $response = $this->actingAs($this->helpdeskUser)
            ->getJson(route('tickets.detail-json', $ticket));

        $response->assertOk();
        $response->assertJsonPath('actions.can_close', false);
        $response->assertJsonPath('actions.can_escalate_fop', false);
        $response->assertJsonPath('actions.can_cancel', false);
        $response->assertJsonPath('fop_task_number', $ticket->fresh()->fopTask->task_number);
    }

    public function test_detail_json_is_forbidden_without_tickets_view(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->teknisiUser)
            ->getJson(route('tickets.detail-json', $ticket))
            ->assertForbidden();
    }

    public function test_detail_json_is_forbidden_outside_pop_scope(): void
    {
        $ticket = $this->submitTicket();

        $otherPop = Pop::create([
            'name' => 'POP Jetis',
            'code' => 'POP-JTS',
            'type' => 'branch',
            'address' => 'Jetis',
            'status' => 'active',
            'city_id' => $this->pop->city_id,
        ]);

        $scopedUser = $this->makeUserWithSelectedPopScope('noc', $otherPop);

        $this->actingAs($scopedUser)
            ->getJson(route('tickets.detail-json', $ticket))
            ->assertForbidden();
    }

    /**
     * Dua halaman kerja memuat drawer bersama & gak lagi nge-link ke halaman
     * detail. Kalau link itu balik, user kelempar keluar halaman kerja dan
     * kehilangan filter/form yang sedang diisi.
     */
    public function test_helpdesk_worksheet_opens_detail_in_drawer_not_new_page(): void
    {
        $ticket = $this->submitTicket();

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('open-ticket-drawer', false);
        $response->assertSee('openTicketDetail', false);
        $response->assertDontSee('\'/tickets\'  }}/\' + task.id', false);
        $response->assertDontSee(route('tickets.show', $ticket), false);
    }

    public function test_noc_worksheet_opens_detail_in_drawer_not_new_page(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertRedirect();

        $response = $this->actingAs($this->nocUser)->get(route('noc.worksheet'));

        $response->assertOk();
        $response->assertSee('open-ticket-drawer', false);
        $response->assertSee($ticket->ticket_number);

        // Barisnya sendiri gak boleh punya link ke halaman mana pun. SENGAJA
        // diperiksa per-baris, bukan assertDontSee(route('tickets.show')) atas
        // seluruh HTML: dropdown notifikasi di layout memang menaut ke halaman
        // detail tiket, jadi assertion global bakal gagal karena alasan yang
        // sama sekali beda.
        preg_match('/<tr data-ticket-row="'.$ticket->id.'".*?<\/tr>/s', $response->getContent(), $row);

        $this->assertNotEmpty($row, 'Baris tiket tidak ditemukan di tabel worksheet NOC.');
        $this->assertStringNotContainsString('href=', $row[0]);
        $this->assertStringContainsString('openDetail('.$ticket->id.')', $row[0]);
    }

    private function submitTicket(): Ticket
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total sejak pagi.',
            'priority' => 'High',
        ])->assertRedirect();

        return Ticket::latest('id')->firstOrFail();
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
