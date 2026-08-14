<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ParsesLegacySqlDump;
use App\Enums\Gender;
use App\Http\Controllers\CustomerController;
use App\Models\Customer;
use App\Models\Distribution;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MigrateLegacyDataCommand extends Command
{
    use ParsesLegacySqlDump;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    /**
     * JIKA INGIN MIGRASI TANPA BILING LAMA
     * php artisan app:import-legacy-sql sand_db_sandya.sql --branch-code=D --branch-name=Siman --without-billing
     *
     * JIKA INGIN MIGRASI DENGAN BILING LAMA
     * php artisan app:import-legacy-sql sand_db_sandya.sql --branch-code=D --branch-name=Siman
     *
     *  */
    protected $signature = 'app:import-legacy-sql 
                        {file? : The path to the legacy sql dump. Default: sand_db_sandya.sql} 
                        {--branch-code= : Tentukan Kode Cabang POP target (contoh: C, D)} 
                        {--branch-name= : Tentukan Nama Cabang POP target (contoh: Jetis, Siman)}
                        {--without-billing : Impor pelanggan/layanan/data teknis saja, tanpa tagihan & pembayaran legacy}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates legacy data from SQL dump directly into the new schema';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileName = $this->argument('file') ?: 'sand_db_sandya.sql';
        $sqlPath = base_path($fileName);

        if (! file_exists($sqlPath)) {
            $this->error("File {$fileName} does not exist in root directory.");

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $this->info("Starting migration from {$fileName}");
        $sql = file_get_contents($sqlPath);

        // Parse tables
        $this->info('Parsing SQL dump...');
        $cabangRows = $this->parseTableData($sql, 'cabang');
        $paketRows = $this->parseTableData($sql, 'paket');
        $penggunaRows = $this->parseTableData($sql, 'pengguna');
        $layananRows = $this->parseTableData($sql, 'prosedure_permintaan_wifi');
        $laporanRows = $this->parseTableData($sql, 'laporan_pemasangan_wifi');
        $biayaRows = $this->parseTableData($sql, 'biaya_tagihan');
        $buktiRows = $this->parseTableData($sql, 'apikeuangan_buktitransaksitagihan');
        // Legacy apikeuangan_buktitransaksitagihan has the same retry/duplicate-submit
        // bug as biaya_tagihan: the same request gets dozens of near-identical proof
        // rows (same IDPERMINTAAN, same BAYAR, seconds/minutes apart, same day) —
        // different IDUNIQ/IDTRANSAKSI each time, so naive dedup-by-id misses it.
        // Collapse to one row per (request, amount, day) before it's used anywhere.
        $buktiDedupSeen = [];
        $dedupedBuktiRows = [];
        foreach ($buktiRows as $row) {
            $dateOnly = '';
            try {
                $dateOnly = Carbon::parse($row['INSERTED_AT'] ?? '')->format('Y-m-d');
            } catch (\Exception $e) {
            }
            $signature = ($row['IDPERMINTAAN'] ?? '').'|'.(int) ($row['BAYAR'] ?? 0).'|'.$dateOnly;
            if (isset($buktiDedupSeen[$signature])) {
                continue;
            }
            $buktiDedupSeen[$signature] = true;
            $dedupedBuktiRows[] = $row;
        }
        $buktiRows = $dedupedBuktiRows;
        $surveyRows = $this->parseTableData($sql, 'survey_pemasangan_wifi');
        $oltRows = $this->parseTableData($sql, 'olt_slot_register');
        $distribusiRows = $this->parseTableData($sql, 'kode_kontrol_distribusi');
        $barangRows = $this->parseTableData($sql, 'barang');
        $merkBarangRows = $this->parseTableData($sql, 'merk_barang');
        $riwayatBarangRows = $this->parseTableData($sql, 'riwayatstatus_penggunabarang');
        $buktiLunasRows = $this->parseTableData($sql, 'apikeuangan_buktitransaksilunas');
        $buktiPemasanganRows = $this->parseTableData($sql, 'apikeuangan_buktitransaksipemasangan');
        $riwayatPelangganRows = $this->parseTableData($sql, 'riwayat_pelanggan');

        $this->info('Parsed counts:');
        $this->line('- cabang: '.count($cabangRows));
        $this->line('- paket: '.count($paketRows));
        $this->line('- pengguna: '.count($penggunaRows));
        $this->line('- prosedure_permintaan_wifi: '.count($layananRows));
        $this->line('- laporan_pemasangan_wifi: '.count($laporanRows));
        $this->line('- biaya_tagihan: '.count($biayaRows));
        $this->line('- apikeuangan_buktitransaksitagihan: '.count($buktiRows));
        $this->line('- survey_pemasangan_wifi: '.count($surveyRows));
        $this->line('- olt_slot_register: '.count($oltRows));
        $this->line('- kode_kontrol_distribusi: '.count($distribusiRows));
        $this->line('- barang: '.count($barangRows));
        $this->line('- merk_barang: '.count($merkBarangRows));
        $this->line('- riwayatstatus_penggunabarang: '.count($riwayatBarangRows));
        $this->line('- apikeuangan_buktitransaksilunas: '.count($buktiLunasRows));
        $this->line('- apikeuangan_buktitransaksipemasangan: '.count($buktiPemasanganRows));
        $this->line('- riwayat_pelanggan: '.count($riwayatPelangganRows));

        // Real activation timestamp map: IDPERMINTAAN => earliest "Berhasil Active"
        // TGLTINDAKAN. prosedure_permintaan_wifi's own TGL_AKTIFPUTUS/TGLSELESAI/
        // TGLDIACC are frequently '0000-00-00' or blank, but this status-history
        // table almost always has the real event that flipped the customer active.
        $activationLogByRequest = [];
        foreach ($riwayatPelangganRows as $row) {
            if (($row['STATUSTINDAKAN'] ?? '') !== 'Berhasil Active') {
                continue;
            }
            $requestId = $row['IDPERMINTAAN'] ?? '';
            $tgl = $row['TGLTINDAKAN'] ?? '';
            if ($requestId === '' || empty($tgl)) {
                continue;
            }
            $existing = $activationLogByRequest[$requestId] ?? null;
            if ($existing === null || strcmp($tgl, $existing) < 0) {
                $activationLogByRequest[$requestId] = $tgl;
            }
        }

        // Asset map: IDPERMINTAAN => { brand_label, mac, serial } sourced from the
        // real device-rental history tables, used as fallback when
        // laporan_pemasangan_wifi's own MACADDR_ROOTER/SNROOTER_FIBER are empty.
        $barangByCode = [];
        foreach ($barangRows as $row) {
            if (! empty($row['KODEBARANG'])) {
                $barangByCode[$row['KODEBARANG']] = $row;
            }
        }
        $merkBarangById = [];
        foreach ($merkBarangRows as $row) {
            if (! empty($row['IDMERK'])) {
                $merkBarangById[$row['IDMERK']] = $row;
            }
        }
        $assetByRequest = [];
        foreach ($riwayatBarangRows as $row) {
            $requestId = $row['IDPERMINTAAN'] ?? '';
            if ($requestId === '' || isset($assetByRequest[$requestId])) {
                continue;
            }
            $barang = $barangByCode[$row['KODEBARANG'] ?? ''] ?? null;
            $merk = $merkBarangById[$barang['MERKBARANG'] ?? ''] ?? null;
            $assetByRequest[$requestId] = [
                'brand_label' => trim((string) ($row['MERKBARANG'] ?? '')),
                'mac' => $barang['MACADDRESS'] ?? '',
                'serial' => $merk['SERIALNUMBER'] ?? '',
            ];
        }

        // Metode/penerima/catatan pembayaran yang sebenarnya, dari
        // apikeuangan_buktitransaksilunas (apikeuangan_buktitransaksitagihan tidak
        // menyimpannya).
        //
        // Tabel ini kena penyakit yang sama dengan buktitransaksitagihan: di-key
        // `IDTRANSAKSI` yang konstan seumur hidup pelanggan, jadi satu cost id bisa
        // punya banyak baris lintas bulan (13 cost id di jetis_db, 2 di antaranya
        // beda bulan, 5 beda metode bayar). Mengambil satu baris lalu mencapkannya
        // ke SEMUA pembayaran cost id itu bikin kuitansi Desember mencatut metode
        // & penerima pembayaran November.
        //
        // Bedanya dengan buktitransaksitagihan: `BULANTAGIHAN` di tabel ini KOSONG
        // di seluruh 52 baris, jadi periode hanya bisa ditebak dari bulan TGLBAYAR.
        // Karena itu tetap disiapkan dua peta — per periode kalau ketemu, dan baris
        // tertua sebagai cadangan.
        $lunasByTransaction = [];
        $lunasByTransactionPeriod = [];
        foreach ($buktiLunasRows as $row) {
            $transactionId = $row['IDTRANSAKSI'] ?? '';
            if ($transactionId === '') {
                continue;
            }

            $existing = $lunasByTransaction[$transactionId] ?? null;
            if ($existing === null || strcmp((string) $row['TGLBAYAR'], (string) $existing['TGLBAYAR']) < 0) {
                $lunasByTransaction[$transactionId] = $row;
            }

            $period = $this->legacyBillingPeriod(null, $row['TGLBAYAR'] ?? null);
            $existingForPeriod = $lunasByTransactionPeriod[$transactionId][$period] ?? null;
            if ($existingForPeriod === null || strcmp((string) $row['TGLBAYAR'], (string) $existingForPeriod['TGLBAYAR']) < 0) {
                $lunasByTransactionPeriod[$transactionId][$period] = $row;
            }
        }

        // Installation-fee payment map: IDPERMINTAAN (actually holds the invoice code, e.g. IN000006) => TGLBAYAR
        $installationPaidAt = [];
        foreach ($buktiPemasanganRows as $row) {
            $invoiceCode = $row['IDPERMINTAAN'] ?? '';
            if ($invoiceCode !== '' && ! isset($installationPaidAt[$invoiceCode])) {
                $installationPaidAt[$invoiceCode] = $row['TGLBAYAR'] ?? null;
            }
        }

        if (empty($paketRows) && empty($penggunaRows)) {
            $this->error('No data parsed. Make sure the SQL format matches.');

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $overrideCode = $this->option('branch-code');
        $overrideName = $this->option('branch-name');

        // Create POPs from the legacy cabang table so this command can migrate
        // multiple branches from any dump with the same schema.
        // Kirimkan override ke pembuatan Map POP
        $legacyPopMap = $this->createLegacyPopMap($cabangRows, $overrideCode, $overrideName);

        if ($legacyPopMap === []) {
            // Fallback jika tabel cabang kosong
            $defaultCode = $overrideCode ?: 'C';
            $defaultName = $overrideName ?: 'Jetis';

            $defaultPop = Pop::firstOrCreate(
                ['pop_code' => $defaultCode],
                [
                    'code' => $defaultCode,
                    'name' => $defaultName,
                    'type' => 'cabang',
                    'status' => 'active',
                    'registration_prefix' => 'RQ',
                    'cid_prefix' => $defaultCode,
                ]
            );
            $legacyPopMap = [
                'default' => [
                    'pop_code' => $defaultPop->pop_code ?? $defaultCode,
                    'pop_name' => $defaultPop->name ?? $defaultName,
                    'pop_model' => $defaultPop,
                ],
            ];
        }

        $this->info('Mapping data to sheets...');

        // Pre-build maps for lookup
        $penggunaMap = [];
        foreach ($penggunaRows as $row) {
            if (! empty($row['IDPENGGUNA'])) {
                $penggunaMap[$row['IDPENGGUNA']] = $row;
            }
        }

        // Build distribution metadata map from kode_kontrol_distribusi table
        $distribusiMetaMap = [];
        foreach ($distribusiRows as $row) {
            $code = strtoupper(trim((string) ($row['kode'] ?? '')));
            if ($code !== '' && $code !== '0') {
                $distribusiMetaMap[$code] = [
                    'name' => trim((string) ($row['nama'] ?? '')),
                    'description' => trim((string) ($row['deskripsi'] ?? '')),
                ];
            }
        }

        $requestToCustomerMap = [];
        $legacyRequestByCustomer = [];
        $legacyMiniPopByCustomer = [];
        $legacyDistributionByCustomer = [];
        $createdDistributions = [];

        foreach ($layananRows as $row) {
            $requestId = $row['IDPERMINTAAN'] ?? '';
            $customerId = $row['IDPENGGUNA'] ?? '';
            $branchId = trim((string) ($penggunaMap[$customerId]['IDCABANG'] ?? ''));
            $legacyPop = $this->resolveLegacyPopForBranch($branchId, $legacyPopMap);
            $branchPop = $legacyPop['pop_model'];
            $miniSegment = $this->normalizeLegacyMiniPopSegment($row['kategori_perangkat_jaringan'] ?? null);
            $miniPopCode = $legacyPop['pop_code'].$miniSegment;
            $distributionCode = strtoupper(trim((string) ($row['kode_kontrol_distribusi'] ?? '')));
            if ($distributionCode === '0') {
                $distributionCode = '';
            }

            $miniPop = Pop::firstOrCreate(
                ['pop_code' => $miniPopCode],
                [
                    'code' => $miniPopCode,
                    'name' => $miniPopCode,
                    'type' => 'mini_pop',
                    'parent_id' => $branchPop?->id,
                    'status' => 'active',
                    'registration_prefix' => 'RQ',
                    'cid_prefix' => $legacyPop['pop_code'],
                ]
            );

            if ($distributionCode !== '' && $distributionCode !== '0') {
                $meta = $distribusiMetaMap[$distributionCode] ?? [];
                $distName = (! empty($meta['name']) && $meta['name'] !== '-') ? $meta['name'] : $distributionCode;
                $distDesc = (! empty($meta['description']) && $meta['description'] !== '-') ? $meta['description'] : 'Distribusi '.$distributionCode;

                Distribution::firstOrCreate(
                    [
                        'code' => $distributionCode,
                    ],
                    [
                        'pop_id' => $miniPop->id,
                        'name' => $distName,
                        'description' => $distDesc,
                    ]
                );
                $createdDistributions[$distributionCode] = true;
            }

            if ($requestId !== '') {
                $requestToCustomerMap[$requestId] = $customerId;
            }

            if ($customerId !== '' && $requestId !== '' && ! isset($legacyRequestByCustomer[$customerId])) {
                $legacyRequestByCustomer[$customerId] = $requestId;
            }

            if ($customerId !== '' && ! isset($legacyMiniPopByCustomer[$customerId])) {
                $legacyMiniPopByCustomer[$customerId] = $miniPopCode;
            }

            if ($customerId !== '' && ! isset($legacyDistributionByCustomer[$customerId])) {
                $legacyDistributionByCustomer[$customerId] = $distributionCode;
            }
        }

        // Seed any remaining distributions from legacy table that don't have customers yet
        // We put them under the default Mini POP of the resolved POP
        $firstPop = collect($legacyPopMap)->first();
        if ($firstPop) {
            $branchPop = $firstPop['pop_model'];
            $defaultMiniPopCode = $firstPop['pop_code'].'1';
            $defaultMiniPop = Pop::firstOrCreate(
                ['pop_code' => $defaultMiniPopCode],
                [
                    'code' => $defaultMiniPopCode,
                    'name' => $defaultMiniPopCode,
                    'type' => 'mini_pop',
                    'parent_id' => $branchPop?->id,
                    'status' => 'active',
                    'registration_prefix' => 'RQ',
                    'cid_prefix' => $firstPop['pop_code'],
                ]
            );

            foreach ($distribusiMetaMap as $code => $meta) {
                if (! isset($createdDistributions[$code])) {
                    $distName = (! empty($meta['name']) && $meta['name'] !== '-') ? $meta['name'] : $code;
                    $distDesc = (! empty($meta['description']) && $meta['description'] !== '-') ? $meta['description'] : 'Distribusi '.$code;

                    Distribution::firstOrCreate(
                        [
                            'code' => $code,
                        ],
                        [
                            'pop_id' => $defaultMiniPop->id,
                            'name' => $distName,
                            'description' => $distDesc,
                        ]
                    );
                }
            }
        }

        $invoiceToCustomerMap = [];
        foreach ($biayaRows as $row) {
            $invoiceToCustomerMap[$row['IDBIAYA']] = $row['IDPELANGGAN'] ?? '';
        }

        $billingExtraByRequest = [];
        foreach ($biayaRows as $row) {
            if (! empty($row['IDPERMINTAAN'])) {
                $billingExtraByRequest[$row['IDPERMINTAAN']] = (int) ($row['BIAYALAINLAIN'] ?? 0);
            }
        }

        // Harga bulanan yang benar-benar ditagihkan per permintaan, dipakai
        // sebagai sumber kebenaran saat `paket.HARGA` legacy bernilai 0 (paket
        // 'default'/'undefined' di data lama). Tanpa ini `monthly_price` layanan
        // ikut 0 dan tagihan bulanan berikutnya terbit Rp 0 untuk pelanggan yang
        // sebenarnya bayar penuh tiap bulan.
        // Ambil nilai TERBARU (baris biaya paling akhir menang) karena harga
        // paket pelanggan bisa naik di tengah masa langganan.
        $monthlyFeeByRequest = [];
        foreach ($biayaRows as $row) {
            $requestId = $row['IDPERMINTAAN'] ?? '';
            $monthlyFee = (int) ($row['BIAYABULANAN'] ?? 0);
            if ($requestId === '' || $monthlyFee <= 0) {
                continue;
            }
            $monthlyFeeByRequest[$requestId] = $monthlyFee;
        }

        $surveyMap = [];
        foreach ($surveyRows as $row) {
            if (! empty($row['IDPERMINTAAN'])) {
                $surveyMap[$row['IDPERMINTAAN']] = $row;
            }
            if (! empty($row['IDPENGGUNA'])) {
                $surveyMap[$row['IDPENGGUNA']] = $row;
            }
            if (! empty($row['IDSURVEY'])) {
                $surveyMap[$row['IDSURVEY']] = $row;
            }
        }

        $laporanMap = [];
        foreach ($laporanRows as $row) {
            if (! empty($row['IDPERMINTAAN'])) {
                $laporanMap[$row['IDPERMINTAAN']] = $row;
            }
            if (! empty($row['IDPENGGUNA'])) {
                $laporanMap[$row['IDPENGGUNA']] = $row;
            }
        }

        $oltMap = [];
        foreach ($oltRows as $row) {
            if (! empty($row['IDPELANGGAN'])) {
                $oltMap[$row['IDPELANGGAN']] = $row;
            }
        }

        // Sheet 1: packages
        $packagesSheet = [];
        foreach ($paketRows as $row) {
            $packagesSheet[] = [
                'old_package_id' => $row['KODEPAKET'],
                'name' => $row['NAMA_PAKET'] ?: 'Default Paket',
                'monthly_price' => (int) ($row['HARGA'] ?? 0),
                'download_speed' => $row['SPEEDDOWN'] > 0 ? ($row['SPEEDDOWN'] / 1000) : 10,
                'upload_speed' => $row['SPEEDUP'] > 0 ? ($row['SPEEDUP'] / 1000) : 10,
                'package_type' => $row['JENIS_PAKET'] ?: 'Broadband',
                'category' => $row['KATEGORI_PAKET'] ?: 'Home',
            ];
        }

        // Sheet 2: customers
        $customersSheet = [];
        $usedCustomerCodes = [];

        $skippedWithoutRequest = 0;

        foreach ($penggunaRows as $row) {
            // Filter: skip internal users starting with PG
            if (! str_starts_with($row['IDPENGGUNA'] ?? '', 'PE')) {
                continue;
            }

            // Akun ber-prefix PE tapi tidak punya satu pun baris di
            // prosedure_permintaan_wifi bukan pelanggan: tidak pernah ada
            // permintaan layanan, tidak pernah ditagih. Di dump Jetis ini
            // menjaring akun internal PE21000001 yang lolos filter prefix di
            // atas. Kalau diimport, hasilnya pelanggan yatim tanpa layanan yang
            // mengotori daftar pelanggan dan hitungan kelengkapan data.
            if (! isset($legacyRequestByCustomer[$row['IDPENGGUNA']])) {
                $skippedWithoutRequest++;

                continue;
            }

            $fullName = trim(($row['NAMADEPAN'] ?? '').' '.($row['NAMABELAKANG'] ?? ''));
            $fullName = ucwords(str_replace('-', ' ', $fullName));
            if (empty($fullName)) {
                $fullName = $row['IDPENGGUNA'];
            }

            // Standardize gender
            $gender = Gender::LAKI_LAKI->value;
            if (isset($row['JENISKELAMIN'])) {
                if (strtoupper($row['JENISKELAMIN']) === 'P') {
                    $gender = Gender::PEREMPUAN->value;
                }
            }

            // Survey lookups for coords & photos
            $custSurvey = $this->resolveLegacySurveyRow([
                'IDPERMINTAAN' => $row['IDPERMINTAAN'] ?? null,
                'IDPENGGUNA' => $row['IDPENGGUNA'] ?? null,
                'IDSURVEY' => $row['IDSURVEY'] ?? null,
            ], $surveyMap);
            $lat = $this->normalizeCoordinate($custSurvey['LAT'] ?? null);
            $lon = $this->normalizeCoordinate($custSurvey['LONG'] ?? null);
            $fotoRumah = $custSurvey['FOTORUMAH'] ?? null;

            // Laporan lookups for contract photo and sales code
            $custLaporan = $laporanMap[$row['IDPENGGUNA']] ?? null;
            $salesCode = '';
            $legacyRequestInsertedAt = null;
            foreach ($layananRows as $lRow) {
                if (($lRow['IDPENGGUNA'] ?? '') === $row['IDPENGGUNA']) {
                    $salesCode = $lRow['CREATED'] ?? '';
                    $legacyRequestInsertedAt = $lRow['inserted_at'] ?? $legacyRequestInsertedAt;
                    if (! $custLaporan) {
                        $custLaporan = $laporanMap[$lRow['IDPERMINTAAN']] ?? null;
                    }
                }
            }
            $fotoKontrak = $custLaporan['FOTOFORMULIR'] ?? null;

            // Date formatting — tanggal registrasi diambil dari inserted_at
            // baris PERMINTAAN (prosedure_permintaan_wifi), BUKAN baris
            // pengguna. pengguna.inserted_at ternyata timestamp sinkronisasi/
            // sentuhan terakhir record pengguna (bisa jauh lebih baru dari
            // tanggal daftar aslinya — kasus Ardiyanto: pengguna.inserted_at
            // = 10 Mei 2022, padahal PENGAJUAN aslinya 6 Jan 2022 per
            // riwayat_pelanggan & inserted_at baris RQ-nya sendiri).
            //
            // inserted_at kolomnya bertipe MySQL TIMESTAMP (bukan DATETIME
            // seperti TGLSURVEY/TGLDIACC/TGLDIPROSES/VERIFIED_AT) — TIMESTAMP
            // otomatis dikonversi ke/dari UTC oleh server lama, sedangkan
            // kolom DATETIME lain di dump ini literal (tidak dikonversi).
            // Itu sebabnya inserted_at kebaca 7 jam mundur dari jam yang
            // ditampilkan aplikasi lama (kasus Ardiyanto: 08:34:22 di dump vs
            // 15:34:22 WIB di UI) — perlu digeser eksplisit UTC → Asia/Jakarta,
            // beda dari field tanggal lain yang sudah literal.
            $regDateTime = now()->format('Y-m-d H:i:s');
            if (! empty($legacyRequestInsertedAt)) {
                try {
                    $regDateTime = Carbon::parse($legacyRequestInsertedAt, 'UTC')->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                }
            } elseif (! empty($row['inserted_at'])) {
                try {
                    $regDateTime = Carbon::parse($row['inserted_at'], 'UTC')->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                }
            }

            $legacyBranchId = trim((string) ($row['IDCABANG'] ?? ''));
            $legacyPop = $this->resolveLegacyPopForBranch($legacyBranchId, $legacyPopMap);
            $miniPopCode = $legacyMiniPopByCustomer[$row['IDPENGGUNA']] ?? ($legacyPop['pop_code'].'1');

            // customer_code is only required to be unique WITHIN a branch (cid_prefix
            // differs per cabang, so the full CID stays unique even if two different
            // branches preserve the exact same legacy RQ number as customer_code).
            // Scope the "already used" check to this row's cabang — a mini-pop
            // sibling under the same cabang shares the same cid_prefix too, so the
            // check must cover the whole cabang subtree, not just the exact pop_id.
            $branchCabangPop = $legacyPop['pop_model'];
            $candidateCode = $legacyRequestByCustomer[$row['IDPENGGUNA']] ?? '';
            if ($candidateCode !== '') {
                $usedKey = $branchCabangPop->id.':'.$candidateCode;
                $alreadyUsedInBranch = Customer::query()
                    ->where('customer_code', $candidateCode)
                    ->where(function ($q) use ($branchCabangPop) {
                        $q->where('pop_id', $branchCabangPop->id)
                            ->orWhereHas('pop', fn ($pq) => $pq->where('parent_id', $branchCabangPop->id));
                    })
                    ->exists();

                if (isset($usedCustomerCodes[$usedKey]) || $alreadyUsedInBranch) {
                    $candidateCode = ''; // Duplicate found within this branch, clear it so it gets auto-generated later
                } else {
                    $usedCustomerCodes[$usedKey] = true;
                }
            }

            $customersSheet[] = [
                'old_customer_id' => $row['IDPENGGUNA'],
                'old_request_id' => $legacyRequestByCustomer[$row['IDPENGGUNA']] ?? '',
                'customer_code' => $candidateCode,
                'branch_pop_code' => $legacyPop['pop_code'],
                'full_name' => $fullName,
                'phone' => $row['HP'] ?? '',
                'primary_phone' => $row['HP'] ?? '',
                'alternative_phone' => $row['TLP'] ?? '',
                'email' => $row['EMAIL'] ?? '',
                'identity_number' => $row['KTP_SIM'] ?? '',
                'gender' => $gender,
                'customer_type' => $row['JENISPELANGGAN'] ?: 'RUMAHAN',
                'company_name' => $row['NAMAPERUSAHAAN'] ?? '',
                'npwp' => $row['NPWP'] ?? '',
                'old_account_status' => $row['STATUSAKUN'] ?? '',
                'full_address' => $this->resolveLegacyAddressText($row),
                'old_region_id' => $row['IDWILAYAH'] ?? '',
                'old_branch_id' => $legacyBranchId,
                'registration_date' => $regDateTime,
                // CREATED di prosedure_permintaan_wifi nyimpen kode aktor yang
                // input permintaan (mis. 'PE21000001' = "Faruqi R"), bukan cuma
                // kode sales — sales_code di atas dibiarkan mentah (dipakai
                // tempat lain sebagai kode), nama hasil resolve dikirim
                // terpisah biar timeline riwayat pelanggan bisa nampilin aktor
                // registrasi yang benar, bukan admin yang jalanin importnya.
                'registered_by_name' => $this->resolveLegacyUserLabel($salesCode, $penggunaMap),
                'pop_code' => $miniPopCode,
                'pop_name' => $legacyPop['pop_name'],
                'distribution_code' => $legacyDistributionByCustomer[$row['IDPENGGUNA']] ?? '',
                'village' => $row['DESA'] ?? '',
                'district' => $row['KEC'] ?? '',
                'city' => $row['KOTA'] ?? '',
                'latitude' => $lat,
                'longitude' => $lon,
                'foto_rumah' => $fotoRumah ?? '',
                'foto_kontrak' => $fotoKontrak ?? '',
                'sales_code' => $salesCode,
                'agent_code' => '',
                'referral_customer_code' => $row['REKOMENDASI'] ?? '',
            ];
        }

        // Sheet 3: services
        $servicesSheet = [];
        $servicesWithoutPackage = 0;
        foreach ($layananRows as $row) {
            // Filter: skip internal users
            if (! str_starts_with($row['IDPENGGUNA'] ?? '', 'PE')) {
                continue;
            }

            // Fallback chain through progressively earlier/less-specific legacy
            // date fields. Deliberately does NOT default to now() when none of
            // these exist — a silently "today" activation_date makes a decades-
            // old customer look like they just activated this month, which
            // fools any code that reasons about "billed this activation period"
            // (e.g. GenerateMonthlyInvoicesCommand's double-bill guard).
            $actDate = null;
            $isZeroDate = fn ($v) => empty($v) || str_starts_with((string) $v, '0000-00-00');
            if (! $isZeroDate($row['TGL_AKTIFPUTUS'] ?? null)) {
                $actDate = $row['TGL_AKTIFPUTUS'];
            } elseif (! $isZeroDate($row['TGLSELESAI'] ?? null)) {
                try {
                    $actDate = Carbon::parse($row['TGLSELESAI'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            } elseif (! $isZeroDate($row['TGLDIPROSES'] ?? null)) {
                try {
                    $actDate = Carbon::parse($row['TGLDIPROSES'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            } elseif (! $isZeroDate($row['TGLDIACC'] ?? null)) {
                try {
                    $actDate = Carbon::parse($row['TGLDIACC'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            // Last resort: riwayat_pelanggan's "Berhasil Active" status-change log.
            // prosedure_permintaan_wifi's own date fields are often blank/zero, but
            // this history table almost always has the real event timestamp.
            //
            // Kalau SEMUA sumber di atas kosong, `activation_date` sengaja
            // dibiarkan NULL. Keputusan owner 2026-07-21: lebih baik kosong dan
            // jujur daripada terisi angka perkiraan. Jangan tambahkan fallback
            // ke `biaya_tagihan.TGLINSERT` (tanggal tagihan DIBUAT, bukan tanggal
            // layanan menyala) atau ke `now()` — keduanya pernah diusulkan dan
            // ditolak. Per 2026-07-21 ada 146 dari 1.956 baris legacy yang
            // kosong karena ini.
            //
            // Aman dibiarkan kosong: penjaga tagihan dobel tidak lagi bergantung
            // pada kolom ini sejak BILLING-B0c memakai pengecekan lintas-jenis
            // invoice. Lihat docs/billing-pembayaran/analisa-pencegahan-tagihan-dobel.md.
            if ($actDate === null && ! empty($activationLogByRequest[$row['IDPERMINTAAN'] ?? ''])) {
                try {
                    $actDate = Carbon::parse($activationLogByRequest[$row['IDPERMINTAAN']])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            // Lookup package profile
            $profile = '';
            if (! empty($row['IDPAKET'])) {
                $pkg = collect($paketRows)->firstWhere('KODEPAKET', $row['IDPAKET']);
                $profile = $pkg['PROFILPPP'] ?? $pkg['PROFILOLT'] ?? '';
            }

            // `IDPAKET` kosong bikin baris ini ditolak validasi import ("ID paket
            // lama wajib diisi") sehingga pelanggannya masuk tanpa layanan sama
            // sekali — yatim di daftar pelanggan. Jatuhkan ke paket placeholder
            // legacy supaya layanannya tetap terbentuk dan pelanggan bisa
            // dilengkapi manual belakangan. Harga tetap diambil dari
            // `biaya_tagihan` di bawah, jadi placeholder ini tidak menyeret
            // harga jadi 0 kalau pelanggannya memang pernah ditagih.
            $packageId = trim((string) ($row['IDPAKET'] ?? ''));
            if ($packageId === '') {
                $placeholder = collect($paketRows)->firstWhere('KODEPAKET', 'PK21000001')
                    ?? collect($paketRows)->first();
                $packageId = $placeholder['KODEPAKET'] ?? '';
                $servicesWithoutPackage++;
            }

            // Harga bulanan: `paket.HARGA` legacy bernilai 0 untuk paket
            // 'default'/'undefined', jadi pakai nominal yang benar-benar
            // ditagihkan di `biaya_tagihan` sebagai sumber utama. Dikirim
            // kosong kalau dua-duanya tidak ada, biar importer yang memutuskan
            // (dia jatuh ke harga paket) ketimbang kita menebak angka.
            $legacyMonthlyPrice = $monthlyFeeByRequest[$row['IDPERMINTAAN'] ?? ''] ?? null;

            // Tanggal jatuh tempo layanan mengikuti konvensi jalur manual
            // (CustomerController::store): aktivasi + 1 bulan. Sebelumnya field
            // ini dikirim string kosong dan tidak pernah terisi, yang membuat
            // SEMUA pelanggan migrasi tertahan di 'perlu_dilengkapi' — jatuh
            // tempo adalah salah satu field wajib di CustomerValidationService.
            // Dibiarkan null kalau tanggal aktivasi memang tidak diketahui;
            // jangan karang dari now() (lihat catatan panjang di atas).
            $dueDate = null;
            if ($actDate !== null) {
                try {
                    $dueDate = Carbon::parse($actDate)->addMonth()->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            // Lookup survey info
            //
            // prosedure_permintaan_wifi.TGLSURVEY (row['TGLSURVEY']) adalah tanggal
            // proses survey resmi — ini yang dipakai "TANGGAL SURVEY" di aplikasi lama.
            // survey_pemasangan_wifi.TGLSURVEY di tabel terpisah malah keisi belakangan,
            // pas teknisi submit LAPORAN hasil survey (bisa berhari-hari setelahnya),
            // jadi harus jadi fallback — bukan prioritas. Ketertukar urutan ini yang
            // bikin migrasi lama nge-grab tanggal report survey, bukan tanggal survey.
            $survey = $this->resolveLegacySurveyRow($row, $surveyMap);
            $surveyDate = null;
            $surveyStartTime = null;
            if (! empty($row['TGLSURVEY'])) {
                try {
                    $cDate = Carbon::parse($row['TGLSURVEY']);
                    $surveyDate = $cDate->format('Y-m-d');
                    $surveyStartTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {
                }
            } elseif ($survey && ! empty($survey['TGLSURVEY'])) {
                try {
                    $cDate = Carbon::parse($survey['TGLSURVEY']);
                    $surveyDate = $cDate->format('Y-m-d');
                    $surveyStartTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {
                }
            }

            // Lookup installation info
            $laporan = $laporanMap[$row['IDPERMINTAAN']] ?? $laporanMap[$row['IDPENGGUNA'] ?? ''] ?? null;
            $installDate = null;
            $installStartTime = null;
            $installEndTime = null;
            if (! empty($row['TGLSELESAI'])) {
                try {
                    $cDate = Carbon::parse($row['TGLSELESAI']);
                    $installDate = $cDate->format('Y-m-d');
                    $installEndTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {
                }
            }
            if (! empty($row['TGLDIPROSES'])) {
                try {
                    $cDate = Carbon::parse($row['TGLDIPROSES']);
                    if (! $installDate) {
                        $installDate = $cDate->format('Y-m-d');
                    }
                    $installStartTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {
                }
            }

            $servicesSheet[] = [
                'old_request_id' => $row['IDPERMINTAAN'],
                'old_customer_id' => $row['IDPENGGUNA'] ?? '',
                'old_package_id' => $packageId,
                'old_cost_id' => $row['IDBIAYA'] ?? '',
                'monthly_price' => $legacyMonthlyPrice,
                'request_status' => $row['STATUS'] ?? '',
                'installation_status' => $row['STATUSPASANG'] ?? '',
                'network_type' => $row['JENISJARINGAN'] ?? '',
                'member_type' => $row['JENISMEMBER'] ?? '',
                'reason' => $row['ALASAN'] ?? '',
                'service_status' => $row['STATUS'] ?? '',
                'activation_date' => $actDate,
                'due_date' => $dueDate,
                // Kapan status terakhir (GAGAL/PUTUS/dst) ini terjadi di sistem
                // lama. TGLSELESAI adalah tanggal proses ditutup (paling akurat);
                // kalau kosong (banyak baris GAGAL lama gak isi TGLSELESAI sama
                // sekali), fallback ke updated_at baris ini — proxy realistis
                // "terakhir disentuh", BUKAN tanggal karangan/now().
                'status_changed_at' => ! $isZeroDate($row['TGLSELESAI'] ?? null)
                    ? $row['TGLSELESAI']
                    : (! empty($row['updated_at']) ? $row['updated_at'] : null),

                // New survey/FOP/Installation fields
                'profile' => $profile,
                // STATUSLANGGANAN kosongan di data lama — jenis kontrak
                // (Sewa/Beli alat) sebenarnya kesimpan di STATUSALAT.
                'contract_type' => strtolower(trim((string) ($row['STATUSALAT'] ?? ''))),
                // Status Alat (List Putus Langganan) — beda field dari STATUSALAT
                // (jenis kontrak) di atas. STATUSTINDAKANALAT cuma keisi kalau
                // STATUS='PUTUS', nilainya 'Sudah diambil' / 'Proses ambil' / kosong.
                'device_retrieved_status' => trim((string) ($row['STATUSTINDAKANALAT'] ?? '')),
                'activation_time' => ! empty($row['VERIFIED_AT']) ? Carbon::parse($row['VERIFIED_AT'])->format('H:i:s') : null,
                'activated_by_name' => $this->resolveLegacyUserLabel($row['VERIFIED'] ?? '', $penggunaMap),
                'other_fee' => $billingExtraByRequest[$row['IDPERMINTAAN']] ?? 0,

                'survey_date' => $surveyDate,
                'survey_start_time' => $surveyStartTime,
                'survey_end_time' => null,
                'surveyors' => $this->resolveLegacyUserLabel($row['DISURVEY'] ?? '', $penggunaMap),
                'survey_assigned_at' => $row['TGLSURVEY'] ?? null,
                'survey_fop_id' => $row['IDPERMINTAAN'],
                'required_tools' => $survey['ESTIMASIKEBUTUHAN'] ?? '',
                'survey_photo' => $survey['FOTORUMAH'] ?? '',
                'survey_note' => $survey['ALATPASIF'] ?? '',
                'survey_duration_minutes' => 30,

                'installation_date' => $installDate,
                'installation_start_time' => $installStartTime,
                'installation_end_time' => $installEndTime,
                'installation_technicians' => $this->resolveLegacyUserLabel($row['DIPROSES'] ?? '', $penggunaMap),
                'installation_photo' => $laporan['FOTOROOTER'] ?? '',
                'installation_note' => $laporan['KETERANGAN'] ?? '',
                'installation_assigned_at' => $row['TGLDIPROSES'] ?? null,
                'installation_fop_id' => $row['IDPERMINTAAN'],

                // Langkah "ACC" / filter admin (TGLDIACC/DIACC) sebelumnya gak
                // pernah disimpan ke sheet manapun — cuma numpang jadi salah satu
                // fallback activation_date di atas. Dikirim di sini biar
                // confirmImport() bisa nyatet riwayat status waiting_acc-nya,
                // bukan hilang total.
                'admin_filter_at' => ! $isZeroDate($row['TGLDIACC'] ?? null) ? $row['TGLDIACC'] : null,
                'admin_filter_by' => $this->resolveLegacyUserLabel($row['DIACC'] ?? '', $penggunaMap),
            ];
        }

        // Sheet 4: technical_details
        $technicalSheet = [];
        foreach ($laporanRows as $row) {
            // Filter: skip internal users
            if (! str_starts_with($row['IDPENGGUNA'] ?? '', 'PE')) {
                continue;
            }

            // Lookup OLT actual data
            $olt = $oltMap[$row['IDPENGGUNA']] ?? null;
            $oltCode = $olt['LOKASIOLT'] ?? $row['NOMOR_PORT_OLT'] ?? '';
            $oltPort = $olt['INDEXONU'] ?? '';
            $actualAttenuation = $olt['RX_POWER'] ?? null;

            // Lookup request date
            $reqDate = null;
            $reqTime = null;
            $req = collect($layananRows)->firstWhere('IDPERMINTAAN', $row['IDPERMINTAAN'])
                ?? collect($layananRows)->firstWhere('IDPENGGUNA', $row['IDPENGGUNA'] ?? null);
            if ($req && ! empty($req['TGLSELESAI'])) {
                try {
                    $cDate = Carbon::parse($req['TGLSELESAI']);
                    $reqDate = $cDate->format('Y-m-d');
                    $reqTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {
                }
            }

            // Jitter / Latency
            $jitter = is_numeric($row['PINGGATEWAY'] ?? null) ? (int) $row['PINGGATEWAY'] : null;
            $latency = is_numeric($row['PINGGOOGLE'] ?? null) ? (int) $row['PINGGOOGLE'] : null;

            // Speed conformity
            $conformity = null;
            if ($req && ! empty($req['IDPAKET'])) {
                $pkg = collect($paketRows)->firstWhere('KODEPAKET', $req['IDPAKET']);
                $pkgSpeed = $pkg['SPEEDDOWN'] ?? 0;
                $testSpeed = is_numeric($row['TESTDOWN'] ?? null) ? (float) $row['TESTDOWN'] * 1000 : 0; // Convert to kbps
                if ($pkgSpeed > 0 && $testSpeed > 0) {
                    $conformity = min(100, round(($testSpeed / $pkgSpeed) * 100, 2));
                }
            }

            // Fallback to real device-rental asset data when the installation
            // report itself left MAC/serial empty (very common in legacy data).
            $asset = $assetByRequest[$row['IDPERMINTAAN'] ?? ''] ?? null;
            $ontSn = $row['SNROOTER_FIBER'] ?: ($asset['serial'] ?? '');
            $routerMac = $row['MACADDR_ROOTER'] ?: ($asset['mac'] ?? '');
            $note = trim((string) ($row['KETERANGAN'] ?? ''));
            if (! empty($asset['brand_label']) && ! str_contains($note, $asset['brand_label'])) {
                $assetNote = 'Perangkat: '.$asset['brand_label'].' (dari data aset migrasi)';
                $note = $note !== '' ? $note.' | '.$assetNote : $assetNote;
            }

            $technicalSheet[] = [
                'old_report_id' => $row['IDREPORT'],
                'old_customer_id' => $row['IDPENGGUNA'] ?? '',
                'old_request_id' => $row['IDPERMINTAAN'] ?? '',
                'connection_type' => $row['JENIS'] ?: 'KABEL',
                'ont_sn' => $ontSn,
                'ip_address' => $row['IPADDR'] ?? '',
                'odp_code' => $row['NOMOR_ODP'] ?? '',
                'odp_port' => $row['NOMOR_PORT_ODP'] ?? '',
                'olt_code' => $oltCode,
                'olt_port' => $oltPort,
                'vlan_id' => '',

                // Full Device & Technical Fields mapping
                'ssid' => $row['SSID'] ?? '',
                'antenna_mac' => $row['MACADDR_ANTENA'] ?? '',
                'router_mac' => $routerMac,
                'wireless_signal' => $row['SIGNAL_WIRELESS'] ?? '',
                'fiber_signal' => $row['SIGNAL_KABEL'] ?? '',
                'location_source' => $row['LOKASIPEMANCAR'] ?? '',
                'note' => $note,
                'form_photo' => $row['FOTOFORMULIR'] ?? '',
                'signed_form_photo' => $row['FOTOTTDFORMULIR'] ?? '',
                'router_photo' => $row['FOTOROOTER'] ?? '',
                'cable_photo' => $row['FOTOKABEL'] ?? '',

                // Extended tech fields
                'passive_device' => $row['BRG_OUTDOOR'] ?? '',
                'branch_number' => $penggunaMap[$row['IDPENGGUNA']]['IDCABANG'] ?? '',
                'pop_number' => $penggunaMap[$row['IDPENGGUNA']]['IDWILAYAH'] ?? '',
                'router_number' => $routerMac,
                'initial_attenuation' => $this->cleanDecimal(is_numeric($row['SIGNAL_KABEL'] ?? null) ? (float) $row['SIGNAL_KABEL'] : (is_numeric($row['SIGNAL_WIRELESS'] ?? null) ? (float) $row['SIGNAL_WIRELESS'] : null), -999.99, 999.99),
                'actual_attenuation' => $this->cleanDecimal(is_numeric($actualAttenuation) ? (float) $actualAttenuation : null, -999.99, 999.99),
                'test_date' => $reqDate,
                'test_time' => $reqTime,
                'speedtest_photo' => $row['FOTOSPEED'] ?? '',
                'jitter_ms' => $jitter,
                'latency_ms' => $latency,
                'test_upload' => $row['TESTUP'] ?? null,
                'test_download' => $row['TESTDOWN'] ?? null,
                'packet_loss_percent' => 0.00,
                'speed_conformity_percent' => $conformity,
                'quality_score' => is_numeric($row['SINYAL'] ?? null) ? (int) $row['SINYAL'] : 5,
            ];
        }

        // Legacy `biaya_tagihan` sometimes has the exact same billing row inserted
        // many times back to back (retry/duplicate-submit bug: same customer,
        // same request, same fee amounts, seconds/minutes apart, same day).
        // Collapse those to one canonical row (the earliest) and remap every
        // duplicate's own IDBIAYA to it, so a payment tied to a duplicate's code
        // still lands on the surviving invoice instead of an invoice we never
        // generate.
        $canonicalCostId = [];
        $dedupSeen = [];
        foreach ($biayaRows as $row) {
            $dateOnly = '';
            try {
                $dateOnly = Carbon::parse($row['TGLINSERT'] ?? '')->format('Y-m-d');
            } catch (\Exception $e) {
            }
            $signature = implode('|', [
                $row['IDPELANGGAN'] ?? '',
                $row['IDPERMINTAAN'] ?? '',
                $row['BIAYAPASANG'] ?? '',
                $row['BIAYABULANAN'] ?? '',
                $row['BIAYALAINLAIN'] ?? '',
                $dateOnly,
            ]);
            if (! isset($dedupSeen[$signature])) {
                $dedupSeen[$signature] = $row['IDBIAYA'];
            }
            $canonicalCostId[$row['IDBIAYA']] = $dedupSeen[$signature];
        }

        // Dedup lapis kedua untuk bukti pembayaran: satu baris per
        // (cost id kanonik, BULANTAGIHAN).
        //
        // Lapis pertama (dekat parsing) memakai signature IDPERMINTAAN|BAYAR|tanggal
        // insert, jadi hanya menangkap retry yang terjadi di hari yang sama. Sistem
        // lama juga pernah menjalankan batch re-insert berbulan-bulan kemudian:
        // Ardiyanto (IN000035) punya dua bukti dengan BULANTAGIHAN identik
        // (2022-11-02) tapi INSERTED_AT 2022-11-02 dan 2022-12-27 — satu pembayaran
        // yang tercatat dobel, bukan pembayaran bulan berikutnya.
        //
        // Aturan pembedanya: IDTRANSAKSI + BULANTAGIHAN sama = duplikat. Kalau
        // BULANTAGIHAN berbeda, itu dua periode yang sah dan keduanya dipertahankan.
        $buktiByCostPeriod = [];
        $conflictingProofs = [];
        foreach ($buktiRows as $row) {
            $costId = $row['IDTRANSAKSI'] ?: ($row['IDPERMINTAAN'] ?? '');
            if ($costId === '') {
                continue;
            }
            $costId = $canonicalCostId[$costId] ?? $costId;
            $period = $this->legacyBillingPeriod($row['BULANTAGIHAN'] ?? null, $row['INSERTED_AT'] ?? null);
            $key = $costId.'|'.$period;

            $existing = $buktiByCostPeriod[$key] ?? null;
            if ($existing === null) {
                $buktiByCostPeriod[$key] = $row;

                continue;
            }

            $amount = (int) ($row['BAYAR'] ?? 0);
            $existingAmount = (int) ($existing['BAYAR'] ?? 0);
            if ($amount === $existingAmount) {
                continue; // duplikat murni, aman dibuang
            }

            // Nominal berbeda = bukan duplikat murni. Di jetis_db selisihnya selalu
            // tepat Rp 11.000 (materai) — kemungkinan besar koreksi manual admin
            // lama. Pertahankan yang bermaterai dan laporkan supaya bisa ditinjau
            // tim billing; jangan dibuang diam-diam.
            $conflictingProofs[] = sprintf(
                '%s periode %s: %s vs %s',
                $costId,
                $period,
                number_format($existingAmount, 0, ',', '.'),
                number_format($amount, 0, ',', '.')
            );
            if ($amount > $existingAmount) {
                $buktiByCostPeriod[$key] = $row;
            }
        }
        $buktiRows = array_values($buktiByCostPeriod);

        // Uang yang benar-benar tertagih, di-key per (cost id, periode tagihan).
        //
        // KRUSIAL: legacy `IDBIAYA` konstan seumur hidup pelanggan — dia nomor
        // kontrak biaya, BUKAN nomor invoice. Seluruh pembayaran bulanan pelanggan
        // memakai IDTRANSAKSI yang sama; yang membedakan periode hanya BULANTAGIHAN.
        // Map ini dulu di-key `costId` saja, sehingga semua pembayaran sepanjang masa
        // langganan dijumlahkan jadi satu "tagihan awal" raksasa (Ardiyanto:
        // 2 × 165.000 = 330.000 padahal paketnya 165.000).
        // Dua peta terpisah, sengaja:
        //  - $periodsByCost  : SEMUA periode yang punya jejak tagihan, termasuk yang
        //                      BAYAR=0. Baris BAYAR=0 tetap dipakai karena
        //                      `BULANTAGIHAN`-nya satu-satunya penanda periode yang
        //                      bisa dipercaya untuk tagihan yang belum dibayar
        //                      (mis. baris reaktivasi Boyke Santiago IN001619:
        //                      BAYAR=0, BULANTAGIHAN 2025-05). Kalau ikut dibuang,
        //                      invoicenya jatuh ke periode aktivasi pertama dan
        //                      menabrak tagihan lama pelanggan yang sama.
        //  - $paidByCostPeriod: nominal, hanya dari baris BAYAR>0.
        $periodsByCost = [];
        $paidByCostPeriod = [];
        foreach ($buktiRows as $row) {
            $costId = $row['IDTRANSAKSI'] ?: ($row['IDPERMINTAAN'] ?? '');
            if ($costId === '') {
                continue;
            }
            $costId = $canonicalCostId[$costId] ?? $costId;
            $period = $this->legacyBillingPeriod($row['BULANTAGIHAN'] ?? null, $row['INSERTED_AT'] ?? null);
            $periodsByCost[$costId][$period] = true;

            if ((int) ($row['BAYAR'] ?? 0) > 0) {
                $paidByCostPeriod[$costId][$period] = ($paidByCostPeriod[$costId][$period] ?? 0) + (int) $row['BAYAR'];
            }
        }

        // Sheet 5: invoices
        $invoicesSheet = [];
        // (IDBIAYA kanonik => periode 'Y-m' => old_invoice_id), supaya loop payments
        // di bawah bisa merutekan tiap bukti bayar ke invoice periodenya sendiri.
        $invoiceKeyByCostPeriod = [];
        $skippedActivationLogRows = 0;
        foreach ($biayaRows as $row) {
            // Skip duplicate rows collapsed into an earlier canonical row.
            if (($canonicalCostId[$row['IDBIAYA']] ?? $row['IDBIAYA']) !== $row['IDBIAYA']) {
                continue;
            }

            // Filter: skip internal users
            $cust = $row['IDPELANGGAN'] ?? $requestToCustomerMap[$row['IDPERMINTAAN'] ?? ''] ?? '';
            if (! str_starts_with($cust, 'PE')) {
                continue;
            }

            $costId = $row['IDBIAYA'];
            $requestId = $row['IDPERMINTAAN'] ?? '';
            $installationFee = (int) ($row['BIAYAPASANG'] ?? 0);
            $otherFee = (int) ($row['BIAYALAINLAIN'] ?? 0);
            $monthlyFee = (int) ($row['BIAYABULANAN'] ?? 0);

            // Legacy menulis satu baris `biaya_tagihan` setiap kali admin menekan
            // "Berhasil Active" — bukan setiap kali tagihan terbit. Baris dengan
            // BIAYAPASANG=0 DAN BIAYABULANAN=0 tidak menagihkan apa pun (isinya cuma
            // materai): itu jejak log aktivasi, bukan tagihan. Wiyono Wonoketro
            // punya sembilan baris seperti ini dari sembilan klik dalam dua menit,
            // dan sebelumnya salah satunya jadi utang hantu Rp 11.000 yang tidak
            // pernah ada di sistem lama. Sisi pembayaran sudah menolak BAYAR<=0;
            // sisi invoice sekarang ikut menolak supaya guard-nya tidak asimetris.
            if ($installationFee <= 0 && $monthlyFee <= 0) {
                $skippedActivationLogRows++;

                continue;
            }

            // Hanya BIAYAPASANG>0 yang benar-benar tagihan pemasangan. BIAYALAINLAIN
            // (materai Rp 11.000) menempel di hampir semua baris termasuk pelanggan
            // lama, jadi ikut memakainya sebagai penanda registrasi bikin 1.707 dari
            // 1.707 invoice berlabel "awal" padahal di jetis_db cuma 29 baris yang
            // punya BIAYAPASANG > 0.
            $isRegistrationRow = $installationFee > 0;

            $periods = array_keys($periodsByCost[$costId] ?? []);
            sort($periods);

            // `TGLINSERT` adalah kolom ON UPDATE current_timestamp() — nilainya waktu
            // perubahan TERAKHIR, bukan tanggal tagihan terbit (IN000037 bertanggal
            // 2025-05 padahal pembayarannya November 2022). Karena itu periode acuan
            // diambil dari riwayat "Berhasil Active" dulu, lalu periode pembayaran
            // paling awal, dan TGLINSERT hanya jaring pengaman terakhir.
            $activatedAt = $activationLogByRequest[$requestId] ?? null;
            $anchorPeriod = $this->resolveLegacyAnchorPeriod($activatedAt, $periods, $row['TGLINSERT'] ?? null);

            $billedTotal = $installationFee + $otherFee + $monthlyFee;

            $baseInvoice = [
                'old_request_id' => $requestId,
                'old_customer_id' => $row['IDPELANGGAN'] ?: $cust,
                'status' => 'belum_dibayar',
                'extra_cable_fee' => 0,
                'extra_pole_fee' => 0,
            ];

            // `$invoiceOtherFee` sengaja tidak selalu ikut `$otherFee` legacy:
            // untuk periode yang SUDAH dibayar, totalnya adalah uang yang benar-benar
            // masuk dan tidak bisa dipecah lagi jadi komponen (materai sering memang
            // tidak ditagihkan). Mengisi `other_fee` di situ bikin rinciannya bohong.
            $pushInvoice = function (string $period, string $type, int $totalAmount, ?int $prorate, int $invoiceOtherFee) use (
                &$invoicesSheet, &$invoiceKeyByCostPeriod, $baseInvoice, $costId, $installationFee, $monthlyFee, $activatedAt
            ) {
                $isAwal = $type === 'awal';
                $invoiceKey = $costId.'-'.($isAwal ? 'AWAL' : str_replace('-', '', $period));
                $issueDate = $this->legacyPeriodIssueDate($period, $isAwal ? $activatedAt : null);

                $invoicesSheet[] = array_merge($baseInvoice, [
                    'old_invoice_id' => $invoiceKey,
                    'old_cost_id' => $invoiceKey,
                    'invoice_type' => $type,
                    'billing_period' => $period,
                    'issue_date' => $issueDate,
                    'due_date' => $this->legacyDueDate($issueDate),
                    'total_amount' => $totalAmount,
                    'monthly_fee' => $isAwal ? null : $monthlyFee,
                    'extra_installation_fee' => $isAwal ? $installationFee : 0,
                    'installation_fee' => $isAwal ? $installationFee : 0,
                    'other_fee' => $invoiceOtherFee,
                    'prorate_amount' => $prorate,
                ]);
                $invoiceKeyByCostPeriod[$costId][$period] = $invoiceKey;
            };

            // Tidak ada satu pun jejak tagihan untuk cost id ini: tetap terbitkan satu
            // tagihan supaya tunggakan legacy tidak hilang begitu saja di sistem baru.
            if ($periods === []) {
                $pushInvoice($anchorPeriod, $isRegistrationRow ? 'awal' : 'bulanan', $billedTotal, null, $otherFee);

                continue;
            }

            foreach ($periods as $index => $period) {
                $paidForPeriod = $paidByCostPeriod[$costId][$period] ?? 0;
                $isAnchor = $index === 0;

                // Periode pertama pada baris registrasi = tagihan awal. Sisanya —
                // dan seluruh periode pada baris non-registrasi — adalah tagihan
                // bulanan yang dulu ikut ditelan invoice AWAL.
                if ($isRegistrationRow && $isAnchor) {
                    // Admin lama memprorata tagihan awal secara manual dan tidak
                    // pernah menuliskannya balik ke biaya_tagihan, jadi pakai yang
                    // benar-benar tertagih (bukti tagihan + bukti pemasangan)
                    // ketimbang menghitung ulang pasang + materai + bulanan.
                    $paidFromPasang = ($installationPaidAt[$costId] ?? null) !== null ? $installationFee : 0;
                    $actualPaid = $paidForPeriod + $paidFromPasang;
                    $totalAmount = $actualPaid > 0 ? $actualPaid : $billedTotal;

                    $pushInvoice($period, 'awal', $totalAmount, $totalAmount < $billedTotal ? $totalAmount : null, $otherFee);

                    continue;
                }

                if ($paidForPeriod > 0) {
                    $pushInvoice($period, 'bulanan', $paidForPeriod, null, 0);

                    continue;
                }

                // Belum dibayar. Materai cuma menempel di tagihan pertama baris ini;
                // periode lanjutan flat sebesar biaya bulanan.
                $pushInvoice($period, 'bulanan', $monthlyFee + ($isAnchor ? $otherFee : 0), null, $isAnchor ? $otherFee : 0);
            }
        }

        // Sheet 6: payments
        $paymentsSheet = [];
        foreach ($buktiRows as $row) {
            // BAYAR=0 rows are activation/log placeholders, not real payments
            // (legacy system logs the billing event itself before money is
            // actually collected) — skip so we don't create a phantom "Valid"
            // Rp 0 payment against the invoice.
            if ((int) ($row['BAYAR'] ?? 0) <= 0) {
                continue;
            }

            $reqCust = $requestToCustomerMap[$row['IDPERMINTAAN'] ?? ''] ?? '';
            $invCust = $invoiceToCustomerMap[$row['IDTRANSAKSI'] ?? ''] ?? '';
            $cust = $invCust ?: $reqCust;

            // Filter: skip internal users
            if ($cust !== '' && ! str_starts_with($cust, 'PE')) {
                continue;
            }

            $payDate = now()->format('Y-m-d');
            if (! empty($row['INSERTED_AT'])) {
                try {
                    $payDate = Carbon::parse($row['INSERTED_AT'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            $billingPeriod = $this->legacyBillingPeriod($row['BULANTAGIHAN'] ?? null, $row['INSERTED_AT'] ?? null);

            // Metode/penerima/catatan diambil dari bukti lunas PERIODE INI dulu; baru
            // jatuh ke baris tertua cost id yang sama kalau periodenya tidak ketemu.
            // Tanpa langkah pertama, pembayaran bulan kedua dan seterusnya mencatut
            // metode & penerima pembayaran pertama (lihat catatan di peta itu).
            $transactionId = $row['IDTRANSAKSI'] ?? '';
            $lunas = $lunasByTransactionPeriod[$transactionId][$billingPeriod]
                ?? $lunasByTransaction[$transactionId]
                ?? null;

            $costId = $row['IDTRANSAKSI'] ?: $row['IDPERMINTAAN'];
            $costId = $canonicalCostId[$costId] ?? $costId;

            // Rutekan ke invoice PERIODE-nya sendiri, bukan ke satu invoice per cost
            // id. Sebelumnya semua bukti bayar sepanjang masa langganan menempel ke
            // `{costId}-AWAL` karena peta tipenya cuma bernilai tunggal per cost id —
            // itu yang bikin satu tagihan awal punya dua "pembayaran awal".
            $invoiceKey = $invoiceKeyByCostPeriod[$costId][$billingPeriod] ?? null;
            if ($invoiceKey === null) {
                // Periodenya tidak punya invoice (mis. baris biaya-nya dibuang sebagai
                // log aktivasi). Jatuhkan ke invoice paling awal milik cost id yang
                // sama supaya uangnya tidak hilang; kalau memang tidak ada invoice
                // sama sekali, importer yang akan mencatatnya sebagai gagal petakan.
                $fallback = $invoiceKeyByCostPeriod[$costId] ?? [];
                ksort($fallback);
                $invoiceKey = $fallback === [] ? $costId.'-AWAL' : reset($fallback);
            }

            $paymentsSheet[] = [
                'old_payment_id' => $row['IDUNIQ'],
                'old_invoice_id' => $invoiceKey,
                'old_transaction_id' => $row['IDTRANSAKSI'] ?? '',
                'old_request_id' => $row['IDPERMINTAAN'] ?? '',
                'old_customer_id' => $cust,
                'billing_period' => $billingPeriod,
                'amount' => (int) ($row['BAYAR'] ?? 0),
                'payment_date' => $payDate,
                'payment_method' => $lunas['JENISPEMBAYARAN'] ?? 'cash',
                'received_by_old' => $this->resolveLegacyUserLabel($lunas['IDPENERIMA'] ?? '', $penggunaMap),
                'deposited_by_old' => $this->resolveLegacyUserLabel($lunas['IDPENYETOR'] ?? '', $penggunaMap),
                'note' => trim((string) ($lunas['KET'] ?? '')),
                'status' => 'valid',
            ];
        }

        // Sheet 6b: installation-fee payments, sourced from
        // apikeuangan_buktitransaksipemasangan which the legacy system never
        // mixed into the recurring monthly billing proof table. Always the AWAL
        // invoice — this table only ever records the PSB installation payment.
        foreach ($biayaRows as $row) {
            // Skip duplicate rows collapsed into an earlier canonical row.
            if (($canonicalCostId[$row['IDBIAYA']] ?? $row['IDBIAYA']) !== $row['IDBIAYA']) {
                continue;
            }

            $invoiceCode = $row['IDBIAYA'];
            $paidAt = $installationPaidAt[$invoiceCode] ?? null;
            $installationFee = (int) ($row['BIAYAPASANG'] ?? 0);

            if ($paidAt === null || $installationFee <= 0) {
                continue;
            }

            $cust = $row['IDPELANGGAN'] ?? '';
            if (! str_starts_with($cust, 'PE')) {
                continue;
            }

            $payDate = now()->format('Y-m-d');
            try {
                $payDate = Carbon::parse($paidAt)->format('Y-m-d');
            } catch (\Exception $e) {
            }

            $paymentsSheet[] = [
                'old_payment_id' => $invoiceCode.'-PASANG',
                'old_invoice_id' => $invoiceCode.'-AWAL',
                'old_transaction_id' => $invoiceCode,
                'old_request_id' => $row['IDPERMINTAAN'] ?? '',
                'old_customer_id' => $cust,
                'billing_period' => Carbon::parse($payDate)->format('Y-m'),
                'amount' => $installationFee,
                'payment_date' => $payDate,
                'payment_method' => 'cash',
                'received_by_old' => '',
                'deposited_by_old' => '',
                'note' => 'Pembayaran biaya pasang (migrasi)',
                'status' => 'valid',
            ];
        }

        // Tag every row with the branch POP code for this import run so the
        // controller can scope its "already imported" duplicate checks per
        // branch instead of globally — legacy dumps from different branches
        // reuse the same sequential ID scheme (PE000001, RQ000001, ...) starting
        // from 1 in each source install, so old_customer_id/old_request_id/
        // old_cost_id collide across branches even though they refer to
        // completely different people.
        $primaryBranchPopCode = $overrideCode ?: (collect($legacyPopMap)->first()['pop_code'] ?? '');
        foreach ([&$customersSheet, &$servicesSheet, &$technicalSheet, &$invoicesSheet, &$paymentsSheet] as &$sheetRows) {
            foreach ($sheetRows as &$sheetRow) {
                $sheetRow['branch_pop_code'] = $primaryBranchPopCode;
            }
            unset($sheetRow);
        }
        unset($sheetRows);

        if ($skippedWithoutRequest > 0) {
            $this->warn("Dilewati {$skippedWithoutRequest} akun ber-prefix PE tanpa permintaan layanan (bukan pelanggan).");
        }
        if ($servicesWithoutPackage > 0) {
            $this->warn("{$servicesWithoutPackage} layanan tanpa IDPAKET dijatuhkan ke paket placeholder legacy — cek manual setelah import.");
        }
        if ($skippedActivationLogRows > 0) {
            $this->warn("Dilewati {$skippedActivationLogRows} baris biaya_tagihan tanpa biaya pasang & biaya bulanan (jejak klik 'Berhasil Active', bukan tagihan).");
        }
        if ($conflictingProofs !== []) {
            $this->warn(count($conflictingProofs).' bukti bayar dobel dengan NOMINAL BERBEDA — yang terbesar dipakai, tinjau manual:');
            foreach ($conflictingProofs as $line) {
                $this->line('  - '.$line);
            }
        }

        // Riwayat tagihan legacy hanya menutup ~5% dari bulan-bulan yang benar-benar
        // dilalui pelanggan: `biaya_tagihan` itu kontrak biaya per pemasangan (tanpa
        // kolom periode), dan satu-satunya jejak per bulan (`apikeuangan_
        // buktitransaksitagihan`) cuma berisi puluhan baris per bulan untuk ribuan
        // pelanggan. Hasilnya riwayat yang bolong-bolong dan menyesatkan kalau dibaca
        // sebagai tunggakan.
        //
        // Karena itu ada skenario go-live "pelanggan saja": tagihan berjalan
        // diselesaikan manual di luar sistem, lalu sistem mulai bersih dari periode
        // berikutnya lewat `billing:generate-monthly-invoices`. Harga langganan tetap
        // ikut terimpor (`customer_services.monthly_price` dari `BIAYABULANAN`), jadi
        // generator tetap punya dasar nominal. `activation_date` juga tidak bergantung
        // pada sheet ini — sumbernya `prosedure_permintaan_wifi`/`riwayat_pelanggan`.
        $withoutBilling = (bool) $this->option('without-billing');

        if ($withoutBilling) {
            $this->warn('--without-billing: '.count($invoicesSheet).' tagihan & '.count($paymentsSheet).' pembayaran legacy TIDAK diimpor.');
        }

        $sheets = [
            'packages' => $packagesSheet,
            'customers' => $customersSheet,
            'services' => $servicesSheet,
            'technical_details' => $technicalSheet,
            'invoices' => $withoutBilling ? [] : $invoicesSheet,
            'payments' => $withoutBilling ? [] : $paymentsSheet,
        ];

        // Ensure we are logged in as admin to have full access/audit logs
        $admin = User::whereHas('role', function ($q) {
            $q->where('name', 'Owner');
        })->first();

        if (! $admin) {
            $admin = User::first();
        }

        if (! $admin) {
            // Create a system user if none exists
            $ownerRole = Role::where('code', 'owner')->first();

            $admin = User::create([
                'name' => 'System Admin',
                'username' => 'system',
                'email' => 'system@whusnet.local',
                'password' => bcrypt('password'),
                'status' => 'active',
                'role_id' => $ownerRole?->id,
            ]);
            $this->info('Created a fallback System Admin user.');
        }

        if ($admin) {
            Auth::login($admin);
            $this->info('Logged in programmatically as: '.$admin->name);
        } else {
            $this->warn('No user found. Migration might fail audit log validation.');
        }

        $this->info('Validating import data via internal controller call...');

        $requestValidate = Request::create('/customers/import/validate', 'POST', [
            'sheets' => $sheets,
        ]);

        $controller = app(CustomerController::class);
        $validateResponse = $controller->validateImport($requestValidate);

        $validateData = json_decode($validateResponse->getContent(), true);
        if (! $validateData['success']) {
            $this->error('Validation failed. '.json_encode($validateData['errors'] ?? []));

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $this->info('Validation successful. Confirming import...');

        $requestConfirm = Request::create('/customers/import/confirm', 'POST', [
            'sheets' => json_encode($validateData['sheets']),
            'file_name' => $fileName,
        ]);

        try {
            $confirmResponse = $controller->confirmImport($requestConfirm);

            // Output flash messages
            if (session()->has('success')) {
                $this->info('Success: '.session('success'));
            }
            if (session()->has('errors')) {
                $this->error('Errors: '.json_encode(session('errors')->getBag('default')->all()));
            }

            $this->info('Data migration execution completed.');

            return \Symfony\Component\Console\Command\Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Migration failed with exception: '.$e->getMessage());

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }
    }

    private function resolveLegacySurveyRow(array $row, array $surveyMap): ?array
    {
        foreach ([
            $row['IDPERMINTAAN'] ?? null,
            $row['IDPENGGUNA'] ?? null,
            $row['IDSURVEY'] ?? null,
        ] as $key) {
            if (! empty($key) && isset($surveyMap[$key])) {
                return $surveyMap[$key];
            }
        }

        return null;
    }

    private function resolveLegacyAddressText(array $row): string
    {
        $streetAddress = trim((string) ($row['ALMT'] ?? $row['ALAMAT'] ?? ''));
        if ($streetAddress !== '' && ! in_array(strtolower($streetAddress), ['-', 'null', 'n/a'], true)) {
            return $streetAddress;
        }

        $parts = array_filter([
            trim((string) ($row['DESA'] ?? '')),
            trim((string) ($row['KEC'] ?? '')),
            trim((string) ($row['KOTA'] ?? '')),
        ]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return $streetAddress !== '' ? $streetAddress : '-';
    }

    /**
     * Periode tagihan legacy (`Y-m`) dari `BULANTAGIHAN`, dengan `INSERTED_AT`
     * sebagai cadangan saat kolomnya kosong atau `0000-00-00`.
     *
     * Ini satu-satunya pembeda periode di `apikeuangan_buktitransaksitagihan` —
     * `IDTRANSAKSI` konstan seumur hidup pelanggan, jadi tanpa kolom ini semua
     * pembayaran terlihat seperti pembayaran yang sama.
     */
    private function legacyBillingPeriod(?string $bulanTagihan, ?string $fallback): string
    {
        foreach ([$bulanTagihan, $fallback] as $candidate) {
            if (empty($candidate) || str_starts_with((string) $candidate, '0000')) {
                continue;
            }

            try {
                return Carbon::parse($candidate)->format('Y-m');
            } catch (\Exception $e) {
            }
        }

        return now()->format('Y-m');
    }

    /**
     * Periode acuan sebuah baris `biaya_tagihan`.
     *
     * Urutan: tanggal aktivasi asli (riwayat "Berhasil Active") → periode
     * pembayaran paling awal → `TGLINSERT`. `TGLINSERT` sengaja ditaruh paling
     * belakang karena kolomnya `ON UPDATE current_timestamp()`, jadi nilainya
     * waktu perubahan terakhir dan bisa meleset bertahun-tahun dari tanggal
     * tagihan yang sebenarnya.
     *
     * @param  array<int, string>  $paymentPeriods  periode 'Y-m' terurut menaik
     */
    private function resolveLegacyAnchorPeriod(?string $activatedAt, array $paymentPeriods, ?string $insertedAt): string
    {
        if (! empty($activatedAt) && ! str_starts_with((string) $activatedAt, '0000')) {
            try {
                return Carbon::parse($activatedAt)->format('Y-m');
            } catch (\Exception $e) {
            }
        }

        if ($paymentPeriods !== []) {
            return $paymentPeriods[0];
        }

        return $this->legacyBillingPeriod(null, $insertedAt);
    }

    /**
     * Tanggal terbit sebuah invoice periode `Y-m`.
     *
     * Pakai tanggal asli (mis. tanggal aktivasi) kalau memang jatuh di periode
     * yang sama — kalau tidak, tanggal 1 bulan itu. Yang penting invoice tidak
     * lagi bertanggal `TGLINSERT` yang bisa berada di tahun yang salah.
     */
    private function legacyPeriodIssueDate(string $period, ?string $preferredDate = null): string
    {
        if (! empty($preferredDate) && ! str_starts_with((string) $preferredDate, '0000')) {
            try {
                $parsed = Carbon::parse($preferredDate);
                if ($parsed->format('Y-m') === $period) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Exception $e) {
            }
        }

        return $period.'-01';
    }

    /**
     * Jatuh tempo sebuah invoice legacy.
     *
     * Aturannya tanggal kalender tetap — tempo tanggal 10 — persis seperti
     * GenerateMonthlyInvoicesCommand. Dulu di sini `addDays(10)`, sehingga
     * seluruh tagihan bulanan hasil import jatuh tempo tanggal 11 sementara
     * tagihan yang digenerate cron jatuh tempo tanggal 10: satu aturan bisnis,
     * dua hasil, dan pelanggan yang sama bisa punya dua tanggal tempo berbeda
     * cuma karena tagihannya berasal dari jalur yang berbeda.
     *
     * Invoice AWAL terbit di tanggal aktivasi, yang sering sudah lewat tanggal
     * 10. Tempo tidak boleh mendahului terbit, jadi kasus itu digeser ke
     * tanggal 10 bulan berikutnya.
     */
    private function legacyDueDate(string $issueDate): string
    {
        $issued = Carbon::parse($issueDate);
        $due = $issued->copy()->day(10);

        if ($due->lt($issued)) {
            $due = $issued->copy()->addMonthNoOverflow()->day(10);
        }

        return $due->format('Y-m-d');
    }

    private function resolveLegacyUserLabel(mixed $value, array $penggunaMap): string
    {
        $raw = trim((string) $value);
        if ($raw === '' || in_array(strtolower($raw), ['-', 'null', 'n/a'], true)) {
            return '';
        }

        $tokens = preg_split('/\s*(?:,|\/|\||;|\r\n|\n)\s*/', $raw) ?: [$raw];
        $labels = [];

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            if (isset($penggunaMap[$token])) {
                $labels[] = $this->buildLegacyUserName($penggunaMap[$token]) ?: $token;

                continue;
            }

            $labels[] = $token;
        }

        $labels = array_values(array_filter(array_map('trim', $labels), fn ($label) => $label !== ''));

        return $labels !== [] ? implode(', ', array_unique($labels)) : $raw;
    }

    private function buildLegacyUserName(array $row): string
    {
        $name = trim((string) (($row['NAMADEPAN'] ?? '').' '.($row['NAMABELAKANG'] ?? '')));
        $name = preg_replace('/\s+/', ' ', $name) ?: '';

        return $name !== '' ? $name : trim((string) ($row['IDPENGGUNA'] ?? ''));
    }

    /**
     * Build POP records from the legacy cabang table.
     *
     * @return array<string, array{pop_code: string, pop_name: string, pop_model: Pop}>
     */
    private function createLegacyPopMap(array $cabangRows, ?string $overrideCode = null, ?string $overrideName = null): array
    {
        $map = [];

        foreach ($cabangRows as $row) {
            $legacyBranchId = trim((string) ($row['ID'] ?? ''));
            $legacyBranchName = trim((string) ($row['CABANG'] ?? ''));

            // 1. Tentukan Kode Cabang POP
            if ($overrideCode) {
                $popCode = strtoupper(trim($overrideCode));
            } else {
                // Tanya secara interaktif jika dijalankan tanpa parameter
                $detectedDefault = $this->resolveLegacyBranchPopCode($legacyBranchName, $legacyBranchId);
                $popCode = $this->ask("Cabang legacy '{$legacyBranchName}' terdeteksi. Masukkan Kode POP baru (contoh: C, D)", $detectedDefault);
                $popCode = strtoupper(trim($popCode));
            }

            // 2. Tentukan Nama Cabang POP
            if ($overrideName) {
                $popName = $overrideName;
            } else {
                $popName = $this->ask('Masukkan Nama POP baru untuk cabang ini', $legacyBranchName ?: $popCode);
            }

            $pop = Pop::firstOrCreate(
                ['pop_code' => $popCode],
                [
                    'code' => $popCode,
                    'name' => $popName,
                    'type' => 'cabang',
                    'status' => 'active',
                    'registration_prefix' => 'RQ',
                    'cid_prefix' => $popCode, // Otomatis diset sesuai kode cabang yang dipilih (C atau D)
                    'latitude' => is_numeric($row['LAT_PERUSAHAAN'] ?? null) ? (float) $row['LAT_PERUSAHAAN'] : null,
                    'longitude' => is_numeric($row['LONG_PERUSAHAAN'] ?? null) ? (float) $row['LONG_PERUSAHAAN'] : null,
                ]
            );

            $map[$legacyBranchId !== '' ? $legacyBranchId : $popCode] = [
                'pop_code' => $pop->pop_code ?? $popCode,
                'pop_name' => $pop->name ?? $popName,
                'pop_model' => $pop,
            ];
        }

        return $map;
    }

    private function resolveLegacyBranchPopCode(string $legacyBranchName, string $legacyBranchId): string
    {
        $branchName = strtoupper(trim($legacyBranchName));
        $branchCode = preg_replace('/[^A-Z0-9]+/', '', $branchName) ?: '';
        if ($branchCode !== '' && $branchCode !== 'JETIS') {
            return substr($branchCode, 0, 1);
        }

        $branchId = preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($legacyBranchId))) ?: '';
        if ($branchId !== '') {
            return 'C'.$branchId;
        }

        return 'C';
    }

    /**
     * Resolve the POP context for a legacy branch id.
     *
     * @param  array<string, array{pop_code: string, pop_name: string, pop_model: Pop}>  $legacyPopMap
     * @return array{pop_code: string, pop_name: string, pop_model: Pop}
     */
    private function resolveLegacyPopForBranch(string $legacyBranchId, array $legacyPopMap): array
    {
        if ($legacyBranchId !== '' && isset($legacyPopMap[$legacyBranchId])) {
            return $legacyPopMap[$legacyBranchId];
        }

        if (isset($legacyPopMap['default'])) {
            return $legacyPopMap['default'];
        }

        $firstPop = collect($legacyPopMap)->first();
        if ($firstPop) {
            return $firstPop;
        }

        $defaultPop = Pop::firstOrCreate(
            ['pop_code' => 'UNASSIGNED'],
            [
                'code' => 'UNASSIGNED',
                'name' => 'Belum Dialokasikan',
                'type' => 'cabang',
                'status' => 'active',
                'registration_prefix' => 'RQ',
                'cid_prefix' => 'C',
            ]
        );

        return [
            'pop_code' => $defaultPop->pop_code ?? 'UNASSIGNED',
            'pop_name' => $defaultPop->name ?? 'Belum Dialokasikan',
            'pop_model' => $defaultPop,
        ];
    }

    private function normalizeLegacyMiniPopSegment(mixed $value): string
    {
        $segment = strtoupper(trim((string) $value));
        $segment = preg_replace('/[^A-Z0-9]+/', '', $segment) ?: '';

        if ($segment === '' || $segment === '0') {
            return '1';
        }

        $replaced = preg_replace('/^([A-Z]*)0+([1-9A-Z].*)?$/', '$1$2', $segment);
        if ($replaced !== null && $replaced !== '') {
            $segment = $replaced;
        }

        if ($segment === '' || $segment === '0') {
            return '1';
        }

        return $segment;
    }

    private function normalizeLegacyPopCode(string $name, string $fallback = ''): string
    {
        $value = strtoupper(trim($name));
        $value = preg_replace('/[^A-Z0-9]+/', '', $value) ?: '';

        if ($value !== '') {
            return $value;
        }

        $fallback = strtoupper(trim($fallback));
        $fallback = preg_replace('/[^A-Z0-9]+/', '', $fallback) ?: '';

        return $fallback !== '' ? 'CB'.$fallback : 'UNASSIGNED';
    }

    private function normalizeCoordinate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || in_array(strtolower($value), ['null', 'nil', 'n/a', '-'], true)) {
            return null;
        }

        // Replace comma with dot
        $value = str_replace(',', '.', $value);

        // Keep only digits, dots, minus sign
        $value = preg_replace('/[^\d\.\-]/', '', $value);

        if (! is_numeric($value)) {
            return null;
        }

        $floatVal = (float) $value;

        // If the absolute value is out of range (> 180), it means it's a shifted coordinate (missing decimal point)
        if (abs($floatVal) > 180) {
            // Strip any existing dot or minus sign to get only digits
            $isNegative = str_starts_with($value, '-');
            $digits = preg_replace('/[^\d]/', '', $value);

            if ($digits === '') {
                return null;
            }

            if ($isNegative) {
                // Negative coordinates in Indonesia are always latitude (around -7 or -8)
                // Place the dot after the first digit
                $normalized = '-'.substr($digits, 0, 1).'.'.substr($digits, 1);
            } else {
                // Positive coordinates
                if (str_starts_with($digits, '1')) {
                    // Longitude in Indonesia is around 110-115
                    // Place the dot after the first 3 digits
                    $normalized = substr($digits, 0, 3).'.'.substr($digits, 3);
                } else {
                    // Positive latitude
                    $normalized = substr($digits, 0, 1).'.'.substr($digits, 1);
                }
            }
            $value = $normalized;
        }

        return is_numeric($value) ? $value : null;
    }

    private function cleanDecimal(mixed $value, float $min = -999.99, float $max = 999.99): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || in_array(strtolower($value), ['null', 'nil', 'n/a', '-'], true)) {
            return null;
        }

        // Replace comma with dot
        $value = str_replace(',', '.', $value);

        // Keep only digits, dots, minus sign
        $value = preg_replace('/[^\d\.\-]/', '', $value);

        if (! is_numeric($value)) {
            return null;
        }

        $val = (float) $value;

        return ($val >= $min && $val <= $max) ? $val : null;
    }
}
