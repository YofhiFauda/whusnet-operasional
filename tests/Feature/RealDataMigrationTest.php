<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerTechnicalDetail;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable CSRF for testing the post request directly
        $this->withoutMiddleware(VerifyCsrfToken::class);
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
        $this->assertFileExists($sqlPath, 'File sand_db_sandya.sql must exist in root directory.');
        $sql = file_get_contents($sqlPath);

        // 3. Parse and construct sheets using helper
        $sheets = $this->parseSqlToSheets($sql);

        $packagesSheet = $sheets['packages'];
        $customersSheet = $sheets['customers'];
        $servicesSheet = $sheets['services'];
        $technicalSheet = $sheets['technical_details'];
        $invoicesSheet = $sheets['invoices'];
        $paymentsSheet = $sheets['payments'];

        // Output counts for logging/reconciliation verification
        dump('Parsed counts from sand_db_sandya.sql:');
        dump('- paket: '.count($packagesSheet));
        dump('- pengguna: '.count($customersSheet));

        $this->assertNotEmpty($packagesSheet, 'Should have parsed packages.');
        $this->assertNotEmpty($customersSheet, 'Should have parsed customers.');

        $legacySurveyRow = collect($servicesSheet)->firstWhere('old_request_id', 'RQ000006');
        if ($legacySurveyRow) {
            $this->assertSame('Lugas', $legacySurveyRow['surveyors']);
            $this->assertSame('Lisvi Fitria Nur Lita', $legacySurveyRow['activated_by_name']);
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

        // 5. POST to validate endpoint
        $validateResponse = $this->postJson('/customers/import/validate', [
            'sheets' => $sheets,
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

        dump('Response Status: '.$confirmResponse->status());
        dump('Redirect Target: '.$confirmResponse->headers->get('Location'));
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
                'old_request_id' => $sampleCustomer['old_request_id'] ?? null,
                'full_name' => $sampleCustomer['full_name'],
                'sales_code' => $sampleCustomer['sales_code'],
                'referral_customer_code' => empty($sampleCustomer['referral_customer_code']) ? null : $sampleCustomer['referral_customer_code'],
            ]);

            $dbCust = Customer::where('old_customer_id', $sampleCustomer['old_customer_id'])->first();
            $this->assertNotNull($dbCust);

            // Check if address created
            $this->assertDatabaseHas('customer_addresses', [
                'customer_id' => $dbCust->id,
                'full_address' => $sampleCustomer['full_address'],
                'latitude' => $sampleCustomer['latitude'],
                'longitude' => $sampleCustomer['longitude'],
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
                $this->assertEquals($svc['profile'], $dbSvc->profile);
                $this->assertEquals($svc['contract_type'], $dbSvc->contract_type);
                $this->assertEquals((float) ($svc['other_fee'] ?? 0), (float) ($dbSvc->other_fee ?? 0));

                // Verify Survey & Installation matches
                if (! empty($svc['survey_date']) || ! empty($svc['surveyors'])) {
                    $this->assertDatabaseHas('customer_surveys', [
                        'customer_id' => $dbSvc->customer_id,
                        'surveyors' => empty($svc['surveyors']) ? null : $svc['surveyors'],
                        'fop_id' => $svc['survey_fop_id'],
                    ]);
                }
                if (! empty($svc['installation_date']) || ! empty($svc['installation_technicians'])) {
                    $this->assertDatabaseHas('customer_installations', [
                        'customer_id' => $dbSvc->customer_id,
                        'technicians' => empty($svc['installation_technicians']) ? null : $svc['installation_technicians'],
                        'fop_id' => $svc['installation_fop_id'],
                    ]);
                }
            }
        }

        // Verifying Technical Details (check sample)
        $sampleTech = collect($technicalSheet)->first();
        if ($sampleTech) {
            $this->assertDatabaseHas('customer_technical_details', [
                'old_report_id' => $sampleTech['old_report_id'],
                'router_or_ont_serial' => $sampleTech['ont_sn'],
                'passive_device' => empty($sampleTech['passive_device']) ? null : $sampleTech['passive_device'],
                'initial_attenuation' => $sampleTech['initial_attenuation'] !== null ? number_format((float) $sampleTech['initial_attenuation'], 2, '.', '') : null,
            ]);
        }

        // Verifying Invoices
        $sampleInv = collect($invoicesSheet)->first();
        if ($sampleInv) {
            $this->assertDatabaseHas('invoices', [
                'old_invoice_id' => $sampleInv['old_invoice_id'],
                'billing_period' => $sampleInv['billing_period'],
                'other_fee' => $sampleInv['other_fee'],
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
                        $this->assertEquals('lunas', $invoice->invoice_status->value);
                    } else {
                        $this->assertEquals('sebagian', $invoice->invoice_status->value);
                    }
                }
            }
        }

        dump('End-to-end legacy database migration verified successfully!');
    }

    private function mapLegacyServiceStatus(?string $status): string
    {
        $normalized = strtolower(str_replace([' ', '-', '/'], '_', trim((string) $status)));

        return [
            'active' => 'active',
            'aktif' => 'active',
            'putus' => 'terminated',
            'berhenti' => 'terminated',
            'terminated' => 'terminated',
            'gagal' => 'rejected',
            'rejected' => 'rejected',
            'disurvei' => 'waiting_installation',
            'survey' => 'waiting_survey',
            'menunggu_survey' => 'waiting_survey',
            'pengajuan' => 'waiting_survey',
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
     * Parse SQL insert statement into key-value array of rows.
     */
    private function parseTableData(string $sql, string $tableName): array
    {
        preg_match_all('/INSERT INTO\s+`?'.$tableName.'`?\s*\(([^)]+)\)\s*VALUES\s*(.+?);/is', $sql, $matches);

        $rows = [];
        if (empty($matches[0])) {
            return $rows;
        }

        foreach ($matches[1] as $matchIndex => $columnsStr) {
            $columns = array_map(function ($col) {
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
                if ($inRow) {
                    $currentRow .= $char.($valuesStr[$i + 1] ?? '');
                }
                $i++;

                continue;
            }

            if (($char === "'" || $char === '"') && ! $inQuote) {
                $inQuote = true;
                $quoteChar = $char;
                if ($inRow) {
                    $currentRow .= $char;
                }
            } elseif ($char === $quoteChar && $inQuote) {
                $inQuote = false;
                if ($inRow) {
                    $currentRow .= $char;
                }
            } elseif ($char === '(' && ! $inQuote && ! $inRow) {
                $inRow = true;
                $currentRow = '';
            } elseif ($char === ')' && ! $inQuote && $inRow) {
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

            if (($char === "'" || $char === '"') && ! $inQuote) {
                $inQuote = true;
                $quoteChar = $char;
            } elseif ($char === $quoteChar && $inQuote) {
                $inQuote = false;
            } elseif ($char === ',' && ! $inQuote) {
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
        $this->assertFileExists($sqlPath, 'File sand_db_sandya.sql must exist in root directory.');
        $sql = file_get_contents($sqlPath);

        // 3. Parse and construct sheets using helper
        $sheets = $this->parseSqlToSheets($sql);

        $packagesSheet = $sheets['packages'];
        $customersSheet = $sheets['customers'];
        $servicesSheet = $sheets['services'];
        $technicalSheet = $sheets['technical_details'];
        $invoicesSheet = $sheets['invoices'];
        $paymentsSheet = $sheets['payments'];

        // Create Pop corresponding to legacy branch/POP
        Pop::firstOrCreate([
            'code' => 'SMN',
            'pop_code' => 'SMN',
        ], [
            'name' => 'sandya',
            'type' => 'cabang',
            'status' => 'active',
            'registration_prefix' => 'REG',
            'cid_prefix' => 'CID',
        ]);

        // First execution of import
        $validateResponse1 = $this->postJson('/customers/import/validate', [
            'sheets' => $sheets,
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
            'sheets' => $sheets,
        ]);
        $validateResponse2->assertStatus(200);
        $validateData2 = $validateResponse2->json();

        $confirmResponse2 = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($validateData2['sheets']),
            'file_name' => 'sand_db_sandya.sql',
        ]);
        $confirmResponse2->assertRedirect('/customers');

        // Verify that database counts have not changed (idempotent / duplicate prevention)
        $this->assertEquals($initialPkgCount, InternetPackage::count('*'), 'Internet packages count should remain idempotent.');
        $this->assertEquals($initialCustCount, Customer::count('*'), 'Customers count should remain idempotent.');
        $this->assertEquals($initialSvcCount, CustomerService::count('*'), 'Customer services count should remain idempotent.');
        $this->assertEquals($initialTechCount, CustomerTechnicalDetail::count('*'), 'Technical details count should remain idempotent.');
        $this->assertEquals($initialInvCount, Invoice::count('*'), 'Invoices count should remain idempotent.');
        $this->assertEquals($initialPmtCount, Payment::count('*'), 'Payments count should remain idempotent.');

        // 3. Edge Cases: empty fields & broken relationships
        // Inject an invalid customer row (missing name and broken POP)
        $invalidCustomers = $customersSheet;
        $invalidCustomers[] = [
            'old_customer_id' => 'PE-EDGE-1',
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
            'old_customer_id' => 'PE_NON_EXISTENT', // Broken customer relation
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
            'sheets' => $edgeSheets,
        ]);
        $validateEdge->assertStatus(200);
        $edgeData = $validateEdge->json();

        // Verify the customer edge case is handled with warnings but marked valid row
        $custRows = $edgeData['sheets']['customers']['rows'];
        $edgeCustRow = collect($custRows)->firstWhere('old_customer_id', 'PE-EDGE-1');
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
            'old_customer_id' => 'PE-EDGE-1',
            'data_completeness_status' => 'perlu_dilengkapi',
        ]);

        dump('Idempotency, duplication prevention, empty fields, and broken relations tested successfully on real data!');
    }

    /**
     * Parses the SQL dump directly into sheets format matching the command mappings.
     */
    private function parseSqlToSheets(string $sql): array
    {
        $paketRows = $this->parseTableData($sql, 'paket');
        $penggunaRows = $this->parseTableData($sql, 'pengguna');
        $layananRows = $this->parseTableData($sql, 'prosedure_permintaan_wifi');
        $laporanRows = $this->parseTableData($sql, 'laporan_pemasangan_wifi');
        $biayaRows = $this->parseTableData($sql, 'biaya_tagihan');
        $buktiRows = $this->parseTableData($sql, 'apikeuangan_buktitransaksitagihan');
        $surveyRows = $this->parseTableData($sql, 'survey_pemasangan_wifi');
        $oltRows = $this->parseTableData($sql, 'olt_slot_register');

        // Pre-build maps for lookup
        $penggunaMap = [];
        foreach ($penggunaRows as $row) {
            if (! empty($row['IDPENGGUNA'])) {
                $penggunaMap[$row['IDPENGGUNA']] = $row;
            }
        }

        $requestToCustomerMap = [];
        $legacyRequestByCustomer = [];
        foreach ($layananRows as $row) {
            $requestId = $row['IDPERMINTAAN'] ?? '';
            $customerId = $row['IDPENGGUNA'] ?? '';

            if ($requestId !== '') {
                $requestToCustomerMap[$requestId] = $customerId;
            }

            if ($customerId !== '' && $requestId !== '' && ! isset($legacyRequestByCustomer[$customerId])) {
                $legacyRequestByCustomer[$customerId] = $requestId;
            }
        }

        $invoiceToCustomerMap = [];
        foreach ($biayaRows as $row) {
            $invoiceToCustomerMap[$row['IDBIAYA']] = $row['IDPELANGGAN'] ?? '';
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
        foreach ($penggunaRows as $row) {
            if (! str_starts_with($row['IDPENGGUNA'] ?? '', 'PE')) {
                continue;
            }

            $fullName = trim(($row['NAMADEPAN'] ?? '').' '.($row['NAMABELAKANG'] ?? ''));
            $fullName = ucwords(str_replace('-', ' ', $fullName));
            if (empty($fullName)) {
                $fullName = $row['IDPENGGUNA'];
            }

            $gender = 'Laki-laki';
            if (isset($row['JENISKELAMIN'])) {
                if (strtoupper($row['JENISKELAMIN']) === 'P') {
                    $gender = 'Perempuan';
                }
            }

            $regDate = now()->format('Y-m-d');
            if (! empty($row['inserted_at'])) {
                try {
                    $regDate = Carbon::parse($row['inserted_at'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            $custSurvey = $this->resolveLegacySurveyRow([
                'IDPERMINTAAN' => $row['IDPERMINTAAN'] ?? null,
                'IDPENGGUNA' => $row['IDPENGGUNA'] ?? null,
                'IDSURVEY' => $row['IDSURVEY'] ?? null,
            ], $surveyMap);
            $lat = $custSurvey['LAT'] ?? null;
            $lon = $custSurvey['LONG'] ?? null;
            $fotoRumah = $custSurvey['FOTORUMAH'] ?? null;

            $custLaporan = $laporanMap[$row['IDPENGGUNA']] ?? null;
            $salesCode = '';
            foreach ($layananRows as $lRow) {
                if (($lRow['IDPENGGUNA'] ?? '') === $row['IDPENGGUNA']) {
                    $salesCode = $lRow['CREATED'] ?? '';
                    if (! $custLaporan) {
                        $custLaporan = $laporanMap[$lRow['IDPERMINTAAN']] ?? null;
                    }
                }
            }
            $fotoKontrak = $custLaporan['FOTOFORMULIR'] ?? null;

            $customersSheet[] = [
                'old_customer_id' => $row['IDPENGGUNA'],
                'old_request_id' => $legacyRequestByCustomer[$row['IDPENGGUNA']] ?? '',
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
                'old_branch_id' => $row['IDCABANG'] ?? '',
                'registration_date' => $regDate,
                'pop_code' => 'SMN',
                'pop_name' => 'sandya',
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
        foreach ($layananRows as $row) {
            if (! str_starts_with($row['IDPENGGUNA'] ?? '', 'PE')) {
                continue;
            }

            $actDate = now()->format('Y-m-d');
            if (! empty($row['TGL_AKTIFPUTUS']) && $row['TGL_AKTIFPUTUS'] !== '0000-00-00') {
                $actDate = $row['TGL_AKTIFPUTUS'];
            } elseif (! empty($row['TGLSELESAI'])) {
                try {
                    $actDate = Carbon::parse($row['TGLSELESAI'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            $profile = '';
            if (! empty($row['IDPAKET'])) {
                $pkg = collect($paketRows)->firstWhere('KODEPAKET', $row['IDPAKET']);
                $profile = $pkg['PROFILPPP'] ?? $pkg['PROFILOLT'] ?? '';
            }

            $survey = $this->resolveLegacySurveyRow($row, $surveyMap);
            $surveyDate = null;
            $surveyStartTime = null;
            if ($survey && ! empty($survey['TGLSURVEY'])) {
                try {
                    $cDate = Carbon::parse($survey['TGLSURVEY']);
                    $surveyDate = $cDate->format('Y-m-d');
                    $surveyStartTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {
                }
            } elseif (! empty($row['TGLSURVEY'])) {
                try {
                    $cDate = Carbon::parse($row['TGLSURVEY']);
                    $surveyDate = $cDate->format('Y-m-d');
                    $surveyStartTime = $cDate->format('H:i:s');
                } catch (\Exception $e) {
                }
            }

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
                'profile' => $profile,
                'contract_type' => strtolower(trim((string) ($row['STATUSALAT'] ?? ''))),
                'activation_time' => ! empty($row['VERIFIED_AT']) ? Carbon::parse($row['VERIFIED_AT'])->format('H:i:s') : null,
                'activated_by_name' => $this->resolveLegacyUserLabel($row['VERIFIED'] ?? '', $penggunaMap),
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
            if (! str_starts_with($row['IDPENGGUNA'] ?? '', 'PE')) {
                continue;
            }

            $olt = $oltMap[$row['IDPENGGUNA']] ?? null;
            $oltCode = $olt['LOKASIOLT'] ?? $row['NOMOR_PORT_OLT'] ?? '';
            $oltPort = $olt['INDEXONU'] ?? '';
            $actualAttenuation = $olt['RX_POWER'] ?? null;

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

            $jitter = is_numeric($row['PINGGATEWAY'] ?? null) ? (int) $row['PINGGATEWAY'] : null;
            $latency = is_numeric($row['PINGGOOGLE'] ?? null) ? (int) $row['PINGGOOGLE'] : null;

            $conformity = null;
            if ($req && ! empty($req['IDPAKET'])) {
                $pkg = collect($paketRows)->firstWhere('KODEPAKET', $req['IDPAKET']);
                $pkgSpeed = $pkg['SPEEDDOWN'] ?? 0;
                $testSpeed = is_numeric($row['TESTDOWN'] ?? null) ? (float) $row['TESTDOWN'] * 1000 : 0;
                if ($pkgSpeed > 0 && $testSpeed > 0) {
                    $conformity = min(100, round(($testSpeed / $pkgSpeed) * 100, 2));
                }
            }

            $technicalSheet[] = [
                'old_report_id' => $row['IDREPORT'],
                'old_customer_id' => $row['IDPENGGUNA'] ?? '',
                'old_request_id' => $row['IDPERMINTAAN'] ?? '',
                'connection_type' => $row['JENIS'] ?: 'KABEL',
                'ont_sn' => $row['SNROOTER_FIBER'] ?? '',
                'odp_code' => $row['NOMOR_ODP'] ?? '',
                'odp_port' => $row['NOMOR_PORT_ODP'] ?? '',
                'olt_code' => $oltCode,
                'olt_port' => $oltPort,
                'vlan_id' => '',
                'passive_device' => $row['BRG_OUTDOOR'] ?? '',
                'branch_number' => $penggunaMap[$row['IDPENGGUNA']]['IDCABANG'] ?? '',
                'pop_number' => $penggunaMap[$row['IDPENGGUNA']]['IDWILAYAH'] ?? '',
                'router_number' => $row['MACADDR_ROOTER'] ?? '',
                'initial_attenuation' => is_numeric($row['SIGNAL_KABEL'] ?? null) ? (float) $row['SIGNAL_KABEL'] : (is_numeric($row['SIGNAL_WIRELESS'] ?? null) ? (float) $row['SIGNAL_WIRELESS'] : null),
                'actual_attenuation' => is_numeric($actualAttenuation) ? (float) $actualAttenuation : null,
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

        // Sheet 5: invoices
        $invoicesSheet = [];
        foreach ($biayaRows as $row) {
            $cust = $row['IDPELANGGAN'] ?? $requestToCustomerMap[$row['IDPERMINTAAN'] ?? ''] ?? '';
            if (! str_starts_with($cust, 'PE')) {
                continue;
            }

            $issueDate = now()->format('Y-m-d');
            if (! empty($row['TGLINSERT'])) {
                try {
                    $issueDate = Carbon::parse($row['TGLINSERT'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            $dueDate = Carbon::parse($issueDate)->addDays(10)->format('Y-m-d');
            $billingPeriod = Carbon::parse($issueDate)->format('Y-m');

            $req = collect($layananRows)->firstWhere('IDPERMINTAAN', $row['IDPERMINTAAN'])
                ?? collect($layananRows)->firstWhere('IDPENGGUNA', $row['IDPENGGUNA'] ?? null);
            $pkgPrice = 0;
            if ($req && ! empty($req['IDPAKET'])) {
                $pkg = collect($paketRows)->firstWhere('KODEPAKET', $req['IDPAKET']);
                $pkgPrice = (int) ($pkg['HARGA'] ?? 0);
            }

            $monthlyFee = (int) ($row['BIAYABULANAN'] ?? 0);
            $prorateAmount = null;
            if ($pkgPrice > 0 && $monthlyFee < $pkgPrice) {
                $prorateAmount = $monthlyFee;
            }

            $invoicesSheet[] = [
                'old_invoice_id' => $row['IDBIAYA'],
                'old_cost_id' => $row['IDBIAYA'],
                'old_request_id' => $row['IDPERMINTAAN'] ?? '',
                'old_customer_id' => $row['IDPELANGGAN'] ?: $cust,
                'billing_period' => $billingPeriod,
                'total_amount' => (int) ($row['TOTALBIAYA'] ?? 0),
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'monthly_fee' => $monthlyFee,
                'status' => 'belum_dibayar',
                'installation_fee' => (int) ($row['BIAYAPASANG'] ?? 0),
                'other_fee' => (int) ($row['BIAYALAINLAIN'] ?? 0),
                'prorate_amount' => $prorateAmount,
                'extra_cable_fee' => 0,
                'extra_installation_fee' => 0,
                'extra_pole_fee' => 0,
            ];
        }

        // Sheet 6: payments
        $paymentsSheet = [];
        foreach ($buktiRows as $row) {
            $reqCust = $requestToCustomerMap[$row['IDPERMINTAAN'] ?? ''] ?? $requestToCustomerMap[$row['IDSURVEY'] ?? ''] ?? '';
            $invCust = $invoiceToCustomerMap[$row['IDTRANSAKSI'] ?? ''] ?? '';
            $cust = $invCust ?: $reqCust;

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

            $billingPeriod = now()->format('Y-m');
            if (! empty($row['BULANTAGIHAN'])) {
                try {
                    $billingPeriod = Carbon::parse($row['BULANTAGIHAN'])->format('Y-m');
                } catch (\Exception $e) {
                }
            }

            $paymentsSheet[] = [
                'old_payment_id' => $row['IDUNIQ'],
                'old_invoice_id' => $row['IDTRANSAKSI'] ?: $row['IDPERMINTAAN'],
                'old_transaction_id' => $row['IDTRANSAKSI'] ?? '',
                'old_request_id' => $row['IDPERMINTAAN'] ?? '',
                'old_customer_id' => $cust,
                'billing_period' => $billingPeriod,
                'amount' => (int) ($row['BAYAR'] ?? 0),
                'payment_date' => $payDate,
                'payment_method' => 'cash',
                'received_by_old' => '',
                'deposited_by_old' => '',
                'status' => 'valid',
            ];
        }

        return [
            'packages' => $packagesSheet,
            'customers' => $customersSheet,
            'services' => $servicesSheet,
            'technical_details' => $technicalSheet,
            'invoices' => $invoicesSheet,
            'payments' => $paymentsSheet,
        ];
    }
}
