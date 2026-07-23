<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeder volume untuk verifikasi §15 docs/plan/ANALISA_INDEX_DATABASE.md.
 *
 * Analisa index bersifat STRUKTURAL — dibuat dari bentuk query × bentuk index,
 * bukan pengukuran. Di tabel kecil optimizer MySQL memilih full scan apa pun
 * indexnya, jadi `EXPLAIN` tidak membuktikan apa-apa sampai ada volume.
 * Command ini mengisi volume yang diminta §15: ±20.000 pelanggan, ±240.000
 * invoice (12 periode), ±200.000 pembayaran, ±100.000 audit_logs.
 *
 * JANGAN dijalankan di database berisi data legacy asli. DB development saat
 * ini 100% hasil migrasi legacy — menambah puluhan ribu baris palsu di situ
 * mencemari dataset dan memaksa import ulang untuk membersihkannya. Jalankan di
 * database throwaway terpisah (mis. `whusnet_perf`) yang di-`migrate:fresh`
 * khusus untuk benchmark, lalu di-drop. Command menolak jalan di produksi dan
 * meminta konfirmasi kalau tabel target sudah berisi data.
 */

/**
 *php artisan benchmark:seed-volume --customers=20000 --periods=12 --paid-ratio=0.83 --audit-per-customer=5 --chunk=2000
┌────────────────────────┬─────────┬─────────────────────────────────────────────────────────────┐
│          Flag          │ Default │                            Guna                             │
├────────────────────────┼─────────┼─────────────────────────────────────────────────────────────┤
│ --customers=N          │ 20000   │ jumlah pelanggan                                            │
├────────────────────────┼─────────┼─────────────────────────────────────────────────────────────┤
│ --periods=N            │ 12      │ invoice per pelanggan (total invoice = customers × periods) │
├────────────────────────┼─────────┼─────────────────────────────────────────────────────────────┤
│ --paid-ratio=F         │ 0.83    │ proporsi invoice punya pembayaran                           │
├────────────────────────┼─────────┼─────────────────────────────────────────────────────────────┤
│ --audit-per-customer=N │ 5       │ baris audit_logs per pelanggan                              │
├────────────────────────┼─────────┼─────────────────────────────────────────────────────────────┤
│ --chunk=N              │ 2000    │ ukuran batch insert                                         │
└────────────────────────┴─────────┴─────────────────────────────────────────────────────────────┘
 */
class SeedVolumeForBenchmark extends Command
{
    protected $signature = 'benchmark:seed-volume
        {--customers=20000 : Jumlah pelanggan}
        {--periods=12 : Periode tagihan per pelanggan (invoice = customers × periods)}
        {--paid-ratio=0.83 : Proporsi invoice yang punya pembayaran}
        {--audit-per-customer=5 : Rata-rata baris audit_logs per pelanggan}
        {--chunk=2000 : Ukuran batch insert}';

    protected $description = 'Isi volume data sintetis untuk benchmark index (§15). JANGAN di DB legacy — pakai database throwaway.';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Ditolak: tidak boleh dijalankan di environment produksi.');

            return self::FAILURE;
        }

        $db = DB::connection()->getDatabaseName();
        $existing = DB::table('customers')->count();

        $this->warn("Database target : {$db}");
        $this->warn("Pelanggan saat ini di target: {$existing}");

        if ($existing > 0 && ! $this->option('no-interaction')) {
            if (! $this->confirm("Tabel customers sudah berisi {$existing} baris — mungkin BUKAN data benchmark. Lanjut tetap seed?", false)) {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        $customers = (int) $this->option('customers');
        $periods = (int) $this->option('periods');
        $paidRatio = (float) $this->option('paid-ratio');
        $auditPer = (int) $this->option('audit-per-customer');
        $chunk = (int) $this->option('chunk');

        $t0 = microtime(true);

        [$popIds, $packageIds, $userId] = $this->seedSupport();
        $this->info('Support siap: '.count($popIds).' pop, '.count($packageIds).' paket, user #'.$userId);

        $customerIds = $this->seedCustomers($customers, $popIds, $chunk);
        $this->info(count($customerIds).' pelanggan.');

        $serviceIdByCustomer = $this->seedServices($customerIds, $packageIds, $chunk);
        $this->info(count($serviceIdByCustomer).' customer_services.');

        $invoiceCount = $this->seedInvoices($customerIds, $serviceIdByCustomer, $packageIds, $userId, $periods, $paidRatio, $chunk);
        $this->info("{$invoiceCount} invoice.");

        $paymentCount = $this->seedPayments($userId, $chunk);
        $this->info("{$paymentCount} pembayaran.");

        $auditCount = $this->seedAuditLogs($customerIds, $userId, $auditPer, $chunk);
        $this->info("{$auditCount} audit_logs.");

        $this->info('ANALYZE TABLE untuk memperbarui statistik optimizer...');
        foreach (['customers', 'customer_services', 'invoices', 'payments', 'audit_logs'] as $t) {
            DB::statement("ANALYZE TABLE `{$t}`");
        }

        $this->newLine();
        $this->info('Selesai dalam '.round(microtime(true) - $t0, 1).' detik.');

        return self::SUCCESS;
    }

    /**
     * Baris pendukung minimal supaya command jalan di DB kosong (migrate:fresh
     * tanpa seeder). Kalau tabel sudah punya isi, pakai yang ada.
     *
     * @return array{0: array<int>, 1: array<int>, 2: int}
     */
    private function seedSupport(): array
    {
        $now = now();

        $popIds = DB::table('pops')->pluck('id')->all();
        if ($popIds === []) {
            foreach (range(1, 8) as $i) {
                DB::table('pops')->insert([
                    'code' => "PERF{$i}",
                    'pop_code' => "PERF{$i}",
                    'name' => "POP Benchmark {$i}",
                    'type' => 'cabang',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $popIds = DB::table('pops')->pluck('id')->all();
        }

        $packageIds = DB::table('internet_packages')->pluck('id')->all();
        if ($packageIds === []) {
            foreach ([10, 20, 30, 50, 100] as $mbps) {
                DB::table('internet_packages')->insert([
                    'package_code' => "PKG{$mbps}",
                    'name' => "Paket {$mbps} Mbps",
                    'category' => 'rumahan',
                    'package_group' => 'reguler',
                    'bandwidth_label' => "{$mbps} Mbps",
                    'monthly_price' => $mbps * 5000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $packageIds = DB::table('internet_packages')->pluck('id')->all();
        }

        $userId = DB::table('users')->min('id');
        if ($userId === null) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Benchmark Bot',
                'email' => 'benchmark@example.test',
                'password' => bcrypt('password'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [$popIds, $packageIds, (int) $userId];
    }

    /**
     * @param  array<int>  $popIds
     * @return array<int>
     */
    private function seedCustomers(int $count, array $popIds, int $chunk): array
    {
        $this->info("Seeding {$count} pelanggan...");
        $bar = $this->output->createProgressBar($count);
        $statuses = ['active', 'registered', 'waiting_survey', 'waiting_installation', 'terminated', 'rejected'];
        $completeness = ['draft', 'perlu_dilengkapi', 'lengkap'];
        $rows = [];
        $firstId = ((int) DB::table('customers')->max('id')) + 1;

        for ($i = 0; $i < $count; $i++) {
            // registration_date tersebar 3 tahun ke belakang supaya filter
            // rentang tanggal punya sesuatu untuk disaring.
            $reg = Carbon::now()->subDays(random_int(0, 1095));
            $rows[] = [
                'customer_code' => 'PERF'.str_pad((string) ($firstId + $i), 8, '0', STR_PAD_LEFT),
                'full_name' => 'Pelanggan Benchmark '.($firstId + $i),
                'phone' => '08'.random_int(1000000000, 9999999999),
                'pop_id' => $popIds[array_rand($popIds)],
                'status' => $statuses[array_rand($statuses)],
                'data_completeness_status' => $completeness[array_rand($completeness)],
                'registration_date' => $reg->toDateString(),
                'created_at' => $reg,
                'updated_at' => $reg,
            ];

            if (count($rows) >= $chunk) {
                DB::table('customers')->insert($rows);
                $bar->advance(count($rows));
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('customers')->insert($rows);
            $bar->advance(count($rows));
        }
        $bar->finish();
        $this->newLine();

        return DB::table('customers')->where('id', '>=', $firstId)->pluck('id')->all();
    }

    /**
     * Satu customer_service per pelanggan (invoice butuh FK ke sini).
     *
     * @param  array<int>  $customerIds
     * @param  array<int>  $packageIds
     * @return array<int, int> [customer_id => service_id]
     */
    private function seedServices(array $customerIds, array $packageIds, int $chunk): array
    {
        $this->info('Seeding customer_services...');
        $now = now();
        $rows = [];
        $firstId = ((int) DB::table('customer_services')->max('id')) + 1;

        foreach ($customerIds as $cid) {
            $price = [50000, 100000, 150000, 250000][array_rand([0, 1, 2, 3])];
            $rows[] = [
                'customer_id' => $cid,
                'internet_package_id' => $packageIds[array_rand($packageIds)],
                'package_name_snapshot' => 'Paket Benchmark',
                'monthly_price' => $price,
                'total_monthly_bill' => $price,
                'service_status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($rows) >= $chunk) {
                DB::table('customer_services')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('customer_services')->insert($rows);
        }

        return DB::table('customer_services')
            ->where('id', '>=', $firstId)
            ->pluck('id', 'customer_id')
            ->all();
    }

    /**
     * @param  array<int>  $customerIds
     * @param  array<int, int>  $serviceIdByCustomer
     * @param  array<int>  $packageIds
     */
    private function seedInvoices(array $customerIds, array $serviceIdByCustomer, array $packageIds, int $userId, int $periods, float $paidRatio, int $chunk): int
    {
        $total = count($customerIds) * $periods;
        $this->info("Seeding {$total} invoice ({$periods} periode/pelanggan)...");
        $bar = $this->output->createProgressBar($total);

        // pop_id per pelanggan diambil sekali supaya invoice mewarisi pop yang
        // sama — index (pop_id, billing_period) baru realistis kalau distribusi
        // pop-nya nyata.
        $popByCustomer = DB::table('customers')->whereIn('id', $customerIds)->pluck('pop_id', 'id')->all();

        $rows = [];
        $count = 0;
        $seq = ((int) DB::table('invoices')->max('id')) + 1;

        foreach ($customerIds as $cid) {
            $serviceId = $serviceIdByCustomer[$cid] ?? null;
            if ($serviceId === null) {
                continue;
            }
            $popId = $popByCustomer[$cid];
            $pkgId = $packageIds[array_rand($packageIds)];

            for ($p = 0; $p < $periods; $p++) {
                $month = Carbon::now()->subMonths($periods - 1 - $p)->startOfMonth();
                $period = $month->format('Y-m');
                $isPaid = mt_rand() / mt_getrandmax() < $paidRatio;
                $amount = [50000, 100000, 150000, 250000][array_rand([0, 1, 2, 3])];

                $rows[] = [
                    'invoice_number' => 'INV-'.$month->format('Ym').'-'.str_pad((string) $seq, 7, '0', STR_PAD_LEFT),
                    'invoice_type' => $p === 0 ? 'awal' : 'bulanan',
                    'customer_id' => $cid,
                    'pop_id' => $popId,
                    'customer_service_id' => $serviceId,
                    'internet_package_id' => $pkgId,
                    'created_by' => $userId,
                    'billing_period' => $period,
                    'invoice_status' => $isPaid ? 'lunas' : 'belum_dibayar',
                    'issue_date' => $month->toDateString(),
                    'due_date' => $month->copy()->addDays(10)->toDateString(),
                    'subtotal' => $amount,
                    'total_amount' => $amount,
                    'paid_amount' => $isPaid ? $amount : 0,
                    'remaining_amount' => $isPaid ? 0 : $amount,
                    'created_at' => $month,
                    'updated_at' => $month,
                ];
                $seq++;
                $count++;

                if (count($rows) >= $chunk) {
                    DB::table('invoices')->insert($rows);
                    $bar->advance(count($rows));
                    $rows = [];
                }
            }
        }
        if ($rows !== []) {
            DB::table('invoices')->insert($rows);
            $bar->advance(count($rows));
        }
        $bar->finish();
        $this->newLine();

        return $count;
    }

    /**
     * Satu pembayaran untuk tiap invoice lunas. Invoice lunas dibaca langsung
     * dari DB (bukan disimpan di memori) supaya jejak RAM tetap datar.
     */
    private function seedPayments(int $userId, int $chunk): int
    {
        $this->info('Seeding pembayaran (satu per invoice lunas)...');
        $count = 0;
        $rows = [];
        $seq = ((int) DB::table('payments')->max('id')) + 1;

        DB::table('invoices')
            ->where('invoice_status', 'lunas')
            ->select('id', 'customer_id', 'pop_id', 'issue_date', 'total_amount')
            ->orderBy('id')
            ->chunk($chunk, function ($invoices) use (&$rows, &$count, &$seq, $userId, $chunk) {
                foreach ($invoices as $inv) {
                    $payDate = Carbon::parse($inv->issue_date)->addDays(random_int(0, 9));
                    $rows[] = [
                        'payment_number' => 'PAY-'.str_pad((string) $seq, 8, '0', STR_PAD_LEFT),
                        'invoice_id' => $inv->id,
                        'customer_id' => $inv->customer_id,
                        'pop_id' => $inv->pop_id,
                        'received_by' => $userId,
                        'payment_date' => $payDate->toDateString(),
                        'payment_method' => ['cash', 'transfer', 'qris'][array_rand([0, 1, 2])],
                        'payment_status' => 'valid',
                        'amount' => $inv->total_amount,
                        'created_at' => $payDate,
                        'updated_at' => $payDate,
                    ];
                    $seq++;
                    $count++;

                    if (count($rows) >= $chunk) {
                        DB::table('payments')->insert($rows);
                        $rows = [];
                    }
                }
            });

        if ($rows !== []) {
            DB::table('payments')->insert($rows);
        }

        return $count;
    }

    /**
     * audit_logs hanya punya created_at (tidak ada updated_at) — sengaja tidak
     * diisi updated_at di sini.
     *
     * @param  array<int>  $customerIds
     */
    private function seedAuditLogs(array $customerIds, int $userId, int $avgPerCustomer, int $chunk): int
    {
        $total = count($customerIds) * $avgPerCustomer;
        $this->info("Seeding ~{$total} audit_logs...");
        $bar = $this->output->createProgressBar($total);

        $modules = ['customers', 'Pembayaran', 'Tagihan', 'Customer Workflow', 'Data Teknis'];
        $actions = ['created', 'updated', 'status_changed', 'verified', 'deleted'];
        $rows = [];
        $count = 0;

        foreach ($customerIds as $cid) {
            for ($k = 0; $k < $avgPerCustomer; $k++) {
                $when = Carbon::now()->subDays(random_int(0, 1095))->subSeconds(random_int(0, 86400));
                $rows[] = [
                    'user_id' => $userId,
                    'auditable_type' => 'App\\Models\\Customer',
                    'auditable_id' => $cid,
                    'module' => $modules[array_rand($modules)],
                    'action' => $actions[array_rand($actions)],
                    'created_at' => $when,
                ];
                $count++;

                if (count($rows) >= $chunk) {
                    DB::table('audit_logs')->insert($rows);
                    $bar->advance(count($rows));
                    $rows = [];
                }
            }
        }
        if ($rows !== []) {
            DB::table('audit_logs')->insert($rows);
            $bar->advance(count($rows));
        }
        $bar->finish();
        $this->newLine();

        return $count;
    }
}
