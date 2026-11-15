<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * WebPushDashboardFeatureSeeder
 *
 * Menanamkan Feature ROOT `webpush_dashboard` — Dashboard Delivery Push
 * Notification (ADHOC-288 Tahap 8, ditunda dari Tahap 6 item 4): delivery
 * rate (terkirim vs ack) per merek HP dan OS dari `push_deliveries` +
 * `push_subscriptions.device_name`. Permission SENDIRI, bukan numpang
 * fitur lain — audiens manajemen/infra, bukan operasional harian.
 *
 * Permission-nya digenerate otomatis oleh PermissionGeneratorService dari
 * config/rbac.php. Assignment ke role diatur di RolePermissionSeeder,
 * jalankan lagi setelah seeder ini biar ke-sync (pola sama FopAnalyticsFeatureSeeder).
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=WebPushDashboardFeatureSeeder
 */
class WebPushDashboardFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'webpush_dashboard'],
            [
                'name' => 'Dashboard Delivery Push Notification',
                'type' => FeatureType::ROOT,
                'sort_order' => 34,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('WebPushDashboardFeatureSeeder: feature webpush_dashboard digenerate. Jalankan RolePermissionSeeder biar ke-assign ke role.');
    }
}
