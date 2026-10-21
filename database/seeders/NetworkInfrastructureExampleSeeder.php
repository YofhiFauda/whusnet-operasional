<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * @deprecated The old example wrote aggregation_switch and pon_port nodes.
 * Use WhusnetTopologySeeder after verifying real POP and Mini POP locations.
 */
class NetworkInfrastructureExampleSeeder extends Seeder
{
    public function run(): void
    {
        throw new RuntimeException('Seeder contoh infrastruktur lama sudah dinonaktifkan; gunakan WhusnetTopologySeeder setelah verifikasi POP/Mini POP.');
    }
}
