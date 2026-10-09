<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Counter nomor dokumen. Lihat NumberSequenceService untuk alasan
     * kenapa tidak pakai count()+1 di atas tabel dokumen.
     */
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->unsignedBigInteger('value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
