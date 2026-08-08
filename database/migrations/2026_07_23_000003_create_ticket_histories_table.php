<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_histories')) {
            return;
        }

        Schema::create('ticket_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('reason')->nullable();

            // Pelaku bisa null kalau aksinya dipicu proses sistem (mis. artisan
            // command) yang gak punya user login.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['ticket_id', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_histories');
    }
};
