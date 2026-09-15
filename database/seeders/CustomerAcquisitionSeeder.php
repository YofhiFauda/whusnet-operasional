<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerService;
use App\Models\Pop;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * CustomerAcquisitionSeeder — DATA CONTOH, buat lihat tampilan modul Customer
 * Acquisition (Busdev) terisi. JANGAN dijalankan di production (nama & alamat
 * di sini karangan Faker, bukan pelanggan asli).
 *
 * Nyebar baris ke 3 periode (bulan berjalan + 2 bulan lalu) biar filter
 * periode kelihatan bedanya. Ditulis LANGSUNG ke `customer_acquisitions`
 * (bukan lewat CustomerObserver/transisi status asli) — buat demo data
 * isinya cukup, gak perlu simulasi alur verifikasi penuh. "Harga Dikurangi
 * PPN" TIDAK diisi di sini — dihitung otomatis oleh accessor model dari
 * `customer_services.total_monthly_bill` yang ditanam di bawah.
 *
 * Jalankan: php artisan db:seed --class=CustomerAcquisitionSeeder
 */
class CustomerAcquisitionSeeder extends Seeder
{
    public function run(): void
    {
        // Pop/Cabang contoh — namanya sengaja mirip pola di lapangan
        // (nama kecamatan/lokasi OLT), biar kolom "POP/Cabang" di tabel gak
        // kosong/aneh.
        $pops = collect(['Slahong', 'Whusnet', 'Siman', 'Pacitan Kota'])
            ->map(fn ($name) => Pop::firstOrCreate(
                ['name' => $name],
                ['code' => Str::slug($name, '_'), 'type' => 'cabang']
            ));

        $periodes = [
            now()->format('Y-m'),
            now()->subMonth()->format('Y-m'),
            now()->subMonths(2)->format('Y-m'),
        ];

        foreach ($periodes as $periodeIndex => $periode) {
            $jumlahBaris = $periodeIndex === 0 ? 6 : 4; // bulan berjalan lebih ramai

            for ($i = 0; $i < $jumlahBaris; $i++) {
                $pop = $pops->random();
                $hargaLangganan = fake()->randomElement([138000, 150000, 198000]);
                $verifiedAt = Carbon::createFromFormat('Y-m', $periode)
                    ->addDays(fake()->numberBetween(0, 25));

                $customer = Customer::factory()->create([
                    'full_name' => fake()->name(),
                    'status' => 'active',
                    'pop_id' => $pop->id,
                    'address' => fake()->streetAddress().', '.fake()->city(),
                ]);

                CustomerService::create([
                    'customer_id' => $customer->id,
                    'package_name_snapshot' => 'Home '.fake()->randomElement(['10 Mbps', '20 Mbps', '30 Mbps']),
                    'monthly_price' => $hargaLangganan,
                    'total_monthly_bill' => $hargaLangganan,
                    'activation_date' => $verifiedAt->toDateString(),
                    'service_status' => 'aktif',
                    'billing_status' => 'pending',
                ]);

                CustomerAcquisition::create([
                    'customer_id' => $customer->id,
                    'periode' => $periode,
                    'verified_at' => $verifiedAt,
                ]);
            }
        }

        $this->command->info('CustomerAcquisitionSeeder: data contoh 3 periode ditanam (lihat /customer-acquisitions).');
    }
}
