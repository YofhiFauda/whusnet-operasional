<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
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
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tab "Riwayat Ticketing" di halaman Detail Pelanggan: satu tiket wajib
 * tampil sebagai SATU baris, regime tampilan ngikut `handler` tiket
 * (lihat Ticket::bucket()/statusLabel()):
 *  - handler=FOP (turun ke Ticketing FOP)     → tampil sebagai "Ticket FOP".
 *  - handler=HELPDESK/NOC (selesai di situ)   → tampil sebagai "Ticket Helpdesk/NOC".
 *
 * FopTask yang berasal dari tiket TIDAK boleh dobel muncul lagi di seksi
 * "FOP Field Task" legacy — itu cuma buat FopTask yang dibuat langsung dari
 * /fop-tasks (bukan lewat Ticket::escalateToFop()).
 */
class CustomerTicketingHistoryRegimeTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdeskUser;

    private User $adminUser;

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

        $this->adminUser = $this->loginAsAdmin();

        $role = Role::where('code', 'helpdesk')->first();
        $this->helpdeskUser = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $this->helpdeskUser->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

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

    public function test_ticket_closed_at_helpdesk_shown_as_ticket_helpdesk(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.close', $ticket))
            ->assertRedirect();

        $response = $this->actingAs($this->adminUser)
            ->get(route('customers.show', $this->customer))
            ->assertOk();

        $response->assertSee($ticket->ticket_number)
            ->assertSee('TICKET HELPDESK');
    }

    public function test_ticket_escalated_to_fop_shown_as_ticket_fop_and_not_duplicated(): void
    {
        $ticket = $this->submitTicket();

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        $ticket->refresh();

        $response = $this->actingAs($this->adminUser)
            ->get(route('customers.show', $this->customer))
            ->assertOk();

        // Regime "Ticket FOP" — nomor TFOP tampil, bukan TKT.
        $response->assertSee($ticket->fopTask->task_number)
            ->assertSee('TICKET FOP');

        // FopTask hasil eskalasi tiket TIDAK boleh dobel tampil sebagai
        // "FOP FIELD TASK" legacy — dia sudah terwakili sebagai baris tiket.
        $response->assertDontSee('FOP FIELD TASK');
    }

    /**
     * FopTask yang dibuat LANGSUNG dari /fop-tasks (bukan lewat tiket) tetap
     * tampil seperti sebelumnya, karena tidak ada Ticket yang mewakilinya.
     */
    public function test_non_ticket_fop_task_still_shown_as_legacy_fop_field_task(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->first();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $this->actingAs($this->adminUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN',
            'task_date' => now()->format('Y-m-d').' 08:00:00',
            'tugas' => 'Task murni FOP',
            'village_id' => $this->customer->village_id,
            'pop_id' => $this->pop->id,
            'issue' => 'FO Cut',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'customer_id' => $this->customer->id,
            'technicians' => [$teknisi->id],
        ])->assertRedirect();

        $fopTask = FopTask::where('tugas', 'Task murni FOP')->firstOrFail();
        $this->assertNull($fopTask->ticket);

        $response = $this->actingAs($this->adminUser)
            ->get(route('customers.show', $this->customer))
            ->assertOk();

        $response->assertSee('FOP FIELD TASK')
            ->assertSee($fopTask->task_number ?? 'FOP-TSK-'.$fopTask->id);
    }
}
