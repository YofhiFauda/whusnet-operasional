<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 2026-09-07 — laporan user (screenshot Riwayat Mutasi): baris TRANSFER yang
 * masih in-transit (belum dikonfirmasi Cabang tujuan) nampilin tujuan
 * "Pelanggan / Luar" — salah total, bikin kelihatan kayak barang dikirim ke
 * pelanggan padahal cuma nunggu konfirmasi terima di gudang Cabang.
 *
 * Akar masalah: baris ledger dispatch TRANSFER `to_pop_id`-nya emang NULL
 * sampai leg confirm ditulis (dua baris independen per transfer, lihat
 * docblock migration `create_inventory_transactions_table`) — view pakai
 * fallback generik `$txn->toPop->name ?? ($txn->toTechnician->name ??
 * 'Pelanggan / Luar')` yang gak bisa bedain "emang gak ada tujuan" dari
 * "tujuan ADA tapi belum keisi di baris INI".
 */
class WarehouseHistoryTransferLabelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function baris_transfer_in_transit_nampilin_tujuan_asli_bukan_pelanggan_luar(): void
    {
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $owner = User::factory()->create(['role_id' => $ownerRole->id]);

        $pusat = Pop::create(['code' => 'HTL-PUSAT', 'pop_code' => 'HTLP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Histori Test', 'type' => 'pusat', 'status' => 'active']);
        $cabang = Pop::create(['code' => 'HTL-A', 'pop_code' => 'HTLA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Histori Test', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $modem = Item::create(['code' => 'HTL-MODEM', 'name' => 'Modem Histori Test', 'item_category_id' => $category->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);

        app(InventoryReceiveService::class)->receiveSerialized($pusat, $modem, ['HTL-SN-001'], 250000, $owner);
        // Cuma dispatch, SENGAJA gak dikonfirmasi — transfer harus tetap
        // `in_transit` biar baris ledgernya kepetik kasus dispatch ini.
        app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $modem->id, 'serial_numbers' => ['HTL-SN-001']]], $owner);

        $response = $this->actingAs($owner)->get(route('warehouse.history.index'));

        $response->assertOk()
            ->assertSee('Cabang Histori Test')
            ->assertSee('menunggu konfirmasi')
            ->assertDontSee('Pelanggan / Luar');
    }
}
