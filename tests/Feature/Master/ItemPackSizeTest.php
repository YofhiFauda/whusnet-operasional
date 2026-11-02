<?php

namespace Tests\Feature\Master;

use App\Enums\OwnershipMode;
use App\Enums\TrackingType;
use App\Models\ItemCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `pack_size` cuma relevan buat item tracking_type QUANTITY — konversi
 * Pack→pcs ditampilkan belakangan lewat `entered_pack_qty` (ledger), bukan
 * baca ulang kolom ini. Pola validasinya sama dengan `meter_per_roll`
 * (ADD_MIGRATION add_meter_per_roll_to_items_table) tapi arah sebaliknya:
 * WAJIB buat tracking_type tertentu di sana, di sini NULLABLE tapi
 * PROHIBITED di luar QUANTITY — item SERIALIZED/ROLL gak punya konsep Pack.
 */
class ItemPackSizeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pack_size_tersimpan_untuk_item_quantity(): void
    {
        $this->loginAsAdmin();
        $category = ItemCategory::create([
            'code' => 'konektor_uji',
            'name' => 'Konektor Uji',
            'default_unit' => 'pcs',
            'is_operational' => true,
            'is_active' => true,
        ]);

        $response = $this->post(route('master.items.store'), [
            'code' => 'RJ45-01',
            'name' => 'RJ45 Connector',
            'item_category_id' => $category->id,
            'unit' => 'pcs',
            'is_active' => '1',
            'tracking_type' => TrackingType::QUANTITY->value,
            'ownership_mode' => OwnershipMode::INSTALLABLE->value,
            'pack_size' => 50,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['code' => 'RJ45-01', 'pack_size' => 50]);
    }

    #[Test]
    public function pack_size_ditolak_untuk_item_serialized(): void
    {
        $this->loginAsAdmin();
        $category = ItemCategory::create([
            'code' => 'ont_uji',
            'name' => 'ONT Uji',
            'default_unit' => 'unit',
            'is_operational' => true,
            'is_active' => true,
        ]);

        $response = $this->post(route('master.items.store'), [
            'code' => 'ONT-01',
            'name' => 'ONT',
            'item_category_id' => $category->id,
            'unit' => 'unit',
            'is_active' => '1',
            'tracking_type' => TrackingType::SERIALIZED->value,
            'ownership_mode' => OwnershipMode::INSTALLABLE->value,
            'auto_generate_serial' => '0',
            'pack_size' => 10,
        ]);

        $response->assertSessionHasErrors('pack_size');
    }
}
