<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table): void {
            $table->foreignId('current_infrastructure_pop_id')->nullable()->after('current_mini_pop_id')->constrained('pops')->restrictOnDelete();
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->foreignId('from_infrastructure_pop_id')->nullable()->after('to_mini_pop_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('to_infrastructure_pop_id')->nullable()->after('from_infrastructure_pop_id')->constrained('pops')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('inventory_serials')->whereNotNull('current_infrastructure_pop_id')->exists()
            || DB::table('inventory_transactions')->whereNotNull('from_infrastructure_pop_id')->orWhereNotNull('to_infrastructure_pop_id')->exists()) {
            throw new RuntimeException('Lokasi aset POP masih dipakai; migrasi tidak dapat dibatalkan tanpa kehilangan histori.');
        }

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('from_infrastructure_pop_id');
            $table->dropConstrainedForeignId('to_infrastructure_pop_id');
        });
        Schema::table('inventory_serials', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_infrastructure_pop_id');
        });
    }
};
