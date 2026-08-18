<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * TITIK NOL pencatatan kas.
     *
     * Masalah yang ditutup: setoran kolektor yang sudah diverifikasi SEBELUM
     * modul ini ada punya `cash_deposit_id` NULL — persis sama dengan "belum
     * disetor". Tanpa migrasi ini, saldo kas admin di hari pertama menampilkan
     * seluruh uang sejak sistem berjalan sebagai kewajiban setor yang belum
     * ditunaikan. Angka itu palsu: uangnya sudah lama masuk bank.
     *
     * Yang dibuat: SATU baris sentinel `saldo_awal` yang tidak mengklaim apa
     * pun — `declared_amount` 0, `depositor_id` NULL, `pop_id` NULL — lalu
     * seluruh sumber lama ditautkan padanya.
     *
     * Kenapa bukan cutoff tanggal (`cash_tracking_start_at`): cutoff menuntut
     * syarat KEDUA (`verified_at >= :start`) yang harus diulang di setiap query
     * kas — saldo, daftar sumber, rekap, laporan Owner. Cepat atau lambat ada
     * satu yang lupa, dan yang muncul adalah uang lama yang hidup lagi sebagai
     * kewajiban setor. Dengan sentinel, aturannya tetap satu
     * (`cash_deposit_id IS NULL`) dan tiap baris lama membawa sendiri alasan
     * kenapa ia tak dihitung.
     *
     * Kenapa bukan backfill retroaktif: membuat setoran "terverifikasi" per
     * admin per periode berarti mengarang riwayat serah-terima uang yang tak
     * pernah terjadi. Lima tahun lagi tak ada yang bisa membedakannya dari
     * setoran asli.
     *
     * IDEMPOTEN: hanya menyentuh baris yang `cash_deposit_id`-nya masih NULL.
     *
     * docs/plan/kolektor/analisa-setoran-kas-admin.md §7.
     */
    private const SENTINEL_NUMBER = 'SETKAS-0000-0000';

    public function up(): void
    {
        $sentinelId = DB::table('cash_deposits')
            ->where('deposit_number', self::SENTINEL_NUMBER)
            ->value('id');

        if (! $sentinelId) {
            $sentinelId = DB::table('cash_deposits')->insertGetId([
                'deposit_number' => self::SENTINEL_NUMBER,
                'depositor_id' => null,
                'pop_id' => null,
                'status' => 'saldo_awal',
                'declared_amount' => 0,
                'difference' => 0,
                'note' => 'Titik nol pencatatan kas. Transaksi sebelum '
                    .now()->format('d/m/Y')
                    .' tidak pernah tercatat di sistem, jadi tidak dihitung sebagai kewajiban setor.',
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Setoran kolektor yang SUDAH diperiksa kantor. Yang masih
        // `menunggu_verifikasi` SENGAJA dilewat: uangnya memang masih di tas
        // kolektor, dan sesudah diverifikasi nanti ia benar-benar menjadi
        // saldo admin lewat jalur normal.
        //
        // Setoran berstatus selisih ikut terserap agar tak masuk saldo kas,
        // tapi kewajiban kolektornya TIDAK ikut hilang: sisa kewajiban dihitung
        // dari `difference` & `settled_amount` di CollectorDeposit, yang tak
        // disentuh migrasi ini sama sekali.
        DB::table('collector_deposits')
            ->whereNull('cash_deposit_id')
            ->whereNotNull('verified_at')
            ->update(['cash_deposit_id' => $sentinelId]);

        // Pembayaran manual di kantor: tunai, sah, tak pernah lewat kolektor.
        // Non-tunai tidak diserap — ia tak pernah masuk saldo tunai sehingga
        // menautkannya justru merusak rekap non-tunai historis.
        DB::table('payments')
            ->whereNull('cash_deposit_id')
            ->whereNull('collector_deposit_id')
            ->whereNull('collected_by')
            ->where('payment_method', 'cash')
            ->where('payment_status', 'valid')
            ->update(['cash_deposit_id' => $sentinelId]);
    }

    public function down(): void
    {
        $sentinelId = DB::table('cash_deposits')
            ->where('deposit_number', self::SENTINEL_NUMBER)
            ->value('id');

        if (! $sentinelId) {
            return;
        }

        DB::table('collector_deposits')->where('cash_deposit_id', $sentinelId)->update(['cash_deposit_id' => null]);
        DB::table('payments')->where('cash_deposit_id', $sentinelId)->update(['cash_deposit_id' => null]);
        DB::table('cash_deposits')->where('id', $sentinelId)->delete();
    }
};
