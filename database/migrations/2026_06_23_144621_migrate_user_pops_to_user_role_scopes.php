<?php

use App\Enums\ScopeType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all user pops
        $userPops = DB::table('user_pops')->get();

        foreach ($userPops as $userPop) {
            $user = DB::table('users')->where('id', $userPop->user_id)->first();

            if (! $user || ! $user->role_id) {
                continue;
            }

            // Create user role scope if it doesn't exist
            $scopeId = DB::table('user_role_scopes')->insertGetId([
                'user_id' => $user->id,
                'role_id' => $user->role_id,
                'scope_type' => ScopeType::SELECTED_POP->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add target pop
            DB::table('user_role_scope_targets')->insertOrIgnore([
                'user_role_scope_id' => $scopeId,
                'pop_id' => $userPop->pop_id,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration
    }
};
