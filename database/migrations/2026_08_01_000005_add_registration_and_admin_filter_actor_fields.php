<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // registered_by_name: aktor "DI INPUT OLEH" tahap registrasi di sistem
        // lama. Sebelumnya gak kesimpan di mana pun (customers.created_by cuma
        // nyatet ID admin yang jalanin proses import, bukan orang yang beneran
        // input pelanggan waktu itu) — jadi timeline riwayat pelanggan hasil
        // migrasi selalu nampilin "Super Admin (Owner)" alih-alih aktor asli.
        Schema::table('customers', function (Blueprint $table) {
            $table->string('registered_by_name', 150)->nullable()->after('registration_date');
        });

        // admin_filter_at/admin_filter_by_name: tahap "ACC"/filter admin (antara
        // survey & proses pemasangan) di sistem lama. Persis pola
        // activation_time/activated_by_name yang sudah ada di tabel ini untuk
        // tahap verifikasi — sebelumnya tahap ini gak punya kolom tujuan sama
        // sekali di migrasi, cuma numpang jadi salah satu fallback activation_date.
        Schema::table('customer_services', function (Blueprint $table) {
            $table->dateTime('admin_filter_at')->nullable()->after('activated_by_name');
            $table->string('admin_filter_by_name', 150)->nullable()->after('admin_filter_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('registered_by_name');
        });

        Schema::table('customer_services', function (Blueprint $table) {
            $table->dropColumn(['admin_filter_at', 'admin_filter_by_name']);
        });
    }
};
