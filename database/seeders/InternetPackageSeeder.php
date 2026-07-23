<?php

namespace Database\Seeders;

use App\Models\InternetPackage;
use Illuminate\Database\Seeder;

class InternetPackageSeeder extends Seeder
{
    /**
     * Seed WHUSNET internet service package master data.
     */
    public function run(): void
    {
        foreach ($this->packages() as $package) {
            InternetPackage::query()->updateOrCreate([
                'package_code' => $package['package_code'],
            ], $package);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function packages(): array
    {
        return [
            $this->home('Net138', 'Reguler Broadband Home Internet Only', '50 Mbps', 50, 138000, 'Singleband', [], null, 12, 'Gratis', 'Kontrak 12M'),
            $this->home('Net150', 'Reguler Broadband Home Internet Only', '100 Mbps', 100, 150000, 'Dualband', [], null, 8, 'Gratis', 'Kontrak 8M'),
            $this->home('Net165', 'Reguler Broadband Home Internet Only', '150 Mbps', 150, 165000, 'Dualband', [], null, 6, 'Gratis', 'Kontrak 6M'),
            $this->home('Net198', 'Reguler Broadband Home Internet Only', '200 Mbps', 200, 198000, 'Dualband Wifi6', ['CCTV 1CH'], 200000, 6, 'Gratis + 200rb jika ambil CCTV', 'Kontrak 6M'),

            $this->home('NetTC138', 'Broadband Internet + TV', '40 Mbps', 40, 138000, 'Singleband', ['IPTV'], 50000, 12, 'Rp 50.000', 'Kontrak 12'),
            $this->home('NetTC150', 'Broadband Internet + TV', '70 Mbps', 70, 150000, 'Dualband', ['IPTV'], 50000, 8, 'Rp 50.000', 'Kontrak 8'),
            $this->home('NetTC165', 'Broadband Internet + TV', '100 Mbps', 100, 165000, 'Dualband', ['IPTV'], 50000, 6, 'Rp 50.000', 'Kontrak 6'),
            $this->home('NetTC198', 'Broadband Internet + TV', '150 Mbps', 150, 198000, 'Dualband Wifi6', ['IPTV', 'CCTV 1CH'], 250000, 6, 'Rp 200.000 CCTV + Rp 50.000 IPTV', 'Kontrak 6'),

            $this->home('NetP110', 'Penawaran Khusus', '25 Mbps', 25, 110000, null, [], null, null, '-', 'Khusus pelanggan lama, minimal langganan 6 bulan'),
            $this->home('NetP125', 'Penawaran Khusus', '30 Mbps', 30, 125000, null, [], null, null, '-', 'Khusus pelanggan lama, minimal langganan 6 bulan'),

            $this->businessBroadband('NetSoLite75', '70 Mbps 1:8', 70, 8, 450000, ['Unlimited', '1 AP + 1 Router'], 'IP Private', 500000),
            $this->businessBroadband('NetSoLite100', '100 Mbps 1:8', 100, 8, 550000, ['Unlimited', '1 AP + 1 Router'], 'IP Private', 500000),
            $this->businessBroadband('NetSo100', '100 Mbps 1:4', 100, 4, 700000, ['Unlimited', '1 AP & 1 Router'], '1 Public Static', 250000),
            $this->businessBroadband('NetSo200', '200 Mbps 1:4', 200, 4, 1350000, ['Unlimited', '1 AP & 1 Router'], '1 Public Static', 2500000),
            $this->businessBroadband('NetSo300', '300 Mbps 1:4', 300, 4, 2150000, ['Unlimited', '1 AP & 1 Router'], '1 Public Static', 2500000),
            $this->businessBroadband('NetSo500', '500 Mbps 1:4', 500, 4, 3200000, ['Unlimited', '2 AP & 1 Router'], '2 Public Static', 2500000),
            $this->businessBroadband('NetSo1G', '1 Gbps 1:4', 1000, 4, 5900000, ['Unlimited', '3 AP & 1 Router'], '2 Public Static', 2500000),

            $this->sme('NetBLite25', 'Up To 35 Mbps', 35, 200000, ['Login Portal', 'Bandwidth Management', '1 AP Wifi6'], 5, 150000),
            $this->sme('NetBLite55', 'Up To 55 Mbps', 55, 250000, ['Login Portal', 'Bandwidth Management', '1 AP Wifi6'], 7, 150000),
            $this->sme('NetBLite110', 'Up To 110 Mbps', 110, 390000, ['Login Portal', 'Bandwidth Management', '1 AP Wifi6 + 1 AP Wifi5'], 15, 250000),
            $this->sme('NetBLite165', 'Up To 165 Mbps', 165, 490000, ['Login Portal', 'Bandwidth Management', '1 AP Wifi6 + 1 AP Wifi6'], 30, 250000),
            $this->sme('NetBLite330', 'Up To 330 Mbps', 330, 690000, ['Login Portal', 'Bandwidth Management', '1 AP Wifi6 + 1 AP Wifi6'], 50, 250000),
            $this->sme('NetBLite550', 'Up To 550 Mbps', 550, 980000, ['Login Portal', 'Bandwidth Management', '1 AP Wifi6 + 1 AP Wifi6'], 60, 250000),

            $this->dedicated('Dedicated100', '100 Mbps', 100, 3500000, ['2 AP Wifi6 & 1 Router'], '1 IP Public', 2500000),
            $this->dedicated('Dedicated250', '250 Mbps', 250, 6500000, ['2 AP Wifi6 & 1 Router'], '1 IP Public', 2500000),
            $this->dedicated('Dedicated500', '500 Mbps', 500, 12000000, ['2 AP Wifi6 & 1 Router'], '2 IP Public', 2500000),
            $this->dedicated('Dedicated1G', '1 Gbps', 1000, 23000000, ['2 AP Wifi6 & 1 Router'], '2 IP Public', 2500000),
        ];
    }

    /**
     * @param  array<int, string>  $features
     * @return array<string, mixed>
     */
    private function home(
        string $code,
        string $group,
        string $bandwidthLabel,
        int $downloadSpeed,
        int $monthlyPrice,
        ?string $modem,
        array $features,
        ?int $installationFee,
        ?int $contractMonths,
        string $installationFeeLabel,
        string $terms,
    ): array {
        return $this->package($code, 'Paket Home Broadband', $group, $bandwidthLabel, $downloadSpeed, $monthlyPrice, [
            'modem' => $modem,
            'features' => $features,
            'contract_period_months' => $contractMonths,
            'installation_fee' => $installationFee,
            'installation_fee_label' => $installationFeeLabel,
            'terms' => $terms,
        ]);
    }

    /**
     * @param  array<int, string>  $features
     * @return array<string, mixed>
     */
    private function businessBroadband(
        string $code,
        string $bandwidthLabel,
        int $downloadSpeed,
        int $contentionRatio,
        int $monthlyPrice,
        array $features,
        string $ipAddressType,
        int $installationFee,
    ): array {
        return $this->package($code, 'Paket Bisnis Broadband', 'Bisnis Broadband 1:4 & 1:8', $bandwidthLabel, $downloadSpeed, $monthlyPrice, [
            'contention_ratio' => $contentionRatio,
            'features' => $features,
            'ip_address_type' => $ipAddressType,
            'contract_period_months' => 12,
            'installation_fee' => $installationFee,
            'installation_fee_label' => 'Rp '.number_format($installationFee, 0, ',', '.'),
            'terms' => 'Masa kontrak 1 Tahun',
        ]);
    }

    /**
     * @param  array<int, string>  $features
     * @return array<string, mixed>
     */
    private function sme(
        string $code,
        string $bandwidthLabel,
        int $downloadSpeed,
        int $monthlyPrice,
        array $features,
        int $maxUsers,
        int $registrationFee,
    ): array {
        return $this->package($code, 'Paket Bisnis UKM', 'Cafe & Warung Kopi / UKM', $bandwidthLabel, $downloadSpeed, $monthlyPrice, [
            'features' => $features,
            'max_users' => $maxUsers,
            'installation_fee' => $registrationFee,
            'installation_fee_label' => 'Biaya Registrasi Rp '.number_format($registrationFee, 0, ',', '.'),
        ]);
    }

    /**
     * @param  array<int, string>  $features
     * @return array<string, mixed>
     */
    private function dedicated(
        string $code,
        string $bandwidthLabel,
        int $downloadSpeed,
        int $monthlyPrice,
        array $features,
        string $ipAddressType,
        int $installationFee,
    ): array {
        return $this->package($code, 'Paket Bisnis Dedicated', 'Internet Bisnis Dedicated', $bandwidthLabel, $downloadSpeed, $monthlyPrice, [
            'features' => $features,
            'ip_address_type' => $ipAddressType,
            'contract_period_months' => 12,
            'installation_fee' => $installationFee,
            'installation_fee_label' => 'Rp '.number_format($installationFee, 0, ',', '.'),
            'terms' => 'Masa kontrak 1 Tahun',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function package(
        string $code,
        string $category,
        string $group,
        string $bandwidthLabel,
        int $downloadSpeed,
        int $monthlyPrice,
        array $overrides = [],
    ): array {
        return array_merge([
            'package_code' => $code,
            'name' => $code,
            'category' => $category,
            'package_group' => $group,
            'bandwidth_label' => $bandwidthLabel,
            'download_speed_mbps' => $downloadSpeed,
            'upload_speed_mbps' => null,
            'contention_ratio' => null,
            'monthly_price' => $monthlyPrice,
            'ppn' => 0,
            'discount_default' => 0,
            'total_price' => $monthlyPrice,
            'modem' => null,
            'features' => [],
            'max_users' => null,
            'ip_address_type' => null,
            'contract_period_months' => null,
            'installation_fee' => null,
            'installation_fee_label' => null,
            'profile' => null,
            'technical_profile' => null,
            'terms' => null,
            'description' => null,
            'is_active' => true,
        ], $overrides);
    }
}
