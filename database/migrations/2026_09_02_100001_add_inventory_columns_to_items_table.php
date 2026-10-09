<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tiga kolom baru buat modul Inventory (ADHOC-54) — dijelaskan penuh di
     * docs/plan/warehouse/. Masing-masing axis INDEPENDEN, jangan digabung:
     *
     * - `tracking_type`  : cara hitung stok (App\Enums\TrackingType).
     * - `ownership_mode` : boleh/gak transisi ke SerialStatus::INSTALLED
     *                      (App\Enums\OwnershipMode).
     * - `equipment_class_override` : override PER-ITEM dari default
     *                      `item_categories.equipment_class` (App\Enums\EquipmentClass),
     *                      nullable — null berarti ikut default kategori.
     *                      Cuma kepake buat item pengecualian di kategori
     *                      catch-all `lainnya`.
     *
     * Default `tracking_type=quantity` & `ownership_mode=installable` — kasus
     * paling umum (material habis pakai, device yang dipasang ke pelanggan).
     * Barang serialized/company_asset (modem, OTDR, dst) diset eksplisit satu
     * per satu lewat halaman Master Barang, BUKAN di-guess migration ini
     * (migration gak tau barang mana yang serialized).
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('tracking_type', 20)->default('quantity')->after('item_category_id');
            $table->string('ownership_mode', 20)->default('installable')->after('tracking_type');
            $table->string('equipment_class_override', 10)->nullable()->after('ownership_mode');

            $table->index('tracking_type');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['tracking_type']);
            $table->dropColumn(['tracking_type', 'ownership_mode', 'equipment_class_override']);
        });
    }
};
