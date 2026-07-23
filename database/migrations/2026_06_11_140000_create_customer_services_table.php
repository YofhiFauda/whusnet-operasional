<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('internet_package_id')->nullable()->constrained('internet_packages')->nullOnDelete();
            $table->string('package_name_snapshot', 150);
            $table->string('download_speed_snapshot', 50)->nullable();
            $table->string('upload_speed_snapshot', 50)->nullable();
            $table->decimal('monthly_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0.00);
            $table->decimal('ppn', 5, 2)->default(0.00);
            $table->decimal('total_monthly_bill', 12, 2);
            $table->date('activation_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('billing_cycle', 50)->default('monthly');
            $table->string('service_status', 50)->default('calon_pelanggan');
            $table->string('billing_status', 50)->default('pending');
            $table->timestamps();
        });

        // Copy existing package data from customers & internet_packages to customer_services
        $customers = DB::table('customers')->get();
        foreach ($customers as $customer) {
            if (isset($customer->internet_package_id)) {
                $package = DB::table('internet_packages')->where('id', $customer->internet_package_id)->first();
                if ($package) {
                    $monthlyPrice = $package->monthly_price;
                    $discount = $customer->discount_amount ?? 0.00;
                    $ppn = $customer->tax_percent ?? 0.00;

                    // Calculate total bill
                    $discountedPrice = max(0, $monthlyPrice - $discount);
                    $totalBill = $discountedPrice * (1 + $ppn / 100);

                    // Speeds labels
                    $downLabel = isset($package->download_speed_mbps) ? $package->download_speed_mbps.' Mbps' : null;
                    $upLabel = isset($package->upload_speed_mbps) ? $package->upload_speed_mbps.' Mbps' : null;

                    // Activation & Due dates
                    $activationDate = $customer->registration_date ?? null;
                    $dueDate = null;
                    if ($activationDate) {
                        $dueDate = Carbon::parse($activationDate)->addMonth()->format('Y-m-d');
                    }

                    // Statuses mapping
                    $serviceStatus = $customer->customer_status ?? 'calon_pelanggan';
                    $billingStatus = ($customer->status === 'active' || $serviceStatus === 'aktif') ? 'active' : 'pending';

                    DB::table('customer_services')->insert([
                        'customer_id' => $customer->id,
                        'internet_package_id' => $customer->internet_package_id,
                        'package_name_snapshot' => $package->name,
                        'download_speed_snapshot' => $downLabel,
                        'upload_speed_snapshot' => $upLabel,
                        'monthly_price' => $monthlyPrice,
                        'discount' => $discount,
                        'ppn' => $ppn,
                        'total_monthly_bill' => $totalBill,
                        'activation_date' => $activationDate,
                        'due_date' => $dueDate,
                        'billing_cycle' => 'monthly',
                        'service_status' => $serviceStatus,
                        'billing_status' => $billingStatus,
                        'created_at' => $customer->created_at ?? now(),
                        'updated_at' => $customer->updated_at ?? now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_services');
    }
};
