<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak anggota tim yang dilepas saat task di-Pending (ADHOC-298).
 *
 * Kenapa tabel sendiri, bukan kolom di `task_teams`: Pending WAJIB menghapus
 * baris `task_teams` (task balik ke antrean FOP tanpa tim), dan tabel itu
 * punya unique (task_id, user_id) — baris "bekas" tidak bisa dibiarkan di sana
 * tanpa memblokir penjadwalan ulang ke teknisi yang sama, dan tanpa membuat
 * semua query/policy berbasis `teamMembers` ikut menghitung tim lama.
 *
 * Satu task bisa di-pending berkali-kali ⇒ sengaja tanpa unique.
 *
 * `user_id` cascade (bukan restrict seperti task_teams): ini cuma jejak
 * Riwayat milik teknisi itu sendiri — gak boleh jadi penghalang hapus user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_member_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_in_task', 30)->nullable();
            $table->string('reason', 255);
            $table->string('source', 30)->comment('reschedule = teknisi, pending = FOP');
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at');
            $table->timestamps();

            $table->index(['user_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_member_releases');
    }
};
