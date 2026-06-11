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
            $table->string('foto_ktp')->nullable()->after('gender');
            $table->string('foto_rumah')->nullable()->after('longitude');
            $table->string('foto_kontrak')->nullable()->after('referral_customer_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['foto_ktp', 'foto_rumah', 'foto_kontrak']);
        });
    }
};
