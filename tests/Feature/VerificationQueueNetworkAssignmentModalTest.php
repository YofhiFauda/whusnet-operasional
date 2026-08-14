<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mini POP & Distribusi ditentukan justru saat pemasangan berjalan, tapi
 * modal "Atur Jaringan & Mini POP" cuma ada di List Pelanggan — admin harus
 * pindah halaman di tengah proses verifikasi. Antrean Verifikasi & Pemasangan
 * kini memakai modal yang SAMA (partial bersama, bukan salinan), dipicu dari
 * ID pelanggan persis seperti kolom CID di List Pelanggan — bukan tombol aksi
 * tambahan di kolom Aksi.
 *
 * Pemicunya tunduk pada permission yang sama dengan jalur lama
 * (customers.detail.installation.validate); kalau tidak, halaman antrean jadi
 * pintu belakang ke penugasan jaringan.
 */
class VerificationQueueNetworkAssignmentModalTest extends TestCase
{
    use RefreshDatabase;

    private function seedPop(): Pop
    {
        return Pop::firstOrCreate(['pop_code' => 'VQ1'], [
            'code' => 'VQ1',
            'name' => 'POP Antrean',
            'type' => 'cabang',
            'status' => 'active',
            'registration_prefix' => 'RQ',
            'cid_prefix' => 'C',
        ]);
    }

    private function seedCustomerDalamAntrean(Pop $pop): Customer
    {
        return Customer::create([
            'customer_code' => 'CVQ-0001',
            'full_name' => 'Pelanggan Antrean',
            'primary_phone' => '081234567800',
            'registration_date' => '2026-08-01',
            'status' => 'installation_in_progress',
            'pop_id' => $pop->id,
            'address' => 'Jl. Antrean No. 1',
        ]);
    }

    public function test_id_pelanggan_di_antrean_memicu_modal_atur_jaringan_untuk_user_berhak(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $customer = $this->seedCustomerDalamAntrean($this->seedPop());

        $response = $this->get(route('verifications.queue'));

        $response->assertStatus(200);
        $response->assertSee('Atur Jaringan & Mini POP', false);
        $response->assertSee('id="network-modal-wrapper"', false);
        // Pemicunya ID pelanggan, bukan tombol terpisah di kolom Aksi.
        $response->assertSee('Klik untuk Atur Jaringan & Mini POP', false);
        $response->assertSee($customer->display_id, false);
        // URL dirender server-side, bukan dirakit di klien (ADHOC-20).
        $response->assertSee(route('customers.network-assignment.update', $customer), false);
        $response->assertSee(route('customers.network-assignment.data', $customer), false);
    }

    public function test_id_pelanggan_tidak_bisa_diklik_tanpa_permission_validate(): void
    {
        $this->seed(DatabaseSeeder::class);
        $pop = $this->seedPop();
        $customer = $this->seedCustomerDalamAntrean($pop);

        // Boleh lihat antrean pemasangan, TIDAK boleh memvalidasi instalasi.
        $role = Role::create(['name' => 'Pemantau Antrean', 'code' => 'pemantau-antrean-'.uniqid(), 'is_system' => false]);
        $role->permissions()->attach(Permission::where('code', 'customers.detail.installation.view')->firstOrFail());
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP,
        ]);

        $response = $this->actingAs($user)->get(route('verifications.queue'));

        $response->assertStatus(200);
        // Barisnya memang tampil — kalau tidak, assertDontSee di bawah lolos palsu.
        $response->assertSee($customer->display_id, false);
        $response->assertDontSee(route('customers.network-assignment.data', $customer), false);
        $response->assertDontSee('Klik untuk Atur Jaringan & Mini POP', false);
    }
}
