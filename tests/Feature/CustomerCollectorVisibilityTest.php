<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin bisa lihat pelanggan sudah punya kolektor atau belum, dan kolektor
 * mana kalau banyak — di daftar pelanggan (badge + filter) dan di detail
 * pelanggan (badge di header). Permintaan user 2026-08-03.
 */
class CustomerCollectorVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = Pop::create([
            'code' => 'POP-CCV1',
            'pop_code' => 'CCV1',
            'registration_prefix' => 'CV',
            'cid_prefix' => 'DV',
            'name' => 'POP Collector Visibility',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createCustomer(string $code, ?int $collectorId): Customer
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
            'collector_id' => $collectorId,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. '.$code,
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        return $customer;
    }

    private function owner(): User
    {
        return User::where('email', 'owner@whusnet.net')->firstOrFail();
    }

    public function test_customer_list_shows_collector_badge_and_filters_by_collector(): void
    {
        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektorA = User::factory()->create(['name' => 'Kolektor Andi', 'role_id' => $kolektorRole->id, 'status' => 'active']);
        $kolektorB = User::factory()->create(['name' => 'Kolektor Budi', 'role_id' => $kolektorRole->id, 'status' => 'active']);

        $this->createCustomer('C-CCV-A1', $kolektorA->id);
        $this->createCustomer('C-CCV-B1', $kolektorB->id);
        $this->createCustomer('C-CCV-N1', null);

        $owner = $this->owner();

        // Tanpa filter: badge kolektor A & B tampil, yang tanpa kolektor tak ada badge.
        $response = $this->actingAs($owner)->get(route('customers.index'));
        $response->assertOk();
        $response->assertSee('Kolektor: Kolektor Andi');
        $response->assertSee('Kolektor: Kolektor Budi');

        // Filter ke kolektor A saja.
        $responseA = $this->actingAs($owner)->get(route('customers.index', ['collector_id' => $kolektorA->id]));
        $responseA->assertSee('Pelanggan C-CCV-A1');
        $responseA->assertDontSee('Pelanggan C-CCV-B1');
        $responseA->assertDontSee('Pelanggan C-CCV-N1');

        // Filter "belum ada kolektor".
        $responseNone = $this->actingAs($owner)->get(route('customers.index', ['collector_id' => 'none']));
        $responseNone->assertSee('Pelanggan C-CCV-N1');
        $responseNone->assertDontSee('Pelanggan C-CCV-A1');
        $responseNone->assertDontSee('Pelanggan C-CCV-B1');
    }

    public function test_customer_detail_shows_collector_or_belum_ada_kolektor(): void
    {
        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['name' => 'Kolektor Citra', 'role_id' => $kolektorRole->id, 'status' => 'active']);

        $withCollector = $this->createCustomer('C-CCV-D1', $kolektor->id);
        $withoutCollector = $this->createCustomer('C-CCV-D2', null);

        $owner = $this->owner();

        $responseWith = $this->actingAs($owner)->get(route('customers.show', $withCollector->id));
        $responseWith->assertOk();
        $responseWith->assertSee('Kolektor: Kolektor Citra');

        $responseWithout = $this->actingAs($owner)->get(route('customers.show', $withoutCollector->id));
        $responseWithout->assertOk();
        $responseWithout->assertSee('Belum Ada Kolektor');
    }
}
