<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fop_task_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nama tim, e.g. Tim 1');
            $table->foreignId('pop_id')->nullable()->constrained('pops')->onDelete('set null');
            $table->date('work_date')->comment('Tanggal berlaku tim (1 hari, bisa lanjut kalau ada task pending)');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fop_task_teams');
    }
};
