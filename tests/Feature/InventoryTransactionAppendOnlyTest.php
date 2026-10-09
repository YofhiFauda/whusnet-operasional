<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `InventoryTransactionObserver` (ADHOC-54) — ledger `inventory_transactions`
 * append-only, ditegakkan dari SEMUA jalur Eloquent (Service, artisan,
 * tinker), bukan cuma validasi UI. Lihat
 * docs/plan/warehouse/kontrol-anti-manipulasi.md §6.
 */
class InventoryTransactionAppendOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function createTransaction(): InventoryTransaction
    {
        $pop = Pop::create([
            'code' => 'POP-LEDGER',
            'pop_code' => 'LDG',
            'registration_prefix' => 'CL',
            'cid_prefix' => 'DL',
            'name' => 'Gudang Pusat Ledger Test',
            'type' => 'pusat',
            'status' => 'active',
        ]);

        $category = ItemCategory::create([
            'code' => 'ledger_test_cat',
            'name' => 'Kategori Uji Ledger',
            'default_unit' => 'pcs',
            'equipment_class' => 'pasif',
            'is_system' => false,
            'is_active' => true,
        ]);

        $item = Item::create([
            'code' => 'LEDGER-ITEM',
            'name' => 'Barang Uji Ledger',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
        ]);

        return InventoryTransaction::create([
            'type' => 'receive',
            'item_id' => $item->id,
            'qty' => 10,
            'to_pop_id' => $pop->id,
        ]);
    }

    #[Test]
    public function transaksi_bisa_dibuat_normal(): void
    {
        $transaction = $this->createTransaction();

        $this->assertDatabaseHas('inventory_transactions', [
            'id' => $transaction->id,
            'type' => 'receive',
        ]);
    }

    #[Test]
    public function baris_ledger_tidak_bisa_diupdate(): void
    {
        $transaction = $this->createTransaction();

        $this->expectException(LogicException::class);

        $transaction->qty = 999;
        $transaction->save();
    }

    #[Test]
    public function baris_ledger_tidak_bisa_dihapus(): void
    {
        $transaction = $this->createTransaction();

        $this->expectException(LogicException::class);

        $transaction->delete();
    }

    #[Test]
    public function update_gagal_tidak_mengubah_data_tersimpan(): void
    {
        $transaction = $this->createTransaction();

        try {
            $transaction->qty = 999;
            $transaction->save();
        } catch (LogicException) {
            // diharapkan — cek DB tetap utuh di bawah.
        }

        $this->assertDatabaseHas('inventory_transactions', [
            'id' => $transaction->id,
            'qty' => 10,
        ]);
    }
}
