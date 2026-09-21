<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `auto_generate_serial` di Master Barang — sub-konfigurasi SERIALIZED buat
 * barang tanpa SN vendor (ODP, Splitter). Lihat
 * docs/plan/warehouse/analisa-generate-id-barang-non-serial.md.
 */
class MasterItemAutoGenerateSerialTest extends TestCase
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
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);
    }

    #[Test]
    public function tambah_barang_serialized_dengan_auto_generate_serial_tersimpan(): void
    {
        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();

        $response = $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'MIA-ODP-1',
            'name' => 'ODP Master Item Test',
            'item_category_id' => $catAktif->id,
            'unit' => 'pcs',
            'is_active' => 1,
            'tracking_type' => 'serialized',
            'ownership_mode' => 'installable',
            'auto_generate_serial' => 1,
        ]);

        $response->assertRedirect(route('master.items.index'));
        $item = Item::where('code', 'MIA-ODP-1')->firstOrFail();
        $this->assertTrue($item->auto_generate_serial);
    }

    #[Test]
    public function tambah_barang_serialized_tanpa_auto_generate_serial_default_manual(): void
    {
        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();

        $response = $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'MIA-MODEM-1',
            'name' => 'Modem Master Item Test',
            'item_category_id' => $catAktif->id,
            'unit' => 'unit',
            'is_active' => 1,
            'tracking_type' => 'serialized',
            'ownership_mode' => 'installable',
            'auto_generate_serial' => 0,
        ]);

        $response->assertRedirect(route('master.items.index'));
        $item = Item::where('code', 'MIA-MODEM-1')->firstOrFail();
        $this->assertFalse($item->auto_generate_serial);
    }

    #[Test]
    public function barang_non_serialized_gak_boleh_isi_auto_generate_serial(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        $response = $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'MIA-QTY-1',
            'name' => 'RJ45 Master Item Test',
            'item_category_id' => $catKabel->id,
            'unit' => 'pcs',
            'is_active' => 1,
            'tracking_type' => 'quantity',
            'auto_generate_serial' => 1,
        ]);

        $response->assertSessionHasErrors('auto_generate_serial');
        $this->assertEquals(0, Item::where('code', 'MIA-QTY-1')->count());
    }

    #[Test]
    public function edit_barang_serialized_yang_sudah_locked_gak_bisa_ganti_sumber_sn(): void
    {
        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $item = Item::create([
            'code' => 'MIA-LOCKED-1', 'name' => 'ODP Locked Test', 'item_category_id' => $catAktif->id,
            'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable', 'auto_generate_serial' => true,
        ]);

        InventoryTransaction::create(['type' => 'receive', 'item_id' => $item->id, 'qty' => 1]);

        $response = $this->actingAs($this->owner)->put(route('master.items.update', $item), [
            'code' => $item->code,
            'name' => $item->name,
            'item_category_id' => $catAktif->id,
            'unit' => 'pcs',
            'is_active' => 1,
            'auto_generate_serial' => 0,
        ]);

        $response->assertRedirect(route('master.items.index'));
        $item->refresh();
        $this->assertTrue($item->auto_generate_serial, 'locked — kiriman auto_generate_serial=0 wajib diabaikan');
    }

    #[Test]
    public function tambah_dan_edit_barang_quantity_sukses_tanpa_error_prohibited(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        // 1. Simpan barang baru tipe quantity (field serialized dan roll disabled di form)
        $response = $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'MIA-QTY-FORM',
            'name' => 'Konektor RJ45 Form Test',
            'item_category_id' => $catKabel->id,
            'unit' => 'pcs',
            'is_active' => 1,
            'tracking_type' => 'quantity',
        ]);

        $response->assertRedirect(route('master.items.index'));
        $item = Item::where('code', 'MIA-QTY-FORM')->firstOrFail();
        $this->assertFalse($item->auto_generate_serial);
        $this->assertNull($item->meter_per_roll);

        // 2. Edit barang quantity yang belum locked
        $updateResponse = $this->actingAs($this->owner)->put(route('master.items.update', $item), [
            'code' => 'MIA-QTY-FORM',
            'name' => 'Konektor RJ45 Form Test Edited',
            'item_category_id' => $catKabel->id,
            'unit' => 'pack',
            'is_active' => 1,
            'tracking_type' => 'quantity',
        ]);

        $updateResponse->assertRedirect(route('master.items.index'));
        $item->refresh();
        $this->assertEquals('Konektor RJ45 Form Test Edited', $item->name);
        $this->assertEquals('pack', $item->unit);
    }
}
