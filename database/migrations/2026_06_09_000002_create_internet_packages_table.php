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
        Schema::create('internet_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_code', 50);
            $table->string('name', 150);
            $table->string('category', 100);
            $table->string('package_group', 150);
            $table->string('bandwidth_label', 50);
            $table->decimal('download_speed_mbps', 8, 2)->nullable();
            $table->decimal('upload_speed_mbps', 8, 2)->nullable();
            $table->unsignedTinyInteger('contention_ratio')->nullable();
            $table->decimal('monthly_price', 12, 2);
            $table->decimal('ppn', 5, 2)->default(0.00);
            $table->decimal('discount_default', 5, 2)->default(0.00);
            $table->decimal('total_price', 12, 2)->default(0.00);
            $table->string('modem', 100)->nullable();
            $table->json('features')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->string('ip_address_type', 100)->nullable();
            $table->unsignedSmallInteger('contract_period_months')->nullable();
            $table->decimal('installation_fee', 12, 2)->nullable();
            $table->string('installation_fee_label', 150)->nullable();
            $table->string('profile', 100)->nullable();
            $table->string('technical_profile', 100)->nullable();
            $table->text('terms')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('package_code');
            $table->index(['category', 'package_group']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internet_packages');
    }
};
