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
        Schema::table('distributions', function (Blueprint $table) {
            // Drop old unique index
            // Note: In some DBs, the index name might be distributions_code_unique
            $table->dropUnique(['code']);
            
            // Add composite unique index
            $table->unique(['pop_id', 'code']);
            
            // Fix description to be nullable (it was required in the first migration)
            $table->string('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropUnique(['pop_id', 'code']);
            $table->unique(['code']);
            $table->string('description')->nullable(false)->change();
        });
    }
};
