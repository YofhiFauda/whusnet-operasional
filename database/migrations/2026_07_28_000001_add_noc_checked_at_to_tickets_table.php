<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // null = tiket handler=NOC belum di-"Oncheck" (Helpdesk masih pegang
            // kendali penuh); terisi = NOC udah resmi ambil alih. Nullable dan
            // gak butuh backfill — tiket lama handler=NOC otomatis kebaca
            // "pending", yang justru bikin guard baru lebih longgar buat data
            // lama itu (bukan lebih ketat), jadi aman.
            $table->timestamp('noc_checked_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('noc_checked_at');
        });
    }
};
