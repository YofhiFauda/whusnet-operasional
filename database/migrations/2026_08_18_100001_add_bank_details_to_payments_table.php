<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field pendukung metode Transfer di Modal Bayar Invoice. Nullable —
     * cuma diisi ketika `payment_method = transfer`
     * (PaymentMethod::requiresBankDetails()).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('bank_name', 100)->nullable()->after('payment_method');
            $table->string('account_number', 50)->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_number']);
        });
    }
};
