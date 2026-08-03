<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checkbox pemilihan baris di List Pelanggan dihapus (bulk action-nya cuma toast
 * placeholder tanpa backend). Navigasi keyboard dulu mendeteksi baris lewat
 * checkbox .select-customer, jadi menghapus checkbox tanpa mengganti penandanya
 * akan mematikan shortcut ↑/↓/Home/End/Enter tanpa error yang kelihatan.
 *
 * Penanda barisnya sekarang [data-customer-row].
 */
class CustomerListRowShortcutTest extends TestCase
{
    use RefreshDatabase;

    private function seedPop(): Pop
    {
        return Pop::firstOrCreate(['pop_code' => 'SC1'], [
            'code' => 'POP-SC-1',
            'name' => 'POP Shortcut',
            'type' => 'cabang',
            'status' => 'active',
            'registration_prefix' => 'RQ',
            'cid_prefix' => 'C',
        ]);
    }

    public function test_data_rows_carry_keyboard_navigation_marker(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $pop = $this->seedPop();

        Customer::factory()->create([
            'full_name' => 'Pelanggan Shortcut',
            'status' => 'active',
            'pop_id' => $pop->id,
        ]);

        $response = $this->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertSee('data-customer-row', false);
        $response->assertSee('tbody tr[data-customer-row]', false);
    }

    public function test_selection_checkboxes_and_bulk_bar_are_gone(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $pop = $this->seedPop();

        Customer::factory()->create([
            'full_name' => 'Pelanggan Tanpa Checkbox',
            'status' => 'active',
            'pop_id' => $pop->id,
        ]);

        $response = $this->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertDontSee('name="selected_customers[]"', false);
        $response->assertDontSee('id="selectAll"', false);
        $response->assertDontSee('id="bulkBar"', false);
        $response->assertDontSee('onclick="bulkCetak()"', false);
    }
}
