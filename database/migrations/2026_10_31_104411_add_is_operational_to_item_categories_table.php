<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_categories', function (Blueprint $table) {
            $table->boolean('is_operational')->default(true);
        });

        DB::table('item_categories')->where('code', 'merchandise_marketing')->update(['is_operational' => false]);
        DB::table('items')
            ->whereIn('item_category_id', DB::table('item_categories')->where('code', 'merchandise_marketing')->select('id'))
            ->update(['ownership_mode' => 'not_applicable']);
    }

    public function down(): void
    {
        DB::table('items')
            ->whereIn('item_category_id', DB::table('item_categories')->where('code', 'merchandise_marketing')->select('id'))
            ->where('ownership_mode', 'not_applicable')
            ->update(['ownership_mode' => 'installable']);

        Schema::table('item_categories', function (Blueprint $table) {
            $table->dropColumn('is_operational');
        });
    }
};
