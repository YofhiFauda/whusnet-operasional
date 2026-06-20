<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah relasi activated_by_user_id ke customer_services.
     *
     * Gap yang ditutup:
     * - activated_by_name hanya string nama, tidak bisa di-trace ke user aktif di sistem.
     * - Tambah activated_by_user_id sebagai FK ke users agar traceability lebih baik.
     * - Field ini nullable agar data existing tidak rusak.
     */
    public function up(): void
    {
        Schema::table('customer_services', function (Blueprint $table) {
            $table->foreignId('activated_by_user_id')->nullable()->after('activated_by_name')
                ->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('customer_services', function (Blueprint $table) {
            $table->dropForeign(['activated_by_user_id']);
            $table->dropColumn('activated_by_user_id');
        });
    }
};
