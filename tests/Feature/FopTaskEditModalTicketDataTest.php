<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\Distribution;
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
 * Point 2 dari kebutuhan sinkronisasi: modal Edit di /fop-tasks buat task
 * MTN/C-REQ yang asalnya dari Ticketing harus punya akses ke data ticket
 * (CID lengkap, data pelanggan, detail keluhan, catatan teknis) — bukan cuma
 * form generik yang bisa ngelepas task dari ticket aslinya.
 */
class FopTaskEditModalTicketDataTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private User $helpdeskUser;

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

        $this->fopUser = $this->makeUserWithAllPopScope('fop');
        $this->helpdeskUser = $this->makeUserWithAllPopScope('helpdesk');

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);

        $this->pop = Pop::create([
            'name' => 'POP Polorejo', 'code' => 'POP-PLR', 'cid_prefix' => 'C',
            'type' => 'branch', 'address' => 'Polorejo', 'status' => 'active', 'city_id' => $city->id,
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
     * Inti fix: baris task di /fop-tasks yang nyambung ke ticket harus bawa
     * data ticket lengkap (termasuk CID final pelanggan) di JSON buat modal
     * Edit — sebelumnya relasi `ticket` gak di-eager-load sama sekali.
     */
    public function test_fop_tasks_index_embeds_linked_ticket_data_with_full_cid(): void
    {
        $distribution = Distribution::create([
            'pop_id' => $this->pop->id,
            'code' => 'C',
            'description' => 'Distribusi C',
        ]);

        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
            'distribution_id' => $distribution->id,
            'customer_code' => 'RQ000007',
            'cid' => 'C1X4CRQ000007',
            'status' => 'active',
            'full_name' => 'Budi Santoso',
            'address' => 'Jl. Merdeka No. 1',
            'primary_phone' => '081234567890',
        ]);

        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $customer->id,
            'detail_keluhan' => 'Internet mati total.',
            'catatan_teknis' => 'Redaman -27 dBm.',
            'priority' => 'High',
        ])->assertRedirect();

        $ticket = Ticket::first();

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));

        $response->assertOk();
        // CID lengkap (dari customers.cid), bukan cuma customer_code mentah.
        $response->assertSee('C1X4CRQ000007', false);
        $response->assertSee('Internet mati total.', false);
        $response->assertSee('Redaman -27 dBm.', false);
        $response->assertSee($ticket->ticket_number, false);
    }

    /**
     * FopTask yang gak nyambung ke ticket sama sekali (dibuat manual langsung
     * dari /fop-tasks) tetap render normal tanpa error — relasi `ticket` yang
     * baru di-eager-load harus null-safe.
     */
    public function test_fop_tasks_index_still_works_for_tasks_without_linked_ticket(): void
    {
        $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'O-REQ',
            'task_date' => now()->format('Y-m-d').' 08:00:00',
            'tugas' => 'Task Kantor Manual',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Router mati',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => [$this->makeUserWithAllPopScope('teknisi')->id],
        ])->assertRedirect();

        $this->actingAs($this->fopUser)
            ->get(route('fop-tasks.index'))
            ->assertOk()
            ->assertSee('Task Kantor Manual');
    }
}
