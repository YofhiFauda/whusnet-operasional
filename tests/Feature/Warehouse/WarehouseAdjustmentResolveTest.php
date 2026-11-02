<?php

namespace Tests\Feature\Warehouse;

use App\Enums\RollStatus;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryReceiveService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Endpoint `resolve` Koreksi Stok — satu kolom pencarian menentukan mode form
 * (qty/SN/roll). Kebocoran lintas gudang di sini = SN gudang lain bisa ditebak,
 * jadi sebagian besar test di bawah memagari POP scope.
 */
class WarehouseAdjustmentResolveTest extends StockBoardTestCase
{
    private function resolve(User $user, int $popId, string $q)
    {
        return $this->actingAs($user)->getJson(route('warehouse.adjustments.resolve', ['pop_id' => $popId, 'q' => $q]));
    }

    #[Test]
    public function sn_di_gudang_terpilih_terselesaikan_sebagai_serial(): void
    {
        $modem = $this->buatItem('RSV-MODEM', 'Modem Resolve', 'serialized', 'unit', null, 'modem_ont');
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['RSV-SN-1'], 100000, $this->owner);

        $serial = InventorySerial::where('serial_number', 'RSV-SN-1')->firstOrFail();

        $this->resolve($this->owner, $this->pusat->id, 'RSV-SN-1')
            ->assertOk()
            ->assertJsonPath('type', 'serial')
            ->assertJsonPath('serial_number', 'RSV-SN-1')
            // URL simpan per-SN dari route() — klien tidak boleh merakit path.
            ->assertJsonPath('adjust_url', route('warehouse.adjustments.serial.store', $serial));
    }

    #[Test]
    public function sn_asal_gudang_lain_tidak_diberi_url_simpan_yang_pasti_403(): void
    {
        $modem = $this->buatItem('RSV-MODEM', 'Modem Resolve', 'serialized', 'unit', null, 'modem_ont');
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['RSV-SN-9'], 100000, $this->owner);
        // Pernah di-issue dari Cabang A, sekarang fisiknya di Pusat.
        InventorySerial::whereKey($serial->id)->update(['issued_from_pop_id' => $this->cabangA->id]);
        $userPusat = $this->userDenganScope([$this->pusat->id]);

        // serial.store mengecek scope ke issued_from_pop_id — user Pusat pasti
        // ditolak, jadi jangan tawarkan tombol simpan yang berujung 403.
        $this->resolve($userPusat, $this->pusat->id, 'RSV-SN-9')
            ->assertOk()
            ->assertJsonPath('type', 'serial')
            ->assertJsonPath('adjust_url', null);
    }

    #[Test]
    public function sn_milik_gudang_lain_tidak_terselesaikan(): void
    {
        $modem = $this->buatItem('RSV-MODEM', 'Modem Resolve', 'serialized', 'unit', null, 'modem_ont');
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['RSV-SN-1'], 100000, $this->owner);

        $this->resolve($this->owner, $this->cabangA->id, 'RSV-SN-1')->assertOk()->assertJsonPath('type', 'none');
    }

    #[Test]
    public function sn_yang_sedang_di_custody_teknisi_tidak_terselesaikan(): void
    {
        $modem = $this->buatItem('RSV-MODEM', 'Modem Resolve', 'serialized', 'unit', null, 'modem_ont');
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['RSV-SN-1'], 100000, $this->owner);
        InventorySerial::whereKey($serial->id)->update(['current_pop_id' => null]);

        $this->resolve($this->owner, $this->pusat->id, 'RSV-SN-1')->assertOk()->assertJsonPath('type', 'none');
    }

    #[Test]
    public function kode_roll_terselesaikan_sebagai_roll(): void
    {
        $kabel = $this->buatItem('RSV-ROLL', 'Kabel Roll Resolve', 'roll', 'meter', 250);
        $roll = InventoryRoll::create([
            'item_id' => $kabel->id, 'roll_code' => 'RSV-ROLL-0001', 'length_total' => 250,
            'length_remaining' => 180, 'status' => RollStatus::AVAILABLE, 'current_pop_id' => $this->pusat->id,
        ]);

        $this->resolve($this->owner, $this->pusat->id, 'RSV-ROLL-0001')
            ->assertOk()
            ->assertJsonPath('type', 'roll')
            ->assertJsonPath('roll_code', 'RSV-ROLL-0001')
            ->assertJsonPath('adjust_url', route('warehouse.adjustments.roll.store', $roll));
    }

    #[Test]
    public function nama_barang_curah_terselesaikan_sebagai_qty_beserta_lot_dan_saldo(): void
    {
        $konektor = $this->buatItem('RSV-KON', 'Konektor Resolve', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $konektor, 40, 1000, $this->owner);

        $response = $this->resolve($this->owner, $this->pusat->id, 'Konektor Resolve')
            ->assertOk()
            ->assertJsonPath('type', 'qty')
            ->assertJsonPath('item_id', $konektor->id);

        $this->assertEquals(40, $response->json('lots.0.qty'));
    }

    #[Test]
    public function nama_barang_ber_sn_memberi_petunjuk_need_unit(): void
    {
        $this->buatItem('RSV-MODEM', 'Modem Resolve', 'serialized', 'unit', null, 'modem_ont');

        $this->resolve($this->owner, $this->pusat->id, 'Modem Resolve')->assertOk()->assertJsonPath('type', 'need_unit');
    }

    #[Test]
    public function dua_barang_cocok_mengembalikan_daftar_choose(): void
    {
        $this->buatItem('RSV-KON-A', 'Konektor Resolve A', 'quantity');
        $this->buatItem('RSV-KON-B', 'Konektor Resolve B', 'quantity');

        $this->resolve($this->owner, $this->pusat->id, 'Konektor Resolve')
            ->assertOk()
            ->assertJsonPath('type', 'choose')
            ->assertJsonPath('more', false)
            ->assertJsonCount(2, 'options');
    }

    #[Test]
    public function lebih_dari_sepuluh_barang_cocok_memberi_petunjuk_lebih_spesifik(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->buatItem("RSV-BANYAK-{$i}", "Konektor Banyak {$i}", 'quantity');
        }

        $this->resolve($this->owner, $this->pusat->id, 'Konektor Banyak')
            ->assertOk()
            ->assertJsonPath('type', 'choose')
            ->assertJsonPath('more', true)
            ->assertJsonCount(10, 'options');
    }

    #[Test]
    public function karakter_persen_dan_garis_bawah_pada_pencarian_tidak_jadi_wildcard(): void
    {
        $this->buatItem('RSV-ESC-A', 'Konektor Escape A', 'quantity');
        $this->buatItem('RSV-ESC-B', 'Konektor Escape B', 'quantity');
        $this->buatItem('RSV-ESC-C', 'Konektor Escape C', 'quantity');

        // Tanpa escaping, '%'/'_' LIKE jadi wildcard dan mencocokkan SEMUA nama
        // (choose 3 barang) — harusnya gak ada satu pun nama yang memuat
        // literal "%%" atau "_", jadi hasilnya none.
        $this->resolve($this->owner, $this->pusat->id, '%%')->assertOk()->assertJsonPath('type', 'none');
        $this->resolve($this->owner, $this->pusat->id, 'A_B_C')->assertOk()->assertJsonPath('type', 'none');
    }

    #[Test]
    public function barang_kategori_non_operasional_tidak_pernah_muncul(): void
    {
        $this->buatItem('RSV-MERCH', 'Kaos Resolve', 'quantity', 'pcs', null, 'merchandise_marketing');

        $this->resolve($this->owner, $this->pusat->id, 'Kaos Resolve')->assertOk()->assertJsonPath('type', 'none');
    }

    #[Test]
    public function roll_kategori_non_operasional_tidak_pernah_muncul(): void
    {
        $merch = $this->buatItem('RSV-MERCH-ROLL', 'Merch Roll Resolve', 'roll', 'meter', 50, 'merchandise_marketing');
        InventoryRoll::create([
            'item_id' => $merch->id, 'roll_code' => 'RSV-MERCH-ROLL-0001', 'length_total' => 50,
            'length_remaining' => 50, 'status' => RollStatus::AVAILABLE, 'current_pop_id' => $this->pusat->id,
        ]);

        $this->resolve($this->owner, $this->pusat->id, 'RSV-MERCH-ROLL-0001')->assertOk()->assertJsonPath('type', 'none');
    }

    // "SN/roll dengan item induk terhapus" TIDAK ditest — `inventory_serials`/
    // `inventory_rolls.item_id` dipagari `restrictOnDelete()` (migration
    // 2026_09_02_100004), jadi item yang masih dirujuk SN/roll gak bisa
    // dihapus. Skenarionya gak bisa terjadi lewat jalur manapun di app ini.

    #[Test]
    public function parameter_q_berbentuk_array_tidak_crash(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('warehouse.adjustments.resolve', ['pop_id' => $this->pusat->id, 'q' => ['a', 'b']]))
            ->assertStatus(422);
    }

    #[Test]
    public function pop_di_luar_scope_user_ditolak_403(): void
    {
        $user = $this->userDenganScope([$this->cabangA->id]);

        $this->resolve($user, $this->pusat->id, 'apa saja')->assertForbidden();
    }

    #[Test]
    public function tanpa_permission_adjustment_create_ditolak_403(): void
    {
        $sales = User::factory()->create(['role_id' => Role::where('code', 'sales')->firstOrFail()->id]);

        $this->resolve($sales, $this->pusat->id, 'apa saja')->assertForbidden();
    }
}
