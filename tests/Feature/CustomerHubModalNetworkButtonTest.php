<?php

namespace Tests\Feature;

use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modal Hub Aksi Cepat di List Pelanggan kehilangan jalan masuk ke pengaturan
 * jaringan: tombol "Atur Mini POP & Distribusi" hanya ada di kolom CID tabel,
 * sementara desain acuan menyediakannya juga dari tab Teknis & Perangkat.
 *
 * Tombol wajib tunduk pada permission yang sama dengan tombol CID
 * (customers.detail.installation.validate) — kalau tidak, user tanpa hak
 * validasi instalasi dapat pintu belakang ke form penugasan jaringan.
 */
class CustomerHubModalNetworkButtonTest extends TestCase
{
    use RefreshDatabase;

    private function seedPop(): Pop
    {
        return Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
    }

    public function test_hub_modal_shows_network_assignment_button_for_permitted_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $this->seedPop();

        $response = $this->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertSee('onclick="triggerNetworkAssignmentFromHub()"', false);
        $response->assertSee('Atur Jaringan', false);
    }

    public function test_hub_modal_hides_network_assignment_button_without_permission(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seedPop();

        // Sales boleh lihat list pelanggan tapi tidak memvalidasi instalasi.
        $role = Role::where('code', 'sales')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertDontSee('onclick="triggerNetworkAssignmentFromHub()"', false);
    }
}
