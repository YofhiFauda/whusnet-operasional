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
        Schema::table('fop_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('fop_tasks', 'task_date')) {
                $table->dateTime('task_date')->nullable()->after('category')->comment('Tanggal & Waktu Task');
            }
            if (! Schema::hasColumn('fop_tasks', 'pop_id')) {
                $table->foreignId('pop_id')->nullable()->after('village_id')->constrained('pops')->onDelete('restrict')->comment('POP / Cabang');
            }
            if (! Schema::hasColumn('fop_tasks', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('pop_id')->constrained('customers')->nullOnDelete()->comment('Terkait dengan Pelanggan (opsional)');
            }
            if (! Schema::hasColumn('fop_tasks', 'notes')) {
                $table->text('notes')->nullable()->after('issue')->comment('Catatan');
            }

            // Make issue nullable if it wasn't
            $table->string('issue')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->dropForeign(['pop_id']);
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['task_date', 'pop_id', 'customer_id', 'notes']);

            $table->string('issue')->nullable(false)->change();
        });
    }
};
