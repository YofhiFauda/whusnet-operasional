<?php

namespace Tests\Feature;

use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\Distribution;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug: daftar ticket (Ticket Masuk/Diproses/dst) nampilin CID pelanggan MENTAH
 * (mis. "RQ000007" doang, gak ada prefix POP/distribusi) padahal
 * customers.cid udah nyimpen CID lengkap yang benar (mis. "C1X4CRQ000007").
 *
 * Akar masalah: TicketController::index() eager-load customer dengan kolom
 * dibatasi ke id/full_name/cid/customer_code — TANPA pop_id/status/
 * distribution_id. Customer::getDisplayIdAttribute() butuh $this->pop buat
 * nentuin format CID (Pop::resolveDisplayId()); tanpa pop_id ke-select,
 * relasi itu selalu null, jadi accessor diam-diam jatuh ke customer_code
 * mentah alih-alih CID lengkap yang udah tersimpan.
 */
class TicketCidDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdeskUser;
    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\TicketFeatureSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $role = Role::where('code', 'helpdesk')->first();
        $this->helpdeskUser = User::factory()->create(['role_id' => $role->id]);
        $this->helpdeskUser->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => \App\Enums\ScopeType::ALL_POP->value,
        ]);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);

        $this->pop = Pop::create([
            'name' => 'POP Polorejo',
            'code' => 'POP-PLR',
            'cid_prefix' => 'C',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
            'city_id' => $city->id,
        ]);

        $this->village = $village;
    }

    private Village $village;

    private function createTicketFor(Customer $customer): Ticket
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $customer->id,
            'detail_keluhan' => 'Internet mati.',
            'priority' => 'High',
        ])->assertRedirect();

        return Ticket::latest('id')->firstOrFail();
    }

    /**
     * Inti bug: pelanggan aktif dengan distribusi & CID lengkap yang udah
     * ke-generate ("C1X4CRQ000007") HARUS tampil utuh di daftar ticket, bukan
     * cuma nomor registrasi mentahnya ("RQ000007").
     */
    public function test_ticket_list_shows_full_complex_cid_not_bare_registration_id(): void
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
        ]);

        $ticket = $this->createTicketFor($customer);

        $response = $this->actingAs($this->helpdeskUser)->get(route('tickets.bucket', 'masuk'));

        $response->assertOk();
        $response->assertSee('C1X4CRQ000007');

        // Bukan cuma "gak error" — pastiin bare code TIDAK muncul sendirian
        // sebagai representasi CID (dia boleh muncul sebagai SUBSTRING di
        // dalam CID lengkap, tapi bukan itu yang perlu dicek di sini — yang
        // penting versi lengkapnya ada).
        $this->assertSame('C1X4CRQ000007', $customer->fresh()->display_id);
    }

    /**
     * Pelanggan yang BELUM distribusi (masih tahap awal) tetap dapet format
     * default yang benar (prefix + "00" + REQ ID) — bukan bare code juga.
     */
    public function test_ticket_list_shows_default_prefixed_id_for_customer_without_distribution(): void
    {
        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
            'distribution_id' => null,
            'customer_code' => 'RQ000009',
            'cid' => null,
            'status' => 'registered',
        ]);

        $ticket = $this->createTicketFor($customer);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.bucket', 'masuk'))
            ->assertOk()
            ->assertSee('C00RQ000009');
    }

    /**
     * Regresi: halaman detail ticket (yang sebelumnya udah bener, gak
     * dibatasi kolom) tetap konsisten nampilin CID lengkap yang sama.
     */
    public function test_ticket_detail_page_also_shows_full_cid_consistently(): void
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
        ]);

        $ticket = $this->createTicketFor($customer);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('C1X4CRQ000007');
    }
}
