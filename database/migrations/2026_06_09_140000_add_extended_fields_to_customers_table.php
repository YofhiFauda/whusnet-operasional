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
         Schema::table('customers', function (Blueprint $table) {
             // Personal Info details
             $table->string('identity_number', 50)->nullable()->after('full_name');
             $table->string('gender', 20)->nullable()->after('identity_number');
             
             // Coordinates
             $table->decimal('latitude', 10, 7)->nullable()->after('address');
             $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
             
             // Referrals
             $table->string('sales_code', 30)->nullable()->after('internet_package_id');
             $table->string('agent_code', 30)->nullable()->after('sales_code');
             $table->string('referral_customer_code', 30)->nullable()->after('agent_code');
             
             // Service details snapshot
             $table->integer('contract_period_months')->default(12)->after('internet_package_id');
             $table->decimal('discount_amount', 15, 2)->default(0.00)->after('contract_period_months');
             $table->decimal('tax_percent', 5, 2)->default(11.00)->after('discount_amount');
             
             // Technical specifications
             $table->string('ont_sn', 100)->nullable()->after('referral_customer_code');
             $table->string('ip_address', 45)->nullable()->after('ont_sn');
             $table->string('odp_code', 50)->nullable()->after('ip_address');
             $table->string('olt_code', 50)->nullable()->after('odp_code');
             $table->string('vlan_id', 20)->nullable()->after('olt_code');
         });
     }

     /**
      * Reverse the migrations.
      */
     public function down(): void
     {
         Schema::table('customers', function (Blueprint $table) {
             $table->dropColumn([
                 'identity_number',
                 'gender',
                 'latitude',
                 'longitude',
                 'sales_code',
                 'agent_code',
                 'referral_customer_code',
                 'contract_period_months',
                 'discount_amount',
                 'tax_percent',
                 'ont_sn',
                 'ip_address',
                 'odp_code',
                 'olt_code',
                 'vlan_id',
             ]);
         });
     }
};
