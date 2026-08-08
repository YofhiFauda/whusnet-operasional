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
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->string('category', 30)->comment('survey|pemasangan|maintenance|ambil_modem|relokasi|c_req|o_req|infr_req')->change();
        });

        // Update existing values
        DB::table('fop_tasks')->where('category', 'MTN')->update(['category' => 'maintenance']);
        DB::table('fop_tasks')->where('category', 'C-REQ')->update(['category' => 'c_req']);
        DB::table('fop_tasks')->where('category', 'O-REQ')->update(['category' => 'o_req']);
        DB::table('fop_tasks')->where('category', 'PSB')->update(['category' => 'pemasangan']);
        DB::table('fop_tasks')->where('category', 'SURVEY')->orWhere('category', 'Survey')->update(['category' => 'survey']);
        DB::table('fop_tasks')->where('category', 'DEAC')->update(['category' => 'ambil_modem']);
        DB::table('fop_tasks')->where('category', 'INFR REQ')->update(['category' => 'infr_req']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back values (best effort)
        DB::table('fop_tasks')->where('category', 'maintenance')->update(['category' => 'MTN']);
        DB::table('fop_tasks')->where('category', 'c_req')->update(['category' => 'C-REQ']);
        DB::table('fop_tasks')->where('category', 'o_req')->update(['category' => 'O-REQ']);
        DB::table('fop_tasks')->where('category', 'pemasangan')->update(['category' => 'PSB']);
        DB::table('fop_tasks')->where('category', 'survey')->update(['category' => 'SURVEY']);
        DB::table('fop_tasks')->where('category', 'ambil_modem')->update(['category' => 'DEAC']);
        DB::table('fop_tasks')->where('category', 'infr_req')->update(['category' => 'INFR REQ']);

        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->string('category', 20)->comment('MTN|C-REQ|O-REQ|PSB|Survey|DEAC|INFR REQ')->change();
        });
    }
};
