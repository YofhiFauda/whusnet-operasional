<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layer identitas pelanggan permanen (gel.1, rancangan-fase4-persons.md §3).
 *
 * `persons` menjawab "SIAPA orangnya" — pertanyaan yang selama ini tidak ada
 * yang menjawab. `customer_code` (RQ) menjawab "kontrak yang mana", CID
 * menjawab "sambungan fisik yang mana". Satu orang bisa punya banyak kontrak
 * (daftar ulang) → banyak baris `customers` → satu `persons`.
 *
 * UUIDv7 disimpan sebagai char(36), BUKAN binary(16): 1.957 baris, penghematan
 * 20 byte tak terasa, sementara binary merusak keterbacaan di query/tinker/log.
 * Ganti ke binary(16) kalau kelak tembus jutaan baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();

            // UUIDv7 (Str::uuid7()) — immutable, tak pernah tampil ke UI, tak
            // bisa dieja CS di telepon. Ini identitas mesin, bukan referensi
            // bisnis. Unik global → aman untuk sinkronisasi antar-instalasi.
            $table->char('uuid', 36)->unique();

            // Anchor backfill yang selamat dari import ulang legacy. Diisi
            // "{cabang}:{IDPENGGUNA}" saat MigrateLegacyDataCommand jalan. Tanpa
            // ini, tiap import ulang melahirkan person baru dan menghapus semua
            // kerja merge manual (rancangan §3.2). Nullable karena pelanggan
            // baru (non-legacy) tidak punya legacy_key.
            $table->string('legacy_key', 60)->nullable()->unique();

            // Merge reversibel: dua person yang ternyata satu orang tidak
            // dihapus, tapi salah satunya menunjuk ke yang lain lewat kolom ini.
            // Bisa dipisah lagi kalau admin salah gabung. Diisi di gel.2
            // (halaman merge) — di gel.1 selalu null.
            $table->foreignId('merged_into')->nullable()->constrained('persons')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
