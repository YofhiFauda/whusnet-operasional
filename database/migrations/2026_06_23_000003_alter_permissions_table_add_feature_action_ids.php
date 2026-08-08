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
        Schema::table('permissions', function (Blueprint $table) {
            $table->foreignId('feature_id')->nullable()->constrained('features')->cascadeOnDelete();
            $table->foreignId('action_id')->nullable()->constrained('actions')->cascadeOnDelete();
            $table->string('code')->nullable()->unique();

            // Jadikan kolom lama nullable agar seeder/kode lama tidak error
            $table->string('name')->nullable()->change();
            $table->string('module')->nullable()->change();

            // Unique constraint kombinasi feature_id dan action_id
            $table->unique(['feature_id', 'action_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropForeign(['feature_id']);
            $table->dropForeign(['action_id']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropUnique(['feature_id', 'action_id']);
            $table->dropUnique('permissions_code_unique');
            $table->dropColumn(['feature_id', 'action_id', 'code']);

            $table->string('name')->nullable(false)->change();
            $table->string('module')->nullable(false)->change();
        });
    }
};
