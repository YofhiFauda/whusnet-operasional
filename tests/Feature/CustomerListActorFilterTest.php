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
 * List Pelanggan: kolom opsional "Pelaku Input" (siapa yang input/registrasi
 * pelanggan) + filter dropdown-nya. Nilainya registered_by_name (migrasi
 * legacy) atau nama user lewat relasi creator (created_by) — pola yang sudah
 * dipakai di List Putus & tab Riwayat Perubahan, dipasang juga di List
 * Pelanggan utama. Permintaan user 2026-11-20.
 */
class CustomerListActorFilterTest extends TestCase
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
            'code' => 'POP-CLA1',
            'pop_code' => 'CLA1',
            'registration_prefix' => 'CA',
            'cid_prefix' => 'DA',
            'name' => 'POP Actor Filter',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createCustomer(string $code, ?int $createdBy, ?string $registeredByName = null): Customer
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
            'created_by' => $createdBy,
            'registered_by_name' => $registeredByName,
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

    public function test_customer_list_shows_pelaku_input_column_and_filters_by_actor(): void
    {
        $helpdeskRole = Role::where('code', 'helpdesk')->firstOrFail();
        $helpdesk = User::factory()->create(['name' => 'Helpdesk Ani', 'role_id' => $helpdeskRole->id, 'status' => 'active']);

        $this->createCustomer('C-CLA-A1', $helpdesk->id);
        $this->createCustomer('C-CLA-L1', null, 'Rina CS Legacy');
        $this->createCustomer('C-CLA-N1', null);

        $admin = $this->loginAsAdmin();

        // Tampilkan kolom Pelaku Input (default-nya tersembunyi — opt-in lewat preferensi).
        $this->putJson(route('customers.list-columns.update'), [
            'columns' => ['cid', 'nama', 'pelaku_input'],
        ])->assertOk();

        $response = $this->actingAs($admin)->get(route('customers.index'));
        $response->assertOk();
        $response->assertSee('Helpdesk Ani');
        $response->assertSee('Rina CS Legacy');

        // Filter ke user tertentu (created_by).
        $responseActor = $this->actingAs($admin)->get(route('customers.index', ['actor_id' => $helpdesk->id]));
        $responseActor->assertSee('Pelanggan C-CLA-A1');
        $responseActor->assertDontSee('Pelanggan C-CLA-L1');
        $responseActor->assertDontSee('Pelanggan C-CLA-N1');

        // Filter "Data Migrasi Legacy".
        $responseLegacy = $this->actingAs($admin)->get(route('customers.index', ['actor_id' => 'legacy']));
        $responseLegacy->assertSee('Pelanggan C-CLA-L1');
        $responseLegacy->assertDontSee('Pelanggan C-CLA-A1');
        $responseLegacy->assertDontSee('Pelanggan C-CLA-N1');
    }
}
