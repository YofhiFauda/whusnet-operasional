<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update tasks table
        DB::table('tasks')->where('task_type', 'maintenance')->update(['task_type' => 'MTN']);
        DB::table('tasks')->where('task_type', 'c_req')->update(['task_type' => 'C-REQ']);
        DB::table('tasks')->where('task_type', 'o_req')->update(['task_type' => 'O-REQ']);
        DB::table('tasks')->where('task_type', 'pemasangan')->update(['task_type' => 'PSB']);
        DB::table('tasks')->where('task_type', 'survey')->update(['task_type' => 'SURVEY']);
        DB::table('tasks')->where('task_type', 'ambil_modem')->update(['task_type' => 'DEAC']);
        DB::table('tasks')->where('task_type', 'relokasi')->update(['task_type' => 'RELOKASI']);
        DB::table('tasks')->where('task_type', 'infr_req')->update(['task_type' => 'INFR REQ']);

        // 2. Update fop_tasks table
        DB::table('fop_tasks')->where('category', 'maintenance')->update(['category' => 'MTN']);
        DB::table('fop_tasks')->where('category', 'c_req')->update(['category' => 'C-REQ']);
        DB::table('fop_tasks')->where('category', 'o_req')->update(['category' => 'O-REQ']);
        DB::table('fop_tasks')->where('category', 'pemasangan')->update(['category' => 'PSB']);
        DB::table('fop_tasks')->where('category', 'survey')->update(['category' => 'SURVEY']);
        DB::table('fop_tasks')->where('category', 'ambil_modem')->update(['category' => 'DEAC']);
        DB::table('fop_tasks')->where('category', 'relokasi')->update(['category' => 'RELOKASI']);
        DB::table('fop_tasks')->where('category', 'infr_req')->update(['category' => 'INFR REQ']);

        // 3. Update comments in schema (tasks table)
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('task_type', 30)->comment('SURVEY|PSB|MTN|DEAC|RELOKASI|C-REQ|O-REQ|INFR REQ')->change();
        });

        // 4. Update comments in schema (fop_tasks table)
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->string('category', 30)->comment('SURVEY|PSB|MTN|DEAC|RELOKASI|C-REQ|O-REQ|INFR REQ')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back tasks table
        DB::table('tasks')->where('task_type', 'MTN')->update(['task_type' => 'maintenance']);
        DB::table('tasks')->where('task_type', 'C-REQ')->update(['task_type' => 'c_req']);
        DB::table('tasks')->where('task_type', 'O-REQ')->update(['task_type' => 'o_req']);
        DB::table('tasks')->where('task_type', 'PSB')->update(['task_type' => 'pemasangan']);
        DB::table('tasks')->where('task_type', 'SURVEY')->update(['task_type' => 'survey']);
        DB::table('tasks')->where('task_type', 'DEAC')->update(['task_type' => 'ambil_modem']);
        DB::table('tasks')->where('task_type', 'RELOKASI')->update(['task_type' => 'relokasi']);
        DB::table('tasks')->where('task_type', 'INFR REQ')->update(['task_type' => 'infr_req']);

        // Revert back fop_tasks table
        DB::table('fop_tasks')->where('category', 'MTN')->update(['category' => 'maintenance']);
        DB::table('fop_tasks')->where('category', 'C-REQ')->update(['category' => 'c_req']);
        DB::table('fop_tasks')->where('category', 'O-REQ')->update(['category' => 'o_req']);
        DB::table('fop_tasks')->where('category', 'PSB')->update(['category' => 'pemasangan']);
        DB::table('fop_tasks')->where('category', 'SURVEY')->update(['category' => 'survey']);
        DB::table('fop_tasks')->where('category', 'DEAC')->update(['category' => 'ambil_modem']);
        DB::table('fop_tasks')->where('category', 'RELOKASI')->update(['category' => 'relokasi']);
        DB::table('fop_tasks')->where('category', 'INFR REQ')->update(['category' => 'infr_req']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('task_type', 30)->comment('survey|pemasangan|maintenance|ambil_modem|relokasi|c_req|o_req|infr_req')->change();
        });

        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->string('category', 30)->comment('survey|pemasangan|maintenance|ambil_modem|relokasi|c_req|o_req|infr_req')->change();
        });
    }
};
