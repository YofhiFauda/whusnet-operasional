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
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->string('contract_photo')->nullable()->after('installation_photo');
            $table->string('signature_photo')->nullable()->after('contract_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_installations', function (Blueprint $table) {
            $table->dropColumn(['contract_photo', 'signature_photo']);
        });
    }
};
