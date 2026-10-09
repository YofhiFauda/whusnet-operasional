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
        Schema::create('customer_billing_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            // Format sama dengan invoices.billing_period (YYYY-MM).
            $table->string('billing_period', 7);
            // App\Enums\BillingWaiverSource — 'termination' (Request Putus
            // Langganan) | 'leave' (Cuti Berlangganan). String, bukan enum DB
            // native, sama pola invoice_type/manual_category.
            $table->string('source', 20);
            $table->text('reason');
            // Null = periode belum punya invoice saat dibebaskan (Cuti untuk
            // bulan yang belum terbit). Terisi = invoice itu dibatalkan.
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'billing_period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_billing_waivers');
    }
};
