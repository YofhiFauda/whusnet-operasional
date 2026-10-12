<?php

namespace Tests\Unit;

use App\Enums\TrackingType;
use App\Models\Item;
use Database\Seeders\ItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gejala yang dijaga: splitter/ODP harus terdaftar SERIALIZED dengan
 * auto-generate SN (dipasang per-unit, SN dibuat sistem), dan kabel dropcore/
 * feeder harus ROLL (dilacak per drum, sisa meter berkurang). Sebelumnya
 * kedua kategori jatuh ke default `quantity` karena `ItemSeeder` tidak
 * mengisi `tracking_type` dan payload `updateOrCreate` tidak meneruskan
 * `auto_generate_serial`, jadi flag di baris splitter/ODP diam-diam hilang.
 *
 * Barang lain (ONT) dijadikan pembanding: tidak mengisi flag auto-generate,
 * jadi harus tetap `false` — memastikan fallback `?? false` tidak merusak
 * barang yang tidak diatur.
 */
class ItemSeederTrackingTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ItemSeeder::class);
    }

    #[Test]
    public function splitter_item_is_serialized_with_auto_generated_serial(): void
    {
        $item = Item::where('code', 'SPL-1X8')->firstOrFail();

        $this->assertSame(TrackingType::SERIALIZED, $item->tracking_type);
        $this->assertTrue($item->auto_generate_serial);
    }

    #[Test]
    public function odp_box_is_serialized_with_auto_generated_serial(): void
    {
        $item = Item::where('code', 'ODP-8')->firstOrFail();

        $this->assertSame(TrackingType::SERIALIZED, $item->tracking_type);
        $this->assertTrue($item->auto_generate_serial);
    }

    #[Test]
    public function dropcore_cable_is_roll_tracked(): void
    {
        $item = Item::where('code', 'DC-1C')->firstOrFail();

        $this->assertSame(TrackingType::ROLL, $item->tracking_type);
    }

    #[Test]
    public function untouched_item_keeps_serialized_without_auto_generate(): void
    {
        $item = Item::where('code', 'ONT-ZTE-F609')->firstOrFail();

        $this->assertSame(TrackingType::SERIALIZED, $item->tracking_type);
        $this->assertFalse($item->auto_generate_serial);
    }
}
