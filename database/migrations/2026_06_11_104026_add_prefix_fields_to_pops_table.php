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
        Schema::table('pops', function (Blueprint $table) {
            $table->string('pop_code', 20)->nullable()->unique()->after('code');
            $table->string('registration_prefix', 10)->nullable()->after('pop_code');
            $table->string('cid_prefix', 10)->nullable()->after('registration_prefix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pops', function (Blueprint $table) {
            $table->dropColumn([
                'pop_code',
                'registration_prefix',
                'cid_prefix',
            ]);
        });
    }
};
