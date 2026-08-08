<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Nullable + nullOnDelete: kategori yang di-nonaktifkan/dihapus tidak
            // boleh menghilangkan jejak tiket lama yang sudah memakainya — pola
            // sama seperti InternetPackage (toggle is_active, bukan delete keras).
            $table->foreignId('issue_category_id')->nullable()->after('type')
                ->constrained('ticket_issue_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issue_category_id');
        });
    }
};
