<?php

namespace Tests\Feature;

use App\Models\InternetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternetPackageImportFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    /**
     * Test Task 2: Fix Internet Package pricing and PPN consistency during the legacy data import.
     */
    public function test_confirm_import_sets_ppn_and_total_price_for_legacy_packages(): void
    {
        $this->loginAsAdmin();

        $sheets = [
            'packages' => [
                [
                    'old_package_id' => 'PKG-LEGACY-TDD',
                    'name' => 'Legacy Package TDD',
                    'monthly_price' => 150000,
                    'download_speed' => 10,
                    'upload_speed' => 10,
                    'package_type' => 'Broadband',
                    'category' => 'Home',
                    'status_row' => 'valid',
                ],
            ],
            'customers' => [],
            'services' => [],
            'technical_details' => [],
            'invoices' => [],
            'payments' => [],
        ];

        $response = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($sheets),
            'file_name' => 'tdd_test.xlsx',
        ]);

        $response->assertRedirect();

        $package = InternetPackage::where('old_package_id', 'PKG-LEGACY-TDD')->firstOrFail();

        $this->assertEquals(150000.00, (float) $package->monthly_price);
        $this->assertEquals(0.00, (float) $package->ppn);
        $this->assertEquals(150000.00, (float) $package->total_price);
    }
}
