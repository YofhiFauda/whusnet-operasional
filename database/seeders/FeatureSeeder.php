<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Root Features
        $features = [
            [
                'code' => 'dashboard',
                'name' => 'Dashboard',
                'type' => FeatureType::ROOT,
                'sort_order' => 1,
            ],
            // Blok Master Data (sort_order 2-7) sengaja dibikin nyambung berurutan,
            // niru urutan tampil grup dropdown "Master Data" di sidebar:
            // Wilayah -> POP/Cabang -> Distribusi -> Paket Internet -> Status Pelanggan -> Timeline SLA
            // (Timeline SLA sort_order-nya di-set di SlaTimelineFeatureSeeder.php, harus 7).
            [
                'code' => 'master_wilayah',
                'name' => 'Master Data Wilayah',
                'type' => FeatureType::ROOT,
                'sort_order' => 2,
            ],
            [
                'code' => 'pops',
                'name' => 'POP/Cabang',
                'type' => FeatureType::ROOT,
                'sort_order' => 3,
            ],
            [
                'code' => 'master_distribusi',
                'name' => 'Master Distribusi',
                'type' => FeatureType::ROOT,
                'sort_order' => 4,
            ],
            [
                'code' => 'packages',
                'name' => 'Paket Internet',
                'type' => FeatureType::ROOT,
                'sort_order' => 5,
            ],
            [
                'code' => 'master_status_pelanggan',
                'name' => 'Master Status Pelanggan',
                'type' => FeatureType::ROOT,
                'sort_order' => 6,
            ],
            [
                'code' => 'users',
                'name' => 'User Management',
                'type' => FeatureType::ROOT,
                'sort_order' => 8,
            ],
            [
                'code' => 'roles',
                'name' => 'Role & Permission',
                'type' => FeatureType::ROOT,
                'sort_order' => 9,
            ],
            [
                'code' => 'customers',
                'name' => 'Pelanggan',
                'type' => FeatureType::ROOT,
                'sort_order' => 10,
            ],
            [
                'code' => 'invoices',
                'name' => 'Tagihan',
                'type' => FeatureType::ROOT,
                'sort_order' => 11,
            ],
            [
                'code' => 'payments',
                'name' => 'Pembayaran',
                'type' => FeatureType::ROOT,
                'sort_order' => 12,
            ],
            [
                // Worklist read-only kolektor — permission SENDIRI, bukan
                // `customers.view`. Kolektor cuma boleh baca pelanggan yang
                // ter-assign ke dirinya sendiri (§B-8 no. 5), bukan seluruh
                // daftar pelanggan yang `customers.view` bukakan.
                'code' => 'kolektor',
                'name' => 'Worklist Kolektor',
                'type' => FeatureType::ROOT,
                'sort_order' => 12,
            ],
            [
                'code' => 'reports',
                'name' => 'Laporan',
                'type' => FeatureType::ROOT,
                'sort_order' => 13,
            ],
            [
                'code' => 'audit_logs',
                'name' => 'Audit Log',
                'type' => FeatureType::ROOT,
                'sort_order' => 14,
            ],
            [
                'code' => 'fop_tasks',
                'name' => 'Tiket FOP — Perencanaan & Kategori',
                'type' => FeatureType::ROOT,
                'sort_order' => 15,
            ],
            // Root sendiri, sengaja dipisah dari 'customers' — sebelumnya nested
            // sebagai mini-feature anak 'customers.detail', jadi JS dependency
            // chaining di matrix.blade.php (checkbox anak auto-nyentang parent)
            // bikin role Teknisi yang cuma dikasih akses Survey/Pemasangan otomatis
            // ikut kebawa dapet customers.view + customers.detail.view (bisa lihat
            // List & Detail Pelanggan penuh lintas status). Kode permission
            // ('customers.detail.survey.view' dst) TIDAK berubah — kode permission
            // = feature.code + action.code, independen dari parent_id
            // (lihat PermissionGeneratorService::generate()), jadi route/controller/
            // config/rbac.php tidak perlu disentuh.
            [
                'code' => 'customers.detail.survey',
                'name' => 'Survey Pelanggan',
                'type' => FeatureType::ROOT,
                'sort_order' => 17,
            ],
            [
                'code' => 'customers.detail.installation',
                'name' => 'Pemasangan Pelanggan',
                'type' => FeatureType::ROOT,
                'sort_order' => 18,
            ],
        ];

        $rootFeatureIds = [];
        foreach ($features as $f) {
            $model = Feature::updateOrCreate(
                ['code' => $f['code']],
                [
                    'name' => $f['name'],
                    'type' => $f['type'],
                    'sort_order' => $f['sort_order'],
                    'is_active' => true,
                    'parent_id' => null,
                ]
            );
            $rootFeatureIds[$f['code']] = $model->id;
        }

        // 2. Sub Features of 'customers'
        $subFeatures = [
            [
                'parent_code' => 'customers',
                'code' => 'customers.import',
                'name' => 'Import Pelanggan',
                'type' => FeatureType::SUB_FEATURE,
                'sort_order' => 1,
            ],
            [
                'parent_code' => 'customers',
                'code' => 'customers.detail',
                'name' => 'Detail Pelanggan',
                'type' => FeatureType::SUB_FEATURE,
                'sort_order' => 2,
            ],
            [
                'parent_code' => 'customers',
                'code' => 'customers.terminated',
                'name' => 'List Pelanggan Putus',
                'type' => FeatureType::SUB_FEATURE,
                'sort_order' => 3,
            ],
            [
                'parent_code' => 'customers',
                'code' => 'customers.failed',
                'name' => 'List Pelanggan Gagal',
                'type' => FeatureType::SUB_FEATURE,
                'sort_order' => 4,
            ],
        ];

        $subFeatureIds = [];
        foreach ($subFeatures as $sf) {
            $parentId = $rootFeatureIds[$sf['parent_code']] ?? null;
            $model = Feature::updateOrCreate(
                ['code' => $sf['code']],
                [
                    'name' => $sf['name'],
                    'type' => $sf['type'],
                    'sort_order' => $sf['sort_order'],
                    'is_active' => true,
                    'parent_id' => $parentId,
                ]
            );
            $subFeatureIds[$sf['code']] = $model->id;
        }

        // 3. Mini Features of 'customers.detail'
        $miniFeatures = [
            [
                'parent_code' => 'customers.detail',
                'code' => 'customers.detail.identity',
                'name' => 'Identitas Pelanggan',
                'type' => FeatureType::MINI_FEATURE,
                'sort_order' => 1,
            ],
            [
                'parent_code' => 'customers.detail',
                'code' => 'customers.detail.address',
                'name' => 'Alamat Pelanggan',
                'type' => FeatureType::MINI_FEATURE,
                'sort_order' => 2,
            ],
            [
                'parent_code' => 'customers.detail',
                'code' => 'customers.detail.packages',
                'name' => 'Paket & Layanan',
                'type' => FeatureType::MINI_FEATURE,
                'sort_order' => 3,
            ],
            [
                'parent_code' => 'customers.detail',
                'code' => 'customers.detail.devices',
                'name' => 'Perangkat Pelanggan',
                'type' => FeatureType::MINI_FEATURE,
                'sort_order' => 4,
            ],
            [
                'parent_code' => 'customers.detail',
                'code' => 'customers.detail.documents',
                'name' => 'Dokumen Pelanggan',
                'type' => FeatureType::MINI_FEATURE,
                'sort_order' => 5,
            ],
        ];

        foreach ($miniFeatures as $mf) {
            $parentId = $subFeatureIds[$mf['parent_code']] ?? null;
            Feature::updateOrCreate(
                ['code' => $mf['code']],
                [
                    'name' => $mf['name'],
                    'type' => $mf['type'],
                    'sort_order' => $mf['sort_order'],
                    'is_active' => true,
                    'parent_id' => $parentId,
                ]
            );
        }
    }
}
