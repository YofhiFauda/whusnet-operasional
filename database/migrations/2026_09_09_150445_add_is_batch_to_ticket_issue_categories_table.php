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
            // Kategori ber-checklist Batch (mis. "ODP LOS") — tiketnya support
            // Parent/Child banyak pelanggan sekaligus (lihat migration
            // add_batch_and_reporter_fields_to_tickets_table &
            // create_ticket_batch_members_table). Default false — kategori
            // lama TIDAK otomatis jadi batch.
            $table->boolean('is_batch')->default(false)->after('sla_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_issue_categories', function (Blueprint $table) {
            $table->dropColumn('is_batch');
        });
    }
};
