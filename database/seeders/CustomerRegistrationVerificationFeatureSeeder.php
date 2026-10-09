<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * CustomerRegistrationVerificationFeatureSeeder
 *
 * Feature ROOT `customer_registration_verification` — antrean "Verifikasi
 * Registrasi" (pelanggan hasil Registrasi non-Skip-Survey yang masih
 * `WorkflowTransition::REGISTERED`, belum punya Task/FopTask Survey sampai
 * disetujui, lihat `CustomerRegistrationVerificationController`). ADHOC-73.
 *
 * Beda dari `BusinessDevelopmentVerificationFeatureSeeder`: approve & reject
 * di sini permission STATIS terpisah (bukan gerbang view + logic dinamis
 * per pelanggan) — dua aksi independen yang wajar dipisah PIC-nya.
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=CustomerRegistrationVerificationFeatureSeeder
 */
class CustomerRegistrationVerificationFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'customer_registration_verification'],
            [
                'name' => 'Verifikasi Registrasi',
                'type' => FeatureType::ROOT,
                'sort_order' => 26,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command->info('CustomerRegistrationVerificationFeatureSeeder: feature customer_registration_verification + permission digenerate.');
    }
}
