<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('from_status'); // VARCHAR murni untuk fleksibilitas
            $table->string('to_status');   // VARCHAR murni
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete(); // Cegah hilangnya histori jika user resign/dihapus
            $table->text('note')->nullable();
            
            // Log bersifat immutable (hanya waktu pembuatan yang dicatat)
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_status_logs');
    }
};
