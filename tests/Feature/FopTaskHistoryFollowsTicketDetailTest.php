<?php

namespace Tests\Feature;

use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Detail Task FOP untuk category MTN & C-REQ yang asalnya dari Ticketing
 * harus menampilkan data yang sama kayak /tickets/{ticket} — data pelanggan
 * snapshot, detail keluhan utuh, catatan teknis, lampiran, dan riwayat
 * ticketing — bukan cuma $fopTask->issue yang kepotong 255 karakter.
 */
class FopTaskHistoryFollowsTicketDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;
    private User $helpdeskUser;
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
            'address' => 'Jl. Merdeka No. 1',
            'primary_phone' => '081234567890',
            'odp_code' => 'ODP-PLR-01',
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

    private function submitTicket(array $overrides = []): Ticket
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), array_merge([
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => str_repeat('Internet mati total sejak pagi, sudah dicoba restart modem berkali-kali tapi tetap tidak menyala lampu LOS-nya. ', 3),
            'catatan_teknis' => 'Redaman -28 dBm, indikasi kabel FO putus di tiang dekat rumah.',
            'priority' => 'High',
        ], $overrides))->assertRedirect();

        return Ticket::latest('id')->firstOrFail();
    }

    public function test_mtn_ticket_detail_shows_full_customer_snapshot_and_complaint(): void
    {
        $ticket = $this->submitTicket();

        $response = $this->actingAs($this->fopUser)
            ->get(route('fop-tasks.history.show', $ticket->fop_task_id));

        $response->assertOk();
        $response->assertSee('Detail Ticket');
        $response->assertSee($ticket->ticket_number);
        $response->assertSee('Budi Santoso');
        $response->assertSee('Jl. Merdeka No. 1');
        $response->assertSee('081234567890');
        $response->assertSee('ODP-PLR-01');

        // Detail keluhan lengkap (>255 char) harus utuh, BUKAN cuma versi
        // kepotong yang disimpan di $fopTask->issue.
        $response->assertSee('sudah dicoba restart modem berkali-kali');
        $response->assertSee('Redaman -28 dBm, indikasi kabel FO putus di tiang dekat rumah.');
    }

    public function test_creq_ticket_detail_also_follows_ticketing(): void
    {
        $ticket = $this->submitTicket(['type' => TaskType::CREQ->value]);

        $this->actingAs($this->fopUser)
            ->get(route('fop-tasks.history.show', $ticket->fop_task_id))
            ->assertOk()
            ->assertSee('Detail Ticket')
            ->assertSee($ticket->ticket_number);
    }

    public function test_mtn_detail_shows_ticket_attachments(): void
    {
        Storage::fake('local');

        $ticket = $this->submitTicket([
            'attachments' => [UploadedFile::fake()->image('bukti.jpg')],
        ]);

        $this->actingAs($this->fopUser)
            ->get(route('fop-tasks.history.show', $ticket->fop_task_id))
            ->assertOk()
            ->assertSee('bukti.jpg');
    }

    public function test_mtn_detail_shows_ticket_history_alongside_fop_status_history(): void
    {
        $ticket = $this->submitTicket();

        $response = $this->actingAs($this->fopUser)
            ->get(route('fop-tasks.history.show', $ticket->fop_task_id));

        $response->assertOk();
        $response->assertSee('Riwayat Ticketing');
        $response->assertSee('Histori Status');
        $response->assertSee('Ticket dikirim');
    }

    public function test_link_back_to_ticketing_detail_is_present(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->fopUser)
            ->get(route('fop-tasks.history.show', $ticket->fop_task_id))
            ->assertOk()
            ->assertSee(route('tickets.show', $ticket), false);
    }

    /**
     * FopTask MTN yang dibuat LANGSUNG dari /fop-tasks (bukan lewat Ticketing)
     * gak punya ticket terkait — halaman tetap tampil normal tanpa section
     * "Detail Ticket" dan tanpa error.
     */
    public function test_directly_created_mtn_fop_task_has_no_ticket_section(): void
    {
        $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN',
            'task_date' => now()->format('Y-m-d') . ' 08:00:00',
            'tugas' => 'Task MTN Manual FOP',
            'village_id' => Village::first()->id,
            'pop_id' => $this->pop->id,
            'issue' => 'FO Cut',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => [$this->makeUserWithAllPopScope('teknisi')->id],
        ])->assertRedirect();

        $fopTask = FopTask::where('tugas', 'Task MTN Manual FOP')->firstOrFail();

        $response = $this->actingAs($this->fopUser)
            ->get(route('fop-tasks.history.show', $fopTask->id));

        $response->assertOk();
        $response->assertDontSee('Detail Ticket');
        $response->assertDontSee('Riwayat Ticketing');
    }

    /**
     * Category di luar MTN/C-REQ (mis. SURVEY) gak pernah nampilin section
     * Ticketing walau seandainya ada ticket nyasar terhubung — invarian tipe
     * tiket Ticketing dijaga TaskType::ticketValues().
     */
    public function test_non_ticket_category_never_shows_ticketing_section(): void
    {
        $task = \App\Models\Task::create([
            'task_number' => 'TASK-SRV-0001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey: ' . $this->customer->full_name,
            'status' => 'terjadwal',
            'scheduled_at' => now(),
            'sla_minutes' => 120,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-SRV-0001',
            'task_date' => now(),
            'category' => 'SURVEY',
            'tugas' => 'Survey Pelanggan: ' . $this->customer->full_name,
            'village_id' => $this->customer->village_id,
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'issue' => 'Survey',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'task_id' => $task->id,
        ]);

        $this->actingAs($this->fopUser)
            ->get(route('fop-tasks.history.show', $fopTask->id))
            ->assertOk()
            ->assertDontSee('Detail Ticket')
            ->assertDontSee('Riwayat Ticketing');
    }
}
