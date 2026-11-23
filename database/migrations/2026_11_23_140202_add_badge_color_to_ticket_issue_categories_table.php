<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_issue_categories', function (Blueprint $table) {
            // Warna badge kategori, hex `#rrggbb` dari color picker Master
            // Issue. Nullable SENGAJA: kategori lama (dan yang dibiarkan
            // kosong) tetap pakai tebakan kata kunci lama di Worksheet
            // Helpdesk (`issueBadgeClass()`), jadi tampilannya gak berubah
            // mendadak setelah deploy.
            $table->string('badge_color', 7)->nullable()->after('is_batch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_issue_categories', function (Blueprint $table) {
            $table->dropColumn('badge_color');
        });
    }
};
