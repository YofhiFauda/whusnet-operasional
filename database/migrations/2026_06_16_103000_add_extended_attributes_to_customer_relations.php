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
        Schema::table('customer_services', function (Blueprint $table) {
            $table->string('profile', 100)->nullable();
            $table->string('contract_type', 100)->nullable();
            $table->time('activation_time')->nullable();
            $table->string('activated_by_name', 150)->nullable();
        });

        Schema::table('customer_surveys', function (Blueprint $table) {
            $table->date('end_date')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('surveyors', 255)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->string('fop_id', 50)->nullable();
        });

        Schema::table('customer_installations', function (Blueprint $table) {
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('technicians', 255)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->string('fop_id', 50)->nullable();
        });

        Schema::table('customer_technical_details', function (Blueprint $table) {
            $table->string('passive_device', 150)->nullable();
            $table->string('branch_number', 50)->nullable();
            $table->string('pop_number', 50)->nullable();
            $table->string('router_number', 50)->nullable();
            $table->decimal('initial_attenuation', 5, 2)->nullable();
            $table->decimal('actual_attenuation', 5, 2)->nullable();
            $table->date('test_date')->nullable();
            $table->time('test_time')->nullable();
            $table->decimal('jitter_ms', 8, 2)->nullable();
            $table->decimal('latency_ms', 8, 2)->nullable();
            $table->decimal('packet_loss_percent', 5, 2)->nullable();
            $table->decimal('speed_conformity_percent', 5, 2)->nullable();
            $table->integer('quality_score')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('prorate_amount', 12, 2)->nullable();
            $table->decimal('extra_cable_fee', 12, 2)->nullable();
            $table->decimal('extra_installation_fee', 12, 2)->nullable();
            $table->decimal('extra_pole_fee', 12, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_services', function (Blueprint $table) {
            $table->dropColumn(['profile', 'contract_type', 'activation_time', 'activated_by_name']);
        });

        Schema::table('customer_surveys', function (Blueprint $table) {
            $table->dropColumn(['end_date', 'duration_minutes', 'surveyors', 'assigned_at', 'fop_id']);
        });

        Schema::table('customer_installations', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'technicians', 'assigned_at', 'fop_id']);
        });

        Schema::table('customer_technical_details', function (Blueprint $table) {
            $table->dropColumn([
                'passive_device', 'branch_number', 'pop_number', 'router_number',
                'initial_attenuation', 'actual_attenuation', 'test_date', 'test_time',
                'jitter_ms', 'latency_ms', 'packet_loss_percent', 'speed_conformity_percent', 'quality_score',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['prorate_amount', 'extra_cable_fee', 'extra_installation_fee', 'extra_pole_fee']);
        });
    }
};
