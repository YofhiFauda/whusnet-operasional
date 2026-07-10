<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fop_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_number')->unique()->comment('Nomor task FOP unik, e.g. TFOP-2026-0001');
            $table->string('category', 20)->comment('MTN|C-REQ|O-REQ|PSB|Survey|DEAC|INFR REQ');
            $table->string('tugas')->comment('Deskripsi tugas dinamis');
            $table->foreignId('village_id')->nullable()->constrained('villages')->onDelete('restrict')->comment('Area Desa');
            $table->string('issue')->comment('Jenis gangguan, e.g. FO CUT, ODP LOS, Backbone LOS, Lemot');
            $table->string('status', 20)->default('Proses')->comment('Proses|Cancel|Pending');
            $table->string('priority', 20)->default('low')->comment('low|Medium|High|Urgent');
            $table->string('pending_reason')->nullable()->comment('Alasan pending dari teknisi/client');
            $table->date('client_request_date')->nullable()->comment('Tanggal request dari client jika pending');
            $table->timestamp('cancelled_at')->nullable()->comment('Waktu pembatalan');
            $table->timestamps();

            $table->index('status');
            $table->index('priority');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fop_tasks');
    }
};
