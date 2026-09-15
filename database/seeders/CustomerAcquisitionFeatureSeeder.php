<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Models\PackageCategory;
use App\Models\Role;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * CustomerAcquisitionFeatureSeeder
 *
 * Menanamkan Feature ROOT `customer_acquisitions` — "List Pelanggan Aktif
 * < 30 Hari Diverifikasi", dipantau tim Busdev buat perhitungan gaji/komisi
 * sales. Baris kebentuk otomatis (CustomerObserver) tiap pelanggan pertama
 * kali diverifikasi admin, dikelompokkan per bulan (`periode`) dan reset
 * sendiri tanggal 1 tanpa job/cron (lihat CustomerAcquisitionController).
 *
 * Sub-feature `customer_acquisitions.installation_fee` (UPDATE) — validasi
 * "Biaya Instalasi" khusus pelanggan paket Bisnis. Jalur UTAMA buat
 * menentukan siapa yang wajib validasi per kategori paket adalah ROLE
 * (`package_categories.installation_fee_approval_role_id`, dipilih admin
 * di Master Kategori Paket — nama role biasa, bukan kode permission
 * mentah). Permission ini sendiri tetap digenerate sebagai JALUR TEKNIS
 * kedua (lihat CustomerAcquisition::canBeValidatedBy()) — siapa pun yang
 * di-grant permission ini lewat Role Matrix biasa selalu lolos, apapun
 * role yang dikonfigurasi di kategori.
 *
 * Permission-nya digenerate oleh PermissionGeneratorService dari
 * config/rbac.php. Assignment default ke role diatur di RolePermissionSeeder,
 * jalankan lagi setelah seeder ini biar ke-sync (pola sama
 * RevenueCategoryFeatureSeeder).
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=CustomerAcquisitionFeatureSeeder
 */
class CustomerAcquisitionFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $root = Feature::updateOrCreate(
            ['code' => 'customer_acquisitions'],
            [
                'name' => 'Busdev - Pelanggan Aktif < 30 Hari',
                'type' => FeatureType::ROOT,
                'sort_order' => 24,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        Feature::updateOrCreate(
            ['code' => 'customer_acquisitions.installation_fee'],
            [
                'name' => 'Validasi Biaya Instalasi',
                'type' => FeatureType::SUB_FEATURE,
                'sort_order' => 1,
                'is_active' => true,
                'parent_id' => $root->id,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        // Default pemetaan awal — kategori "Bisnis" butuh validasi role
        // Business Development, sisanya (Home Broadband, Internet Khusus)
        // TIDAK diubah (tetap NULL = alur seperti sekarang). Ini cuma nilai
        // awal: admin bebas ganti per kategori kapan saja lewat Master
        // Kategori Paket, TERMASUK kategori baru yang ditambah belakangan —
        // gak perlu sentuh kode.
        $busdevRoleId = Role::where('code', 'business_development')->value('id');

        if ($busdevRoleId) {
            PackageCategory::where('name', 'like', '%Bisnis%')
                ->update(['installation_fee_approval_role_id' => $busdevRoleId]);
        }

        $this->command->info('CustomerAcquisitionFeatureSeeder: feature customer_acquisitions (+ sub-feature installation_fee) + permission digenerate, kategori Bisnis dipetakan ke role Business Development.');
    }
}
