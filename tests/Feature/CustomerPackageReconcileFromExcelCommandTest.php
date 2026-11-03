<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Tests\TestCase;

class CustomerPackageReconcileFromExcelCommandTest extends TestCase
{
    use RefreshDatabase;

    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    /**
     * @param  list<array{Nama: string, Paket: int, "paket ": string}>  $rows
     */
    private function writeExcelFixture(array $rows): string
    {
        $path = storage_path('framework/testing/reconcile-'.uniqid().'.xlsx');
        $this->tempFiles[] = $path;

        $writer = SimpleExcelWriter::create($path);
        foreach ($rows as $row) {
            $writer->addRow($row);
        }
        $writer->close();

        return $path;
    }

    private function writeConfigFixture(array $config): string
    {
        $path = storage_path('framework/testing/reconcile-config-'.uniqid().'.json');
        $this->tempFiles[] = $path;
        file_put_contents($path, json_encode($config));

        return $path;
    }

    private function makePackage(string $code, string $name, float $price): InternetPackage
    {
        return InternetPackage::create([
            'package_code' => $code,
            'name' => $name,
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '10 Mbps',
            'download_speed_mbps' => 10,
            'upload_speed_mbps' => 10,
            'monthly_price' => $price,
        ]);
    }

    private function makeService(Customer $customer, ?InternetPackage $package, float $monthlyPrice): CustomerService
    {
        return CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package?->id,
            'package_name_snapshot' => $package?->name ?? 'default',
            'monthly_price' => $monthlyPrice,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $monthlyPrice,
            'billing_cycle' => 'monthly',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);
    }

    private function makeInvoice(Customer $customer, CustomerService $service, string $period, float $amount, string $status = 'belum_dibayar', float $paid = 0): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'TAG-TEST-'.uniqid(),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $service->internet_package_id,
            'billing_period' => $period,
            'issue_date' => $period.'-01',
            'due_date' => $period.'-10',
            'subtotal' => $amount,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $amount,
            'paid_amount' => $paid,
            'remaining_amount' => $amount - $paid,
            'invoice_status' => $status,
        ]);
    }

    public function test_dry_run_tidak_mengubah_apa_pun(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('NetP110', '110K Up to 5M', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Imam Choirul Anam']);
        $service = $this->makeService($customer, $package, 90357);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Imam Choirul Anam', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id])
            ->assertSuccessful();

        $this->assertSame('90357.00', $service->refresh()->monthly_price);
    }

    public function test_apply_mengoreksi_harga_tanpa_mengganti_paket_yang_tier_nya_sudah_benar(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $samePackageDifferentName = $this->makePackage('110K-TIER', '110K Up to 5M', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Imam Choirul Anam']);
        $service = $this->makeService($customer, $samePackageDifferentName, 90357);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Imam Choirul Anam', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $service->refresh();
        $this->assertSame('110000.00', $service->monthly_price);
        $this->assertSame($samePackageDifferentName->id, $service->internet_package_id);
    }

    public function test_apply_tidak_mengganti_paket_yang_harga_dan_speed_sama_walau_id_beda(): void
    {
        // Regresi temuan nyata: "Net198" dan "SPESIAL 200Mbps-198K" adalah
        // baris katalog BEDA (id beda) tapi harga+speed IDENTIK (198000,
        // 200 Mbps) — skema penamaan beda doang, bukan tier beda. Mengganti
        // id di sini cuma churn, bukan perbaikan.
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $specialNaming = InternetPackage::create([
            'package_code' => 'PK000027', 'name' => 'SPESIAL 200Mbps-198K', 'category' => 'Home',
            'package_group' => 'Basic', 'bandwidth_label' => '200 Mbps', 'download_speed_mbps' => 200,
            'upload_speed_mbps' => 200, 'monthly_price' => 198000,
        ]);
        $this->makePackage('Net198', 'Net198', 198000)->update(['download_speed_mbps' => 200]);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Soiman']);
        $service = $this->makeService($customer, $specialNaming, 198000);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Soiman', 'Paket' => 198000, 'paket ' => 'Net198'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->expectsOutputToContain('0 diubah')
            ->assertSuccessful();

        $service->refresh();
        $this->assertSame('198000.00', $service->monthly_price);
        $this->assertSame($specialNaming->id, $service->internet_package_id);
    }

    public function test_apply_mengganti_paket_walau_harga_kebetulan_sama_tapi_speed_beda(): void
    {
        // Regresi temuan nyata: PK000002 "110K Up to 5M" (3 Mbps) dan NetP110
        // (25 Mbps) sama-sama 110000 — diskriminator harga-saja salah
        // nyimpulkan tier sudah benar padahal speed beda sama sekali.
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $wrongSpeedSamePrice = InternetPackage::create([
            'package_code' => 'PK000002', 'name' => '110K Up to 5M', 'category' => 'Home',
            'package_group' => 'Basic', 'bandwidth_label' => '3 Mbps', 'download_speed_mbps' => 3,
            'upload_speed_mbps' => 3, 'monthly_price' => 110000,
        ]);
        $correctPackage = $this->makePackage('NetP110', 'NetP110', 110000);
        $correctPackage->update(['download_speed_mbps' => 25]);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Tria Mustika Aji']);
        $service = $this->makeService($customer, $wrongSpeedSamePrice, 110000);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Tria Mustika Aji', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $service->refresh();
        $this->assertSame('110000.00', $service->monthly_price);
        $this->assertSame($correctPackage->id, $service->internet_package_id);
    }

    public function test_apply_mensinkronkan_internet_package_id_di_tabel_customers(): void
    {
        // Regresi temuan nyata: `customers.internet_package_id` kolom SENDIRI,
        // duplikat dari `customer_services.internet_package_id` — List &
        // Detail Pelanggan baca dari kolom Customer ini. Lupa sync ini = UI
        // tetap kelihatan paket lama walau customer_service sudah benar.
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $wrongTierPackage = $this->makePackage('130K-TIER', '130K Up to 10M', 130000);
        $correctPackage = $this->makePackage('NetP110', '110K Up to 5M', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Imam Choirul Anam', 'internet_package_id' => $wrongTierPackage->id]);
        $this->makeService($customer, $wrongTierPackage, 90357);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Imam Choirul Anam', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame($correctPackage->id, $customer->refresh()->internet_package_id);
    }

    public function test_apply_mengganti_paket_yang_tier_nya_salah(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $wrongTierPackage = $this->makePackage('130K-TIER', '130K Up to 10M', 130000);
        $correctPackage = $this->makePackage('NetP110', '110K Up to 5M', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Imam Choirul Anam']);
        $service = $this->makeService($customer, $wrongTierPackage, 90357);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Imam Choirul Anam', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $service->refresh();
        $this->assertSame('110000.00', $service->monthly_price);
        $this->assertSame($correctPackage->id, $service->internet_package_id);
    }

    public function test_apply_mengisi_paket_kosong(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $defaultPackage = $this->makePackage('PK000001', 'default', 0);
        $realPackage = $this->makePackage('NetP110', '110K Up to 5M', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Veri Toko']);
        $service = $this->makeService($customer, $defaultPackage, 0);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Veri Toko', 'Paket' => 111000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $service->refresh();
        $this->assertSame('111000.00', $service->monthly_price);
        $this->assertSame($realPackage->id, $service->internet_package_id);
    }

    public function test_nama_ambigu_dilewati(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('Net150', 'Net150', 150000);
        $customerA = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Nyamin']);
        $customerB = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Nyamin']);
        $serviceA = $this->makeService($customerA, $package, 0);
        $serviceB = $this->makeService($customerB, $package, 0);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nyamin', 'Paket' => 150000, 'paket ' => 'Net150'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame('0.00', $serviceA->refresh()->monthly_price);
        $this->assertSame('0.00', $serviceB->refresh()->monthly_price);
    }

    public function test_idempotent_jalan_dua_kali(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('NetP110', '110K Up to 5M', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Imam Choirul Anam']);
        $service = $this->makeService($customer, $package, 90357);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Imam Choirul Anam', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();
        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->expectsOutputToContain('0 diubah')
            ->assertSuccessful();

        $this->assertSame('110000.00', $service->refresh()->monthly_price);
    }

    public function test_config_alias_dan_skip_names_dipakai(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('Net150', 'Net150', 150000);
        $aliasedCustomer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Rini']);
        $aliasedService = $this->makeService($aliasedCustomer, $package, 0);
        $skippedCustomer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Smp 3 Sambit']);
        $skippedService = $this->makeService($skippedCustomer, null, 0);

        $config = $this->writeConfigFixture([
            'name_aliases' => ['Rini (migrasi)' => 'Rini'],
            'skip_names' => ['Smp 3 Sambit (migrasi)'],
        ]);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Rini (migrasi)', 'Paket' => 150000, 'paket ' => 'Net150'],
            ['Nama' => 'Smp 3 Sambit (migrasi)', 'Paket' => 999999, 'paket ' => '999M'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame('150000.00', $aliasedService->refresh()->monthly_price);
        $this->assertSame('0.00', $skippedService->refresh()->monthly_price);
    }

    public function test_pop_name_dipakai_kalau_pop_id_kosong(): void
    {
        $pop = Pop::factory()->create(['name' => 'Jetis']);
        $package = $this->makePackage('NetP110', '110K Up to 5M', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Budi Santoso']);
        $service = $this->makeService($customer, $package, 90357);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Budi Santoso', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-name' => 'Jetis', '--apply' => true])
            ->assertSuccessful();

        $this->assertSame('110000.00', $service->refresh()->monthly_price);
    }

    public function test_apply_mengoreksi_invoice_bulanan_belum_dibayar_yang_kebentuk_duluan_pakai_harga_lama(): void
    {
        // Regresi temuan nyata: `billing:generate-monthly-invoices` jalan
        // sendiri lewat scheduler, bisa bersamaan dengan command ini —
        // invoice BULANAN sempat kebentuk duluan pakai monthly_price yang
        // belum dibetulkan. Satu command ini harus ikut membetulkannya,
        // supaya end user tidak perlu jalanin command kedua.
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('NetP110', 'NetP110', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Imam Choirul Anam']);
        $service = $this->makeService($customer, $package, 90357);
        $invoice = $this->makeInvoice($customer, $service, '2026-11', 90357);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Imam Choirul Anam', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $invoice->refresh();
        $this->assertSame('110000.00', $invoice->subtotal);
        $this->assertSame('110000.00', $invoice->total_amount);
        $this->assertSame(1, $invoice->items()->count());
    }

    public function test_apply_tidak_menyentuh_invoice_yang_sudah_dibayar_sebagian(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('NetP110', 'NetP110', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Imam Choirul Anam']);
        $service = $this->makeService($customer, $package, 90357);
        $invoice = $this->makeInvoice($customer, $service, '2026-11', 90357, 'sebagian', 50000);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Imam Choirul Anam', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame('90357.00', $invoice->refresh()->total_amount);
    }

    /**
     * `manual_overrides` (2026-11-02, migrasi skala besar) — nama yang gak
     * bisa dicocokkan otomatis dari Excel (ambigu/typo parah) tetap lewat
     * command ini via CID, jalur koreksi NON-PRORATE yang sama dengan baris
     * Excel — bukan lewat "Ganti Paket" UI (`CustomerPackageService::change()`)
     * yang bakal reprorate invoice periode berjalan kalau sudah terbit.
     */
    public function test_manual_override_mengoreksi_paket_dan_harga_lewat_cid(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $target = $this->makePackage('NetP110', 'NetP110', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Smp 3 Sambit', 'cid' => 'G00RQ000024']);
        $service = $this->makeService($customer, null, 46200);

        // Excel-nya sengaja gak punya baris buat pelanggan ini — ini persis
        // kasus yang perlu manual_overrides (nama ambigu/gak ketemu otomatis).
        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain Yang Gak Relevan', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);
        $config = $this->writeConfigFixture([
            'manual_overrides' => ['G00RQ000024' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--apply' => true])
            ->expectsOutputToContain('1 diubah')
            ->assertSuccessful();

        $service->refresh();
        $this->assertSame($target->id, $service->internet_package_id);
        $this->assertSame('110000.00', $service->monthly_price);
        $this->assertSame($target->id, $customer->fresh()->internet_package_id);
    }

    public function test_manual_override_membetulkan_invoice_stale_tanpa_prorate(): void
    {
        // Jebakan yang harus dihindari: `CustomerPackageService::change()`
        // (tombol "Ganti Paket") bakal MEMBLEND hari-lama+hari-baru kalau
        // invoice periode berjalan sudah terbit — benar untuk event ganti
        // paket sungguhan, SALAH untuk koreksi data migrasi (pelanggan gak
        // pernah beneran pakai paket lama yang salah itu). manual_overrides
        // harus timpa FULL ke harga target, bukan blend.
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $wrongPackage = $this->makePackage('SALAH-MIGRASI', 'Paket Salah Migrasi', 46200);
        $target = $this->makePackage('NetP110', 'NetP110', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Smp 3 Sambit', 'cid' => 'G00RQ000024']);
        $service = $this->makeService($customer, $wrongPackage, 46200);
        $invoice = $this->makeInvoice($customer, $service, now()->format('Y-m'), 46200);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain Yang Gak Relevan', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);
        $config = $this->writeConfigFixture([
            'manual_overrides' => ['G00RQ000024' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--apply' => true])
            ->assertSuccessful();

        $invoice->refresh();
        // FULL 110000 — bukan blend proporsional hari lama (46200) + hari
        // baru (110000) seperti yang dihasilkan CustomerPackageService::change().
        $this->assertSame('110000.00', $invoice->total_amount);
        $this->assertSame($target->id, $invoice->internet_package_id);
    }

    public function test_manual_override_cid_tidak_ditemukan_dilewati(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('NetP110', 'NetP110', 110000);
        // Pelanggan lain yang sudah cocok, biar baris Excel-nya TIDAK ikut
        // ke "dilewati" — supaya hitungan "dilewati" isinya murni dari
        // manual_overrides yang CID-nya gak ketemu.
        $sudahCocok = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Sudah Cocok']);
        $this->makeService($sudahCocok, $package, 110000);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Sudah Cocok', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);
        $config = $this->writeConfigFixture([
            'manual_overrides' => ['CID-TIDAK-ADA' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--apply' => true])
            ->expectsOutputToContain('1 dilewati')
            ->assertSuccessful();
    }

    public function test_manual_override_kode_paket_tidak_dikenal_dilewati(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('NetP110', 'NetP110', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Smp 3 Sambit', 'cid' => 'G00RQ000024']);
        $service = $this->makeService($customer, null, 46200);
        $sudahCocok = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Sudah Cocok']);
        $this->makeService($sudahCocok, $package, 110000);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Sudah Cocok', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);
        $config = $this->writeConfigFixture([
            'manual_overrides' => ['G00RQ000024' => 'KodeGakAda'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--apply' => true])
            ->expectsOutputToContain('1 dilewati')
            ->assertSuccessful();

        $this->assertSame('46200.00', $service->refresh()->monthly_price);
    }
}
