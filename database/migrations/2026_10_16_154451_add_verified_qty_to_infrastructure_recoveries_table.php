<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infrastructure_recoveries', function (Blueprint $table): void {
            $table->decimal('verified_qty', 12, 2)->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('infrastructure_recoveries', function (Blueprint $table): void {
            $table->dropColumn('verified_qty');
        });
    }
};
