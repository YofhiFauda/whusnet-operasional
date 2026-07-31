<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `tickets.customer_village` — snapshot nama desa saat tiket dibuat.
 *
 * SENGAJA snapshot, bukan join ke `customer.village` (lihat
 * docs/plan/analisa-halaman-history-ticketing.md §4.2): pelanggan bisa pindah
 * desa, dan history yang ikut berubah retroaktif bikin rekap bulan lalu bohong.
 * Sejajar dengan snapshot yang sudah ada (`customer_address`, `customer_phone`,
 * `customer_package`, `customer_odp`, `customer_device`, koordinat).
 *
 * Backfill tiket lama diambil dari `customers.village_id` SEKARANG — itu
 * perkiraan terbaik yang tersedia, BUKAN desa pelanggan pada saat tiket dulu
 * dibuat. Dicatat di sini biar gak dikira data historis asli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('customer_village', 150)->nullable()->after('customer_address');
        });

        DB::table('tickets')
            ->whereNull('customer_village')
            ->whereNotNull('customer_id')
            ->select('id', 'customer_id')
            ->orderBy('id')
            ->chunk(500, function ($tickets) {
                foreach ($tickets as $ticket) {
                    $villageId = DB::table('customers')->where('id', $ticket->customer_id)->value('village_id');

                    if (! $villageId) {
                        continue;
                    }

                    $name = DB::table('villages')->where('id', $villageId)->value('name');

                    if ($name) {
                        DB::table('tickets')->where('id', $ticket->id)->update(['customer_village' => $name]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('customer_village');
        });
    }
};
