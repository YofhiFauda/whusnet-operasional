<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.3 (ANALISA_INDEX_DATABASE.md §9 F.2) — index penopang pencarian
 * pelanggan berbasis PREFIX.
 *
 * Pencarian di CustomerController::index diarahkan per bentuk input: query yang
 * mengandung digit (kode/HP/NIK/CID) memakai `LIKE 'x%'` (prefix, sargable),
 * bukan `LIKE '%x%'` di 8 kolom sekaligus yang memaksa full scan. Prefix LIKE
 * baru bisa memakai index kalau kolomnya ter-index — index inilah penopangnya.
 *
 * `customer_code` sudah ada di composite unique (pop_id, customer_code), tapi
 * sebagai kolom ke-2 (leftmost = pop_id) sehingga TIDAK bisa dipakai untuk
 * prefix `customer_code` tanpa pop_id. Perlu index tersendiri.
 * `old_customer_id`/`old_request_id` sudah di-index di Fase 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index('customer_code', 'customers_customer_code_idx');
            $table->index('cid', 'customers_cid_idx');
            $table->index('primary_phone', 'customers_primary_phone_idx');
            $table->index('identity_number', 'customers_identity_number_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_customer_code_idx');
            $table->dropIndex('customers_cid_idx');
            $table->dropIndex('customers_primary_phone_idx');
            $table->dropIndex('customers_identity_number_idx');
        });
    }
};
