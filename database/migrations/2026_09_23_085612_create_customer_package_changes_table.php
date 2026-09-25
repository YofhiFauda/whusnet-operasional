<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail ganti paket (upgrade/downgrade, ADHOC-68) + basis hitung
     * n-segmen kalau paket diganti lebih dari sekali dalam satu periode.
     *
     * `old_monthly_price`/`new_monthly_price` SENGAJA disnapshot di sini
     * (bukan cuma `prorate_old_amount`/`prorate_new_amount` yang disebut di
     * rancangan awal) — supaya `CustomerPackageService` bisa merekonstruksi
     * ulang seluruh segmen periode dari riwayat baris ini tanpa bergantung ke
     * `internet_packages.monthly_price` SAAT INI, yang bisa saja sudah
     * berubah kalau admin mengedit master paket belakangan.
     */
    public function up(): void
    {
        Schema::create('customer_package_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('customer_service_id')->constrained('customer_services')->cascadeOnDelete();
            $table->foreignId('old_internet_package_id')->constrained('internet_packages');
            $table->foreignId('new_internet_package_id')->constrained('internet_packages');
            $table->decimal('old_monthly_price', 12, 2);
            $table->decimal('new_monthly_price', 12, 2);
            // Format 'Y-m' — periode invoice yang kena dampak prorate ini.
            $table->string('billing_period', 7);
            // Hari mulai paket baru berlaku (hari itu sendiri sudah masuk hitungan
            // paket baru — lihat CustomerPackageService::resolvePeriodWindow()).
            $table->date('effective_date');
            $table->unsignedInteger('days_in_period');
            $table->unsignedInteger('days_old_used');
            $table->unsignedInteger('days_new_used');
            $table->decimal('prorate_old_amount', 12, 2);
            $table->decimal('prorate_new_amount', 12, 2);
            $table->decimal('total_recomputed', 12, 2);
            // Snapshot invoice.paid_amount SAAT diproses — bukan buat dihitung
            // ulang, murni jejak audit "berapa yang sudah dibayar waktu itu".
            $table->decimal('previously_paid', 12, 2);
            $table->foreignId('resulting_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('deposit_mutation_id')->nullable()->constrained('customer_balance_mutations')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_service_id', 'billing_period'], 'cpc_service_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_package_changes');
    }
};
