<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * CreqBillingVerificationFeatureSeeder
 *
 * Feature ROOT `creq_billing_verification` — antrean "Verifikasi Biaya
 * C-REQ" (task C-REQ yang ditandai berbayar oleh teknisi lewat Laporan
 * C-REQ, menunggu disetujui/ditolak CS sebelum boleh diteruskan ke Tagihan
 * Manual). Lihat `TaskCreqBillingController`.
 *
 * Pola sama persis `CustomerRegistrationVerificationFeatureSeeder` — approve
 * & reject permission STATIS terpisah dari view (bukan gerbang view + logic
 * dinamis), karena keduanya aksi independen yang wajar dipisah PIC-nya.
 *
 * docs/plan/task-teknisi/rancangan-biaya-creq-verifikasi-cs.md
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=CreqBillingVerificationFeatureSeeder
 */
class CreqBillingVerificationFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'creq_billing_verification'],
            [
                'name' => 'Verifikasi Biaya C-REQ',
                'type' => FeatureType::ROOT,
                'sort_order' => 31,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('CreqBillingVerificationFeatureSeeder: feature creq_billing_verification + permission digenerate.');
    }
}
