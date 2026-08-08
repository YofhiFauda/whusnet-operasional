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
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'reject_reason')) {
                $table->string('reject_reason', 1000)->nullable();
            }
            if (! Schema::hasColumn('tasks', 'fop_review_status')) {
                $table->enum('fop_review_status', ['pending', 'approved', 'rejected'])->default('pending');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'reject_reason')) {
                $table->dropColumn('reject_reason');
            }
            if (Schema::hasColumn('tasks', 'fop_review_status')) {
                $table->dropColumn('fop_review_status');
            }
        });
    }
};
