<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\MarketingMerchandiseService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemNonOperationalCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class, ItemCategorySeeder::class] as $seeder) {
            $this->seed($seeder);
        }

        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
    }

    #[Test]
    public function kategori_baru_dapat_ditandai_nonoperasional(): void
    {
        $this->actingAs($this->owner)->post(route('master.item-categories.store'), [
            'code' => 'hadiah_event',
            'name' => 'Hadiah Event',
            'default_unit' => 'pcs',
            'is_operational' => '0',
            'is_active' => '1',
        ])->assertRedirect(route('master.item-categories.index'));

        $this->assertFalse(ItemCategory::where('code', 'hadiah_event')->firstOrFail()->is_operational);
        $this->assertFalse(ItemCategory::where('code', 'merchandise_marketing')->firstOrFail()->is_operational);
    }

    #[Test]
    public function barang_nonoperasional_disimpan_tanpa_atribut_pemasangan(): void
    {
        $category = ItemCategory::create([
            'code' => 'hadiah_event',
            'name' => 'Hadiah Event',
            'default_unit' => 'pcs',
            'is_operational' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'HADIAH-001',
            'name' => 'Pin Event',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'is_active' => '1',
            'tracking_type' => 'quantity',
        ])->assertRedirect(route('master.items.index'));

        $item = Item::where('code', 'HADIAH-001')->firstOrFail();
        $this->assertSame('not_applicable', $item->ownership_mode->value);
        $this->assertNull($item->infrastructure_node_type_id);
        $this->assertNull($item->infrastructure_location_kind);
    }

    #[Test]
    public function form_barang_menyembunyikan_atribut_jaringan_berdasarkan_kategori(): void
    {
        $html = $this->actingAs($this->owner)->get(route('master.items.create'))->assertOk()->getContent();

        $this->assertStringContainsString('nonOperationalCategoryIds:', $html);
        $this->assertStringContainsString("x-effect=\"if (!isOperational) trackingType = 'quantity'\"", $html);
        $this->assertStringContainsString('x-show="isOperational"', $html);
        $this->assertStringContainsString('name="ownership_mode" :disabled="!isOperational"', $html);
        $this->assertStringContainsString('name="infrastructure_location_kind" id="infrastructure_location_kind" :disabled="!isOperational"', $html);
    }

    #[Test]
    public function jenis_penggunaan_kategori_tidak_bisa_diubah_setelah_ada_barang(): void
    {
        $category = ItemCategory::create([
            'code' => 'hadiah_event',
            'name' => 'Hadiah Event',
            'default_unit' => 'pcs',
            'is_operational' => false,
            'is_active' => true,
        ]);
        Item::create([
            'code' => 'HADIAH-003',
            'name' => 'Pin Event',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'tracking_type' => 'quantity',
            'ownership_mode' => 'not_applicable',
        ]);

        $this->actingAs($this->owner)->put(route('master.item-categories.update', $category), [
            'code' => $category->code,
            'name' => $category->name,
            'default_unit' => 'pcs',
            'is_active' => '1',
            'is_operational' => '1',
        ])->assertSessionHasErrors('is_operational');

        $this->assertFalse($category->fresh()->is_operational);
    }

    #[Test]
    public function kategori_barang_nonoperasional_terkunci_setelah_stok_bergerak(): void
    {
        $first = ItemCategory::create([
            'code' => 'hadiah_event',
            'name' => 'Hadiah Event',
            'default_unit' => 'pcs',
            'is_operational' => false,
            'is_active' => true,
        ]);
        $second = ItemCategory::create([
            'code' => 'souvenir',
            'name' => 'Souvenir',
            'default_unit' => 'pcs',
            'is_operational' => false,
            'is_active' => true,
        ]);
        $item = Item::create([
            'code' => 'HADIAH-004',
            'name' => 'Pin Event',
            'is_active' => true,
            'item_category_id' => $first->id,
            'unit' => 'pcs',
            'tracking_type' => 'quantity',
            'ownership_mode' => 'not_applicable',
        ]);
        $pusat = Pop::create([
            'code' => 'HADIAH-PUSAT',
            'pop_code' => 'HDP',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'Pusat',
            'type' => 'pusat',
            'status' => 'active',
        ]);
        app(MarketingMerchandiseService::class)->receive($pusat, $item, 10, $this->owner);

        $this->actingAs($this->owner)->put(route('master.items.update', $item), [
            'code' => $item->code,
            'name' => $item->name,
            'item_category_id' => $second->id,
            'unit' => 'pcs',
            'is_active' => '1',
        ])->assertSessionHasErrors('item_category_id');

        $this->assertSame($first->id, $item->fresh()->item_category_id);
    }

    #[Test]
    public function atribut_pemasangan_yang_dikirim_untuk_barang_nonoperasional_ditolak(): void
    {
        $category = ItemCategory::create([
            'code' => 'hadiah_event',
            'name' => 'Hadiah Event',
            'default_unit' => 'pcs',
            'is_operational' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)->post(route('master.items.store'), [
            'code' => 'HADIAH-002',
            'name' => 'Pin Event',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'is_active' => '1',
            'tracking_type' => 'quantity',
            'ownership_mode' => 'installable',
        ])->assertSessionHasErrors('ownership_mode');

        $this->assertDatabaseMissing('items', ['code' => 'HADIAH-002']);
    }
}
