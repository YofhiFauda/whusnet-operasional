<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * BusinessDevelopmentVerificationFeatureSeeder
 *
 * Feature ROOT `business_development_verification` — antrean "Menunggu
 * Verifikasi BD" (pelanggan kategori Bisnis yang sudah lolos CS tapi belum
 * resmi ACTIVE, lihat `BusinessDevelopmentVerificationController`). CUMA
 * permission VIEW buat menggerbangi akses ke halaman antrean/detailnya —
 * aksi TULIS ("Verifikasi & Aktifkan") gerbangnya DINAMIS per pelanggan
 * (`Customer::canInstallationFeeBeValidatedBy()`, dari role yang
 * dikonfigurasi admin di Master Kategori Paket), bukan permission statis
 * kedua di sini — pola sama modul `customer_acquisitions`.
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=BusinessDevelopmentVerificationFeatureSeeder
 */
class BusinessDevelopmentVerificationFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'business_development_verification'],
            [
                'name' => 'Verifikasi BD',
                'type' => FeatureType::ROOT,
                'sort_order' => 25,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command->info('BusinessDevelopmentVerificationFeatureSeeder: feature business_development_verification + permission digenerate.');
    }
}
