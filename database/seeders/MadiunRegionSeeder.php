<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\Village;
use Illuminate\Database\Seeder;

class MadiunRegionSeeder extends Seeder
{
    /**
     * Seed the Madiun service area region master data.
     */
    public function run(): void
    {
        $city = City::query()->firstOrCreate(['name' => 'Kabupaten Madiun']);

        $districtsData = [
            'Kebonsari' => [
                'Tambakmas' => '63173',
                'Tanjungrejo' => '63173',
                'Sukorejo' => '63173',
                'Pucanganom' => '63173',
                'Krandegan' => '63173',
                'Singgahan' => '63173',
                'Sidorejo' => '63173',
                'Palur' => '63173',
                'Mojorejo' => '63173',
                'Kebonsari' => '63173',
                'Rejosari' => '63173',
                'Balerejo' => '63173',
                'Bacem' => '63173',
                'Kedondong' => '63173',
            ],
            'Geger' => [
                'Banaran' => '63171',
                'Klorogan' => '63171',
                'Slambur' => '63171',
                'Sareng' => '63171',
                'Purworejo' => '63171',
                'Sumberejo' => '63171',
                'Jatisari' => '63171',
                'Uteran' => '63171',
                'Pagotan' => '63171',
                'Jogodayuh' => '63171',
                'Nglandung' => '63171',
                'Samberejo' => '63171',
                'Putat' => '63171',
                'Sangen' => '63171',
                'Kertosari' => '63171',
                'Kertobanyon' => '63171',
                'Kaibon' => '63171',
                'Kranggan' => '63171',
            ],
            'Dolopo' => [
                'Lembah' => '63174',
                'Mlilir' => '63174',
                'Kradinan' => '63174',
                'Blimbing' => '63174',
                'Bader' => '63174',
                'Candimulyo' => '63174',
                'Glonggong' => '63174',
                'Dolopo' => '63174',
                'Doho' => '63174',
                'Ketawang' => '63174',
                'Bangunsari' => '63174',
            ],
            'Dagangan' => [
                'Ketandan' => '63172',
                'Tileng' => '63172',
                'Mendak' => '63172',
                'Padas' => '63172',
                'Ngranget' => '63172',
                'Joho' => '63172',
                'Dagangan' => '63172',
                'Jetis' => '63172',
                'Prambon' => '63172',
                'Banjarejo' => '63172',
                'Mruwak' => '63172',
                'Banjarsari Wetan' => '63172',
                'Banjarsari Kulon' => '63172',
                'Sewulan' => '63172',
                'Sukosari' => '63172',
            ],
            'Wungu' => [
                'Sidorejo' => '63181',
                'Pilangrejo' => '63181',
                'Mojopurno' => '63181',
                'Karangrejo' => '63181',
                'Mojorayung' => '63181',
                'Bantengan' => '63181',
                'Tempursari' => '63181',
                'Nglanduk' => '63181',
                'Nglambangan' => '63181',
                'Sobrah' => '63181',
            ],
            'Kare' => [
                'Bodag' => '63182',
                'Kare' => '63182',
                'Bolo' => '63182',
                'Kuwiran' => '63182',
                'Randualas' => '63182',
                'Cermo' => '63182',
                'Morang' => '63182',
            ],
            'Gemarang' => [
                'Batok' => '63156',
                'Durenan' => '63156',
                'Winong' => '63156',
                'Tawangrejo' => '63156',
                'Gemarang' => '63156',
                'Sebayi' => '63156',
                'Nampu' => '63156',
            ],
            'Saradan' => [
                'Bajulan' => '63155',
                'Sukorejo' => '63155',
                'Bongsopotro' => '63155',
                'Sidorejo' => '63155',
                'Sugihwaras' => '63155',
                'Bandungan' => '63155',
                'Pajaran' => '63155',
                'Klumutan' => '63155',
                'Sumbersari' => '63155',
                'Sambirejo' => '63155',
                'Sumberbendo' => '63155',
                'Klangon' => '63155',
            ],
            'Pilangkenceng' => [
                'Purworejo' => '63154',
                'Wonoayu' => '63154',
                'Kedungrejo' => '63154',
                'Kedungmaron' => '63154',
                'Sumbergandu' => '63154',
                'Pilangkenceng' => '63154',
                'Pulerejo' => '63154',
                'Ngale' => '63154',
                'Kedung Banteng' => '63154',
                'Luworo' => '63154',
                'Gandul' => '63154',
                'Ngengor' => '63154',
                'Kenongorejo' => '63154',
                'Dawuhan' => '63154',
            ],
            'Mejayan' => [
                'Blabakan' => '63153',
                'Wonorejo' => '63153',
                'Kebonagung' => '63153',
                'Darmorejo' => '63153',
                'Kaligunting' => '63153',
                'Sidodadi' => '63153',
                'Klecorejo' => '63153',
                'Kaliabu' => '63153',
                'Krajan' => '63153',
                'Pandeyan' => '63153',
                'Mejayan' => '63153',
                'Bangunsari' => '63153',
                'Ngampel' => '63153',
            ],
            'Wonoasri' => [
                'Ngadirejo' => '63157',
                'Jatirejo' => '63157',
                'Banyukambang' => '63157',
                'Sidomulyo' => '63157',
                'Plumpungrejo' => '63157',
                'Wonoasri' => '63157',
                'Bancong' => '63157',
                'Klitik' => '63157',
                'Purwosari' => '63157',
                'Buduran' => '63157',
            ],
            'Balerejo' => [
                'Garon' => '63152',
                'Balerejo' => '63152',
                'Kebonagung' => '63152',
                'Gading' => '63152',
                'Sumberbening' => '63152',
                'Bulakrejo' => '63152',
                'Tapelan' => '63152',
                'Babadan Lor' => '63152',
                'Warurejo' => '63152',
                'Kedungjati' => '63152',
                'Glonggong' => '63152',
                'Sogo' => '63152',
                'Banaran' => '63152',
                'Kedungrejo' => '63152',
                'Pacinan' => '63152',
                'Simo' => '63152',
            ],
            'Madiun' => [
                'Dempelan' => '63151',
                'Sendangrejo' => '63151',
                'Sirapan' => '63151',
                'Dimong' => '63151',
                'Tulungrejo' => '63151',
                'Sumberejo' => '63151',
                'Tanjungrejo' => '63151',
                'Banjarsari' => '63151',
                'Nglames' => '63151',
                'Tiron' => '63151',
                'Gunungsari' => '63151',
                'Bagi' => '63151',
            ],
            'Sawahan' => [
                'Kanung' => '63162',
                'Sidomulyo' => '63162',
                'Rejosari' => '63162',
                'Bakur' => '63162',
                'Pucangrejo' => '63162',
                'Krokeh' => '63162',
                'Lebakayu' => '63162',
                'Golan' => '63162',
                'Cabean' => '63162',
                'Sawahan' => '63162',
                'Kajang' => '63162',
                'Klumpit' => '63162',
            ],
            'Jiwan' => [
                'Sambirejo' => '63161',
                'Metesih' => '63161',
                'Jiwan' => '63161',
                'Sukolilo' => '63161',
                'Kincang Wetan' => '63161',
                'Kwangsen' => '63161',
                'Grobogan' => '63161',
                'Wayut' => '63161',
                'Klagen Serut' => '63161',
                'Teguhan' => '63161',
                'Bedoho' => '63161',
                'Bibrik' => '63161',
            ],
        ];

        foreach ($districtsData as $districtName => $villages) {
            $district = District::query()->firstOrCreate([
                'city_id' => $city->id,
                'name' => $districtName,
            ]);

            foreach ($villages as $villageName => $postalCode) {
                Village::query()->updateOrCreate([
                    'district_id' => $district->id,
                    'name' => $villageName,
                ], [
                    'postal_code' => $postalCode,
                ]);
            }
        }

        $city = City::query()->firstOrCreate(['name' => 'Kota Madiun']);

        $districtsData = [
            'Mangu Harjo' => [
                'Nambangan Kidul' => '63128',
                'Nambangan Lor' => '63129',
                'Manguharjo' => '63127',
                'Pangongangan' => '63121',
                'Winongo' => '63126',
                'Madiun Lor' => '63122',
                'Patihan' => '63123',
                'Ngegong' => '63125',
                'Sogaten' => '63124',
            ],
            'Taman' => [
                'Banjarejo' => '63137',
                'Demangan' => '63136',
                'Josenan' => '63134',
                'Kejuron' => '63132',
                'Kuncen' => '63135',
                'Manisrejo' => '63138',
                'Mojorejo' => '63139',
                'Pandean' => '63133',
                'Taman' => '63131',
            ],
            'Kartoharjo' => [
                'Kanigoro' => '63118',
                'Kartoharjo' => '63117',
                'Kelun' => '63112',
                'Klegen' => '63117',
                'Oro-Oro Ombo' => '63119',
                'Pilangbango' => '63119',
                'Rejomulyo' => '63111',
                'Sukosari' => '63119',
                'Tawangrejo' => '63113',
            ],
        ];

        foreach ($districtsData as $districtName => $villages) {
            $district = District::query()->firstOrCreate([
                'city_id' => $city->id,
                'name' => $districtName,
            ]);

            foreach ($villages as $villageName => $postalCode) {
                Village::query()->updateOrCreate([
                    'district_id' => $district->id,
                    'name' => $villageName,
                ], [
                    'postal_code' => $postalCode,
                ]);
            }
        }

    }
}
