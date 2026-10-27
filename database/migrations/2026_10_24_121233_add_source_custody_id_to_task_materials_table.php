<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_materials', function (Blueprint $table): void {
            $table->foreignId('source_custody_id')->nullable()->after('lot_no')
                ->constrained('technician_custody')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_materials', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_custody_id');
        });
    }
};
