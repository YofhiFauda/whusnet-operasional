<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tiga guard wajib pada `customers.collector_id` (docs/plan/analisa-billing-
 * tagihan-pembayaran-kolektor.md §B-3 "Validasi wajib pada collector_id",
 * §B-7 no. 6), lewat hub /collectors/{collector} (CollectorController):
 *   1. Target wajib ber-role `kolektor` — sekarang manifestasinya 404,
 *      karena kolektor sudah fixed dari route param, bukan dipilih dari
 *      dropdown di tengah proses seperti versi lama.
 *   2. POP pelanggan wajib masuk scope kolektor.
 *   3. Kolektor yang masih pegang pelanggan tak boleh dinonaktifkan
 *      (diuji terpisah — lihat test di bawah yang menyentuh UserController).
 */
class CollectorAssignmentGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    private function createAdmin(Pop $pop): User
    {
        $role = Role::where('name', 'POP Admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->pops()->attach($pop->id);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    private function createKolektor(Pop $pop): User
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C'.substr($code, -1),
            'cid_prefix' => 'D'.substr($code, -1),
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createCustomer(Pop $pop, string $code): Customer
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
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

    public function test_guard1_rejects_assigning_to_non_kolektor_user(): void
    {
        $pop = $this->createPop('G1A');
        $admin = $this->createAdmin($pop);
        $customer = $this->createCustomer($pop, 'C-G1-001');

        $notAKolektor = User::factory()->create([
            'role_id' => Role::where('name', 'Teknisi')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('collectors.assign', $notAKolektor->id), [
            'customer_ids' => [$customer->id],
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'collector_id' => null]);
    }

    public function test_guard2_rejects_assigning_customer_outside_collector_pop_scope(): void
    {
        $popA = $this->createPop('G2A');
        $popB = $this->createPop('G2B');
        $admin = $this->createAdmin($popA);
        // Admin di sini sengaja diberi akses ke kedua POP biar bisa memilih
        // pelanggan popB juga — fokus tes ini guard scope KOLEKTOR, bukan admin.
        $admin->pops()->attach($popB->id);
        UserRoleScopeTarget::create([
            'user_role_scope_id' => $admin->roleScopes()->first()->id,
            'pop_id' => $popB->id,
        ]);

        $kolektorPopA = $this->createKolektor($popA);
        $customerInPopB = $this->createCustomer($popB, 'C-G2-001');

        $response = $this->actingAs($admin)->post(route('collectors.assign', $kolektorPopA->id), [
            'customer_ids' => [$customerInPopB->id],
        ]);

        $response->assertRedirect(route('collectors.show', ['collector' => $kolektorPopA->id, 'tab' => 'assign']));
        $response->assertSessionHasErrors('customer_ids');
        $this->assertDatabaseHas('customers', ['id' => $customerInPopB->id, 'collector_id' => null]);
    }

    public function test_valid_assignment_succeeds_and_writes_audit_log(): void
    {
        $pop = $this->createPop('G3A');
        $admin = $this->createAdmin($pop);
        $kolektor = $this->createKolektor($pop);
        $customer = $this->createCustomer($pop, 'C-G3-001');

        $response = $this->actingAs($admin)->post(route('collectors.assign', $kolektor->id), [
            'customer_ids' => [$customer->id],
        ]);

        $response->assertRedirect(route('collectors.show', ['collector' => $kolektor->id, 'tab' => 'assign']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'collector_id' => $kolektor->id]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'action' => 'update',
        ]);
    }

    public function test_reassign_and_release_both_work(): void
    {
        $pop = $this->createPop('G4A');
        $admin = $this->createAdmin($pop);
        $kolektorA = $this->createKolektor($pop);
        $kolektorB = $this->createKolektor($pop);
        $customer = $this->createCustomer($pop, 'C-G4-001');
        $customer->update(['collector_id' => $kolektorA->id]);

        // Reassign ke kolektor lain — cukup assign ke kolektor baru, timpa yang lama.
        $this->actingAs($admin)->post(route('collectors.assign', $kolektorB->id), [
            'customer_ids' => [$customer->id],
        ]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'collector_id' => $kolektorB->id]);

        // Lepas — endpoint terpisah, per pelanggan.
        $this->actingAs($admin)->post(route('collectors.release', ['collector' => $kolektorB->id, 'customer' => $customer->id]));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'collector_id' => null]);
    }

    public function test_release_rejects_customer_not_belonging_to_this_collector(): void
    {
        $pop = $this->createPop('G6A');
        $admin = $this->createAdmin($pop);
        $kolektorA = $this->createKolektor($pop);
        $kolektorB = $this->createKolektor($pop);
        $customer = $this->createCustomer($pop, 'C-G6-001');
        $customer->update(['collector_id' => $kolektorA->id]);

        // Coba lepas lewat kolektor B, padahal pelanggan itu punya A.
        $response = $this->actingAs($admin)->post(route('collectors.release', ['collector' => $kolektorB->id, 'customer' => $customer->id]));

        $response->assertRedirect(route('collectors.show', ['collector' => $kolektorB->id, 'tab' => 'assign']));
        $response->assertSessionHasErrors('customer_ids');
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'collector_id' => $kolektorA->id]);
    }

    public function test_guard3_blocks_deactivating_kolektor_with_assigned_customers(): void
    {
        $pop = $this->createPop('G5A');
        $owner = User::where('role_id', Role::where('code', 'owner')->value('id'))->first()
            ?? User::factory()->create(['role_id' => Role::where('code', 'owner')->value('id'), 'status' => 'active']);
        $kolektor = $this->createKolektor($pop);
        $customer = $this->createCustomer($pop, 'C-G5-001');
        $customer->update(['collector_id' => $kolektor->id]);

        $response = $this->actingAs($owner)->put(route('users.update', $kolektor->id), [
            'name' => $kolektor->name,
            'email' => $kolektor->email,
            'status' => 'inactive',
            'role_id' => $kolektor->role_id,
            'scope_type' => 'all_pop',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('users', ['id' => $kolektor->id, 'status' => 'active']);
    }
}
