<?php

namespace Tests\Unit;

use App\Enums\OwnershipMode;
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
    public function splitter_odp_and_feeder_are_seeded_as_infrastructure_assets(): void
    {
        foreach (['SPL-1X2', 'SPL-1X4', 'SPL-1X8', 'SPL-1X16', 'SPL-1X32', 'ODP-8', 'ODP-16', 'ODC-24', 'KBL-FEEDER-12C'] as $code) {
            $this->assertSame(
                OwnershipMode::INFRASTRUCTURE_ASSET,
                Item::where('code', $code)->firstOrFail()->ownership_mode,
                "{$code} harus aset infrastruktur",
            );
        }
    }

    #[Test]
    public function dropcore_stays_installable_because_it_is_cut_to_customers(): void
    {
        foreach (['DC-1C', 'DC-2C'] as $code) {
            $this->assertSame(OwnershipMode::INSTALLABLE, Item::where('code', $code)->firstOrFail()->ownership_mode);
        }
    }

    #[Test]
    public function reseeding_updates_ownership_mode_of_existing_item(): void
    {
        // Lingkungan dev/staging yang udah di-seed sebelum perbaikan ini:
        // updateOrCreate wajib menimpa ownership_mode lama (lewat Eloquent,
        // gak kena kunci `$locked` UI di ItemController).
        Item::where('code', 'ODP-8')->update(['ownership_mode' => OwnershipMode::INSTALLABLE->value]);

        $this->seed(ItemSeeder::class);

        $this->assertSame(OwnershipMode::INFRASTRUCTURE_ASSET, Item::where('code', 'ODP-8')->firstOrFail()->ownership_mode);
    }

    #[Test]
    public function untouched_item_keeps_serialized_without_auto_generate(): void
    {
        $item = Item::where('code', 'ONT-ZTE-F609')->firstOrFail();

        $this->assertSame(TrackingType::SERIALIZED, $item->tracking_type);
        $this->assertFalse($item->auto_generate_serial);
    }
}
