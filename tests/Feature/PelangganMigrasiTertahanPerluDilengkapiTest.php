<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Invoice;
use App\Models\Pop;
use App\Services\CustomerValidationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi dua cacat migrasi legacy yang ketahuan saat audit 2026-07-21.
 *
 * 1. Sheet services dulu mengirim `due_date` sebagai string kosong, jadi kolom
 *    itu NULL untuk SELURUH pelanggan hasil migrasi. Tanggal jatuh tempo adalah
 *    field wajib di CustomerValidationService, jadi 1.488 pelanggan aktif
 *    tertahan di 'perlu_dilengkapi' padahal mereka menyala dan ditagih tiap
 *    bulan.
 *
 * 2. Importer dulu SELALU mengambil `monthly_price` dari harga paket dan
 *    membuang nilai yang dikirim sheet. Data legacy punya paket
 *    'default'/'undefined' berharga 0 yang dipakai puluhan pelanggan, sehingga
 *    layanan mereka berharga Rp 0 — tagihan bulanan berikutnya akan terbit
 *    Rp 0 untuk orang yang sebenarnya bayar penuh.
 */
class PelangganMigrasiTertahanPerluDilengkapiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
    }

    public function test_harga_dari_sheet_menang_atas_paket_legacy_berharga_nol(): void
    {
        $this->importLegacy(sheetMonthlyPrice: 110000, dueDate: '2022-03-08');

        $service = CustomerService::where('old_request_id', 'RQ000004')->firstOrFail();

        // Paket legacy 'default' berharga 0; nominal yang benar-benar ditagih
        // (110.000) yang harus menempel di layanan.
        $this->assertSame(110000.0, (float) $service->monthly_price, 'Harga layanan ikut 0 dari paket legacy.');
    }

    public function test_jatuh_tempo_terisi_membuat_pelanggan_tidak_lagi_kurang_field_wajib(): void
    {
        $this->importLegacy(sheetMonthlyPrice: 110000, dueDate: '2022-03-08');

        $customer = Customer::where('old_customer_id', 'PE000004')->firstOrFail();
        $service = $customer->customerService;

        $this->assertNotNull($service->due_date, 'Jatuh tempo layanan migrasi masih kosong.');
        $this->assertSame('2022-03-08', $service->due_date->format('Y-m-d'));

        $hasil = app(CustomerValidationService::class)->validate($customer->fresh());
        $this->assertArrayNotHasKey(
            'service_due_date',
            $hasil['missing_required'],
            'Tanggal jatuh tempo masih dihitung sebagai field wajib yang kosong.'
        );
    }

    public function test_backfill_mengisi_tempo_dan_harga_layanan_legacy_yang_terlanjur_kosong(): void
    {
        // Tiru kondisi data yang sudah terlanjur masuk sebelum importer dibetulkan.
        $this->importLegacy(sheetMonthlyPrice: 0, dueDate: null);

        $service = CustomerService::where('old_request_id', 'RQ000004')->firstOrFail();
        $this->assertNull($service->due_date);
        $this->assertSame(0.0, (float) $service->monthly_price);

        // Harga diambil dari invoice BULANAN yang pernah terbit untuk dia.
        Invoice::where('customer_id', $service->customer_id)->update([
            'invoice_type' => 'bulanan',
            'total_amount' => 110000,
        ]);

        $this->artisan('billing:backfill-legacy-service-fields', ['--force' => true])
            ->assertSuccessful();

        $service->refresh();
        $this->assertSame('2022-02-08', $service->due_date->format('Y-m-d'), 'Jatuh tempo tidak diisi dari aktivasi + 1 bulan.');
        $this->assertSame(110000.0, (float) $service->monthly_price, 'Harga tidak diambil dari invoice bulanan.');
    }

    public function test_backfill_memulihkan_harga_dari_dump_legacy_saat_database_tidak_punya_jejak(): void
    {
        // Pelanggan berpaket 'default' tidak punya invoice BULANAN maupun harga
        // paket — satu-satunya jejak harganya ada di biaya_tagihan dump legacy.
        $this->importLegacy(sheetMonthlyPrice: 0, dueDate: null);

        $dump = storage_path('app/legacy_test_dump.sql');
        file_put_contents($dump, <<<'SQL'
            INSERT INTO `biaya_tagihan` (`IDBIAYA`, `IDPELANGGAN`, `IDPERMINTAAN`, `BIAYAPASANG`, `BIAYABULANAN`, `BIAYALAINLAIN`, `TGLINSERT`) VALUES
            ('IN000004', 'PE000004', 'RQ000004', '0', '165000', '0', '2022-01-08 08:00:00');
            SQL);

        try {
            $this->artisan('billing:backfill-legacy-service-fields', [
                '--force' => true,
                '--dump' => [$dump],
            ])->assertSuccessful();
        } finally {
            @unlink($dump);
        }

        $service = CustomerService::where('old_request_id', 'RQ000004')->firstOrFail();
        $this->assertSame(165000.0, (float) $service->monthly_price, 'Harga tidak dipulihkan dari biaya_tagihan legacy.');
    }

    public function test_backfill_tanpa_force_tidak_menulis_apa_pun(): void
    {
        $this->importLegacy(sheetMonthlyPrice: 0, dueDate: null);

        $this->artisan('billing:backfill-legacy-service-fields')->assertSuccessful();

        $service = CustomerService::where('old_request_id', 'RQ000004')->firstOrFail();
        $this->assertNull($service->due_date, 'Mode daftar saja ternyata menulis ke database.');
        $this->assertSame(0.0, (float) $service->monthly_price);
    }

    public function test_backfill_tidak_menyentuh_layanan_non_legacy(): void
    {
        $this->importLegacy(sheetMonthlyPrice: 0, dueDate: null);

        // Layanan pendaftaran normal tidak punya old_request_id.
        $service = CustomerService::where('old_request_id', 'RQ000004')->firstOrFail();
        $service->update(['old_request_id' => null, 'old_cost_id' => null]);

        $this->artisan('billing:backfill-legacy-service-fields', ['--force' => true])
            ->assertSuccessful();

        $service->refresh();
        $this->assertNull($service->due_date, 'Baris non-legacy ikut disentuh backfill.');
    }

    /**
     * Import satu pelanggan lewat pipeline validate → confirm, memakai paket
     * legacy berharga 0 persis seperti paket 'default' di dump asli.
     */
    private function importLegacy(float $sheetMonthlyPrice, ?string $dueDate): void
    {
        $sheets = [
            'packages' => [[
                'old_package_id' => 'PK21000001', 'name' => 'default', 'monthly_price' => 0,
                'download_speed' => 10, 'upload_speed' => 10, 'package_type' => 'Broadband', 'category' => 'Home',
                'branch_pop_code' => 'C',
            ]],
            'customers' => [[
                'old_customer_id' => 'PE000004', 'old_request_id' => 'RQ000004', 'full_name' => 'Ardiyanto Cahyo Nugroho',
                'phone' => '081234567890', 'primary_phone' => '081234567890',
                'full_address' => 'Josari, Jetis, Ponorogo', 'pop_code' => 'C', 'pop_name' => 'Jetis',
                'village' => 'Josari', 'district' => 'Jetis', 'city' => 'Ponorogo',
                'registration_date' => '2022-02-08', 'branch_pop_code' => 'C',
            ]],
            'services' => [[
                'old_request_id' => 'RQ000004', 'old_customer_id' => 'PE000004', 'old_package_id' => 'PK21000001',
                'old_cost_id' => 'IN000004', 'request_status' => 'ACTIVE', 'service_status' => 'aktif',
                'activation_date' => '2022-01-08',
                'due_date' => $dueDate,
                'monthly_price' => $sheetMonthlyPrice > 0 ? $sheetMonthlyPrice : null,
                'branch_pop_code' => 'C',
            ]],
            'technical_details' => [],
            'invoices' => [[
                'old_invoice_id' => 'IN000004-AWAL', 'old_cost_id' => 'IN000004-AWAL', 'old_request_id' => 'RQ000004',
                'old_customer_id' => 'PE000004', 'billing_period' => '2022-01', 'total_amount' => 110000,
                'issue_date' => '2022-01-08', 'due_date' => '2022-01-18', 'monthly_fee' => 110000,
                'status' => 'belum_dibayar', 'installation_fee' => 0, 'other_fee' => 0, 'invoice_type' => 'awal',
                'branch_pop_code' => 'C',
            ]],
            'payments' => [],
        ];

        $validate = $this->postJson('/customers/import/validate', ['sheets' => $sheets]);
        $validate->assertStatus(200);
        $validated = $validate->json();
        $this->assertTrue($validated['success'], 'Validasi import gagal: '.json_encode($validated['errors'] ?? []));

        $confirm = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($validated['sheets']),
            'file_name' => 'legacy_test.sql',
        ]);
        $confirm->assertRedirect('/customers');
    }
}
