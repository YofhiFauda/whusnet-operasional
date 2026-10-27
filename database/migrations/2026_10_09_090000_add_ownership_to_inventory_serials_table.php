<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemilik fisik unit (App\Enums\DeviceOwnership). Default `company` supaya
     * seluruh SN yang sudah ada sebelum migrasi ini tetap dianggap aset
     * perusahaan — perilaku lama tidak berubah tanpa ada alur Beli dari Gudang.
     */
    public function up(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->string('ownership', 20)->default('company')->after('condition_checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->dropColumn('ownership');
        });
    }
};
