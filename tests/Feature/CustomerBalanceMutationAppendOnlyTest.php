<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerBalanceMutation;
use App\Models\Pop;
use App\Services\CustomerBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * ADHOC-92 (G8) — `customer_balance_mutations` itu ledger append-only. Baris
 * yang sudah tercatat tidak boleh diubah/dihapus lewat jalur Eloquent biasa,
 * termasuk oleh owner.
 */
class CustomerBalanceMutationAppendOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_baris_ledger_ditolak(): void
    {
        $mutation = $this->makeMutation();

        $this->expectException(LogicException::class);
        $mutation->update(['note' => 'Diubah paksa']);
    }

    public function test_delete_baris_ledger_ditolak(): void
    {
        $mutation = $this->makeMutation();

        $this->expectException(LogicException::class);
        $mutation->delete();
    }

    private function makeMutation(): CustomerBalanceMutation
    {
        $this->seed(DatabaseSeeder::class);

        $pop = Pop::create([
            'code' => 'POP-LEDGER-1',
            'pop_code' => 'LDG1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Ledger Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-LDG-0001',
            'full_name' => 'Customer Ledger Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'address' => 'Jl. Ledger Test',
        ]);

        return app(CustomerBalanceService::class)->creditWithoutPayment(
            $customer, 100000, $pop->id, 'Test append-only'
        );
    }
}
