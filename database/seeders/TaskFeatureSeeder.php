<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * TaskFeatureSeeder
 *
 * Menanamkan:
 * 1. Feature tree untuk modul Task Management (root + 2 sub-feature)
 * 2. Permission terpisah per kelompok: Aksi FOP & Aksi Teknisi
 * 3. Assign permission ke role FOP dan Teknisi
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=TaskFeatureSeeder
 */
class TaskFeatureSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. Root Feature ─────────────────────────────────────────

        $root = Feature::updateOrCreate(
            ['code' => 'tasks'],
            [
                'name'       => 'Task Management',
                'type'       => FeatureType::ROOT,
                'sort_order' => 11,
                'is_active'  => true,
                'parent_id'  => null,
            ]
        );

        // ─── 2. Sub-Feature: Aksi FOP ────────────────────────────────

        $subFop = Feature::updateOrCreate(
            ['code' => 'tasks.fop'],
            [
                'name'       => 'Aksi FOP (Koordinator Lapangan)',
                'type'       => FeatureType::SUB_FEATURE,
                'sort_order' => 1,
                'is_active'  => true,
                'parent_id'  => $root->id,
            ]
        );

        // ─── 3. Sub-Feature: Aksi Teknisi ────────────────────────────

        $subTeknisi = Feature::updateOrCreate(
            ['code' => 'tasks.teknisi'],
            [
                'name'       => 'Aksi Teknisi (Eksekutor Lapangan)',
                'type'       => FeatureType::SUB_FEATURE,
                'sort_order' => 2,
                'is_active'  => true,
                'parent_id'  => $root->id,
            ]
        );

        // ─── 4. Permissions FOP ──────────────────────────────────────

        $fopPermDefs = [
            ['code' => 'task.view.all',          'name' => 'Lihat Semua Task'],
            ['code' => 'task.create',            'name' => 'Buat Task'],
            ['code' => 'task.edit',              'name' => 'Edit Task'],
            ['code' => 'task.cancel',            'name' => 'Batalkan Task'],
            ['code' => 'task.schedule',          'name' => 'Jadwalkan Task'],
            ['code' => 'task.assign.team',       'name' => 'Assign Tim'],
            ['code' => 'task.report.view',       'name' => 'Lihat Laporan'],
            ['code' => 'task.conflict.override', 'name' => 'Override Konflik'],
            ['code' => 'task.reject',            'name' => 'Tolak Task'],
            ['code' => 'task.approve',           'name' => 'Setujui Task'],
            ['code' => 'task.status.pending',    'name' => 'Pending Task (FOP)'],
        ];

        $fopPermIds = [];
        foreach ($fopPermDefs as $pd) {
            // Cari berdasarkan code (unik), update name & feature_id
            $perm = Permission::where('code', $pd['code'])->first();
            if ($perm) {
                $perm->update(['name' => $pd['name'], 'feature_id' => $subFop->id]);
            } else {
                $perm = Permission::create([
                    'code'       => $pd['code'],
                    'name'       => $pd['name'],
                    'feature_id' => $subFop->id,
                ]);
            }
            $fopPermIds[] = $perm->id;
        }

        // ─── 5. Permissions Teknisi ──────────────────────────────────

        $teknisiPermDefs = [
            ['code' => 'task.view.own',          'name' => 'Lihat Task Sendiri'],
            ['code' => 'task.status.start',      'name' => 'Mulai Task'],
            ['code' => 'task.status.complete',   'name' => 'Selesaikan Task'],
            ['code' => 'task.checklist.update',  'name' => 'Update Checklist'],
            ['code' => 'task.evidence.upload',   'name' => 'Upload Bukti Foto'],
            ['code' => 'task.status.pending',    'name' => 'Laporan Nanti'],
        ];

        $teknisiPermIds = [];
        foreach ($teknisiPermDefs as $pd) {
            $perm = Permission::where('code', $pd['code'])->first();
            if ($perm) {
                $perm->update(['name' => $pd['name'], 'feature_id' => $subTeknisi->id]);
            } else {
                $perm = Permission::create([
                    'code'       => $pd['code'],
                    'name'       => $pd['name'],
                    'feature_id' => $subTeknisi->id,
                ]);
            }
            $teknisiPermIds[] = $perm->id;
        }

        // ─── 6. Assign permission ke role FOP ────────────────────────

        $fopRole = Role::where('code', 'fop')->first();
        if ($fopRole) {
            $fopRole->permissions()->syncWithoutDetaching($fopPermIds);
        }

        // ─── 7. Assign permission ke role Teknisi ────────────────────

        $teknisiRole = Role::where('code', 'teknisi')->first();
        if ($teknisiRole) {
            $teknisiRole->permissions()->syncWithoutDetaching($teknisiPermIds);
        }

        // ─── 8. Laporan ──────────────────────────────────────────────

        $this->command->info('TaskFeatureSeeder: sub-feature FOP & Teknisi created/updated.');
        $this->command->info('TaskFeatureSeeder: ' . count($fopPermDefs) . ' FOP permissions seeded.');
        $this->command->info('TaskFeatureSeeder: ' . count($teknisiPermDefs) . ' Teknisi permissions seeded.');
    }
}
