<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('items')
            ->whereIn('code', ['OLT-ZTE-C320', 'OLT-MODULE-GPON-8P'])
            ->where('ownership_mode', 'company_asset')
            ->update(['ownership_mode' => 'infrastructure_asset', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Status aset yang sudah dideploy tidak aman dikembalikan ke company_asset.
    }
};
