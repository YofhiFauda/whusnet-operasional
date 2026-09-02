<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi: List Pelanggan Gagal & List Pelanggan Putus harus dirender dari VIEW
 * SENDIRI (customers/failed.blade.php, customers/terminated.blade.php), bukan
 * cabang @if($statusGroup) di dalam customers/index.blade.php.
 *
 * Dulu ketiganya satu file 2000+ baris: mengubah kolom halaman arsip berarti
 * mengedit file yang sama dengan List Pelanggan biasa, dan `$statusGroup` jadi
 * dua sumber kebenaran (dipaksa di controller TAPI tetap dicabangkan di view).
 * Test ini yang menahan supaya tidak digabung lagi.
 */
class CustomerListSeparateViewsTest extends TestCase
{
    use RefreshDatabase;

    private function seedPop(): Pop
    {
        return Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
    }

    public function test_terminated_page_renders_its_own_view(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $this->seedPop();

        $response = $this->get(route('customers.terminated'));

        $response->assertStatus(200);
        $response->assertViewIs('customers.terminated');
        // Kolom khas halaman ini (tidak ada di List Pelanggan biasa).
        $response->assertSee('Status Alat');
        $response->assertSee('Alasan Putus');
    }

    public function test_failed_page_renders_its_own_view(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $this->seedPop();

        $response = $this->get(route('customers.failed'));

        $response->assertStatus(200);
        $response->assertViewIs('customers.failed');
        $response->assertSee('Daftar Pelanggan Gagal');
    }

    public function test_main_list_renders_index_view_without_archive_tables(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $this->seedPop();

        $response = $this->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertViewIs('customers.index');
        // Tabel arsip TIDAK boleh ikut ter-render di List Pelanggan biasa.
        $response->assertDontSee('Daftar Pelanggan Gagal');
        $response->assertDontSee('Daftar Pelanggan Putus');
        $response->assertDontSee('Status Alat');
    }

    public function test_each_archive_page_still_lists_only_its_own_status(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $pop = $this->seedPop();

        $putus = Customer::factory()->create([
            'full_name' => 'Pelanggan Putus Bagas',
            'status' => 'terminated',
            'pop_id' => $pop->id,
        ]);
        $gagal = Customer::factory()->create([
            'full_name' => 'Pelanggan Gagal Bagas',
            'status' => 'rejected',
            'pop_id' => $pop->id,
        ]);

        $terminated = $this->get(route('customers.terminated'));
        $terminated->assertSee($putus->full_name);
        $terminated->assertDontSee($gagal->full_name);

        $failed = $this->get(route('customers.failed'));
        $failed->assertSee($gagal->full_name);
        $failed->assertDontSee($putus->full_name);
    }

    /**
     * Halaman arsip TIDAK boleh bisa "dipaksa ganti grup" lewat query string —
     * grup dikunci dari controller, permission-nya beda.
     */
    public function test_status_group_query_string_cannot_override_forced_group(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $pop = $this->seedPop();

        $aktif = Customer::factory()->create([
            'full_name' => 'Pelanggan Aktif Bagas',
            'status' => 'active',
            'pop_id' => $pop->id,
        ]);

        $response = $this->get(route('customers.terminated', ['status_group' => '']));

        $response->assertStatus(200);
        $response->assertViewIs('customers.terminated');
        $response->assertDontSee($aktif->full_name);
    }
}
