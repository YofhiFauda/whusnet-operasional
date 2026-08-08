<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot data pelanggan pada tabel tickets — diisi otomatis dari CID yang
 * dipilih saat submit (lihat TicketService::create()), BUKAN kolom
 * pass-through ke customers.
 *
 * Kenapa di-freeze, bukan cukup baca live lewat relasi customer():
 * ticket adalah catatan keluhan pada satu titik waktu (alamat/HP/paket
 * pelanggan saat itu). Kalau field-field ini cuma dibaca live, riwayat
 * ticket lama bisa "berubah" begitu pelanggan pindah alamat, ganti paket,
 * atau nomor HP-nya diupdate — padahal itu bukan yang terjadi saat keluhan
 * dilaporkan. Field yang backed FK ke master data (pop_id) TIDAK ikut
 * di-snapshot di sini — ID POP sendiri sudah cukup jadi jangkar historis,
 * beda dari string/angka bebas seperti alamat atau koordinat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('customer_id');
            $table->text('customer_address')->nullable()->after('customer_name');
            $table->string('customer_phone')->nullable()->after('customer_address');
            $table->string('customer_odp')->nullable()->after('customer_phone');
            $table->string('customer_package')->nullable()->after('customer_odp');
            $table->string('customer_device')->nullable()->after('customer_package');
            $table->decimal('customer_latitude', 10, 7)->nullable()->after('customer_device');
            $table->decimal('customer_longitude', 10, 7)->nullable()->after('customer_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'customer_address',
                'customer_phone',
                'customer_odp',
                'customer_package',
                'customer_device',
                'customer_latitude',
                'customer_longitude',
            ]);
        });
    }
};
