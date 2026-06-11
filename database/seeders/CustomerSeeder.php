<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\InternetPackage;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $city = City::query()->where('name', 'Ponorogo')->first() ?? City::query()->first();

        if (!$city) {
            return;
        }

        $districts = District::query()
            ->where('city_id', $city->id)
            ->with('villages')
            ->orderBy('name')
            ->get()
            ->filter(fn (District $district) => $district->villages->isNotEmpty())
            ->values();

        $packages = InternetPackage::query()->where('is_active', true)->orderBy('package_code')->get();

        if ($districts->isEmpty() || $packages->isEmpty()) {
            return;
        }

        $names = [
            'Budi Santoso', 'Siti Aminah', 'Joko Widodo', 'Dewi Lestari', 'Agus Susanto',
            'Rian Hidayat', 'Mega Utami', 'Adi Nugroho', 'Sri Wahyuni', 'Eko Prasetyo',
            'Indah Permatasari', 'Bambang Hermawan', 'Kartika Sari', 'Hendarto', 'Rina Marlina',
            'Dian Wijaya', 'Slamet Riyadi', 'Yuliana', 'Aris Munandar', 'Fitriani',
            'Heri Setiawan', 'Ani Suryani', 'Dedi Kurniawan', 'Tuti Alawiyah', 'Roni Wijaya',
            'Lilis Suryani', 'Andi Pratama', 'Novianti', 'Edi Sunarto', 'Ratna Sari'
        ];

        $statuses = [
            'registered',
            'waiting_survey',
            'surveyed',
            'waiting_installation',
            'installed',
            'active',
            'active',
            'active',
            'suspended',
            'terminated',
            'rejected',
        ];

        $streetNames = [
            'Jl. Raya Ponorogo',
            'Jl. Merdeka',
            'Jl. Sudirman',
            'Jl. Pahlawan',
            'Jl. Diponegoro',
            'Jl. Gajah Mada',
        ];

        $genders = ['Laki-laki', 'Perempuan'];

        $salesCodes = ['SLS-PON-001', 'SLS-PON-002', 'SLS-PON-003', 'SLS-MDN-001'];
        $agentCodes = ['AGT-BBD-001', 'AGT-JNG-002', 'AGT-SMN-003', 'AGT-MLR-004'];

        foreach ($names as $index => $name) {
            $num = $index + 1;
            $code = 'WHUS-2026-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);

            $district = $districts[$index % $districts->count()];
            $villages = $district->villages->sortBy('name')->values();
            $village = $villages[$index % $villages->count()];
            $package = $packages[$index % $packages->count()];
            $status = $statuses[$index % count($statuses)];
            $regDate = Carbon::create(2026, 1, 15)->addDays($index * 4);
            $gender = $genders[$index % count($genders)];

            $emailName = strtolower(str_replace(' ', '.', $name));
            $address = $streetNames[$index % count($streetNames)]
                . ' No. ' . (12 + $index)
                . ', RT ' . str_pad((string)(($index % 9) + 1), 2, '0', STR_PAD_LEFT)
                . '/RW ' . str_pad((string)(($index % 6) + 1), 2, '0', STR_PAD_LEFT)
                . ', ' . $village->name . ', ' . $district->name;

            $record = [
                'full_name' => $name,
                'identity_number' => '3502' . str_pad((string)(101990000000 + $num), 12, '0', STR_PAD_LEFT),
                'gender' => $gender,
                'email' => $emailName . str_pad((string)$num, 2, '0', STR_PAD_LEFT) . '@whusnet.test',
                'phone' => '08' . (12 + ($index % 8)) . str_pad((string)(34000000 + ($num * 1379)), 8, '0', STR_PAD_LEFT),
                'registration_date' => $regDate->format('Y-m-d'),
                'status' => $status,
                'address' => $address,
                'latitude' => -7.8650000 + ($index * 0.0021000),
                'longitude' => 111.4620000 + ($index * 0.0023000),
                'city_id' => $city->id,
                'district_id' => $district->id,
                'village_id' => $village->id,
                'internet_package_id' => $package->id,
                'contract_period_months' => $package->contract_period_months ?? 12,
                'discount_amount' => $index % 5 === 0 ? 25000 : 0,
                'tax_percent' => 11,
                'sales_code' => $salesCodes[$index % count($salesCodes)],
                'agent_code' => $agentCodes[$index % count($agentCodes)],
                'referral_customer_code' => $index === 0 ? 'CID-PON-0001' : 'WHUS-2026-' . str_pad((string)max(1, $index), 4, '0', STR_PAD_LEFT),
                'ont_sn' => 'ZTEG' . str_pad((string)(8600000000 + $num), 10, '0', STR_PAD_LEFT),
                'ip_address' => '10.200.' . (45 + ($index % 8)) . '.' . (10 + $num),
                'odp_code' => 'ODP-PON-' . str_pad((string)(20 + ($index % 18)), 3, '0', STR_PAD_LEFT),
                'olt_code' => 'OLT-PON-' . str_pad((string)(($index % 4) + 1), 2, '0', STR_PAD_LEFT),
                'vlan_id' => (string)(1000 + $num),
                'foto_ktp' => 'documents/simulasi/ktp-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT) . '.jpg',
                'foto_rumah' => 'documents/simulasi/rumah-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT) . '.jpg',
                'foto_kontrak' => 'documents/simulasi/kontrak-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT) . '.pdf',
                'created_at' => $regDate,
                'updated_at' => Carbon::now(),
            ];

            DB::table('customers')->updateOrInsert(
                ['customer_code' => $code],
                $record,
            );
        }
    }
}
