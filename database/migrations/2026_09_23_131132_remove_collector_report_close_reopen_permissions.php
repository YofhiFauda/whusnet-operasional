<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tutup buku otomatis & kunci permanen (ADHOC-96): tombol Tutup Periode /
     * Buka Ulang dihapus, jadi dua permission-nya jadi yatim. Dihapus lewat
     * migration, bukan manual — PermissionGeneratorService cuma MENAMBAH dari
     * config/rbac.php, tidak pernah menghapus, jadi tanpa ini keduanya tetap
     * tampil di Role Matrix sebagai centang tanpa fungsi.
     */
    public function up(): void
    {
        $orphanCodes = ['collector_report.approve', 'collector_report.cancel'];

        DB::table('role_permissions')->whereIn('permission_id', function ($query) use ($orphanCodes) {
            $query->select('id')->from('permissions')->whereIn('code', $orphanCodes);
        })->delete();

        DB::table('permissions')->whereIn('code', $orphanCodes)->delete();
    }

    public function down(): void
    {
        // Tidak dikembalikan: fiturnya sudah dihapus permanen.
    }
};
