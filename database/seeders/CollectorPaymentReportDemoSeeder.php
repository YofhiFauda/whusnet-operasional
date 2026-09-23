<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentBatch;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Data DEMO untuk Laporan Bayar Kolektor (`/reports/collector-payments`).
 *
 * Isinya disalin dari tabel manual "Bayar Wifi Cash"
 * (`docs/plan/billing/tabel-bayar-list-kolektor.md`) supaya angka laporan
 * bisa langsung dicocokkan dengan spreadsheet:
 *   - kelompok 03-09-26 (17 pelanggan)  → Total Sub Rp 2.035.000
 *   - kelompok 09-09-26 (14 pelanggan)  → Total Sub Rp 1.651.000
 *   - total seluruh baris (Kas Terkumpul) → Rp 7.685.000
 *
 * TIDAK dipanggil DatabaseSeeder — jalankan manual:
 *   php artisan db:seed --class=CollectorPaymentReportDemoSeeder
 *
 * Aman dijalankan ulang: data demo lama (kode pelanggan `DEMO-…`, batch
 * `demo-…`) dihapus lebih dulu, data asli tidak disentuh. Kode akun di
 * laporan tampil `DEMO-C00RQ…` sengaja diberi awalan — kode aslinya sudah
 * dipakai pelanggan nyata hasil migrasi, dan `cid` dikosongkan supaya tak
 * bentrok dengan mereka. Ditolak di production.
 */
class CollectorPaymentReportDemoSeeder extends Seeder
{
    public const KOLEKTOR_EMAIL = 'kolektor.demo@whusnet.test';

    /**
     * Tiap kelompok = satu baris tanggal di template. `batch` true = satu
     * sesi input (Total Sub dijumlah sekelompok); false = pembayaran tunggal.
     *
     * @var list<array{date: string, batch: bool, rows: list<array{0: string, 1: string, 2: string, 3: int, 4: string}>}>
     */
    private const GROUPS = [
        ['date' => '2026-09-08', 'batch' => false, 'rows' => [
            ['C1X4DRQ0018', 'Hefida Tri Puspitasari', 'Bila Bakery Jetis, Jl. Dipor', 138000, 'hari'],
        ]],
        ['date' => '2026-09-03', 'batch' => true, 'rows' => [
            ['C00RQ000019', 'Slamet Harianto', 'Jl. Moh yahmin, RT.03 RW...', 110000, 'lunas ags'],
            ['C00RQ000023', 'Imam Jaenudin', 'Jln Jend. Sudirman RT 3 RW', 150000, 'lunas ags'],
            ['C00RQ000022', 'Santi Rahayu', 'Jln Moh. Yamin RT 3 RW 2', 70000, 'lunas ags'],
            ['C00RQ000032', 'Mohamad Zanuar Anggara Putra', 'Jln Moh. Yamin RT 3 RW 2', 110000, 'lunas ags'],
            ['C00RQ000064', 'Kabul Saichoni', "Jl K.Muhammad Na'im No", 115000, 'lunas ags'],
            ['C00RQ000074', 'Heri Setiardi', 'Jl. K. Asngari RT 02 RW 01 J', 130000, 'lunas ags'],
            ['C00RQ000185', 'Puji Astutik', 'Dukuh Josari Kulon RT 03', 130000, 'lunas ags'],
            ['C00RQ000196', 'Kasriati', 'Dukuh Josari Kulon RT 03', 110000, 'lunas ags'],
            ['C00RQ000219', 'Wiji', 'Jl. moh yamin 7 Rt 03/Rw 0', 111000, 'lunas ags'],
            ['C00RQ000214', 'M Aris Nurhidayat', 'jl. hasan besari, rt/rw 003', 130000, 'lunas ags'],
            ['C00RQ000405', 'Munari', 'Jl. Muh Yamin RT 03 RW 02', 111000, 'lunas ags'],
            ['C00RQ000897', 'Tolu', 'Jl. Moh. Yahmin, Josari We', 165000, 'lunas ags'],
            ['C00RQ001351', 'Katenan', 'Jl Moh Yamin 04 RT/RW 03', 111000, 'lunas ags'],
            ['C00RQ001439', 'Eka Maria Fransisca', 'Jl Moh Yamin RT/RW 03/02', 110000, 'lunas ags'],
            ['C00RQ001467', 'Imam Meseno Bachtiar', 'Josari Wetan, Josari, Kec.', 111000, 'lunas ags'],
            ['C00RQ001539', 'Muhamad Nuri', 'Jl. Jend. Sudirman, No. 301', 111000, 'lunas ags'],
            ['C1X4CRQ0016', 'Aida Faizatur Rahma', 'Raudlatul Athfal (RA) Mift', 150000, 'lunas ags'],
        ]],
        ['date' => '2026-09-03', 'batch' => false, 'rows' => [
            ['C00RQ000350', 'Yulianti', 'Dukuh Durungan RT2/RW2', 110000, 'lunas ags'],
        ]],
        ['date' => '2026-09-09', 'batch' => true, 'rows' => [
            ['C00RQ000638', 'Watini', 'JL. Manggar RT 002 / RW 00', 110000, ''],
            ['C00RQ000643', 'Agus Hariyanto', 'JL. Manggar, RT.02/RW.02,', 110000, ''],
            ['C00RQ000639', 'Herman London', 'JL. Manggar Dukuh Butung', 110000, ''],
            ['C00RQ000640', 'Sri Lestari Bajang', 'JL. Manggar Dukuh Butung', 110000, ''],
            ['C00RQ000646', 'Tubianto', 'JL. Manggar, RT.02/RW.00', 110000, ''],
            ['C00RQ000660', 'Sugiono', 'JL.Kenongo RT 002 / RW 00', 110000, ''],
            ['C00RQ000647', 'Nana Supriatna', 'JL. Manggar, RT/RW 002/00', 130000, ''],
            ['C00RQ000678', 'Liya Ikfina', 'Dukuh Mantren, RT.03/RW', 130000, ''],
            ['C00RQ000698', 'Djari', 'Jl. Kenongo, RT.01/RW.02,', 111000, ''],
            ['C00RQ000748', 'Sutrisno', 'Jl. Kenongo RT 002 / RW 00', 130000, ''],
            ['C00RQ000936', 'Rizky Purna Aji Galih Pangestu', 'Dukuh Mantren, RT 002/ R', 130000, ''],
            ['C00RQ001047', 'Rumini', 'Jl Kenongo RT/RW 02/01 D', 130000, ''],
            ['C00RQ001246', 'Sukardi Bajang', 'Dukuh Mantren RT/RW 03,', 115000, ''],
            ['C00RQ001247', 'Dasir', 'Jl Manggar RT/RW 02/02 D', 115000, ''],
        ]],
        ['date' => '2026-09-09', 'batch' => false, 'rows' => [
            ['C00RQ001558', 'H Effendi Eko Cahyono', 'Setono, Tegalsari, Jetis, Ka', 1656000, 'lunas sep 26 s/d ags 27, free sep dan ok 2027'],
        ]],
        ['date' => '2026-09-09', 'batch' => true, 'rows' => [
            ['C00RQ000079', 'Kiky Alamsyah Supriadi', 'Dukuh Tempel RT 01 RW 0', 165000, ''],
            ['C00RQ000099', "Siti Samsiyah / Smp Ma'arif Gandu", 'Jl. Teratai 8 RT 01 RW 01 G', 198000, ''],
            ['C00RQ000111', 'Djoko Susilo', 'Dukuh Caru RT 03 RW 02 B', 198000, ''],
            ['C00RQ000155', 'Nehru Benny Wiyoga', 'Dukuh Krajan RT 01 RW 01', 198000, ''],
            ['C00RQ000195', 'Musrianah', 'Warung Soto Desember sa', 110000, ''],
            ['C00RQ000238', 'Lina Widayati', 'Dukuh Jetis 2 RT.04/RW.01', 130000, ''],
            ['C00RQ000344', 'Zuheri Faruq Ridwan / MUHAMMADIY', 'MBS Jetis Kampus 2', 198000, ''],
            ['C00RQ000404', 'Kateno', 'Dkh. Pandanderek, RT.014', 110000, ''],
            ['C00RQ000481', 'Imanul Cholifah (Migrasi Whusnet)', 'Salon Rosana Dkh.Jintap,', 150000, ''],
            ['C00RQ000488', 'Wahid Khoiru Sholikhin', 'Jl. Kamboja , Winong 1, W', 198000, ''],
            ['C00RQ000315', 'Muchamad Fatchul Fauzi', 'Dukuh Krajan Barat RT 01 R', 110000, ''],
            ['C00RQ000721', 'Muhammad Munawir', 'Jl.Aster RT 001 / RW 002 D', 330000, ''],
        ]],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('CollectorPaymentReportDemoSeeder ditolak di production: ini data demo.');
        }

        $package = InternetPackage::query()->first();
        $role = Role::where('code', 'kolektor')->first();

        if (! $package || ! $role) {
            throw new RuntimeException('Paket internet & role kolektor belum ada. Jalankan `php artisan db:seed` (InternetPackageSeeder, RoleSeeder) dulu.');
        }

        DB::transaction(function () use ($package, $role): void {
            $this->wipeDemoData();

            $pop = $this->demoPop();
            $kolektor = User::updateOrCreate(
                ['email' => self::KOLEKTOR_EMAIL],
                ['name' => 'Kolektor Demo', 'password' => Hash::make('password'), 'role_id' => $role->id, 'status' => 'active'],
            );

            $n = 0;

            foreach (self::GROUPS as $g => $group) {
                $batch = $group['batch']
                    ? PaymentBatch::create([
                        'idempotency_key' => 'demo-'.($g + 1),
                        'submitted_by' => $kolektor->id,
                        'collector_id' => $kolektor->id,
                        'submitted_at' => $group['date'].' 15:00:00',
                    ])
                    : null;

                foreach ($group['rows'] as [$akun, $nama, $alamat, $jumlah, $keterangan]) {
                    $n++;
                    $this->createPayment($n, $pop, $kolektor, $package, $batch, $group['date'], $akun, $nama, $alamat, $jumlah, $keterangan);
                }
            }

            $this->command?->info("CollectorPaymentReportDemoSeeder: {$n} pembayaran demo di POP {$pop->name}, kolektor {$kolektor->email}.");
            $this->command?->info('Buka /reports/collector-payments?start_date=2026-09-01&end_date=2026-09-30 — Kas Terkumpul harus Rp 7.685.000; Total Sub kelompok 03-09 = Rp 2.035.000, kelompok 09-09 = Rp 1.651.000.');
        });
    }

    /** POP Jetis kalau ada (sesuai template), kalau tidak POP cabang pertama, kalau tidak buat satu. */
    private function demoPop(): Pop
    {
        return Pop::query()->where('type', 'cabang')->where('name', 'like', '%Jetis%')->first()
            ?? Pop::query()->where('type', 'cabang')->orderBy('id')->first()
            ?? Pop::create([
                'code' => 'DEMO-JETIS',
                'pop_code' => 'DJ',
                'registration_prefix' => 'DJ',
                'cid_prefix' => 'DJ',
                'name' => 'Jetis (Demo)',
                'type' => 'cabang',
                'status' => 'active',
            ]);
    }

    private function createPayment(int $n, Pop $pop, User $kolektor, InternetPackage $package, ?PaymentBatch $batch, string $date, string $akun, string $nama, string $alamat, int $jumlah, string $keterangan): void
    {
        $code = 'DEMO-'.$akun;

        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => $nama,
            'primary_phone' => '0811000'.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'registration_date' => '2026-01-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => $alamat,
            'collector_id' => $kolektor->id,
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => $jumlah,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $jumlah,
            'activation_date' => '2026-01-01',
            'due_date' => '2026-01-10',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        // "lunas ags" = tagihan Agustus yang baru dibayar September (piutang);
        // selain itu tagihan September. Sudah lunas: diisi langsung, tanpa
        // recalculateFromPayments() supaya seeder tak memicu broadcast.
        $period = str_contains($keterangan, 'ags') && ! str_contains($keterangan, 's/d') ? '2026-08' : '2026-09';

        $invoice = Invoice::create([
            'invoice_number' => 'DEMO-INV-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => $period,
            'issue_date' => $period.'-01',
            'due_date' => $period.'-10',
            'subtotal' => $jumlah,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $jumlah,
            'paid_amount' => $jumlah,
            'remaining_amount' => 0,
            'invoice_status' => 'lunas',
        ]);

        Payment::create([
            'payment_number' => 'DEMO-PAY-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'invoice_id' => $invoice->id,
            'payment_batch_id' => $batch?->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_date' => $date,
            'collected_date' => $date,
            'payment_method' => 'cash',
            'amount' => $jumlah,
            'received_by' => $kolektor->id,
            'collected_by' => $kolektor->id,
            'payment_status' => 'valid',
            'note' => $keterangan !== '' ? $keterangan : null,
        ]);
    }

    /**
     * Hapus data demo lama (dan hanya itu) supaya seeder bisa diulang.
     * Query builder langsung, urutan mengikuti FK — tanpa observer/broadcast.
     */
    private function wipeDemoData(): void
    {
        $customerIds = DB::table('customers')->where('customer_code', 'like', 'DEMO-%')->pluck('id');

        DB::table('payments')->whereIn('customer_id', $customerIds)->delete();
        DB::table('payment_batches')->where('idempotency_key', 'like', 'demo-%')->delete();
        DB::table('invoices')->whereIn('customer_id', $customerIds)->delete();
        DB::table('customer_services')->whereIn('customer_id', $customerIds)->delete();
        DB::table('customers')->whereIn('id', $customerIds)->delete();
    }
}
