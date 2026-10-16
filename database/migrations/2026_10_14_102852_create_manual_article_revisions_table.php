<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_article_id')->constrained('manual_articles')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->string('category', 100);
            $table->string('summary', 500);
            $table->longText('body_markdown');
            $table->string('required_permission', 150)->nullable();
            $table->json('route_names')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->unique(['manual_article_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_article_revisions');
    }
};
