<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stok QUANTITY/ROLL yang dideploy ke titik infrastruktur jaringan
     * (ODC/ODP/splitter) — TERPISAH dari `inventory_balances` karena
     * `inventory_balances.pop_id` sengaja dibatasi ke POP bertipe
     * `pusat`/`cabang` (`Pop::WAREHOUSE_TYPES`), sedangkan `network_node_id`
     * di sini tidak punya batasan tipe POP sama sekali (§3.6 design spec).
     *
     * TANPA unique index (item_id, network_node_id, lot_no) — keunikan
     * ditegakkan Service (`lockForUpdate()` + `firstOrCreate()`, Task 10),
     * konsisten dengan `inventory_serials`. Jangan ditambahkan di migration
     * manapun tanpa menyesuaikan juga pola tulis Service-nya.
     */
    public function up(): void
    {
        Schema::create('infrastructure_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('lot_no', 50)->default('');
            $table->foreignId('network_node_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['item_id', 'network_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_balances');
    }
};
