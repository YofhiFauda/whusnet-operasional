<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {

            Schema::table('users', function (Blueprint $table) {
                //
            });

        } catch (QueryException $e) {
            if (
                in_array($e->getCode(), ['42S01', '42000', '23000', 1050, 1060, 1061, 1091, 1062])
                || str_contains($e->getMessage(), 'already exists')
                || str_contains($e->getMessage(), 'Duplicate column')
                || str_contains($e->getMessage(), 'Duplicate key')
                || str_contains($e->getMessage(), 'Multiple primary key')
            ) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
