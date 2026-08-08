<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur "Foto Bukti" task teknisi (TaskEvidence) dihapus — tiap tipe task
 * sudah punya foto wajibnya sendiri lewat Laporan (Survey: survey_photo/
 * house_photo, Pemasangan: installation_photo/contract_photo/signature_photo/
 * speedtest_photo, Maintenance/lainnya: opm_photo/speedtest_photo). Section
 * evidence generik ini gak pernah gate completion (Task::canComplete() sudah
 * lama hardcoded true) — cuma sisa fitur lama yang bikin bingung maintenance.
 * File foto lama di storage (`task-evidences/`) SENGAJA tidak dihapus di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('task_evidences');
    }

    public function down(): void
    {
        Schema::create('task_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('file_path');
            $table->string('caption')->nullable();
            $table->timestamps();

            $table->index('task_id');
        });
    }
};
