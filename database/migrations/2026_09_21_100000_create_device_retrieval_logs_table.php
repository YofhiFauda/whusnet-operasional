<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak pengambilan modem dari pelanggan, SATU BARIS PER SN (ADHOC-88).
     *
     * Kenapa tabel sendiri: `inventory_serials.customer_id` dikosongkan begitu
     * gudang menerima modem (SN itu nanti di-Issue ke pelanggan lain), dan
     * `customer_devices.device_retrieved_at` direset saat pelanggan "Langganan
     * Lagi". Tanpa tabel ini, "modem ini pernah diambil dari pelanggan siapa,
     * oleh teknisi siapa, diterima gudang siapa" hilang. Baris di sini TIDAK
     * boleh ikut hilang walau flag di tabel lain berubah.
     *
     * `serial_number` disalin (snapshot) supaya pencarian riwayat tidak
     * bergantung pada baris `inventory_serials` yang statusnya terus berubah.
     */
    public function up(): void
    {
        Schema::create('device_retrieval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('serial_id')->constrained('inventory_serials');
            $table->string('serial_number', 100);
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('source', 20);
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('retrieved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('warehouse_pop_id')->nullable()->constrained('pops')->nullOnDelete();
            $table->string('condition', 20)->nullable();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->string('condition_photo')->nullable();
            $table->json('accessories')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('retrieved_at');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'retrieved_at']);
            $table->index(['retrieved_by', 'retrieved_at']);
            $table->index(['warehouse_pop_id', 'retrieved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_retrieval_logs');
    }
};
