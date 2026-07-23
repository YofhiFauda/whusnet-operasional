<?php

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
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'old_customer_id')) {
                $table->string('old_customer_id', 50)->nullable()->after('customer_code');
            }
            if (! Schema::hasColumn('customers', 'cid')) {
                $table->string('cid', 50)->nullable()->after('old_customer_id');
            }
            if (! Schema::hasColumn('customers', 'primary_phone')) {
                $table->string('primary_phone', 20)->nullable()->after('gender');
            }
            if (! Schema::hasColumn('customers', 'alternative_phone')) {
                $table->string('alternative_phone', 20)->nullable()->after('primary_phone');
            }
            if (! Schema::hasColumn('customers', 'data_completeness_status')) {
                $table->string('data_completeness_status', 50)->default('draft')->after('registration_date');
            }
            if (! Schema::hasColumn('customers', 'customer_status')) {
                $table->string('customer_status', 50)->default('calon_pelanggan')->after('data_completeness_status');
            }
            if (! Schema::hasColumn('customers', 'pop_id')) {
                $table->foreignId('pop_id')->nullable()->after('customer_status')->constrained('pops')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        // Copy phone data to primary_phone if empty
        if (Schema::hasColumn('customers', 'phone') && Schema::hasColumn('customers', 'primary_phone')) {
            DB::statement("UPDATE customers SET primary_phone = phone WHERE primary_phone IS NULL OR primary_phone = ''");
        }

        // Copy status values to customer_status based on mapping
        if (Schema::hasColumn('customers', 'status') && Schema::hasColumn('customers', 'customer_status')) {
            DB::statement("
                UPDATE customers SET customer_status = CASE 
                    WHEN status = 'active' THEN 'aktif'
                    WHEN status = 'suspended' THEN 'isolir'
                    WHEN status = 'terminated' THEN 'berhenti'
                    WHEN status = 'rejected' THEN 'nonaktif'
                    WHEN status = 'waiting_survey' OR status = 'surveyed' THEN 'survey'
                    WHEN status = 'waiting_installation' OR status = 'installed' THEN 'menunggu_pemasangan'
                    ELSE 'calon_pelanggan'
                END
                WHERE customer_status = 'calon_pelanggan' OR customer_status IS NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['pop_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);

            $table->dropColumn([
                'old_customer_id',
                'cid',
                'primary_phone',
                'alternative_phone',
                'data_completeness_status',
                'customer_status',
                'pop_id',
                'created_by',
                'updated_by',
            ]);
        });
    }
};
