<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * DeviceRetrievalReviewFeatureSeeder
 *
 * Feature ROOT `device_retrieval_review` — halaman "Review Penarikan Alat":
 * laporan DEAC yang gagal (modem tidak berhasil diambil) menunggu keputusan
 * FOP — Approve (pelanggan dibebaskan) atau Tolak (deact diulang lewat task
 * DEAC baru). CS & BD cuma melihat + berkomentar.
 *
 * Action `review` & `comment` baru (ActionCode) — ActionSeeder dipanggil di
 * sini supaya seeder ini cukup dijalankan sendiri di DB yang sudah jalan.
 *
 * docs/plan/warehouse/analisa-review-penarikan-deac.md
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=DeviceRetrievalReviewFeatureSeeder
 * lalu:     php artisan db:seed --class=RolePermissionSeeder
 */
class DeviceRetrievalReviewFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ActionSeeder::class);

        Feature::updateOrCreate(
            ['code' => 'device_retrieval_review'],
            [
                'name' => 'Review Penarikan Alat',
                'type' => FeatureType::ROOT,
                'sort_order' => 32,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('DeviceRetrievalReviewFeatureSeeder: feature device_retrieval_review + permission digenerate.');
    }
}
