<?php

namespace Database\Seeders;

use App\Enums\TaskType;
use App\Models\InternetPackage;
use App\Models\PackageSlaSetting;
use Illuminate\Database\Seeder;

class PackageSlaSettingSeeder extends Seeder
{
    /**
     * Isi Master Timeline SLA default (fallback global TaskType::defaultHandlingSlaHours())
     * ke semua paket internet aktif, biar tidak ada paket tanpa SLA saat rollout.
     * Admin tinggal edit angka yang beda-beda lewat halaman Master Timeline SLA.
     */
    public function run(): void
    {
        InternetPackage::active()->each(function (InternetPackage $package) {
            foreach (TaskType::cases() as $type) {
                PackageSlaSetting::firstOrCreate(
                    [
                        'internet_package_id' => $package->id,
                        'task_type' => $type->value,
                    ],
                    [
                        'sla_duration' => $type->defaultHandlingSlaHours(),
                        'sla_unit' => 'hour',
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}
