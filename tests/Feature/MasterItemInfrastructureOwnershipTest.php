<?php

namespace Tests\Feature;

use App\Enums\OwnershipMode;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNodeType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\ItemSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gejala yang dijaga: barang QUANTITY/ROLL gak pernah bisa disimpan sebagai
 * Aset Infrastruktur — `ItemController::validateItem()` memaksa semua
 * non-SERIALIZED jadi INSTALLABLE dan select Kepemilikan di form disabled
 * total, jadi jalur InventoryService::deployQuantity()/deployRoll() gak
 * pernah kejangkau. Aset Perusahaan tetap SERIALIZED-only.
 */
class MasterItemInfrastructureOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private ItemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);

        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
    }

    #[Test]
    public function barang_quantity_bisa_disimpan_sebagai_aset_infrastruktur(): void
    {
        $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'INF-QTY-1', 'name' => 'Klem Tiang', 'item_category_id' => $this->category->id,
            'unit' => 'pcs', 'is_active' => 1, 'tracking_type' => 'quantity',
            'ownership_mode' => 'infrastructure_asset',
        ])->assertRedirect(route('master.items.index'));

        $this->assertSame(OwnershipMode::INFRASTRUCTURE_ASSET, Item::where('code', 'INF-QTY-1')->firstOrFail()->ownership_mode);
    }

    #[Test]
    public function barang_roll_bisa_disimpan_sebagai_aset_infrastruktur(): void
    {
        $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'INF-ROLL-1', 'name' => 'Kabel Feeder 24C', 'item_category_id' => $this->category->id,
            'unit' => 'meter', 'is_active' => 1, 'tracking_type' => 'roll', 'meter_per_roll' => 1000,
            'ownership_mode' => 'infrastructure_asset',
        ])->assertRedirect(route('master.items.index'));

        $this->assertSame(OwnershipMode::INFRASTRUCTURE_ASSET, Item::where('code', 'INF-ROLL-1')->firstOrFail()->ownership_mode);
    }

    #[Test]
    public function barang_quantity_aset_perusahaan_diturunkan_ke_installable(): void
    {
        $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'INF-QTY-2', 'name' => 'Konektor', 'item_category_id' => $this->category->id,
            'unit' => 'pcs', 'is_active' => 1, 'tracking_type' => 'quantity',
            'ownership_mode' => 'company_asset',
        ])->assertRedirect(route('master.items.index'));

        $this->assertSame(OwnershipMode::INSTALLABLE, Item::where('code', 'INF-QTY-2')->firstOrFail()->ownership_mode);
    }

    #[Test]
    public function barang_quantity_tanpa_ownership_default_installable(): void
    {
        $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'INF-QTY-3', 'name' => 'Konektor 2', 'item_category_id' => $this->category->id,
            'unit' => 'pcs', 'is_active' => 1, 'tracking_type' => 'quantity',
        ])->assertRedirect(route('master.items.index'));

        $this->assertSame(OwnershipMode::INSTALLABLE, Item::where('code', 'INF-QTY-3')->firstOrFail()->ownership_mode);
    }

    #[Test]
    public function edit_barang_quantity_belum_locked_bisa_dijadikan_infrastruktur(): void
    {
        $item = Item::create([
            'code' => 'INF-QTY-4', 'name' => 'Klem', 'item_category_id' => $this->category->id,
            'unit' => 'pcs', 'tracking_type' => 'quantity', 'ownership_mode' => 'installable',
        ]);

        $this->actingAs($this->owner)->put(route('master.items.update', $item), [
            'code' => 'INF-QTY-4', 'name' => 'Klem', 'item_category_id' => $this->category->id,
            'unit' => 'pcs', 'is_active' => 1, 'tracking_type' => 'quantity',
            'ownership_mode' => 'infrastructure_asset',
        ])->assertRedirect(route('master.items.index'));

        $this->assertSame(OwnershipMode::INFRASTRUCTURE_ASSET, $item->refresh()->ownership_mode);
    }

    #[Test]
    public function form_tambah_tidak_mendisable_seluruh_select_kepemilikan(): void
    {
        $html = $this->actingAs($this->owner)->get(route('master.items.create'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<select name="ownership_mode"[^>]*:disabled/', $html);
        $this->assertMatchesRegularExpression('/<option value="company_asset"[^>]*:disabled=/', $html);
        $this->assertMatchesRegularExpression('/<option value="infrastructure_asset"[^>]*:disabled=/', $html);
        $this->assertStringContainsString('categoryId === modemCategoryId', $html);
        $this->assertMatchesRegularExpression('/<select name="infrastructure_node_type_id"[^>]*:disabled="categoryId === modemCategoryId"/', $html);
        $this->assertStringContainsString('Penjelasan Tipe Node Tujuan', $html);
        $this->assertStringContainsString('bukan lokasi tertentu', $html);
    }

    #[Test]
    public function modem_ont_harus_dipasang_ke_pelanggan_saat_dibuat(): void
    {
        $modemCategory = ItemCategory::where('code', 'modem_ont')->firstOrFail();
        $data = [
            'code' => 'ONT-PELANGGAN-01', 'name' => 'ONT Pelanggan',
            'item_category_id' => $modemCategory->id, 'unit' => 'pcs',
            'is_active' => 1, 'tracking_type' => 'serialized',
            'auto_generate_serial' => 0,
        ];

        $this->actingAs($this->owner)->post(route('master.items.store'), [
            ...$data, 'ownership_mode' => 'infrastructure_asset',
        ])->assertSessionHasErrors('ownership_mode');
        $this->assertDatabaseMissing('items', ['code' => 'ONT-PELANGGAN-01']);

        $this->actingAs($this->owner)->post(route('master.items.store'), [
            ...$data, 'ownership_mode' => 'installable',
        ])->assertRedirect(route('master.items.index'));
        $this->assertSame(OwnershipMode::INSTALLABLE, Item::where('code', 'ONT-PELANGGAN-01')->firstOrFail()->ownership_mode);
    }

    #[Test]
    public function barang_infrastruktur_tidak_bisa_dipindah_ke_kategori_modem_ont(): void
    {
        $item = Item::create([
            'code' => 'INF-KE-ONT', 'name' => 'Aset Infrastruktur',
            'item_category_id' => $this->category->id, 'unit' => 'pcs',
            'tracking_type' => 'serialized', 'ownership_mode' => 'infrastructure_asset',
        ]);
        $modemCategory = ItemCategory::where('code', 'modem_ont')->firstOrFail();

        $this->actingAs($this->owner)->put(route('master.items.update', $item), [
            'code' => $item->code, 'name' => $item->name,
            'item_category_id' => $modemCategory->id, 'unit' => 'pcs',
            'is_active' => 1, 'tracking_type' => 'serialized',
            'auto_generate_serial' => 0, 'ownership_mode' => 'infrastructure_asset',
        ])->assertSessionHasErrors('ownership_mode');

        $this->assertSame($this->category->id, $item->refresh()->item_category_id);
    }

    #[Test]
    public function katalog_olt_seeder_dapat_dideploy_sebagai_aset_infrastruktur(): void
    {
        $this->seed(ItemSeeder::class);

        foreach (['OLT-ZTE-C320', 'OLT-MODULE-GPON-8P'] as $code) {
            $item = Item::where('code', $code)->firstOrFail();
            $this->assertSame(OwnershipMode::INFRASTRUCTURE_ASSET, $item->ownership_mode);
        }
        $this->assertSame('olt_chassis', Item::where('code', 'OLT-ZTE-C320')->first()->infrastructureNodeType?->code);
        // 'olt_card' dihapus dari seeder master 2026-10-23 (ADHOC-212) —
        // kartu OLT sekarang aset yang di-deploy ke Slot, tidak baca kolom
        // ini. Null di sini sengaja, bukan regresi.
        $this->assertNull(Item::where('code', 'OLT-MODULE-GPON-8P')->first()->infrastructure_node_type_id);
    }

    #[Test]
    public function master_barang_dapat_memetakan_sku_ke_tipe_node(): void
    {
        // 'olt_card' dihapus dari seeder master 2026-10-23 (ADHOC-212) —
        // subjek tes ini mekanisme generik "map SKU ke tipe node", bukan
        // 'olt_card' spesifik. 'olt_chassis' kompatibel kategori olt_module
        // juga (InventoryService::assertNodeAcceptsSerialItem()).
        $type = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
        $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'OLT-ADMIN-CARD', 'name' => 'Kartu OLT Admin',
            'item_category_id' => ItemCategory::where('code', 'olt_module')->firstOrFail()->id,
            'unit' => 'pcs', 'is_active' => 1, 'tracking_type' => 'serialized',
            'auto_generate_serial' => 0, 'ownership_mode' => 'infrastructure_asset',
            'infrastructure_node_type_id' => $type->id,
        ])->assertRedirect(route('master.items.index'));

        $item = Item::where('code', 'OLT-ADMIN-CARD')->firstOrFail();
        $this->assertSame($type->id, $item->infrastructure_node_type_id);
        $this->actingAs($this->owner)->get(route('master.items.edit', $item))
            ->assertOk()->assertSee('Tipe Node Tujuan');
    }
}
