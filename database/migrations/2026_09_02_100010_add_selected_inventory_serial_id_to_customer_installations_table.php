<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pointer DRAFT "SN mana yang dipilih teknisi dari custody-nya" — diisi
     * berkali-kali aman di `storePemasangan()` (form itu resubmittable), TAPI
     * aksi INSTALL sungguhan (`InventoryService::installSerial()`, ubah
     * `inventory_serials.status`) baru jalan SEKALI di `storeSpeedtest()`.
     * Sama alasan `unit_price_snapshot` custody: state yang berefek samping
     * nyata (custody/serial berubah) gak boleh nempel di titik yang bisa
     * disubmit berkali-kali. Lihat komentar di
     * CustomerInstallationController::storeSpeedtest().
     *
     * Nullable — instalasi tanpa device ke-track Inventory (mayoritas data
     * existing/legacy) tetap jalan seperti biasa, field ini kosong selamanya.
     */
    public function up(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->foreignId('selected_inventory_serial_id')->nullable()->after('installation_note')
                ->constrained('inventory_serials')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_inventory_serial_id');
        });
    }
};
