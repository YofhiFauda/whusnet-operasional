<?php

namespace Database\Seeders;

use App\Models\SubscriptionStatus;
use Illuminate\Database\Seeder;

class SubscriptionStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'code' => 'registered',
                'name' => 'Registered',
                'workflow_order' => 1,
                'badge_color' => 'slate',
                'description' => 'Calon pelanggan sudah terdaftar dan data awal masuk ke sistem.',
                'is_terminal' => false,
            ],
            [
                'code' => 'waiting_survey',
                'name' => 'Waiting Survey',
                'workflow_order' => 2,
                'badge_color' => 'sky',
                'description' => 'Menunggu jadwal survey kelayakan lokasi dan jalur instalasi.',
                'is_terminal' => false,
            ],
            [
                'code' => 'surveyed',
                'name' => 'Surveyed',
                'workflow_order' => 3,
                'badge_color' => 'blue',
                'description' => 'Survey selesai dan hasil kelayakan sudah dicatat.',
                'is_terminal' => false,
            ],
            [
                'code' => 'waiting_installation',
                'name' => 'Waiting Installation',
                'workflow_order' => 4,
                'badge_color' => 'amber',
                'description' => 'Menunggu jadwal pemasangan perangkat dan penarikan kabel.',
                'is_terminal' => false,
            ],
            [
                'code' => 'installed',
                'name' => 'Installed',
                'workflow_order' => 5,
                'badge_color' => 'blue',
                'description' => 'Instalasi fisik selesai dan layanan menunggu aktivasi.',
                'is_terminal' => false,
            ],
            [
                'code' => 'active',
                'name' => 'Active',
                'workflow_order' => 6,
                'badge_color' => 'green',
                'description' => 'Layanan sudah aktif dan pelanggan masuk siklus tagihan berjalan.',
                'is_terminal' => false,
            ],
            [
                'code' => 'suspended',
                'name' => 'Suspended',
                'workflow_order' => 7,
                'badge_color' => 'amber',
                'description' => 'Layanan diisolir sementara karena alasan operasional atau pembayaran.',
                'is_terminal' => false,
            ],
            [
                'code' => 'terminated',
                'name' => 'Terminated',
                'workflow_order' => 8,
                'badge_color' => 'red',
                'description' => 'Langganan dihentikan dan tidak lagi aktif.',
                'is_terminal' => true,
            ],
            [
                'code' => 'rejected',
                'name' => 'Rejected',
                'workflow_order' => 9,
                'badge_color' => 'red',
                'description' => 'Registrasi ditolak, misalnya karena lokasi tidak layak atau data tidak valid.',
                'is_terminal' => true,
            ],
        ];

        foreach ($statuses as $status) {
            SubscriptionStatus::updateOrCreate(
                ['code' => $status['code']],
                $status + ['is_active' => true],
            );
        }
    }
}
