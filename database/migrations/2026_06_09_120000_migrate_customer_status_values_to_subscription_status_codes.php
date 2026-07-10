<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $statusMap = [
            'Active' => 'active',
            'Suspended' => 'suspended',
            'Inactive' => 'terminated',
            'Pending' => 'registered',
        ];

        foreach ($statusMap as $oldStatus => $newStatus) {
            DB::table('customers')
                ->where('status', $oldStatus)
                ->update(['status' => $newStatus]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $statusMap = [
            'active' => 'Active',
            'suspended' => 'Suspended',
            'terminated' => 'Inactive',
            'registered' => 'Pending',
        ];

        foreach ($statusMap as $newStatus => $oldStatus) {
            DB::table('customers')
                ->where('status', $newStatus)
                ->update(['status' => $oldStatus]);
        }
    }
};
