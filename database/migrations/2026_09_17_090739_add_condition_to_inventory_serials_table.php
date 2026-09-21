<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kondisi fisik unit SERIALIZED (App\Enums\ItemCondition) — axis
     * independen dari `status` (lihat docblock InventorySerial). Default
     * `new` — barang lama sebelum migrasi ini otomatis dianggap baru (aman,
     * gak ada barang yang tiba-tiba ke-gate padahal udah lama dipakai
     * normal). `condition_checked_at` NULL = belum pernah dicek fisik —
     * inilah yang men-trigger gate Issue (lihat
     * docs/plan/warehouse/analisa-gap-kondisi-barang.md rancangan poin 4).
     */
    public function up(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->string('condition', 20)->default('new')->nullable()->after('status');
            $table->timestamp('condition_checked_at')->nullable()->after('condition');
            $table->foreignId('condition_checked_by')->nullable()->after('condition_checked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('condition_checked_by');
            $table->dropColumn(['condition', 'condition_checked_at']);
        });
    }
};
