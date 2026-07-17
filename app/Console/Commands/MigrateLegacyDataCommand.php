<?php

namespace App\Console\Commands;

use App\Models\Distribution;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class MigrateLegacyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
protected $signature = 'app:import-legacy-sql 
                        {file? : The path to the legacy sql dump. Default: sand_db_sandya.sql} 
                        {--branch-code= : Tentukan Kode Cabang POP target (contoh: C, D)} 
                        {--branch-name= : Tentukan Nama Cabang POP target (contoh: Jetis, Siman)}';

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

        if (!file_exists($sqlPath)) {
            $this->error("File {$fileName} does not exist in root directory.");
            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $this->info("Starting migration from {$fileName}");
        $sql = file_get_contents($sqlPath);

        // Parse tables
        $this->info("Parsing SQL dump...");
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
                $dateOnly = \Carbon\Carbon::parse($row['INSERTED_AT'] ?? '')->format('Y-m-d');
            } catch (\Exception $e) {
            }
            $signature = ($row['IDPERMINTAAN'] ?? '') . '|' . (int) ($row['BAYAR'] ?? 0) . '|' . $dateOnly;
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

        $this->info("Parsed counts:");
        $this->line("- cabang: " . count($cabangRows));
        $this->line("- paket: " . count($paketRows));
        $this->line("- pengguna: " . count($penggunaRows));
        $this->line("- prosedure_permintaan_wifi: " . count($layananRows));
        $this->line("- laporan_pemasangan_wifi: " . count($laporanRows));
        $this->line("- biaya_tagihan: " . count($biayaRows));
        $this->line("- apikeuangan_buktitransaksitagihan: " . count($buktiRows));
        $this->line("- survey_pemasangan_wifi: " . count($surveyRows));
        $this->line("- olt_slot_register: " . count($oltRows));
        $this->line("- kode_kontrol_distribusi: " . count($distribusiRows));
        $this->line("- barang: " . count($barangRows));
        $this->line("- merk_barang: " . count($merkBarangRows));
        $this->line("- riwayatstatus_penggunabarang: " . count($riwayatBarangRows));
        $this->line("- apikeuangan_buktitransaksilunas: " . count($buktiLunasRows));
        $this->line("- apikeuangan_buktitransaksipemasangan: " . count($buktiPemasanganRows));
        $this->line("- riwayat_pelanggan: " . count($riwayatPelangganRows));

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
            if (!empty($row['KODEBARANG'])) {
                $barangByCode[$row['KODEBARANG']] = $row;
            }
        }
        $merkBarangById = [];
        foreach ($merkBarangRows as $row) {
            if (!empty($row['IDMERK'])) {
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

        // Payment-truth map: IDTRANSAKSI => first (oldest) real payment proof row,
        // sourced from apikeuangan_buktitransaksilunas which has the real payment
        // method/receiver/note (unlike apikeuangan_buktitransaksitagihan).
        $lunasByTransaction = [];
        foreach ($buktiLunasRows as $row) {
            $transactionId = $row['IDTRANSAKSI'] ?? '';
            if ($transactionId === '') {
                continue;
            }
            $existing = $lunasByTransaction[$transactionId] ?? null;
            if ($existing === null || strcmp((string) $row['TGLBAYAR'], (string) $existing['TGLBAYAR']) < 0) {
                $lunasByTransaction[$transactionId] = $row;
            }
        }

        // Installation-fee payment map: IDPERMINTAAN (actually holds the invoice code, e.g. IN000006) => TGLBAYAR
        $installationPaidAt = [];
        foreach ($buktiPemasanganRows as $row) {
            $invoiceCode = $row['IDPERMINTAAN'] ?? '';
            if ($invoiceCode !== '' && !isset($installationPaidAt[$invoiceCode])) {
                $installationPaidAt[$invoiceCode] = $row['TGLBAYAR'] ?? null;
            }
        }

        if (empty($paketRows) && empty($penggunaRows)) {
            $this->error("No data parsed. Make sure the SQL format matches.");
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

        $this->info("Mapping data to sheets...");

        // Pre-build maps for lookup
        $penggunaMap = [];
        foreach ($penggunaRows as $row) {
            if (!empty($row['IDPENGGUNA'])) {
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
            $miniPopCode = $legacyPop['pop_code'] . $miniSegment;
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
                $distName = (!empty($meta['name']) && $meta['name'] !== '-') ? $meta['name'] : $distributionCode;
                $distDesc = (!empty($meta['description']) && $meta['description'] !== '-') ? $meta['description'] : 'Distribusi ' . $distributionCode;

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

            if ($customerId !== '' && $requestId !== '' && !isset($legacyRequestByCustomer[$customerId])) {
                $legacyRequestByCustomer[$customerId] = $requestId;
            }

            if ($customerId !== '' && !isset($legacyMiniPopByCustomer[$customerId])) {
                $legacyMiniPopByCustomer[$customerId] = $miniPopCode;
            }

            if ($customerId !== '' && !isset($legacyDistributionByCustomer[$customerId])) {
                $legacyDistributionByCustomer[$customerId] = $distributionCode;
            }
        }

        // Seed any remaining distributions from legacy table that don't have customers yet
        // We put them under the default Mini POP of the resolved POP
        $firstPop = collect($legacyPopMap)->first();
        if ($firstPop) {
            $branchPop = $firstPop['pop_model'];
            $defaultMiniPopCode = $firstPop['pop_code'] . '1';
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
                if (!isset($createdDistributions[$code])) {
                    $distName = (!empty($meta['name']) && $meta['name'] !== '-') ? $meta['name'] : $code;
                    $distDesc = (!empty($meta['description']) && $meta['description'] !== '-') ? $meta['description'] : 'Distribusi ' . $code;

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
            if (!empty($row['IDPERMINTAAN'])) {
                $billingExtraByRequest[$row['IDPERMINTAAN']] = (int) ($row['BIAYALAINLAIN'] ?? 0);
            }
        }

        $surveyMap = [];
        foreach ($surveyRows as $row) {
            if (!empty($row['IDPERMINTAAN'])) {
                $surveyMap[$row['IDPERMINTAAN']] = $row;
            }
            if (!empty($row['IDPENGGUNA'])) {
                $surveyMap[$row['IDPENGGUNA']] = $row;
            }
            if (!empty($row['IDSURVEY'])) {
                $surveyMap[$row['IDSURVEY']] = $row;
            }
        }

        $laporanMap = [];
        foreach ($laporanRows as $row) {
            if (!empty($row['IDPERMINTAAN'])) {
                $laporanMap[$row['IDPERMINTAAN']] = $row;
            }
            if (!empty($row['IDPENGGUNA'])) {
                $laporanMap[$row['IDPENGGUNA']] = $row;
            }
        }

        $oltMap = [];
        foreach ($oltRows as $row) {
            if (!empty($row['IDPELANGGAN'])) {
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
        
        foreach ($penggunaRows as $row) {
            // Filter: skip internal users starting with PG
            if (!str_starts_with($row['IDPENGGUNA'] ?? '', 'PE')) {
                continue;
            }

            $fullName = trim(($row['NAMADEPAN'] ?? '') . ' ' . ($row['NAMABELAKANG'] ?? ''));
            $fullName = ucwords(str_replace('-', ' ', $fullName));
            if (empty($fullName)) {
                $fullName = $row['IDPENGGUNA'];
            }

            // Standardize gender
            $gender = \App\Enums\Gender::LAKI_LAKI->value;
            if (isset($row['JENISKELAMIN'])) {
                if (strtoupper($row['JENISKELAMIN']) === 'P') {
                    $gender = \App\Enums\Gender::PEREMPUAN->value;
                }
            }

            // Date formatting
            $regDate = now()->format('Y-m-d');
            if (!empty($row['inserted_at'])) {
                try {
                    $regDate = \Carbon\Carbon::parse($row['inserted_at'])->format('Y-m-d');
                } catch (\Exception $e) {
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
            foreach ($layananRows as $lRow) {
                if (($lRow['IDPENGGUNA'] ?? '') === $row['IDPENGGUNA']) {
                    $salesCode = $lRow['CREATED'] ?? '';
                    if (!$custLaporan) {
                        $custLaporan = $laporanMap[$lRow['IDPERMINTAAN']] ?? null;
                    }
                }
            }
            $fotoKontrak = $custLaporan['FOTOFORMULIR'] ?? null;

            $legacyBranchId = trim((string) ($row['IDCABANG'] ?? ''));
            $legacyPop = $this->resolveLegacyPopForBranch($legacyBranchId, $legacyPopMap);
            $miniPopCode = $legacyMiniPopByCustomer[$row['IDPENGGUNA']] ?? ($legacyPop['pop_code'] . '1');

            $candidateCode = $legacyRequestByCustomer[$row['IDPENGGUNA']] ?? '';
            if ($candidateCode !== '') {
                if (isset($usedCustomerCodes[$candidateCode]) || \App\Models\Customer::query()->where('customer_code', $candidateCode)->exists()) {
                    $candidateCode = ''; // Duplicate found, clear it so it gets auto-generated later
                } else {
                    $usedCustomerCodes[$candidateCode] = true;
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
                'registration_date' => $regDate,
                'pop_code' => $miniPopCode,
                'pop_name' => $legacyPop['pop_name'],
                'distribution_code' => $legacyDistributionByCustomer[$row['IDPENGGUNA']] ?? '',
                'village' => $row['DESA'] ?? '',
                'district' => $row['KEC'] ?? '',
                'city' => $row['KOTA'] ?? '',
                'latitude' => $lat,
                'longitude' => $lon,
                'foto_ktp' => $row['FOTOKTP'] ?? '',
                'foto_rumah' => $fotoRumah ?? '',
                'foto_kontrak' => $fotoKontrak ?? '',
                'sales_code' => $salesCode,
                'agent_code' => '',
                'referral_customer_code' => $row['REKOMENDASI'] ?? '',
            ];
        }

        // Sheet 3: services
        $servicesSheet = [];
        foreach ($layananRows as $row) {
            // Filter: skip internal users
            if (!str_starts_with($row['IDPENGGUNA'] ?? '', 'PE')) {
                continue;
            }

            // Fallback chain through progressively earlier/less-specific legacy
            // date fields. Deliberately does NOT default to now() when none of
            // these exist — a silently "today" activation_date makes a decades-
            // old customer look like they just activated this month, which
            // fools any code that reasons about "billed this activation period"
            // (e.g. GenerateMonthlyInvoicesCommand's double-bill guard).
            $actDate = null;
            $isZeroDate = fn($v) => empty($v) || str_starts_with((string) $v, '0000-00-00');
            if (!$isZeroDate($row['TGL_AKTIFPUTUS'] ?? null)) {
                $actDate = $row['TGL_AKTIFPUTUS'];
            } elseif (!$isZeroDate($row['TGLSELESAI'] ?? null)) {
                try {
                    $actDate = \Carbon\Carbon::parse($row['TGLSELESAI'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            } elseif (!$isZeroDate($row['TGLDIPROSES'] ?? null)) {
                try {
                    $actDate = \Carbon\Carbon::parse($row['TGLDIPROSES'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            } elseif (!$isZeroDate($row['TGLDIACC'] ?? null)) {
                try {
                    $actDate = \Carbon\Carbon::parse($row['TGLDIACC'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            // Last resort: riwayat_pelanggan's "Berhasil Active" status-change log.
            // prosedure_permintaan_wifi's own date fields are often blank/zero, but
            // this history table almost always has the real event timestamp.
            if ($actDate === null && !empty($activationLogByRequest[$row['IDPERMINTAAN'] ?? ''])) {
                try {
                    $actDate = \Carbon\Carbon::parse($activationLogByRequest[$row['IDPERMINTAAN']])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            // Lookup package profile
            $profile = '';
            if (!empty($row['IDPAKET'])) {
                $pkg = collect($paketRows)->firstWhere('KODEPAKET', $row['IDPAKET']);
                $profile = $pkg['PROFILPPP'] ?? $pkg['PROFILOLT'] ?? '';
            }

            // Lookup survey info
            $survey = $this->resolveLegacySurveyRow($row, $surveyMap);
            $surveyDate = null;
            $surveyStartTime = null;
            if ($survey && !empty($survey['TGLSURVEY'])) {
                try {
                    $cDate = \Carbon\Carbon::parse($survey['TGLSURVEY']);
                    $surveyDate = $cDate->format('Y-m-d');
                    $surveyStartTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {}
            } elseif (!empty($row['TGLSURVEY'])) {
                try {
                    $cDate = \Carbon\Carbon::parse($row['TGLSURVEY']);
                    $surveyDate = $cDate->format('Y-m-d');
                    $surveyStartTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {}
            }

            // Lookup installation info
            $laporan = $laporanMap[$row['IDPERMINTAAN']] ?? $laporanMap[$row['IDPENGGUNA'] ?? ''] ?? null;
            $installDate = null;
            $installStartTime = null;
            $installEndTime = null;
            if (!empty($row['TGLSELESAI'])) {
                try {
                    $cDate = \Carbon\Carbon::parse($row['TGLSELESAI']);
                    $installDate = $cDate->format('Y-m-d');
                    $installEndTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {}
            }
            if (!empty($row['TGLDIPROSES'])) {
                try {
                    $cDate = \Carbon\Carbon::parse($row['TGLDIPROSES']);
                    if (!$installDate) {
                        $installDate = $cDate->format('Y-m-d');
                    }
                    $installStartTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {}
            }

            $servicesSheet[] = [
                'old_request_id' => $row['IDPERMINTAAN'],
                'old_customer_id' => $row['IDPENGGUNA'] ?? '',
                'old_package_id' => $row['IDPAKET'] ?? '',
                'old_cost_id' => $row['IDBIAYA'] ?? '',
                'request_status' => $row['STATUS'] ?? '',
                'installation_status' => $row['STATUSPASANG'] ?? '',
                'network_type' => $row['JENISJARINGAN'] ?? '',
                'member_type' => $row['JENISMEMBER'] ?? '',
                'reason' => $row['ALASAN'] ?? '',
                'service_status' => $row['STATUS'] ?? '',
                'activation_date' => $actDate,
                'due_date' => '',
                
                // New survey/FOP/Installation fields
                'profile' => $profile,
                'contract_type' => $row['STATUSLANGGANAN'] ?? '',
                'activation_time' => !empty($row['VERIFIED_AT']) ? \Carbon\Carbon::parse($row['VERIFIED_AT'])->format('H:i:s') : null,
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
            ];
        }

        // Sheet 4: technical_details
        $technicalSheet = [];
        foreach ($laporanRows as $row) {
            // Filter: skip internal users
            if (!str_starts_with($row['IDPENGGUNA'] ?? '', 'PE')) {
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
            if ($req && !empty($req['TGLSELESAI'])) {
                try {
                    $cDate = \Carbon\Carbon::parse($req['TGLSELESAI']);
                    $reqDate = $cDate->format('Y-m-d');
                    $reqTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {}
            }

            // Jitter / Latency
            $jitter = is_numeric($row['PINGGATEWAY'] ?? null) ? (int)$row['PINGGATEWAY'] : null;
            $latency = is_numeric($row['PINGGOOGLE'] ?? null) ? (int)$row['PINGGOOGLE'] : null;
            
            // Speed conformity
            $conformity = null;
            if ($req && !empty($req['IDPAKET'])) {
                $pkg = collect($paketRows)->firstWhere('KODEPAKET', $req['IDPAKET']);
                $pkgSpeed = $pkg['SPEEDDOWN'] ?? 0;
                $testSpeed = is_numeric($row['TESTDOWN'] ?? null) ? (float)$row['TESTDOWN'] * 1000 : 0; // Convert to kbps
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
            if (!empty($asset['brand_label']) && !str_contains($note, $asset['brand_label'])) {
                $assetNote = 'Perangkat: ' . $asset['brand_label'] . ' (dari data aset migrasi)';
                $note = $note !== '' ? $note . ' | ' . $assetNote : $assetNote;
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
                'initial_attenuation' => $this->cleanDecimal(is_numeric($row['SIGNAL_KABEL'] ?? null) ? (float)$row['SIGNAL_KABEL'] : (is_numeric($row['SIGNAL_WIRELESS'] ?? null) ? (float)$row['SIGNAL_WIRELESS'] : null), -999.99, 999.99),
                'actual_attenuation' => $this->cleanDecimal(is_numeric($actualAttenuation) ? (float)$actualAttenuation : null, -999.99, 999.99),
                'test_date' => $reqDate,
                'test_time' => $reqTime,
                'speedtest_photo' => $row['FOTOSPEED'] ?? '',
                'jitter_ms' => $jitter,
                'latency_ms' => $latency,
                'test_upload' => $row['TESTUP'] ?? null,
                'test_download' => $row['TESTDOWN'] ?? null,
                'packet_loss_percent' => 0.00,
                'speed_conformity_percent' => $conformity,
                'quality_score' => is_numeric($row['SINYAL'] ?? null) ? (int)$row['SINYAL'] : 5,
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
                $dateOnly = \Carbon\Carbon::parse($row['TGLINSERT'] ?? '')->format('Y-m-d');
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
            if (!isset($dedupSeen[$signature])) {
                $dedupSeen[$signature] = $row['IDBIAYA'];
            }
            $canonicalCostId[$row['IDBIAYA']] = $dedupSeen[$signature];
        }

        // Sum of apikeuangan_buktitransaksitagihan.BAYAR per cost id (IDTRANSAKSI,
        // falling back to IDPERMINTAAN), used below to grab the amount legacy
        // admins actually collected instead of recomputing it.
        $costPaymentMap = [];
        foreach ($buktiRows as $row) {
            $costId = $row['IDTRANSAKSI'] ?: $row['IDPERMINTAAN'];
            if ($costId === '') {
                continue;
            }
            $costId = $canonicalCostId[$costId] ?? $costId;
            $costPaymentMap[$costId] = ($costPaymentMap[$costId] ?? 0) + (int) ($row['BAYAR'] ?? 0);
        }

        // Sheet 5: invoices
        $invoicesSheet = [];
        // IDBIAYA => 'awal' | 'bulanan', so the payments loop below can route each
        // buktiRow to the invoice actually generated for its cost id.
        $invoiceTypeByCostId = [];
        foreach ($biayaRows as $row) {
            // Skip duplicate rows collapsed into an earlier canonical row.
            if (($canonicalCostId[$row['IDBIAYA']] ?? $row['IDBIAYA']) !== $row['IDBIAYA']) {
                continue;
            }

            // Filter: skip internal users
            $cust = $row['IDPELANGGAN'] ?? $requestToCustomerMap[$row['IDPERMINTAAN'] ?? ''] ?? '';
            if (!str_starts_with($cust, 'PE')) {
                continue;
            }

            $issueDate = now()->format('Y-m-d');
            if (!empty($row['TGLINSERT'])) {
                try {
                    $issueDate = \Carbon\Carbon::parse($row['TGLINSERT'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            $dueDate = \Carbon\Carbon::parse($issueDate)->addDays(10)->format('Y-m-d');
            $billingPeriod = \Carbon\Carbon::parse($issueDate)->format('Y-m');

            $costId = $row['IDBIAYA'];
            $installationFee = (int) ($row['BIAYAPASANG'] ?? 0);
            $otherFee = (int) ($row['BIAYALAINLAIN'] ?? 0);
            $monthlyFee = (int) ($row['BIAYABULANAN'] ?? 0);

            $baseInvoice = [
                'old_request_id' => $row['IDPERMINTAAN'] ?? '',
                'old_customer_id' => $row['IDPELANGGAN'] ?: $cust,
                'billing_period' => $billingPeriod,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'status' => 'belum_dibayar',
                'extra_cable_fee' => 0,
                'extra_installation_fee' => 0,
                'extra_pole_fee' => 0,
            ];

            // `biaya_tagihan` doubles as both the one-time PSB/registration bill
            // (rows with BIAYAPASANG/BIAYALAINLAIN) and, for long-standing
            // customers, a genuine recurring monthly billing log (rows without
            // those fees). Only the registration row bundles a possibly-prorated
            // first month; regular rows are always flat.
            $isRegistrationRow = $installationFee > 0 || $otherFee > 0;

            if ($isRegistrationRow) {
                $billedTotal = $installationFee + $otherFee + $monthlyFee;

                // Legacy admins hand-prorate the registration bill and never write
                // that number back into biaya_tagihan — grab what was actually
                // collected (tagihan proof + pemasangan proof) as the real total
                // instead of recomputing pasang + lainlain + bulanan.
                $paidFromTagihan = $costPaymentMap[$costId] ?? 0;
                $paidFromPasang = ($installationPaidAt[$costId] ?? null) !== null ? $installationFee : 0;
                $actualPaid = $paidFromTagihan + $paidFromPasang;
                $totalAmount = $actualPaid > 0 ? $actualPaid : $billedTotal;

                $invoiceTypeByCostId[$costId] = 'awal';
                $invoicesSheet[] = array_merge($baseInvoice, [
                    'old_invoice_id' => $costId . '-AWAL',
                    'old_cost_id' => $costId . '-AWAL',
                    'invoice_type' => 'awal',
                    'total_amount' => $totalAmount,
                    'monthly_fee' => null,
                    'installation_fee' => $installationFee,
                    'other_fee' => $otherFee,
                    'prorate_amount' => $totalAmount < $billedTotal ? $totalAmount : null,
                ]);
            } elseif ($monthlyFee > 0) {
                $invoiceTypeByCostId[$costId] = 'bulanan';
                $invoicesSheet[] = array_merge($baseInvoice, [
                    'old_invoice_id' => $costId . '-BULANAN',
                    'old_cost_id' => $costId . '-BULANAN',
                    'invoice_type' => 'bulanan',
                    'total_amount' => $monthlyFee,
                    'monthly_fee' => $monthlyFee,
                    'installation_fee' => 0,
                    'other_fee' => 0,
                    'prorate_amount' => null,
                ]);
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
            if ($cust !== '' && !str_starts_with($cust, 'PE')) {
                continue;
            }

            $payDate = now()->format('Y-m-d');
            if (!empty($row['INSERTED_AT'])) {
                try {
                    $payDate = \Carbon\Carbon::parse($row['INSERTED_AT'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            $billingPeriod = now()->format('Y-m');
            if (!empty($row['BULANTAGIHAN'])) {
                try {
                    $billingPeriod = \Carbon\Carbon::parse($row['BULANTAGIHAN'])->format('Y-m');
                } catch (\Exception $e) {
                }
            }

            // Enrich with the real payment proof (method/receiver/note) from
            // apikeuangan_buktitransaksilunas, keyed by the same invoice/transaction code.
            $lunas = $lunasByTransaction[$row['IDTRANSAKSI'] ?? ''] ?? null;

            $costId = $row['IDTRANSAKSI'] ?: $row['IDPERMINTAAN'];
            $costId = $canonicalCostId[$costId] ?? $costId;
            // Route to whichever invoice was actually generated for this cost id
            // (registration/AWAL vs. flat recurring/BULANAN); default to BULANAN
            // when the cost id wasn't seen in Sheet 5 (e.g. filtered-out row).
            $invoiceType = strtoupper($invoiceTypeByCostId[$costId] ?? 'bulanan');

            $paymentsSheet[] = [
                'old_payment_id' => $row['IDUNIQ'],
                'old_invoice_id' => $costId . '-' . $invoiceType,
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
            if (!str_starts_with($cust, 'PE')) {
                continue;
            }

            $payDate = now()->format('Y-m-d');
            try {
                $payDate = \Carbon\Carbon::parse($paidAt)->format('Y-m-d');
            } catch (\Exception $e) {
            }

            $paymentsSheet[] = [
                'old_payment_id' => $invoiceCode . '-PASANG',
                'old_invoice_id' => $invoiceCode . '-AWAL',
                'old_transaction_id' => $invoiceCode,
                'old_request_id' => $row['IDPERMINTAAN'] ?? '',
                'old_customer_id' => $cust,
                'billing_period' => \Carbon\Carbon::parse($payDate)->format('Y-m'),
                'amount' => $installationFee,
                'payment_date' => $payDate,
                'payment_method' => 'cash',
                'received_by_old' => '',
                'deposited_by_old' => '',
                'note' => 'Pembayaran biaya pasang (migrasi)',
                'status' => 'valid',
            ];
        }

        $sheets = [
            'packages' => $packagesSheet,
            'customers' => $customersSheet,
            'services' => $servicesSheet,
            'technical_details' => $technicalSheet,
            'invoices' => $invoicesSheet,
            'payments' => $paymentsSheet,
        ];

        // Ensure we are logged in as admin to have full access/audit logs
        $admin = User::whereHas('role', function ($q) {
            $q->where('name', 'Owner');
        })->first();

        if (!$admin) {
            $admin = User::first();
        }

        if (!$admin) {
            // Create a system user if none exists
            $ownerRole = \App\Models\Role::where('code', 'owner')->first();

            $admin = User::create([
                'name' => 'System Admin',
                'username' => 'system',
                'email' => 'system@whusnet.local',
                'password' => bcrypt('password'),
                'status' => 'active',
                'role_id' => $ownerRole?->id,
            ]);
            $this->info("Created a fallback System Admin user.");
        }

        if ($admin) {
            \Illuminate\Support\Facades\Auth::login($admin);
            $this->info("Logged in programmatically as: " . $admin->name);
        } else {
            $this->warn("No user found. Migration might fail audit log validation.");
        }

        $this->info("Validating import data via internal controller call...");
        
        $requestValidate = Request::create('/customers/import/validate', 'POST', [
            'sheets' => $sheets
        ]);
        
        $controller = app(\App\Http\Controllers\CustomerController::class);
        $validateResponse = $controller->validateImport($requestValidate);
        
        $validateData = json_decode($validateResponse->getContent(), true);
        if (!$validateData['success']) {
            $this->error("Validation failed. " . json_encode($validateData['errors'] ?? []));
            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $this->info("Validation successful. Confirming import...");

        $requestConfirm = Request::create('/customers/import/confirm', 'POST', [
            'sheets' => json_encode($validateData['sheets']),
            'file_name' => $fileName,
        ]);

        try {
            $confirmResponse = $controller->confirmImport($requestConfirm);
            
            // Output flash messages
            if (session()->has('success')) {
                $this->info("Success: " . session('success'));
            }
            if (session()->has('errors')) {
                $this->error("Errors: " . json_encode(session('errors')->getBag('default')->all()));
            }

            $this->info("Data migration execution completed.");
            return \Symfony\Component\Console\Command\Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Migration failed with exception: " . $e->getMessage());
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
            if (!empty($key) && isset($surveyMap[$key])) {
                return $surveyMap[$key];
            }
        }

        return null;
    }

    private function resolveLegacyAddressText(array $row): string
    {
        $streetAddress = trim((string) ($row['ALMT'] ?? $row['ALAMAT'] ?? ''));
        if ($streetAddress !== '' && !in_array(strtolower($streetAddress), ['-', 'null', 'n/a'], true)) {
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
        $name = trim((string) (($row['NAMADEPAN'] ?? '') . ' ' . ($row['NAMABELAKANG'] ?? '')));
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
                $popName = $this->ask("Masukkan Nama POP baru untuk cabang ini", $legacyBranchName ?: $popCode);
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
            return 'C' . $branchId;
        }

        return 'C';
    }

    /**
     * Resolve the POP context for a legacy branch id.
     *
     * @param array<string, array{pop_code: string, pop_name: string, pop_model: Pop}> $legacyPopMap
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

        return $fallback !== '' ? 'CB' . $fallback : 'UNASSIGNED';
    }

    private function parseTableData(string $sql, string $tableName): array
    {
        $insertMatches = [];
        preg_match_all('/INSERT INTO `' . $tableName . '` \(([^\)]+)\) VALUES\s*(.*?);/s', $sql, $insertMatches);

        $rows = [];
        foreach ($insertMatches[1] as $idx => $columnsList) {
            // Extract actual columns from this INSERT block
            $columnMatches = [];
            preg_match_all('/`([^`]+)`/', $columnsList, $columnMatches);
            $columns = $columnMatches[1];

            $valuesBlock = $insertMatches[2][$idx];

            // Trim whitespace
            $valuesBlock = trim($valuesBlock);
            
            // Remove the first '(' and last ')'
            if (str_starts_with($valuesBlock, '(')) {
                $valuesBlock = substr($valuesBlock, 1);
            }
            if (str_ends_with($valuesBlock, ')')) {
                $valuesBlock = substr($valuesBlock, 0, -1);
            }

            // Split by '),(' or '),\n(' or '),\r\n('
            $rowStrings = preg_split('/\),\s*\(/', $valuesBlock);

            foreach ($rowStrings as $rowString) {
                $values = $this->splitSqlValues($rowString);

                $rowData = [];
                foreach ($columns as $index => $colName) {
                    $val = $values[$index] ?? null;
                    $rowData[$colName] = $val;
                }
                $rows[] = $rowData;
            }
        }

        return $rows;
    }

    private function splitSqlValues(string $rowString): array
    {
        $values = [];
        $currentVal = '';
        $inString = false;
        $escape = false;

        for ($i = 0; $i < strlen($rowString); $i++) {
            $char = $rowString[$i];

            if ($escape) {
                $currentVal .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === "'") {
                $inString = !$inString;
                continue;
            }

            if ($char === ',' && !$inString) {
                $values[] = $this->cleanParsedValue($currentVal);
                $currentVal = '';
            } else {
                $currentVal .= $char;
            }
        }
        
        $values[] = $this->cleanParsedValue($currentVal);
        return $values;
    }

    private function cleanParsedValue(?string $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $val = trim($val);
        if (strtolower($val) === 'null') {
            return null;
        }
        return $val;
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

        if (!is_numeric($value)) {
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
                $normalized = '-' . substr($digits, 0, 1) . '.' . substr($digits, 1);
            } else {
                // Positive coordinates
                if (str_starts_with($digits, '1')) {
                    // Longitude in Indonesia is around 110-115
                    // Place the dot after the first 3 digits
                    $normalized = substr($digits, 0, 3) . '.' . substr($digits, 3);
                } else {
                    // Positive latitude
                    $normalized = substr($digits, 0, 1) . '.' . substr($digits, 1);
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

        if (!is_numeric($value)) {
            return null;
        }

        $val = (float) $value;
        return ($val >= $min && $val <= $max) ? $val : null;
    }
}
