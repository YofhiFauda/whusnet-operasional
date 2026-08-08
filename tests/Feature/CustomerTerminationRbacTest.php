<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug RBAC dilaporkan user: "Halaman Antrean - List Pelanggan Putus" (aksi
 * terminasi langganan) gak punya RBAC sama sekali — CustomerTerminationController
 * gak ada abort_unless()/hasPermission() apa pun, cuma numpang middleware
 * customers.update. Efeknya, Helpdesk & Sales (yang punya customers.update
 * buat edit data pelanggan biasa) otomatis ikut bisa putus langganan siapa
 * pun. Fix: permission baru customers.deactivate, terpisah dari customers.update.
 */
class CustomerTerminationRbacTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN-TERM',
            'pop_code' => 'TRM',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Termination Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'TRM-00001',
            'full_name' => 'Pelanggan Akan Diputus',
            'primary_phone' => '081234500001',
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'lengkap',
            'registration_date' => now(),
        ]);
    }

    private function makeUser(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->first();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    private function terminate(User $actor)
    {
        return $this->actingAs($actor)->post(route('customers.terminate', $this->customer), [
            'reason' => 'Pelanggan minta berhenti.',
        ]);
    }

    /**
     * Inti bug — sebelumnya Helpdesk lolos karena cuma dicek customers.update,
     * yang mereka punya buat edit data pelanggan biasa.
     */
    public function test_helpdesk_cannot_terminate_customer(): void
    {
        $this->terminate($this->makeUser('helpdesk'))->assertForbidden();

        $this->assertSame('active', $this->customer->fresh()->status);
    }

    public function test_sales_cannot_terminate_customer(): void
    {
        $this->terminate($this->makeUser('sales'))->assertForbidden();

        $this->assertSame('active', $this->customer->fresh()->status);
    }

    public function test_admin_can_terminate_customer(): void
    {
        $this->terminate($this->makeUser('admin'))->assertRedirect();

        $this->assertSame('terminated', $this->customer->fresh()->status);
    }

    public function test_pop_admin_can_terminate_customer(): void
    {
        $this->terminate($this->makeUser('pop_admin'))->assertRedirect();

        $this->assertSame('terminated', $this->customer->fresh()->status);
    }

    public function test_owner_can_terminate_customer(): void
    {
        $owner = $this->loginAsAdmin();

        $this->terminate($owner)->assertRedirect();

        $this->assertSame('terminated', $this->customer->fresh()->status);
    }

    public function test_noc_cannot_terminate_customer(): void
    {
        $this->terminate($this->makeUser('noc'))->assertForbidden();
    }

    public function test_teknisi_cannot_terminate_customer(): void
    {
        $this->terminate($this->makeUser('teknisi'))->assertForbidden();
    }
}
