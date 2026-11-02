<?php

namespace Tests\Feature;

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

class MasterItemShortSpecificationTest extends TestCase
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

        $role = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
    }

    private function itemFields(): array
    {
        return [
            'code' => 'SPEC-ONT-1',
            'name' => 'ONT Spesifikasi',
            'item_category_id' => ItemCategory::where('code', 'modem_ont')->firstOrFail()->id,
            'unit' => 'unit',
            'is_active' => 1,
            'tracking_type' => 'serialized',
            'auto_generate_serial' => 0,
        ];
    }

    #[Test]
    public function spesifikasi_singkat_dapat_disimpan_dan_diedit_pada_master_barang(): void
    {
        $response = $this->actingAs($this->owner)->post(route('master.items.store'), $this->itemFields() + [
            'short_specification' => 'GPON, 4 port LAN, dual band',
        ]);

        $response->assertRedirect(route('master.items.index'));
        $item = Item::where('code', 'SPEC-ONT-1')->firstOrFail();
        $this->assertSame('GPON, 4 port LAN, dual band', $item->short_specification);

        $this->actingAs($this->owner)->get(route('master.items.edit', $item))
            ->assertOk()->assertSee('value="GPON, 4 port LAN, dual band"', false);

        $this->actingAs($this->owner)->put(route('master.items.update', $item), $this->itemFields() + [
            'short_specification' => 'GPON, 2 port LAN',
        ])->assertRedirect(route('master.items.index'));

        $this->assertSame('GPON, 2 port LAN', $item->fresh()->short_specification);
    }

    #[Test]
    public function spesifikasi_singkat_bisa_dikosongkan_dan_dibatasi_255_karakter(): void
    {
        $this->actingAs($this->owner)->post(route('master.items.store'), $this->itemFields() + [
            'short_specification' => str_repeat('X', 256),
        ])->assertSessionHasErrors('short_specification');
        $this->assertFalse(Item::where('code', 'SPEC-ONT-1')->exists());

        $this->actingAs($this->owner)->post(route('master.items.store'), $this->itemFields() + [
            'short_specification' => 'Spesifikasi awal',
        ])->assertRedirect(route('master.items.index'));

        $item = Item::where('code', 'SPEC-ONT-1')->firstOrFail();
        $this->actingAs($this->owner)->put(route('master.items.update', $item), $this->itemFields() + [
            'short_specification' => '',
        ])->assertRedirect(route('master.items.index'));

        $this->assertNull($item->fresh()->short_specification);
    }
}
