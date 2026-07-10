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
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('mini_pop_id')
                ->nullable()
                ->after('distribution_id')
                ->constrained('pops')
                ->nullOnDelete()
                ->comment('Mini POP (OLT) spesifik, di-assign pasca pemasangan/aktivasi — nullable karena registrasi awal cuma pakai Cabang POP');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mini_pop_id');
        });
    }
};
