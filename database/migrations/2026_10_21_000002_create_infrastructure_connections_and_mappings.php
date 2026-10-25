<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koneksi fisik antar-posisi (§3: parent = "berada di bawah apa",
     * koneksi = "tersambung ke mana") dan daftar pemetaan legacy.
     *
     * Kolom target SENGAJA FK eksplisit per jenis, bukan pasangan
     * type/ID polimorfik — rancangan §16.1 melarang pasangan type/ID tanpa
     * pemeriksaan referensi. `active_key` = 1 saat aktif, NULL saat dilepas,
     * sehingga unique index (target, active_key) menjamin satu ODP hanya
     * punya satu uplink aktif tanpa melarang histori pelepasan (pola sama
     * `network_port_assignments`).
     *
     * Backfill data lama (dulu `InfrastructureLegacyBackfillService`) sudah
     * dipensiunkan bersama tabel `infrastructure_slots`/`infrastructure_ports`
     * (Slot/PON kini baris `network_nodes`, Task 14), jadi up() cukup membuat
     * tabel. DB yang SUDAH menjalankan migration ini tidak mengulangnya, jadi
     * hasil backfill-nya tidak berubah; di DB baru backfill memang no-op (belum
     * ada data lama). down() tidak lagi membatalkan hasil backfill karena
     * service-nya hilang; tabel mapping langsung di-drop.
     */
    public function up(): void
    {
        Schema::create('infrastructure_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_infrastructure_port_id')->constrained('infrastructure_ports')->restrictOnDelete();
            $table->foreignId('target_network_node_id')->nullable()->constrained('network_nodes')->restrictOnDelete();
            $table->foreignId('target_infrastructure_port_id')->nullable()->constrained('infrastructure_ports')->restrictOnDelete();
            $table->string('target_label', 150)->nullable();
            $table->string('verification_status', 30)->default('belum_terverifikasi');
            $table->unsignedTinyInteger('active_key')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->string('release_note', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['source_infrastructure_port_id', 'active_key'], 'infra_conn_source_active_idx');
            $table->unique(['target_network_node_id', 'active_key'], 'infra_conn_target_node_active_unique');
            $table->unique(['target_infrastructure_port_id', 'active_key'], 'infra_conn_target_port_active_unique');
        });

        Schema::create('infrastructure_legacy_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_type', 50);
            $table->unsignedBigInteger('legacy_id');
            $table->string('new_type', 50)->nullable();
            $table->unsignedBigInteger('new_id')->nullable();
            $table->string('status', 20);
            $table->foreignId('pop_id')->nullable()->constrained('pops')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('evidence')->nullable();
            $table->text('decision')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['legacy_type', 'legacy_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_legacy_mappings');
        Schema::dropIfExists('infrastructure_connections');
    }
};
