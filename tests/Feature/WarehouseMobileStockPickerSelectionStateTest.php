<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi keluhan lapangan 2026-11: di mobile, chip SN di drawer "Rincian
 * Pengiriman" langsung menutup drawer dan tidak punya tampilan terpilih —
 * user tidak tahu barang sudah dipilih. Halaman juga merender sidebar
 * desktop penuh di bawah form (ringkasan & tombol Kirim dobel).
 */
class WarehouseMobileStockPickerSelectionStateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);

        $this->owner = User::factory()->create([
            'role_id' => Role::where('code', 'owner')->firstOrFail()->id,
        ]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            'transfer antar gudang' => ['warehouse.transfers.create', 'transfer-form'],
            'serah terima teknisi' => ['warehouse.issues.create', 'issue-form'],
        ];
    }

    #[Test]
    #[DataProvider('pages')]
    public function chip_stok_bisa_ditoggle_dan_menampilkan_state_terpilih(string $routeName, string $formId): void
    {
        $response = $this->actingAs($this->owner)->get(route($routeName));

        $response->assertOk();
        // Chip memanggil toggle + punya aria-pressed (state terpilih), bukan
        // lagi dispatch satu arah.
        $response->assertSee('@click="toggleSerial(item.item_id, sn)"', false);
        $response->assertSee(':aria-pressed="isSerialPicked(item.item_id, sn)"', false);
        $response->assertSee('@click="toggleRoll(item.item_id, roll.roll_code)"', false);
        $response->assertSee('@click="toggleLot(item.item_id, lot)"', false);
        $response->assertSee("pickedCountFor(item) + ' dipilih'", false);
        $response->assertSee('function warehouseStockPickerMixin()', false);
        $response->assertSee('}, warehouseStockPickerMixin());', false);
    }

    #[Test]
    #[DataProvider('pages')]
    public function drawer_mobile_tidak_menutup_saat_chip_ditap_dan_punya_tab_terpilih(string $routeName, string $formId): void
    {
        $response = $this->actingAs($this->owner)->get(route($routeName));

        $response->assertOk();
        $response->assertDontSee('serialNumber: sn }); openMobileSheet = false', false);
        $response->assertSee("sheetTab = 'picked'", false);
        $response->assertSee('@open-stock-sheet.window', false);
        $response->assertSee("\$dispatch('open-stock-sheet', { tab: 'stock' })", false);
        // List stok tidak lagi dibatasi max-h-52 di dalam drawer yang juga scroll.
        $response->assertDontSee('max-h-52', false);
        $response->assertSee('form="'.$formId.'"', false);
    }

    /**
     * Stok gudang besar (puluhan jenis × ratusan SN) tidak boleh dirender
     * sebagai satu dinding chip: kartu dilipat (accordion, isi via x-if),
     * chip dibatasi + "Tampilkan semua", ada filter kategori.
     */
    #[Test]
    #[DataProvider('pages')]
    public function stok_banyak_dilipat_dibatasi_dan_bisa_difilter_kategori(string $routeName, string $formId): void
    {
        $response = $this->actingAs($this->owner)->get(route($routeName));

        $response->assertOk();
        $response->assertSee('<template x-if="isPickerItemOpen(item, pickerOpen)">', false);
        $response->assertSee("limitChips(item, 'serial', pickerShowAll[item.item_id], chipLimit)", false);
        $response->assertSee("limitChips(item, 'roll', pickerShowAll[item.item_id], chipLimit)", false);
        $response->assertSee('hiddenChipCount(item, chipLimit)', false);
        $response->assertSee('x-for="cat in stockCategories()"', false);
        $response->assertSee('x-for="item in pickerItemsFor(pickerCategory)"', false);
        $response->assertSee('chipLimit: 12', false);
    }

    /**
     * Laptop/desktop: pemilih stok bukan lagi panel sempit di sidebar 4/12,
     * tapi dialog lebar dua panel (stok | terpilih). Sidebar & baris form
     * menampilkan hasil pilihan sebagai chip yang bisa dilepas.
     */
    #[Test]
    #[DataProvider('pages')]
    public function desktop_memakai_dialog_lebar_dan_menampilkan_barang_terpilih(string $routeName, string $formId): void
    {
        $response = $this->actingAs($this->owner)->get(route($routeName));
        $html = $response->getContent();

        $response->assertOk();
        // Dialog lebar dua panel di ≥ lg.
        $response->assertSee('lg:max-w-6xl', false);
        $response->assertSee('lg:h-[85vh]', false);
        $response->assertSee('lg:grid-cols-[minmax(0,1fr)_360px]', false);
        // Sidebar: daftar terpilih + tombol buka pemilih; tidak ada lagi
        // picker chip sempit di sidebar.
        $response->assertSee('Pilih Barang dari Stok', false);
        $this->assertSame(1, substr_count($html, 'x-for="item in pickerItemsFor(pickerCategory)"'));
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'x-for="(picked, i) in pickedRows()"'));
        // Tombol "Pilih dari Stok" di form tampil di semua ukuran layar.
        $this->assertStringNotContainsString('class="lg:hidden inline-flex items-center gap-1.5 px-3 min-h-[36px]', $html);
        // Baris form: SN & roll terpilih tampil sebagai chip yang bisa dilepas.
        $response->assertSee("@click=\"unpickCode(row.item_id, 'serial_numbers', code)\"", false);
        $response->assertSee("@click=\"unpickCode(row.item_id, 'roll_codes', code)\"", false);
    }

    #[Test]
    #[DataProvider('pages')]
    public function sidebar_desktop_tidak_dirender_di_mobile(string $routeName, string $formId): void
    {
        $response = $this->actingAs($this->owner)->get(route($routeName));

        $response->assertOk();
        $response->assertSee('hidden lg:block lg:col-span-4', false);
        $response->assertSee('hidden lg:flex items-center gap-2', false);
    }
}
