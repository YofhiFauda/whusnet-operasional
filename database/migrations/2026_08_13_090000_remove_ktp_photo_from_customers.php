<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Fitur Foto KTP dihapus total dari sistem (keputusan produk 2026-08-13):
 * data identitas cukup diwakili `customers.identity_number` (NIK), foto fisik
 * KTP tidak lagi disimpan sama sekali.
 *
 * Migration ini SENGAJA ireversibel untuk datanya — `down()` cuma mengembalikan
 * kolom kosong. Path & file yang sudah dihapus tidak bisa dipulihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Hapus file fisik dulu, selagi path-nya masih ada di DB.
        $paths = collect()
            ->merge(DB::table('customers')->whereNotNull('foto_ktp')->pluck('foto_ktp'))
            ->merge(DB::table('customer_addresses')->whereNotNull('ktp_photo')->pluck('ktp_photo'))
            ->merge(DB::table('customer_documents')->where('document_type', 'ktp')->pluck('file_path'))
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->unique();

        foreach ($paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        // 2. Baris dokumen bertipe ktp — enum DocumentType tidak punya case-nya
        //    lagi, kalau disisakan tiap cast baris ini melempar ValueError.
        DB::table('customer_documents')->where('document_type', 'ktp')->delete();

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('foto_ktp');
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn('ktp_photo');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('foto_ktp')->nullable()->after('gender');
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('ktp_photo')->nullable();
        });
    }
};
