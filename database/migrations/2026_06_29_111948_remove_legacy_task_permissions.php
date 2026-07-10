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
        $legacyCodes = [
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.cancel',
            'tasks.schedule', 'tasks.assign', 'tasks.export', 'tasks.override',
            'tasks.view_own', 'tasks.status_start', 'tasks.status_complete',
            'tasks.status_pending', 'tasks.checklist_update', 'tasks.evidence_upload'
        ];

        \Illuminate\Support\Facades\DB::table('role_permissions')->whereIn('permission_id', function ($query) use ($legacyCodes) {
            $query->select('id')->from('permissions')->whereIn('code', $legacyCodes);
        })->delete();

        \Illuminate\Support\Facades\DB::table('permissions')->whereIn('code', $legacyCodes)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down needed
    }
};
