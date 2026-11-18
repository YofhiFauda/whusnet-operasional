<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Komentar FOP/CS/BD per percobaan penarikan alat. Tabel sendiri karena
     * komentar bisa banyak per laporan. Append-only: tidak ada kolom/aksi
     * edit atau hapus — ini jejak review, bukan obrolan.
     *
     * `cascadeOnDelete` ke laporan: laporan hanya hilang kalau task-nya
     * dihapus (FK task_device_retrievals.task_id juga cascade), dan komentar
     * tanpa laporan tidak punya arti.
     *
     * docs/plan/warehouse/analisa-review-penarikan-deac.md §3.1
     */
    public function up(): void
    {
        Schema::create('device_retrieval_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_device_retrieval_id')->constrained('task_device_retrievals')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_retrieval_comments');
    }
};
