<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `customers.person_id` — FK ke layer identitas `persons` (gel.1).
 *
 * Sengaja TIDAK mengubah primary key `customers`. 58 tabel menunjuk
 * `customers.id` bigint; semuanya tetap. Backfill awal 1:1 (tiap customer satu
 * person) dilakukan di MigrateLegacyDataCommand → nol perubahan perilaku hari
 * pertama. Nullable supaya import bertahap tidak gagal di baris yang person-nya
 * belum sempat dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('id')->constrained('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropColumn('person_id');
        });
    }
};
