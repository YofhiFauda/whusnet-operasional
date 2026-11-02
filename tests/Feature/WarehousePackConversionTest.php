<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Enums\TrackingType;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Konversi Pack↔pcs — dipakai bersama di 3 titik scope (Receive/Transfer/
 * Issue, lihat docs/superpowers/specs/2026-11-01-pack-pcs-conversion-design.md).
 * File ini ditambah bertahap per task (Task 2 = Receive), jangan dipecah per
 * Service — satu file biar kasus "item tanpa pack_size tetap jalan normal"
 * gak diulang-ulang di tiap Service.
 */
class WarehousePackConversionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function receive_barang_berpack_size_dikonversi_ke_pcs(): void
    {
        $this->loginAsAdmin();
        $pusat = Pop::factory()->create(['type' => 'pusat']);
        // Item::factory() belum ada di repo ini — item master selalu dibuat
        // langsung lewat Item::create() di test lain, ikuti pola itu.
        $item = Item::create([
            'code' => 'PACK-TEST-01',
            'name' => 'Barang Berpack Uji',
            'unit' => 'pcs',
            'tracking_type' => TrackingType::QUANTITY,
            'pack_size' => 50,
        ]);

        $response = $this->post(route('warehouse.receive.store'), [
            'pop_id' => $pusat->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 2, 'unit_price' => 1000],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'type' => InventoryTransactionType::RECEIVE->value,
            'qty' => 100,
            'entered_pack_qty' => 2,
        ]);

        $transaction = InventoryTransaction::where('item_id', $item->id)->first();
        $this->get(route('warehouse.receive.show', $transaction->reference_number))
            ->assertSee('2 Pack (100 pcs)');
    }

    #[Test]
    public function receive_barang_tanpa_pack_size_tetap_pcs_langsung(): void
    {
        $this->loginAsAdmin();
        $pusat = Pop::factory()->create(['type' => 'pusat']);
        $item = Item::create([
            'code' => 'PACK-TEST-02',
            'name' => 'Barang Tanpa Pack Uji',
            'unit' => 'pcs',
            'tracking_type' => TrackingType::QUANTITY,
            'pack_size' => null,
        ]);

        $response = $this->post(route('warehouse.receive.store'), [
            'pop_id' => $pusat->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 73, 'unit_price' => 1000],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'qty' => 73,
            'entered_pack_qty' => null,
        ]);
    }

    #[Test]
    public function receive_barang_berpack_size_konversi_unit_price_ke_per_pcs(): void
    {
        // Harga yang diketik staf itu harga-per-Pack (dibaca dari faktur
        // supplier) — unit_price_snapshot yang tersimpan WAJIB sudah
        // per-pcs, sama seperti qty. Kalau tidak dikonversi, nilai stok
        // menggelembung sebesar pack_size kali lipat (lihat receiveRoll()
        // yang sudah menerapkan pola konversi harga yang sama untuk Roll).
        $this->loginAsAdmin();
        $pusat = Pop::factory()->create(['type' => 'pusat']);
        $item = Item::create([
            'code' => 'PACK-TEST-03',
            'name' => 'Barang Berpack Harga Uji',
            'unit' => 'pcs',
            'tracking_type' => TrackingType::QUANTITY,
            'pack_size' => 50,
        ]);

        $response = $this->post(route('warehouse.receive.store'), [
            'pop_id' => $pusat->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 2, 'unit_price' => 10000],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'type' => InventoryTransactionType::RECEIVE->value,
            'qty' => 100,
            'entered_pack_qty' => 2,
            'unit_price_snapshot' => 200,
        ]);
    }

    #[Test]
    public function issue_barang_berpack_size_dikonversi_ke_pcs(): void
    {
        $this->loginAsAdmin();
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $item = Item::create([
            'code' => 'PACK-TEST-03',
            'name' => 'Barang Berpack Uji Issue',
            'unit' => 'pcs',
            'tracking_type' => TrackingType::QUANTITY,
            'pack_size' => 50,
        ]);
        $technician = User::factory()->create();
        InventoryBalance::create(['pop_id' => $cabang->id, 'item_id' => $item->id, 'lot_no' => '', 'qty' => 200]);

        $response = $this->post(route('warehouse.issues.store'), [
            'cabang_pop_id' => $cabang->id,
            'technician_id' => $technician->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 3],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'type' => InventoryTransactionType::ISSUE->value,
            'qty' => 150,
            'entered_pack_qty' => 3,
        ]);

        $transaction = InventoryTransaction::where('item_id', $item->id)->first();
        $this->get(route('warehouse.issues.show', $transaction->reference_number))
            ->assertSee('3 Pack (150 pcs)');
    }

    #[Test]
    public function dispatch_transfer_barang_berpack_size_dikonversi_ke_pcs(): void
    {
        $this->loginAsAdmin();
        $pusat = Pop::factory()->create(['type' => 'pusat']);
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $item = Item::create([
            'code' => 'PACK-TEST-04',
            'name' => 'Barang Berpack Uji Transfer',
            'unit' => 'pcs',
            'tracking_type' => TrackingType::QUANTITY,
            'pack_size' => 50,
        ]);
        InventoryBalance::create(['pop_id' => $pusat->id, 'item_id' => $item->id, 'lot_no' => '', 'qty' => 500]);

        $response = $this->post(route('warehouse.transfers.store'), [
            'from_pop_id' => $pusat->id,
            'to_pop_id' => $cabang->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 4],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'type' => InventoryTransactionType::TRANSFER->value,
            'qty' => 200,
            'entered_pack_qty' => 4,
            'from_pop_id' => $pusat->id,
        ]);

        $transaction = InventoryTransaction::where('item_id', $item->id)->first();
        $this->get(route('warehouse.transfers.show', $transaction->inventory_transfer_id))
            ->assertSee('4 Pack (200 pcs)');
    }

    /**
     * Test ini menguji mode "Terima Semua" (default, tanpa `receive_mode`
     * di body request) — partial receive (`receive_mode=partial` +
     * `confirmed_quantities`) masih ada di controller, tidak disentuh di
     * sini. `entered_pack_qty` sisi confirm diturunkan balik dari qty pcs
     * yang dikonfirmasi ÷ pack_size — hitungan yang sama berlaku baik untuk
     * jalur "Terima Semua" maupun partial, karena keduanya sampai ke
     * Service dalam satuan pcs (lihat docs/superpowers/specs/
     * 2026-11-01-pack-pcs-conversion-design.md).
     */
    #[Test]
    public function confirm_terima_semua_transfer_barang_berpack_size_hitung_entered_pack_qty(): void
    {
        $this->loginAsAdmin();
        $pusat = Pop::factory()->create(['type' => 'pusat']);
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $item = Item::create([
            'code' => 'PACK-TEST-05',
            'name' => 'Barang Berpack Uji Confirm Transfer',
            'unit' => 'pcs',
            'tracking_type' => TrackingType::QUANTITY,
            'pack_size' => 50,
        ]);
        InventoryBalance::create(['pop_id' => $pusat->id, 'item_id' => $item->id, 'lot_no' => '', 'qty' => 500]);

        $this->post(route('warehouse.transfers.store'), [
            'from_pop_id' => $pusat->id,
            'to_pop_id' => $cabang->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 3], // 3 Pack = 150 pcs
            ],
        ]);
        $dispatchLine = InventoryTransaction::where('item_id', $item->id)->whereNotNull('from_pop_id')->first();
        $transfer = $dispatchLine->transfer;

        $response = $this->post(route('warehouse.transfers.receive', $transfer));

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'to_pop_id' => $cabang->id,
            'qty' => 150,
            'entered_pack_qty' => 3,
        ]);

        $this->get(route('warehouse.transfers.show', $transfer))
            ->assertSee('3 Pack (150 pcs)');
    }

    #[Test]
    public function riwayat_menampilkan_jumlah_pack_untuk_barang_berpack_size(): void
    {
        $this->loginAsAdmin();
        $pusat = Pop::factory()->create(['type' => 'pusat']);
        $item = Item::create([
            'code' => 'PACK-TEST-06',
            'name' => 'RJ45 Connector',
            'unit' => 'pcs',
            'tracking_type' => TrackingType::QUANTITY,
            'pack_size' => 50,
        ]);

        $this->post(route('warehouse.receive.store'), [
            'pop_id' => $pusat->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 2, 'unit_price' => 1000],
            ],
        ]);

        $this->get(route('warehouse.history.index'))
            ->assertSee('2 Pack (100 pcs)');
    }

    /**
     * Modal detail Riwayat baca data terpisah ($detailItemData, dirender ke
     * JSON lewat openDetail()), bukan markup baris list — baris list bisa
     * benar sementara modalnya masih nampilin "100 pcs" polos kalau cuma
     * baris list yang dibenahi. Cek langsung payload JSON `qty`/`unit` yang
     * dikirim ke Alpine, bukan cuma assertSee teks biasa (yang sudah pasti
     * ketemu dari baris list test di atas, gak membedakan modal).
     */
    #[Test]
    public function riwayat_modal_detail_menampilkan_jumlah_pack_untuk_barang_berpack_size(): void
    {
        $this->loginAsAdmin();
        $pusat = Pop::factory()->create(['type' => 'pusat']);
        $item = Item::create([
            'code' => 'PACK-TEST-07',
            'name' => 'RJ45 Connector Modal',
            'unit' => 'pcs',
            'tracking_type' => TrackingType::QUANTITY,
            'pack_size' => 50,
        ]);

        $this->post(route('warehouse.receive.store'), [
            'pop_id' => $pusat->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 2, 'unit_price' => 1000],
            ],
        ]);

        // $detailItemData di-json_encode() lalu dituang ke atribut @click Blade
        // ({{ }}) — otomatis di-HTML-escape, jadi tanda kutip JSON muncul
        // sebagai &quot; di markup mentah, bukan tanda kutip biasa.
        $this->get(route('warehouse.history.index'))
            ->assertSee('&quot;qty&quot;:&quot;2 Pack (100 pcs)&quot;', false)
            ->assertSee('&quot;unit&quot;:&quot;&quot;', false);
    }
}
