<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\City;
use App\Models\District;
use App\Models\Village;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\CustomerTechnicalDetail;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_import_page_loads_successfully(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get('/customers/import');

        $response->assertStatus(200);
        $response->assertSee('Import Pelanggan & Billing Lama', false);
        $response->assertSee('Download Template Excel (Multi-Sheet)');
        $response->assertSee('Tarik & Letakkan File Excel Migrasi di Sini', false);
    }

    public function test_admin_can_download_customer_import_template(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get('/customers/import/template');

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename=template-import-pelanggan.xlsx');
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_validate_multisheet_import_returns_json_with_validation_results(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        
        $pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $sheets = [
            'packages' => [
                [
                    'old_package_id' => 'PKG-LEGACY-1',
                    'name' => 'Legacy Package 10M',
                    'monthly_price' => '150000',
                    'download_speed' => '10',
                    'upload_speed' => '10',
                    'package_type' => 'Broadband',
                    'category' => 'Home',
                ]
            ],
            'customers' => [
                [
                    'old_customer_id' => 'CUST-LEGACY-1',
                    'full_name' => 'Budi Santoso',
                    'phone' => '081234567890',
                    'primary_phone' => '081234567890',
                    'full_address' => 'Jl. Pahlawan No. 10',
                    'village' => $village->name,
                    'district' => $district->name,
                    'city' => $city->name,
                    'pop_code' => 'TST',
                    'pop_name' => 'POP Test',
                    'gender' => 'Laki-laki',
                    'identity_number' => '3502181010900001',
                ]
            ],
            'services' => [
                [
                    'old_request_id' => 'REQ-LEGACY-1',
                    'old_customer_id' => 'CUST-LEGACY-1',
                    'old_package_id' => 'PKG-LEGACY-1',
                    'service_status' => 'aktif',
                    'activation_date' => '2026-01-01',
                    'due_date' => '2026-02-01',
                ]
            ],
            'technical_details' => [
                [
                    'old_report_id' => 'REP-LEGACY-1',
                    'old_customer_id' => 'CUST-LEGACY-1',
                    'old_request_id' => 'REQ-LEGACY-1',
                    'connection_type' => 'FTTH',
                    'router_or_ont_serial' => 'SN12345678',
                    'ip_address' => '192.168.1.50',
                    'odp_code' => 'ODP-TST-01',
                    'odp_port' => '5',
                    'olt_code' => '1/1/2',
                    'wireless_signal' => '-15',
                    'fiber_signal' => '-19',
                    'location_source' => 'POLE-01',
                    'note' => 'Migrasi lancar',
                ]
            ],
            'invoices' => [
                [
                    'old_invoice_id' => 'INV-LEGACY-1',
                    'old_customer_id' => 'CUST-LEGACY-1',
                    'billing_period' => '2026-01',
                    'total_amount' => '166500', // 150000 + 11% PPN
                    'issue_date' => '2026-01-01',
                    'due_date' => '2026-01-10',
                    'status' => 'belum_dibayar',
                ]
            ],
            'payments' => [
                [
                    'old_payment_id' => 'PAY-LEGACY-1',
                    'old_invoice_id' => 'INV-LEGACY-1',
                    'amount' => '166500',
                    'payment_date' => '2026-01-05',
                    'payment_method' => 'cash',
                ]
            ],
        ];

        $response = $this->postJson('/customers/import/validate', [
            'sheets' => $sheets
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        
        $data = $response->json();
        $this->assertArrayHasKey('sheets', $data);
        
        $this->assertEquals('valid', $data['sheets']['packages']['rows'][0]['status_row']);
        $this->assertEquals('valid', $data['sheets']['customers']['rows'][0]['status_row']);
        $this->assertEquals('valid', $data['sheets']['services']['rows'][0]['status_row']);
        $this->assertEquals('valid', $data['sheets']['technical_details']['rows'][0]['status_row']);
        $this->assertEquals('valid', $data['sheets']['invoices']['rows'][0]['status_row']);
        $this->assertEquals('valid', $data['sheets']['payments']['rows'][0]['status_row']);
    }

    public function test_validate_multisheet_import_detects_relational_errors(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $sheets = [
            'packages' => [
                [
                    'old_package_id' => '', // Error: wajib diisi
                    'name' => '', // Error: wajib diisi
                    'monthly_price' => 'abc', // Error: harus angka
                ]
            ],
            'customers' => [
                [
                    'old_customer_id' => 'CUST-LEGACY-2',
                    'full_name' => 'Budi Santoso',
                    'phone' => '081234567890',
                    'full_address' => 'Jl. Pahlawan No. 10',
                    'village' => 'Desa Fiktif', // Error: desa tidak ditemukan
                    'district' => 'Kecamatan Fiktif',
                    'city' => 'Kota Fiktif',
                    'pop_code' => 'NONEXISTENT', // Error: POP tidak ditemukan
                ]
            ],
            'services' => [
                [
                    'old_request_id' => 'REQ-LEGACY-2',
                    'old_customer_id' => 'CUST-NONEXISTENT', // Error: customer tidak ditemukan
                    'old_package_id' => 'PKG-NONEXISTENT', // Error: package tidak ditemukan
                    'service_status' => 'status_salah', // Error: status tidak valid
                ]
            ],
            'technical_details' => [
                [
                    'old_report_id' => 'REP-LEGACY-2',
                    'old_customer_id' => 'CUST-NONEXISTENT', // Error: customer tidak ditemukan
                ]
            ],
            'invoices' => [
                [
                    'old_invoice_id' => 'INV-LEGACY-2',
                    'old_customer_id' => 'CUST-NONEXISTENT', // Error: customer tidak ditemukan
                    'billing_period' => '2026/01', // Error: format YYYY-MM
                    'total_amount' => 'abc', // Error: harus angka
                ]
            ],
            'payments' => [
                [
                    'old_payment_id' => 'PAY-LEGACY-2',
                    'old_invoice_id' => 'INV-NONEXISTENT', // Error: invoice tidak ditemukan
                    'amount' => 'abc', // Error: harus angka
                    'payment_date' => 'tanggal-salah', // Error: tidak valid
                ]
            ],
        ];

        $response = $this->postJson('/customers/import/validate', [
            'sheets' => $sheets
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals('error', $data['sheets']['packages']['rows'][0]['status_row']);
        $this->assertContains('ID paket lama wajib diisi.', $data['sheets']['packages']['rows'][0]['errors']);
        $this->assertContains('Nama paket wajib diisi.', $data['sheets']['packages']['rows'][0]['errors']);
        $this->assertContains('Harga paket harus berupa angka.', $data['sheets']['packages']['rows'][0]['errors']);

        $this->assertEquals('valid', $data['sheets']['customers']['rows'][0]['status_row']);
        $this->assertContains("Desa/Kelurahan 'Desa Fiktif' tidak ditemukan di master wilayah; teks legacy tetap disimpan.", $data['sheets']['customers']['rows'][0]['warnings']);
        $this->assertContains('POP tidak ditemukan atau tidak aktif; pelanggan tetap diimport untuk review dan belum siap billing.', $data['sheets']['customers']['rows'][0]['warnings']);

        $this->assertEquals('error', $data['sheets']['services']['rows'][0]['status_row']);
        $this->assertContains("Pelanggan dengan ID 'CUST-NONEXISTENT' tidak ditemukan.", $data['sheets']['services']['rows'][0]['errors']);
        $this->assertContains("Paket dengan ID 'PKG-NONEXISTENT' tidak ditemukan.", $data['sheets']['services']['rows'][0]['errors']);
        $this->assertContains("Status layanan 'status_salah' tidak didukung.", $data['sheets']['services']['rows'][0]['errors']);

        $this->assertEquals('error', $data['sheets']['technical_details']['rows'][0]['status_row']);
        $this->assertContains("Pelanggan dengan ID 'CUST-NONEXISTENT' tidak ditemukan.", $data['sheets']['technical_details']['rows'][0]['errors']);

        $this->assertEquals('error', $data['sheets']['invoices']['rows'][0]['status_row']);
        $this->assertContains("Pelanggan dengan ID 'CUST-NONEXISTENT' belum ditemukan saat validasi; akan dicoba cocok lewat request saat import.", $data['sheets']['invoices']['rows'][0]['warnings']);
        $this->assertContains('Format periode tagihan harus YYYY-MM.', $data['sheets']['invoices']['rows'][0]['errors']);
        $this->assertContains('Total tagihan harus berupa angka.', $data['sheets']['invoices']['rows'][0]['errors']);

        $this->assertEquals('error', $data['sheets']['payments']['rows'][0]['status_row']);
        $this->assertContains("Invoice dengan ID 'INV-NONEXISTENT' belum ditemukan saat validasi; akan dicoba cocok lewat transaksi/request saat import.", $data['sheets']['payments']['rows'][0]['warnings']);
        $this->assertContains('Nominal bayar harus berupa angka.', $data['sheets']['payments']['rows'][0]['errors']);
        $this->assertContains('Tanggal bayar tidak valid.', $data['sheets']['payments']['rows'][0]['errors']);
    }

    public function test_confirm_multisheet_import_saves_all_relations_transactionally(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->loginAsAdmin();

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        
        $pop = Pop::create([
            'code' => 'POP-TEST-2',
            'pop_code' => 'TS2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test 2',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $sheets = [
            'packages' => [
                [
                    'old_package_id' => 'PKG-LEG2-1',
                    'name' => 'Legacy Package 20M',
                    'monthly_price' => 250000,
                    'download_speed' => 20,
                    'upload_speed' => 20,
                    'package_type' => 'Broadband',
                    'category' => 'Home',
                    'status_row' => 'valid',
                ]
            ],
            'customers' => [
                [
                    'old_customer_id' => 'CUST-LEG2-1',
                    'full_name' => 'Andi Wijaya',
                    'phone' => '087788990011',
                    'primary_phone' => '087788990011',
                    'full_address' => 'Jl. Gajah Mada No. 25',
                    'village_name' => $village->name,
                    'district_name' => $district->name,
                    'city_name' => $city->name,
                    'village_id' => $village->id,
                    'district_id' => $district->id,
                    'city_id' => $city->id,
                    'pop_id' => $pop->id,
                    'pop_code' => 'TS2',
                    'pop_name' => 'POP Test 2',
                    'gender' => 'Laki-laki',
                    'identity_number' => '3502181010900002',
                    'status_row' => 'valid',
                ]
            ],
            'services' => [
                [
                    'old_request_id' => 'REQ-LEG2-1',
                    'old_customer_id' => 'CUST-LEG2-1',
                    'old_package_id' => 'PKG-LEG2-1',
                    'service_status' => 'active',
                    'activation_date' => '2026-01-01',
                    'due_date' => '2026-02-01',
                    'status_row' => 'valid',
                ]
            ],
            'technical_details' => [
                [
                    'old_report_id' => 'REP-LEG2-1',
                    'old_customer_id' => 'CUST-LEG2-1',
                    'old_request_id' => 'REQ-LEG2-1',
                    'connection_type' => 'FTTH',
                    'ont_sn' => 'SN87654321',
                    'ip_address' => '192.168.2.100',
                    'odp_code' => 'ODP-TS2-05',
                    'odp_port' => '2',
                    'olt_code' => '1/1/3',
                    'wireless_signal' => '-12',
                    'fiber_signal' => '-18',
                    'location_source' => 'POLE-05',
                    'note' => 'Pemasangan rapi',
                    'status_row' => 'valid',
                ]
            ],
            'invoices' => [
                [
                    'old_invoice_id' => 'INV-LEG2-1',
                    'old_customer_id' => 'CUST-LEG2-1',
                    'billing_period' => '2026-01',
                    'total_amount' => 277500, // 250000 + 11% PPN
                    'issue_date' => '2026-01-01',
                    'due_date' => '2026-01-10',
                    'status' => 'belum_dibayar',
                    'status_row' => 'valid',
                ]
            ],
            'payments' => [
                [
                    'old_payment_id' => 'PAY-LEG2-1',
                    'old_invoice_id' => 'INV-LEG2-1',
                    'amount' => 277500,
                    'payment_date' => '2026-01-05',
                    'payment_method' => 'cash',
                    'status_row' => 'valid',
                ]
            ],
        ];

        $response = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($sheets),
            'file_name' => 'multisheet_test.xlsx',
        ]);

        $response->assertRedirect('/customers');
        $response->assertSessionHas('success');

        // Assert Package imported
        $this->assertDatabaseHas('internet_packages', [
            'old_package_id' => 'PKG-LEG2-1',
            'package_code' => 'PKG-LEG2-1',
            'name' => 'Legacy Package 20M',
            'monthly_price' => 250000,
        ]);

        // Assert Customer imported
        $this->assertDatabaseHas('customers', [
            'old_customer_id' => 'CUST-LEG2-1',
            'full_name' => 'Andi Wijaya',
            'phone' => '087788990011',
            'pop_id' => $pop->id,
            'status' => 'active',
            'customer_status' => 'aktif',
        ]);

        $customer = Customer::where('old_customer_id', 'CUST-LEG2-1')->firstOrFail();

        // Assert Address imported
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Gajah Mada No. 25',
            'village_id' => $village->id,
        ]);

        // Assert Service imported
        $this->assertDatabaseHas('customer_services', [
            'customer_id' => $customer->id,
            'old_request_id' => 'REQ-LEG2-1',
            'monthly_price' => 250000,
            'total_monthly_bill' => 250000,
            'service_status' => 'active',
        ]);

        // Assert Technical Detail imported
        $this->assertDatabaseHas('customer_technical_details', [
            'customer_id' => $customer->id,
            'old_report_id' => 'REP-LEG2-1',
            'router_or_ont_serial' => 'SN87654321',
            'ip_address' => '192.168.2.100',
        ]);

        // Assert Invoice imported
        $this->assertDatabaseHas('invoices', [
            'old_invoice_id' => 'INV-LEG2-1',
            'invoice_number' => 'INV-LEGACY-INV-LEG2-1',
            'customer_id' => $customer->id,
            'total_amount' => 277500,
            'invoice_status' => 'lunas',
        ]);

        // Assert Payment imported
        $this->assertDatabaseHas('payments', [
            'old_payment_id' => 'PAY-LEG2-1',
            'payment_number' => 'PAY-LEGACY-PAY-LEG2-1',
            'amount' => 277500,
        ]);

        // Verify show page displays legacy technical details
        $showResponse = $this->get("/customers/{$customer->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Detail Teknis Lama');
        $showResponse->assertSee('REP-LEG2-1');
        $showResponse->assertSee('SN87654321');
        $showResponse->assertSee('192.168.2.100');
    }

    public function test_legacy_status_and_payment_transaction_mapping_are_imported(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();

        $pop = Pop::create([
            'code' => 'POP-LEGACY',
            'pop_code' => 'LGC',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Legacy',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $sheets = [
            'packages' => [
                [
                    'old_package_id' => 'PK-TRX-1',
                    'name' => 'Legacy Paket Transaksi',
                    'monthly_price' => 100000,
                    'download_speed' => 10,
                    'upload_speed' => 10,
                    'status_row' => 'valid',
                ],
            ],
            'customers' => [
                [
                    'old_customer_id' => 'PE-TRX-1',
                    'full_name' => 'Legacy Active',
                    'phone' => 'null',
                    'primary_phone' => 'null',
                    'identity_number' => '3502000000000001',
                    'full_address' => 'Alamat legacy',
                    'village_id' => $village->id,
                    'district_id' => $district->id,
                    'city_id' => $city->id,
                    'village_name' => $village->name,
                    'district_name' => $district->name,
                    'city_name' => $city->name,
                    'pop_id' => $pop->id,
                    'status_row' => 'valid',
                ],
            ],
            'services' => [
                [
                    'old_request_id' => 'RQ-TRX-1',
                    'old_customer_id' => 'PE-TRX-1',
                    'old_package_id' => 'PK-TRX-1',
                    'old_cost_id' => 'IN-TRX-1',
                    'request_status' => 'ACTIVE',
                    'service_status' => 'ACTIVE',
                    'activation_date' => '2025-05-06',
                    'due_date' => '2025-05-10',
                    'status_row' => 'valid',
                ],
            ],
            'technical_details' => [],
            'invoices' => [
                [
                    'old_invoice_id' => '',
                    'old_cost_id' => 'IN-TRX-1',
                    'old_customer_id' => 'PE-TRX-1',
                    'old_request_id' => 'RQ-TRX-1',
                    'billing_period' => '2025-05',
                    'issue_date' => '2025-05-06',
                    'due_date' => '2025-05-10',
                    'monthly_fee' => 100000,
                    'total_amount' => 100000,
                    'status' => 'belum_dibayar',
                    'status_row' => 'valid',
                ],
            ],
            'payments' => [
                [
                    'old_payment_id' => 'PAY-TRX-1',
                    'old_transaction_id' => 'IN-TRX-1',
                    'old_invoice_id' => '',
                    'old_customer_id' => 'PE-TRX-1',
                    'old_request_id' => 'RQ-TRX-1',
                    'payment_date' => '2025-05-07',
                    'billing_period' => '2025-05',
                    'payment_method' => 'tunai',
                    'amount' => 50000,
                    'received_by_old' => 'PG000005',
                    'status' => 'valid',
                    'status_row' => 'valid',
                ],
            ],
        ];

        $response = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($sheets),
            'file_name' => 'legacy-transaction.xlsx',
        ]);

        $response->assertRedirect('/customers');

        $customer = Customer::where('old_customer_id', 'PE-TRX-1')->firstOrFail();
        $invoice = Invoice::where('old_cost_id', 'IN-TRX-1')->firstOrFail();

        $this->assertSame('active', $customer->status);
        $this->assertSame('aktif', $customer->customer_status);
        $this->assertSame('sebagian', $invoice->invoice_status);
        $this->assertEquals(50000.00, (float) $invoice->paid_amount);
        $this->assertEquals(50000.00, (float) $invoice->remaining_amount);

        $this->assertDatabaseHas('payments', [
            'old_payment_id' => 'PAY-TRX-1',
            'old_transaction_id' => 'IN-TRX-1',
            'old_request_id' => 'RQ-TRX-1',
            'billing_period' => '2025-05',
            'payment_method' => 'cash',
            'payment_status' => 'valid',
            'amount' => 50000,
        ]);
    }

    public function test_validate_uploaded_xlsx_file_successfully(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is required to write XLSX files');
        }

        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        
        $pop = Pop::create([
            'code' => 'POP-TEST-FILE',
            'pop_code' => 'TFL',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP File Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test-import-' . uniqid() . '.xlsx';
        $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($tempFile);
        
        $writer->nameCurrentSheet('customers');
        $writer->addHeader(['old_customer_id', 'full_name', 'phone', 'primary_phone', 'full_address', 'village', 'district', 'city', 'pop_code', 'pop_name', 'gender', 'identity_number']);
        $writer->addRow([
            'CUST-FILE-1', 'Budi Santoso', '081234567890', '081234567890', 'Jl. Pahlawan No. 10', 
            $village->name, $district->name, $city->name, 'TFL', 'POP File Test', 'Laki-laki', '3502181010900001'
        ]);

        $writer->addNewSheetAndMakeItCurrent()->nameCurrentSheet('packages');
        $writer->addHeader(['old_package_id', 'name', 'monthly_price', 'download_speed', 'upload_speed', 'package_type', 'category']);
        $writer->addRow(['PKG-FILE-1', 'Legacy Package 10M', '150000', '10', '10', 'Broadband', 'Home']);

        $writer->addNewSheetAndMakeItCurrent()->nameCurrentSheet('services');
        $writer->addHeader(['old_request_id', 'old_customer_id', 'old_package_id', 'service_status', 'activation_date', 'due_date']);
        $writer->addRow(['REQ-FILE-1', 'CUST-FILE-1', 'PKG-FILE-1', 'aktif', '2026-01-01', '2026-02-01']);

        $writer->addNewSheetAndMakeItCurrent()->nameCurrentSheet('technical_details');
        $writer->addHeader(['old_report_id', 'old_customer_id', 'old_request_id', 'connection_type', 'router_or_ont_serial', 'ip_address', 'odp_code', 'odp_port', 'olt_code', 'wireless_signal', 'fiber_signal', 'location_source', 'note']);
        $writer->addRow(['REP-FILE-1', 'CUST-FILE-1', 'REQ-FILE-1', 'FTTH', 'SN12345678', '192.168.1.50', 'ODP-TST-01', '5', '1/1/2', '-15', '-19', 'POLE-01', 'Migrasi lancar']);

        $writer->addNewSheetAndMakeItCurrent()->nameCurrentSheet('invoices');
        $writer->addHeader(['old_invoice_id', 'old_customer_id', 'billing_period', 'total_amount', 'issue_date', 'due_date', 'status']);
        $writer->addRow(['INV-FILE-1', 'CUST-FILE-1', '2026-01', '166500', '2026-01-01', '2026-01-10', 'belum_dibayar']);

        $writer->addNewSheetAndMakeItCurrent()->nameCurrentSheet('payments');
        $writer->addHeader(['old_payment_id', 'old_invoice_id', 'amount', 'payment_date', 'payment_method']);
        $writer->addRow(['PAY-FILE-1', 'INV-FILE-1', '166500', '2026-01-05', 'cash']);

        $writer->close();

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tempFile,
            'template-import-pelanggan.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->post('/customers/import/validate', [
            'file' => $uploadedFile
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        
        $data = $response->json();
        $this->assertEquals('valid', $data['sheets']['customers']['rows'][0]['status_row']);
        $this->assertEquals('valid', $data['sheets']['packages']['rows'][0]['status_row']);

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    public function test_validate_uploaded_invalid_xlsx_file_fails_gracefully(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is required to write XLSX files');
        }

        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test-import-invalid-' . uniqid() . '.xlsx';
        $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($tempFile);
        
        // Only write customers sheet, missing packages, services, technical_details, invoices, payments
        $writer->nameCurrentSheet('customers');
        $writer->addHeader(['old_customer_id', 'full_name']);
        $writer->addRow(['CUST-FILE-INVALID', 'Budi']);
        $writer->close();

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tempFile,
            'template-import-pelanggan.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->post('/customers/import/validate', [
            'file' => $uploadedFile
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonStructure(['success', 'message', 'error']);

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}
