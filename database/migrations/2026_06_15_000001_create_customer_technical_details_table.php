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
        Schema::create('customer_technical_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('old_report_id', 50)->nullable()->unique();
            $table->string('old_customer_id', 50)->nullable();
            $table->string('old_request_id', 50)->nullable();
            $table->string('connection_type', 50)->nullable();
            $table->string('test_upload', 50)->nullable();
            $table->string('test_download', 50)->nullable();
            $table->string('ssid', 100)->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->string('antenna_mac', 50)->nullable();
            $table->string('router_mac', 50)->nullable();
            $table->string('router_or_ont_serial', 100)->nullable();
            $table->string('odp_number', 50)->nullable();
            $table->string('odp_port', 50)->nullable();
            $table->string('olt_port', 50)->nullable();
            $table->string('wireless_signal', 50)->nullable();
            $table->string('fiber_signal', 50)->nullable();
            $table->string('location_source', 150)->nullable();
            $table->text('note')->nullable();
            $table->string('speedtest_photo')->nullable();
            $table->string('form_photo')->nullable();
            $table->string('signed_form_photo')->nullable();
            $table->string('router_photo')->nullable();
            $table->string('cable_photo')->nullable();
            $table->timestamps();

            $table->index('old_customer_id');
            $table->index('old_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_technical_details');
    }
};
