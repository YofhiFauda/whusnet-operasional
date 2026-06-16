<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\CustomerTechnicalDetail;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Village;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RealDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable CSRF for testing the post request directly
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /**
     * Test the migration of real legacy data from sand_db_sandya.sql end-to-end.
     */
    public function test_real_data_migration_end_to_end(): void
    {
        $this->withoutExceptionHandling();
        // 1. Seed base tables (regions, packages, roles, permissions)
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        // 2. Read the sql dump
        $sqlPath = base_path('sand_db_sandya.sql');
        $this->assertFileExists($sqlPath, "File sand_db_sandya.sql must exist in root directory.");
        $sql = file_get_contents($sqlPath);

        // 3. Parse tables
        $paketRows = $this->parseTableData($sql, 'paket');
        $penggunaRows = $this->parseTableData($sql, 'pengguna');
        $layananRows = $this->parseTableData($sql, 'prosedure_permintaan_wifi');
        $laporanRows = $this->parseTableData($sql, 'laporan_pemasangan_wifi');
        $biayaRows = $this->parseTableData($sql, 'biaya_tagihan');
        $buktiRows = $this->parseTableData($sql, 'apikeuangan_buktitransaksitagihan');

        // Output counts for logging/reconciliation verification
        dump("Parsed counts from sand_db_sandya.sql:");
        dump("- paket: " . count($paketRows));
        dump("- pengguna: " . count($penggunaRows));
        dump("- prosedure_permintaan_wifi: " . count($layananRows));
        dump("- laporan_pemasangan_wifi: " . count($laporanRows));
        dump("- biaya_tagihan: " . count($biayaRows));
        dump("- apikeuangan_buktitransaksitagihan: " . count($buktiRows));

        $this->assertNotEmpty($paketRows, "Should have parsed packages.");
        $this->assertNotEmpty($penggunaRows, "Should have parsed customers.");

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

        // 4. Map to Sheet Arrays
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

        // 5. POST to validate endpoint
        $validateResponse = $this->postJson('/customers/import/validate', [
            'sheets' => $sheets
        ]);

        $validateResponse->assertStatus(200);
        $validateData = $validateResponse->json();
        $this->assertTrue($validateData['success']);

        // 6. POST to confirm endpoint
        try {
            $confirmResponse = $this->post('/customers/import/confirm', [
                'sheets' => json_encode($validateData['sheets']),
                'file_name' => 'sand_db_sandya.sql',
            ]);
        } catch (\Throwable $e) {
            dump($e->getMessage());
            dump($e->getTraceAsString());
            throw $e;
        }

        dump("Response Status: " . $confirmResponse->status());
        dump("Redirect Target: " . $confirmResponse->headers->get('Location'));
        if (session('errors')) {
            dump(session('errors'));
        }
        if ($confirmResponse->status() !== 302) {
            dump($confirmResponse->getContent());
        }

        $confirmResponse->assertRedirect('/customers');
        $confirmResponse->assertSessionHas('success');

        // 7. Verify Database Reconciliations
        // Verifying Packages
        foreach ($packagesSheet as $pkg) {
            $this->assertDatabaseHas('internet_packages', [
                'old_package_id' => $pkg['old_package_id'],
                'name' => $pkg['name'],
            ]);
        }

        // Verifying Customers (check sample)
        $sampleCustomer = collect($customersSheet)->first();
        if ($sampleCustomer) {
            $this->assertDatabaseHas('customers', [
                'old_customer_id' => $sampleCustomer['old_customer_id'],
                'full_name' => $sampleCustomer['full_name'],
            ]);

            $dbCust = Customer::where('old_customer_id', $sampleCustomer['old_customer_id'])->first();
            $this->assertNotNull($dbCust);

            // Check if address created
            $this->assertDatabaseHas('customer_addresses', [
                'customer_id' => $dbCust->id,
                'full_address' => $sampleCustomer['full_address'],
            ]);
        }

        // Verifying Services (check sample and mapping status)
        foreach (collect($servicesSheet)->take(10) as $svc) {
            // Find in database
            $dbSvc = CustomerService::where('old_request_id', $svc['old_request_id'])->first();
            if ($dbSvc) {
                $mappedStatus = $dbSvc->service_status;
                $expectedStatus = $this->mapLegacyServiceStatus($svc['service_status']);
                $this->assertEquals($expectedStatus, $mappedStatus);
            }
        }

        // Verifying Technical Details (check sample)
        $sampleTech = collect($technicalSheet)->first();
        if ($sampleTech) {
            $this->assertDatabaseHas('customer_technical_details', [
                'old_report_id' => $sampleTech['old_report_id'],
                'router_or_ont_serial' => $sampleTech['ont_sn'],
            ]);
        }

        // Verifying Invoices
        $sampleInv = collect($invoicesSheet)->first();
        if ($sampleInv) {
            $this->assertDatabaseHas('invoices', [
                'old_invoice_id' => $sampleInv['old_invoice_id'],
                'billing_period' => $sampleInv['billing_period'],
            ]);
        }

        // Verifying Payments & Invoice reconciliations
        foreach ($paymentsSheet as $pmt) {
            // Only verify if payment amount was greater than zero and mapped to valid invoice
            if ($pmt['amount'] > 0) {
                $dbPmt = Payment::where('old_payment_id', $pmt['old_payment_id'])->first();
                if ($dbPmt) {
                    $this->assertNotNull($dbPmt->invoice_id);
                    $this->assertEquals($pmt['amount'], $dbPmt->amount);

                    // Reconciled invoice should have invoice status updated accordingly
                    $invoice = $dbPmt->invoice;
                    $this->assertNotNull($invoice);
                    if ($invoice->remaining_amount <= 0) {
                        $this->assertEquals('lunas', $invoice->invoice_status);
                    } else {
                        $this->assertEquals('sebagian', $invoice->invoice_status);
                    }
                }
            }
        }

        dump("End-to-end legacy database migration verified successfully!");
    }

    private function mapLegacyServiceStatus(?string $status): string
    {
        $normalized = strtolower(str_replace([' ', '-', '/'], '_', trim((string)$status)));

        return [
            'active' => 'active',
            'aktif' => 'active',
            'putus' => 'terminated',
            'berhenti' => 'terminated',
            'terminated' => 'terminated',
            'gagal' => 'rejected',
            'rejected' => 'rejected',
            'disurvei' => 'waiting_survey',
            'survey' => 'waiting_survey',
            'menunggu_survey' => 'waiting_survey',
            'pengajuan' => 'registered',
            'calon_pelanggan' => 'registered',
            'terdaftar' => 'registered',
            'registered' => 'registered',
            'menunggu_pemasangan' => 'waiting_installation',
            'waiting_installation' => 'waiting_installation',
            'isolir' => 'suspended',
            'suspended' => 'suspended',
            '' => 'registered',
        ][$normalized] ?? $normalized;
    }

    /**
     * Parse SQL insert statement into key-value array of rows.
     */
    private function parseTableData(string $sql, string $tableName): array
    {
        preg_match_all('/INSERT INTO\s+`?' . $tableName . '`?\s*\(([^)]+)\)\s*VALUES\s*(.+?);/is', $sql, $matches);
        
        $rows = [];
        if (empty($matches[0])) {
            return $rows;
        }
        
        foreach ($matches[1] as $matchIndex => $columnsStr) {
            $columns = array_map(function($col) {
                return trim($col, " `\t\n\r");
            }, explode(',', $columnsStr));
            
            $valuesStr = $matches[2][$matchIndex];
            $valuesList = $this->splitSqlValues($valuesStr);
            
            foreach ($valuesList as $valStr) {
                $rowValues = $this->parseSqlRowValues($valStr);
                if (count($columns) === count($rowValues)) {
                    $rows[] = array_combine($columns, $rowValues);
                }
            }
        }
        
        return $rows;
    }

    private function splitSqlValues(string $valuesStr): array
    {
        $rows = [];
        $length = strlen($valuesStr);
        $inQuote = false;
        $quoteChar = '';
        $inRow = false;
        $currentRow = '';
        
        for ($i = 0; $i < $length; $i++) {
            $char = $valuesStr[$i];
            
            if ($char === '\\') {
                if ($inRow) $currentRow .= $char . ($valuesStr[$i + 1] ?? '');
                $i++;
                continue;
            }
            
            if (($char === "'" || $char === '"') && !$inQuote) {
                $inQuote = true;
                $quoteChar = $char;
                if ($inRow) $currentRow .= $char;
            } elseif ($char === $quoteChar && $inQuote) {
                $inQuote = false;
                if ($inRow) $currentRow .= $char;
            } elseif ($char === '(' && !$inQuote && !$inRow) {
                $inRow = true;
                $currentRow = '';
            } elseif ($char === ')' && !$inQuote && $inRow) {
                $inRow = false;
                $rows[] = $currentRow;
            } else {
                if ($inRow) {
                    $currentRow .= $char;
                }
            }
        }
        
        return $rows;
    }

    private function parseSqlRowValues(string $rowStr): array
    {
        $values = [];
        $length = strlen($rowStr);
        $inQuote = false;
        $quoteChar = '';
        $currentVal = '';
        
        for ($i = 0; $i < $length; $i++) {
            $char = $rowStr[$i];
            
            if ($char === '\\') {
                $currentVal .= $rowStr[$i + 1] ?? '';
                $i++;
                continue;
            }
            
            if (($char === "'" || $char === '"') && !$inQuote) {
                $inQuote = true;
                $quoteChar = $char;
            } elseif ($char === $quoteChar && $inQuote) {
                $inQuote = false;
            } elseif ($char === ',' && !$inQuote) {
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

    /**
     * Test the migration idempotency and edge cases (empty fields, broken references, duplicate keys) on real data.
     */
    public function test_real_data_migration_idempotency_and_edge_cases(): void
    {
        $this->withoutExceptionHandling();
        // 1. Seed base tables (regions, packages, roles, permissions)
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        // 2. Read the sql dump
        $sqlPath = base_path('sand_db_sandya.sql');
        $this->assertFileExists($sqlPath, "File sand_db_sandya.sql must exist in root directory.");
        $sql = file_get_contents($sqlPath);

        // 3. Parse tables
        $paketRows = $this->parseTableData($sql, 'paket');
        $penggunaRows = $this->parseTableData($sql, 'pengguna');
        $layananRows = $this->parseTableData($sql, 'prosedure_permintaan_wifi');
        $laporanRows = $this->parseTableData($sql, 'laporan_pemasangan_wifi');
        $biayaRows = $this->parseTableData($sql, 'biaya_tagihan');
        $buktiRows = $this->parseTableData($sql, 'apikeuangan_buktitransaksitagihan');

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

        // 4. Map to Sheet Arrays
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

        // First execution of import
        $validateResponse1 = $this->postJson('/customers/import/validate', [
            'sheets' => $sheets
        ]);
        $validateResponse1->assertStatus(200);
        $validateData1 = $validateResponse1->json();
        $this->assertTrue($validateData1['success']);

        $confirmResponse1 = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($validateData1['sheets']),
            'file_name' => 'sand_db_sandya.sql',
        ]);
        $confirmResponse1->assertRedirect('/customers');

        // Record initial database counts
        $initialPkgCount = InternetPackage::count('*');
        $initialCustCount = Customer::count('*');
        $initialSvcCount = CustomerService::count('*');
        $initialTechCount = CustomerTechnicalDetail::count('*');
        $initialInvCount = Invoice::count('*');
        $initialPmtCount = Payment::count('*');

        // 2. Second execution of import (checking idempotency/duplikasi check)
        $validateResponse2 = $this->postJson('/customers/import/validate', [
            'sheets' => $sheets
        ]);
        $validateResponse2->assertStatus(200);
        $validateData2 = $validateResponse2->json();

        $confirmResponse2 = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($validateData2['sheets']),
            'file_name' => 'sand_db_sandya.sql',
        ]);
        $confirmResponse2->assertRedirect('/customers');

        // Verify that database counts have not changed (idempotent / duplicate prevention)
        $this->assertEquals($initialPkgCount, InternetPackage::count('*'), "Internet packages count should remain idempotent.");
        $this->assertEquals($initialCustCount, Customer::count('*'), "Customers count should remain idempotent.");
        $this->assertEquals($initialSvcCount, CustomerService::count('*'), "Customer services count should remain idempotent.");
        $this->assertEquals($initialTechCount, CustomerTechnicalDetail::count('*'), "Technical details count should remain idempotent.");
        $this->assertEquals($initialInvCount, Invoice::count('*'), "Invoices count should remain idempotent.");
        $this->assertEquals($initialPmtCount, Payment::count('*'), "Payments count should remain idempotent.");

        // 3. Edge Cases: empty fields & broken relationships
        // Inject an invalid customer row (missing name and broken POP)
        $invalidCustomers = $customersSheet;
        $invalidCustomers[] = [
            'old_customer_id' => 'CUST-EDGE-1',
            'full_name' => '', // Empty field edge case
            'phone' => '0877112233',
            'pop_code' => 'NON_EXISTENT_POP_CODE', // Broken POP relation
            'village' => 'Fiktif Village',
            'district' => 'Fiktif District',
            'city' => 'Fiktif City',
            'registration_date' => now()->format('Y-m-d'),
        ];

        // Inject a service with broken customer and package relations
        $invalidServices = $servicesSheet;
        $invalidServices[] = [
            'old_request_id' => 'REQ-EDGE-1',
            'old_customer_id' => 'NON_EXISTENT_CUST_ID', // Broken customer relation
            'old_package_id' => 'NON_EXISTENT_PKG_ID', // Broken package relation
            'service_status' => 'aktif',
            'activation_date' => '2026-01-01',
        ];

        $edgeSheets = [
            'packages' => $packagesSheet,
            'customers' => $invalidCustomers,
            'services' => $invalidServices,
            'technical_details' => $technicalSheet,
            'invoices' => $invoicesSheet,
            'payments' => $paymentsSheet,
        ];

        $validateEdge = $this->postJson('/customers/import/validate', [
            'sheets' => $edgeSheets
        ]);
        $validateEdge->assertStatus(200);
        $edgeData = $validateEdge->json();

        // Verify the customer edge case is handled with warnings but marked valid row
        $custRows = $edgeData['sheets']['customers']['rows'];
        $edgeCustRow = collect($custRows)->firstWhere('old_customer_id', 'CUST-EDGE-1');
        $this->assertNotNull($edgeCustRow);
        $this->assertEquals('valid', $edgeCustRow['status_row']);
        $this->assertNotEmpty($edgeCustRow['warnings']);

        // Verify the service edge case is marked as error due to missing/broken relations
        $svcRows = $edgeData['sheets']['services']['rows'];
        $edgeSvcRow = collect($svcRows)->firstWhere('old_request_id', 'REQ-EDGE-1');
        $this->assertNotNull($edgeSvcRow);
        $this->assertEquals('error', $edgeSvcRow['status_row']);
        $this->assertNotEmpty($edgeSvcRow['errors']);

        // Confirm import of edge sheets and assert database logging of errors
        $confirmEdge = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($edgeData['sheets']),
            'file_name' => 'sand_db_sandya_edge.sql',
        ]);
        $confirmEdge->assertRedirect('/customers');

        // Verify that the invalid service has logged an import error in DB
        $this->assertDatabaseHas('import_errors', [
            'row_number' => count($invalidServices),
            'field_name' => 'Services',
            'error_message' => '[Services] Baris error pada sheet Services.',
        ]);

        // Verify that the customer with empty name has fallback name or is saved as "perlu_dilengkapi" completeness status
        $this->assertDatabaseHas('customers', [
            'old_customer_id' => 'CUST-EDGE-1',
            'data_completeness_status' => 'perlu_dilengkapi',
        ]);

        dump("Idempotency, duplication prevention, empty fields, and broken relations tested successfully on real data!");
    }
}
