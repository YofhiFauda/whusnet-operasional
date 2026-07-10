<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('task_type', 30)->comment('survey|pemasangan|maintenance|ambil_modem|relokasi');
            $table->string('item')->comment('Nama item checklist');
            $table->boolean('is_required')->default(true)->comment('Wajib dicentang sebelum task bisa Selesai');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('task_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_checklist_templates');
    }
};
