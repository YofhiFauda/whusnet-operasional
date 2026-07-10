<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: these fields now live directly on internet_packages.
    }

    public function down(): void
    {
        // No-op.
    }
};
