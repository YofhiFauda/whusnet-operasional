<?php

namespace App\Console\Commands;

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
    protected $signature = 'app:import-legacy-sql {file? : The path to the legacy sql dump. Default: sand_db_sandya.sql}';

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
            return Command::FAILURE;
        }

        $this->info("Starting migration from {$fileName}");
        $sql = file_get_contents($sqlPath);

        // Parse tables
        $this->info("Parsing SQL dump...");
        $paketRows = $this->parseTableData($sql, 'paket');
        $penggunaRows = $this->parseTableData($sql, 'pengguna');
        $layananRows = $this->parseTableData($sql, 'prosedure_permintaan_wifi');
        $laporanRows = $this->parseTableData($sql, 'laporan_pemasangan_wifi');
        $biayaRows = $this->parseTableData($sql, 'biaya_tagihan');
        $buktiRows = $this->parseTableData($sql, 'apikeuangan_buktitransaksitagihan');

        $this->info("Parsed counts:");
        $this->line("- paket: " . count($paketRows));
        $this->line("- pengguna: " . count($penggunaRows));
        $this->line("- prosedure_permintaan_wifi: " . count($layananRows));
        $this->line("- laporan_pemasangan_wifi: " . count($laporanRows));
        $this->line("- biaya_tagihan: " . count($biayaRows));
        $this->line("- apikeuangan_buktitransaksitagihan: " . count($buktiRows));

        if (empty($paketRows) && empty($penggunaRows)) {
            $this->error("No data parsed. Make sure the SQL format matches.");
            return Command::FAILURE;
        }

        // Create Pop corresponding to legacy branch/POP
        $pop = Pop::firstOrCreate([
            'code' => 'SMN',
            'pop_code' => 'SMN',
        ], [
            'name' => 'sandya',
            'type' => 'cabang',
            'status' => 'active',
            'registration_prefix' => 'REG',
            'cid_prefix' => 'CID',
        ]);

        $this->info("Mapping data to sheets...");
        
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
        foreach ($penggunaRows as $row) {
            $fullName = trim(($row['NAMADEPAN'] ?? '') . ' ' . ($row['NAMABELAKANG'] ?? ''));
            $fullName = ucwords(str_replace('-', ' ', $fullName));
            if (empty($fullName)) {
                $fullName = $row['IDPENGGUNA'];
            }

            // Standardize gender
            $gender = 'Laki-laki';
            if (isset($row['JENISKELAMIN'])) {
                if (strtoupper($row['JENISKELAMIN']) === 'P') {
                    $gender = 'Perempuan';
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

            $customersSheet[] = [
                'old_customer_id' => $row['IDPENGGUNA'],
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
                'full_address' => $row['ALMT'] ?? '',
                'old_region_id' => $row['IDWILAYAH'] ?? '',
                'old_branch_id' => $row['IDCABANG'] ?? '',
                'registration_date' => $regDate,
                'pop_code' => 'SMN',
                'pop_name' => 'sandya',
                'village' => $row['DESA'] ?? '',
                'district' => $row['KEC'] ?? '',
                'city' => $row['KOTA'] ?? '',
            ];
        }

        // Sheet 3: services
        $servicesSheet = [];
        foreach ($layananRows as $row) {
            $actDate = now()->format('Y-m-d');
            if (!empty($row['TGL_AKTIFPUTUS']) && $row['TGL_AKTIFPUTUS'] !== '0000-00-00') {
                $actDate = $row['TGL_AKTIFPUTUS'];
            } elseif (!empty($row['TGLSELESAI'])) {
                try {
                    $actDate = \Carbon\Carbon::parse($row['TGLSELESAI'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
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
            ];
        }

        // Sheet 4: technical_details
        $technicalSheet = [];
        foreach ($laporanRows as $row) {
            $technicalSheet[] = [
                'old_report_id' => $row['IDREPORT'],
                'old_customer_id' => $row['IDPENGGUNA'] ?? '',
                'old_request_id' => $row['IDPERMINTAAN'] ?? '',
                'connection_type' => $row['JENIS'] ?: 'KABEL',
                'ont_sn' => $row['SNROOTER_FIBER'] ?? '',
                'ip_address' => $row['IPADDR'] ?? '',
                'odp_code' => $row['NOMOR_ODP'] ?? '',
                'odp_port' => $row['NOMOR_PORT_ODP'] ?? '',
                'olt_code' => $row['NOMOR_PORT_OLT'] ?? '',
                'vlan_id' => '',
            ];
        }

        // Sheet 5: invoices
        $invoicesSheet = [];
        foreach ($biayaRows as $row) {
            $issueDate = now()->format('Y-m-d');
            if (!empty($row['TGLINSERT'])) {
                try {
                    $issueDate = \Carbon\Carbon::parse($row['TGLINSERT'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            $dueDate = \Carbon\Carbon::parse($issueDate)->addDays(10)->format('Y-m-d');
            $billingPeriod = \Carbon\Carbon::parse($issueDate)->format('Y-m');

            $invoicesSheet[] = [
                'old_invoice_id' => $row['IDBIAYA'],
                'old_cost_id' => $row['IDBIAYA'],
                'old_request_id' => $row['IDPERMINTAAN'] ?? '',
                'old_customer_id' => $row['IDPELANGGAN'] ?? '',
                'billing_period' => $billingPeriod,
                'total_amount' => (int) ($row['TOTALBIAYA'] ?? 0),
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'monthly_fee' => (int) ($row['BIAYAPASANG'] ?? 0),
                'status' => 'belum_dibayar',
            ];
        }

        // Sheet 6: payments
        $paymentsSheet = [];
        foreach ($buktiRows as $row) {
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

            $paymentsSheet[] = [
                'old_payment_id' => $row['IDUNIQ'],
                'old_invoice_id' => $row['IDTRANSAKSI'] ?: $row['IDPERMINTAAN'],
                'old_transaction_id' => $row['IDTRANSAKSI'] ?? '',
                'old_request_id' => $row['IDPERMINTAAN'] ?? '',
                'old_customer_id' => '',
                'billing_period' => $billingPeriod,
                'amount' => (int) ($row['BAYAR'] ?? 0),
                'payment_date' => $payDate,
                'payment_method' => 'cash',
                'received_by_old' => '',
                'deposited_by_old' => '',
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

        if ($admin) {
            auth()->login($admin);
            $this->info("Logged in programmatically as: " . $admin->name);
        } else {
            $this->warn("No Owner user found. Migration might fail audit log validation.");
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
            return Command::FAILURE;
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
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Migration failed with exception: " . $e->getMessage());
            return Command::FAILURE;
        }
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
}
