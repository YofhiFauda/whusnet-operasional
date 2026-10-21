<?php

namespace Tests\Feature;

use App\Models\ItemCategory;
use Database\Seeders\ItemCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdditionalDeviceCategoryBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrasi_mengaktifkan_ap_lama_dan_membuat_stb_tanpa_mengubah_metadata_ap(): void
    {
        DB::table('item_categories')->where('code', 'stb')->delete();

        $accessPoint = ItemCategory::create([
            'code' => 'access_point',
            'name' => 'AP Cabang Khusus',
            'default_unit' => 'pcs',
            'equipment_class' => 'aktif',
            'is_active' => false,
            'is_customer_extra_device' => false,
        ]);

        $migration = $this->backfillMigration();
        $migration->up();

        $this->assertTrue($accessPoint->fresh()->is_customer_extra_device);
        $this->assertSame('AP Cabang Khusus', $accessPoint->fresh()->name);
        $this->assertFalse($accessPoint->fresh()->is_active);

        $setTopBox = ItemCategory::query()->where('code', 'stb')->sole();
        $this->assertTrue($setTopBox->is_customer_extra_device);
        $this->assertSame('Set Top Box (STB)', $setTopBox->name);

        $migration->down();
        $this->assertTrue($accessPoint->fresh()->is_customer_extra_device);
        $this->assertTrue($setTopBox->fresh()->is_customer_extra_device);

        $migration->up();
        $this->assertSame(1, ItemCategory::query()->where('code', 'stb')->count());
    }

    public function test_migrasi_mempertahankan_stb_lama_dan_tidak_mengubah_kategori_lain(): void
    {
        $setTopBox = ItemCategory::query()->where('code', 'stb')->sole();
        $setTopBox->update([
            'name' => 'STB Cabang',
            'is_active' => false,
            'is_customer_extra_device' => false,
        ]);

        $modem = ItemCategory::create([
            'code' => 'modem_ont',
            'name' => 'Modem Pelanggan',
            'default_unit' => 'pcs',
            'equipment_class' => 'aktif',
            'is_customer_extra_device' => false,
        ]);

        $this->backfillMigration()->up();

        $this->assertTrue($setTopBox->fresh()->is_customer_extra_device);
        $this->assertSame('STB Cabang', $setTopBox->fresh()->name);
        $this->assertFalse($setTopBox->fresh()->is_active);
        $this->assertFalse($modem->fresh()->is_customer_extra_device);
    }

    public function test_router_pelanggan_tersedia_tanpa_mengizinkan_gateway_internal(): void
    {
        $router = ItemCategory::query()->where('code', 'customer_router')->sole();

        $this->assertTrue($router->is_customer_extra_device);

        $this->seed(ItemCategorySeeder::class);

        $this->assertTrue($router->fresh()->is_customer_extra_device);
        $this->assertFalse(ItemCategory::query()->where('code', 'router_gateway')->sole()->is_customer_extra_device);
    }

    private function backfillMigration(): object
    {
        $paths = glob(database_path('migrations/*_backfill_customer_extra_device_item_categories.php'));
        $this->assertCount(1, $paths);

        return require $paths[0];
    }
}
