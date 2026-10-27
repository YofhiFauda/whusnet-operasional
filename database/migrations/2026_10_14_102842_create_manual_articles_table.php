<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('title');
            $table->string('category', 100);
            $table->string('summary', 500);
            $table->longText('body_markdown');
            $table->string('required_permission', 150)->nullable();
            $table->json('route_names')->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'published_revision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_articles');
    }
};
