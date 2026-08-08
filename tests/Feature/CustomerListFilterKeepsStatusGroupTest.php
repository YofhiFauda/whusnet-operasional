<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi: pencarian/filter di dalam daftar "Pelanggan Gagal"/"Putus" tidak boleh
 * melempar balik ke List Pelanggan (default active+suspended).
 *
 * Form filter di customers/index.blade.php submit GET ke url()->current() tapi dulu
 * tidak membawa `status_group` (maupun `status`), jadi begitu "Cari" ditekan dari
 * grup terminated/failed, controller kehilangan konteks grup dan jatuh ke tampilan
 * default — pencarian di grup itu jadi mustahil. Fix menambahkan hidden input yang
 * mempertahankan konteks.
 *
 * List Pelanggan Putus kini punya route + permission sendiri
 * (customers.terminated / customers.terminated.view) — lihat
 * CustomerTerminatedController.
 */
class CustomerListFilterKeepsStatusGroupTest extends TestCase
{
    use RefreshDatabase;

    private function seedPop(): Pop
    {
        return Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
    }

    public function test_filter_form_preserves_status_group_as_hidden_input(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $this->seedPop();

        // List Pelanggan Putus sekarang route sendiri (customers.terminated) —
        // /customers?status_group=terminated di-redirect ke sini oleh
        // CustomerController::index() (lihat catatan di controllernya).
        $response = $this->get(route('customers.terminated'));

        $response->assertStatus(200);
        // Form harus membawa status_group terminated agar submit tetap di grup ini.
        $response->assertSee('name="status_group" value="terminated"', false);
    }

    public function test_search_within_terminated_group_stays_scoped(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $pop = $this->seedPop();

        $putus = Customer::factory()->create([
            'full_name' => 'Pelanggan Putus Zulkarnain',
            'status' => 'terminated',
            'pop_id' => $pop->id,
        ]);
        $aktif = Customer::factory()->create([
            'full_name' => 'Pelanggan Aktif Zulkarnain',
            'status' => 'active',
            'pop_id' => $pop->id,
        ]);

        // Cari "Zulkarnain" DI DALAM grup terminated — hanya yang terminated muncul,
        // pelanggan aktif dengan nama mirip tidak boleh ikut (bukti tidak jatuh ke
        // tampilan default).
        $response = $this->get(route('customers.terminated', ['search' => 'Zulkarnain']));

        $response->assertStatus(200);
        $response->assertSee($putus->full_name);
        $response->assertDontSee($aktif->full_name);
    }
}
