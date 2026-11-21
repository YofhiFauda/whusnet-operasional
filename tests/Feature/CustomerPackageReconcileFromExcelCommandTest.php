<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerTerminationReason;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\User;
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

    public function test_cid_jadi_kunci_utama_walau_nama_di_excel_beda_dari_db(): void
    {
        // Kasus Caruban: CID di excel pasti, tapi nama ditulis kolektor beda
        // (typo/alias) dari full_name di DB. CID harus tetap match duluan,
        // bukan ikut ke jalur ambigu/"tidak ditemukan" nama.
        $pop = Pop::factory()->create(['name' => 'Caruban']);
        $package = $this->makePackage('NetP110', '110K Up to 5M', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Sulastri', 'cid' => 'I1X13ARQ000001']);
        $service = $this->makeService($customer, $package, 90357);

        $file = $this->writeExcelFixture([
            ['CID' => 'I1X13ARQ000001', 'Nama' => 'sulastri (nama beda tulis kolektor)', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->expectsOutputToContain('1 diubah')
            ->assertSuccessful();

        $this->assertSame('110000.00', $service->refresh()->monthly_price);
    }

    public function test_cid_tidak_ketemu_fallback_ke_nama(): void
    {
        $pop = Pop::factory()->create(['name' => 'Caruban']);
        $package = $this->makePackage('NetP110', '110K Up to 5M', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Agus Setiawan', 'cid' => 'I1X13ARQ000002']);
        $service = $this->makeService($customer, $package, 90357);

        $file = $this->writeExcelFixture([
            ['CID' => 'CID-SALAH-KETIK', 'Nama' => 'Agus Setiawan', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->expectsOutputToContain('1 diubah')
            ->assertSuccessful();

        $this->assertSame('110000.00', $service->refresh()->monthly_price);
    }

    public function test_cid_fallback_suffix_dipakai_kalau_prefix_beda_tapi_nomor_urut_sama(): void
    {
        // Kasus Siman: CID Excel pakai skema prefix LAMA (sebelum CID
        // regenerate ikut POP/Mini POP/Distribusi sekarang), nomor urut
        // "RQ+NNNNNN" di buntutnya tetap sama persis dengan CID sistem
        // sekarang yang prefix-nya beda total.
        $pop = Pop::factory()->create(['name' => 'Siman']);
        $package = $this->makePackage('NetP110', 'NetP110', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Tika Martianasari', 'cid' => 'D1X6BRQ000114']);
        $service = $this->makeService($customer, $package, 90357);

        $file = $this->writeExcelFixture([
            ['CID' => 'D00RQ000114', 'Nama' => 'Tika Martianasari', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->expectsOutputToContain('1 diubah')
            ->assertSuccessful();

        $this->assertSame('110000.00', $service->refresh()->monthly_price);
    }

    public function test_cid_fallback_suffix_ketemu_tapi_nama_gak_nyambung_ditolak(): void
    {
        // Suffix "RQ000027" kebetulan sama, tapi pemiliknya beneran beda
        // orang (CID Excel nyasar total, bukan cuma beda prefix skema) —
        // sanity check nama tetap wajib menolak, seperti CID exact-match.
        $pop = Pop::factory()->create(['name' => 'Siman']);
        $package = $this->makePackage('NetP110', 'NetP110', 115000);
        $korbanSalahCid = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Miko Aprilianto', 'cid' => 'D00RQ000027']);
        $service = $this->makeService($korbanSalahCid, $package, 77903);

        $file = $this->writeExcelFixture([
            ['CID' => 'D20RQ000027', 'Nama' => 'Farid Wajdi Ardjono', 'Paket' => 115000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->expectsOutputToContain('1 dilewati')
            ->assertSuccessful();

        $this->assertSame('77903.00', $service->refresh()->monthly_price);
        $this->assertSame($package->id, $service->internet_package_id);
    }

    public function test_cid_fallback_suffix_ambigu_tidak_ditebak(): void
    {
        // Suffix sama jatuh ke 2 pelanggan berbeda (nomor urut sama,
        // kebetulan ada di 2 Cabang/Mini POP beda prefix) — jangan ditebak
        // salah satu, biarkan gagal dan jatuh ke fallback nama di bawah.
        $pop = Pop::factory()->create(['name' => 'Siman']);
        $package = $this->makePackage('NetP110', 'NetP110', 110000);
        Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Kandidat Satu', 'cid' => 'D00RQ000500']);
        Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Kandidat Dua', 'cid' => 'D1X6BRQ000500']);

        $file = $this->writeExcelFixture([
            ['CID' => 'D20RQ000500', 'Nama' => 'Nama Yang Gak Ketemu', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->expectsOutputToContain('1 dilewati')
            ->assertSuccessful();
    }

    public function test_cid_ketemu_tapi_nama_gak_nyambung_ditolak_tidak_menimpa_pelanggan_lain(): void
    {
        // Regresi temuan nyata cabang Caruban: CID di Excel kolektor "nyasar"
        // (off-by-satu/beda segmen distribusi) ke pelanggan LAIN yang sama
        // sekali gak nyambung namanya ("suyanto peting" di Excel punya CID
        // yang di DB justru kepunyaan "Sudji Pendot"). Command TIDAK BOLEH
        // percaya CID buta — kalau nama gak nyambung sama sekali, harus
        // ditolak (dilewati), bukan nimpa harga/paket pelanggan yang salah.
        $pop = Pop::factory()->create(['name' => 'Caruban']);
        $package = $this->makePackage('NetP110', 'NetP110', 115000);
        $korbanSalahCid = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Sudji Pendot', 'cid' => 'I1X13ARQ000360']);
        $service = $this->makeService($korbanSalahCid, $package, 77903);

        $file = $this->writeExcelFixture([
            ['CID' => 'I1X13ARQ000360', 'Nama' => 'suyanto peting', 'Paket' => 115000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        // Harga/paket Sudji Pendot (korban CID nyasar) TIDAK boleh berubah
        // sama sekali — kalau ini gagal, berarti safety check-nya bolong dan
        // CID yang salah berhasil nimpa data pelanggan yang gak terkait.
        $this->assertSame('77903.00', $service->refresh()->monthly_price);
        $this->assertSame($package->id, $service->internet_package_id);
    }

    public function test_cid_tetap_dipercaya_walau_nama_beda_gaya_tulis_bukan_beda_orang(): void
    {
        // Regresi temuan nyata: "warung-hari-martono" (Excel, pakai strip)
        // vs "Warung Hari Martono" (DB, pakai spasi) itu PELANGGAN YANG
        // SAMA — beda gaya tulis kolektor doang, bukan CID nyasar. Safety
        // check CID jangan sampai menolak kasus beda format kayak ini.
        $pop = Pop::factory()->create(['name' => 'Caruban']);
        $package = $this->makePackage('NetP110', 'NetP110', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Warung Hari Martono', 'cid' => 'I1X13ARQ000376']);
        $service = $this->makeService($customer, $package, 90000);

        $file = $this->writeExcelFixture([
            ['CID' => 'I1X13ARQ000376', 'Nama' => 'warung-hari-martono', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame('110000.00', $service->refresh()->monthly_price);
    }

    public function test_name_alias_meloloskan_cid_yang_namanya_typo_parah(): void
    {
        // Regresi temuan nyata: "rizka ana" (Excel, typo/singkatan parah)
        // vs "Rizka Fitri Ana" (DB) — CID-nya BENAR pasangannya, tapi typo
        // separah ini gak lolos namaCocokLonggar() yang cuma toleransi beda
        // format/spasi. Reviewer manual yang mastiin lewat name_aliases.
        $pop = Pop::factory()->create(['name' => 'Caruban']);
        $package = $this->makePackage('NetP110', 'NetP110', 115000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Rizka Fitri Ana', 'cid' => 'I1X13ARQ000033']);
        $service = $this->makeService($customer, $package, 90000);

        $file = $this->writeExcelFixture([
            ['CID' => 'I1X13ARQ000033', 'Nama' => 'rizka ana', 'Paket' => 115000, 'paket ' => 'NetP110'],
        ]);
        $config = $this->writeConfigFixture([
            'name_aliases' => ['rizka ana' => 'Rizka Fitri Ana'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame('115000.00', $service->refresh()->monthly_price);
    }

    public function test_header_harga_dan_nama_paket_dipakai_kalau_ada(): void
    {
        // Regresi temuan nyata: cabang Sambit ganti header filenya jadi
        // "Harga"/"Nama Paket" (ikutin template Caruban) — kolom "Paket"
        // lama malah dipakai buat TEKS speed ("Up to 25 Mbps"), bukan
        // nominal lagi. Command harus baca "Harga", BUKAN "Paket", kalau
        // "Harga" ada — jangan diam-diam jatuh balik ke "Paket" yang
        // isinya udah bukan harga.
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('NetP110', 'NetP110', 111000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Veri Toko']);
        $service = $this->makeService($customer, $package, 90000);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Veri Toko', 'Paket' => 'Up to 25 Mbps', 'Harga' => 111000, 'Nama Paket' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame('111000.00', $service->refresh()->monthly_price);
    }

    public function test_fallback_kolom_paket_dipakai_kalau_nama_paket_isinya_teks_speed(): void
    {
        // Regresi temuan nyata cabang Siman: header `Nama Paket`/`Paket` di
        // file ini TERBALIK dari konvensi Caruban/Sambit — `Nama Paket`
        // isinya teks speed ("Up to 25 Mbps "), kode katalog asli justru di
        // `Paket` ("NetP110"). Command harus coba `Paket` sebagai kode
        // ALTERNATIF begitu `Nama Paket` gak dikenal katalog.
        $pop = Pop::factory()->create(['name' => 'Siman']);
        $defaultPackage = $this->makePackage('PK000001', 'default', 0);
        $realPackage = $this->makePackage('NetP110', '110K Up to 5M', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Nilam Juliana']);
        $service = $this->makeService($customer, $defaultPackage, 0);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nilam Juliana', 'Harga' => 110000, 'Nama Paket' => 'Up to 25 Mbps ', 'Paket' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->expectsOutputToContain('1 diubah')
            ->assertSuccessful();

        $service->refresh();
        $this->assertSame('110000.00', $service->monthly_price);
        $this->assertSame($realPackage->id, $service->internet_package_id);
    }

    public function test_fallback_kolom_paket_tidak_dipakai_kalau_harga_tidak_ada(): void
    {
        // Jangan sampai fallback ini kebawa ke file lama (Sambit) yang
        // kolom `Paket`-nya MEMANG dipakai buat nominal (tanpa `Harga`) —
        // fallback cuma aman kalau `Harga` ADA, supaya `Paket` di situ pasti
        // bukan field nominal.
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('NetP110', 'NetP110', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Imam Choirul Anam']);
        $service = $this->makeService($customer, $package, 90357);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Imam Choirul Anam', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame('110000.00', $service->refresh()->monthly_price);
    }

    public function test_cid_kosong_tetap_pakai_nama_seperti_sebelumnya(): void
    {
        // Backward-compat: Excel kolektor lama (Sambit) gak punya kolom CID
        // sama sekali — fixture ini sengaja gak kirim key 'CID'.
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('NetP110', '110K Up to 5M', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Imam Choirul Anam']);
        $service = $this->makeService($customer, $package, 90357);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Imam Choirul Anam', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->expectsOutputToContain('1 diubah')
            ->assertSuccessful();

        $this->assertSame('110000.00', $service->refresh()->monthly_price);
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

    /**
     * `--terminate-missing` (opt-in, default OFF) — pelanggan di Pop yang
     * sama tapi TIDAK ada baris Excel-nya dianggap sudah putus langganan
     * (Excel = sumber kebenaran terbaru). Status "modem belum diambil"
     * muncul otomatis lewat jalur terminate resmi, tanpa command kedua.
     */
    public function test_terminate_missing_memutus_pelanggan_yang_tidak_ada_di_excel(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('NetP110', '110K Up to 5M', 110000);
        $diExcel = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Imam Choirul Anam', 'status' => 'active']);
        $this->makeService($diExcel, $package, 90357);
        $tidakDiExcel = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Pelanggan Lama', 'status' => 'active']);
        $this->makeService($tidakDiExcel, $package, 90357);
        $actor = User::factory()->create();

        $file = $this->writeExcelFixture([
            ['Nama' => 'Imam Choirul Anam', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--terminate-missing' => true, '--actor-id' => $actor->id, '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('active', $diExcel->refresh()->status);
        $this->assertSame('terminated', $tidakDiExcel->refresh()->status);

        $reason = CustomerTerminationReason::where('name', 'Rekonsiliasi Data Excel')->first();
        $this->assertNotNull($reason);
        $this->assertSame($reason->id, $tidakDiExcel->termination_reason_id);
    }

    public function test_terminate_missing_tidak_memutus_nama_ambigu_atau_skip_names(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $package = $this->makePackage('Net150', 'Net150', 150000);
        $ambigu1 = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Nyamin', 'status' => 'active']);
        $ambigu2 = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Nyamin', 'status' => 'active']);
        $this->makeService($ambigu1, $package, 0);
        $this->makeService($ambigu2, $package, 0);
        $diskip = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Smp 3 Sambit', 'status' => 'active']);
        $this->makeService($diskip, null, 0);
        $actor = User::factory()->create();

        $config = $this->writeConfigFixture([
            'skip_names' => ['Smp 3 Sambit'],
        ]);
        $file = $this->writeExcelFixture([
            ['Nama' => 'Nyamin', 'Paket' => 150000, 'paket ' => 'Net150'],
            ['Nama' => 'Smp 3 Sambit', 'Paket' => 999999, 'paket ' => '999M'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--terminate-missing' => true, '--actor-id' => $actor->id, '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('active', $ambigu1->refresh()->status);
        $this->assertSame('active', $ambigu2->refresh()->status);
        $this->assertSame('active', $diskip->refresh()->status);
    }

    public function test_terminate_missing_tidak_memutus_pelanggan_manual_override(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $target = $this->makePackage('NetP110', 'NetP110', 110000);
        $overrideCustomer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Smp 3 Sambit', 'cid' => 'G00RQ000024', 'status' => 'active']);
        $this->makeService($overrideCustomer, null, 46200);
        $actor = User::factory()->create();

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain Yang Gak Relevan', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);
        $config = $this->writeConfigFixture([
            'manual_overrides' => ['G00RQ000024' => 'NetP110'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--terminate-missing' => true, '--actor-id' => $actor->id, '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('active', $overrideCustomer->refresh()->status);
    }

    public function test_terminate_missing_tidak_menyentuh_pelanggan_pop_lain(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $popLain = Pop::factory()->create(['name' => 'Jetis']);
        $diPopLain = Customer::factory()->create(['pop_id' => $popLain->id, 'full_name' => 'Pelanggan Pop Lain', 'status' => 'active']);
        $actor = User::factory()->create();

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--terminate-missing' => true, '--actor-id' => $actor->id, '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('active', $diPopLain->refresh()->status);
    }

    public function test_terminate_missing_dry_run_tidak_menulis_db(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $tidakDiExcel = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Pelanggan Lama', 'status' => 'active']);
        $actor = User::factory()->create();

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--terminate-missing' => true, '--actor-id' => $actor->id,
        ])->assertSuccessful();

        $this->assertSame('active', $tidakDiExcel->refresh()->status);
        $this->assertNull(CustomerTerminationReason::where('name', 'Rekonsiliasi Data Excel')->first());
    }

    public function test_terminate_missing_tanpa_actor_id_gagal(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $tidakDiExcel = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Pelanggan Lama', 'status' => 'active']);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--terminate-missing' => true, '--apply' => true,
        ])->assertFailed();

        $this->assertSame('active', $tidakDiExcel->refresh()->status);
    }

    public function test_terminate_missing_actor_id_tidak_valid_gagal(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $tidakDiExcel = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Pelanggan Lama', 'status' => 'active']);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--terminate-missing' => true, '--actor-id' => 999999, '--apply' => true,
        ])->assertFailed();

        $this->assertSame('active', $tidakDiExcel->refresh()->status);
    }

    public function test_tanpa_flag_terminate_missing_pelanggan_luar_excel_tidak_berubah(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $tidakDiExcel = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Pelanggan Lama', 'status' => 'active']);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', ['file' => $file, '--pop-id' => $pop->id, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame('active', $tidakDiExcel->refresh()->status);
    }

    /**
     * `manual_terminate_cids` (kasus Siman) — kebalikan `--terminate-missing`:
     * pelanggan DIPUTUS walau baris Excel-nya ADA, karena admin tahu dari
     * lapangan dia sudah berhenti (rekap kolektor basi, belum dicoret).
     */
    public function test_manual_terminate_cids_memutus_pelanggan_walau_ada_di_excel(): void
    {
        $pop = Pop::factory()->create(['name' => 'Siman']);
        $package = $this->makePackage('NetP110', 'NetP110', 110000);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Yoyok Priyo Handoko', 'cid' => 'D1X6ARQ000997', 'status' => 'active']);
        $this->makeService($customer, $package, 110000);
        $actor = User::factory()->create();

        $file = $this->writeExcelFixture([
            ['CID' => 'D1X6ARQ000997', 'Nama' => 'Yoyok Priyo Handoko', 'Paket' => 110000, 'paket ' => 'NetP110'],
        ]);
        $config = $this->writeConfigFixture([
            'manual_terminate_cids' => ['D1X6ARQ000997'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--actor-id' => $actor->id, '--apply' => true,
        ])->expectsOutputToContain('1 diputus langganan')
            ->assertSuccessful();

        $this->assertSame('terminated', $customer->refresh()->status);
        $reason = CustomerTerminationReason::where('name', 'Rekonsiliasi Data Excel')->first();
        $this->assertNotNull($reason);
        $this->assertSame($reason->id, $customer->termination_reason_id);
    }

    public function test_manual_terminate_cids_dry_run_tidak_menulis_db(): void
    {
        $pop = Pop::factory()->create(['name' => 'Siman']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Yoyok Priyo Handoko', 'cid' => 'D1X6ARQ000997', 'status' => 'active']);
        $this->makeService($customer, null, 110000);
        $actor = User::factory()->create();

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);
        $config = $this->writeConfigFixture([
            'manual_terminate_cids' => ['D1X6ARQ000997'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--actor-id' => $actor->id,
        ])->expectsOutputToContain('1 diputus langganan')
            ->assertSuccessful();

        $this->assertSame('active', $customer->refresh()->status);
        $this->assertNull(CustomerTerminationReason::where('name', 'Rekonsiliasi Data Excel')->first());
    }

    public function test_manual_terminate_cids_sudah_terminated_tidak_diulang(): void
    {
        $pop = Pop::factory()->create(['name' => 'Siman']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Sudah Putus', 'cid' => 'D1X6ARQ000998', 'status' => 'terminated']);
        $this->makeService($customer, null, 0);
        $actor = User::factory()->create();

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);
        $config = $this->writeConfigFixture([
            'manual_terminate_cids' => ['D1X6ARQ000998'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--actor-id' => $actor->id, '--apply' => true,
        ])->expectsOutputToContain('0 diputus langganan')
            ->assertSuccessful();

        $this->assertSame('terminated', $customer->refresh()->status);
    }

    public function test_manual_terminate_cids_cid_tidak_ditemukan_dilewati(): void
    {
        $pop = Pop::factory()->create(['name' => 'Siman']);
        $actor = User::factory()->create();

        $file = $this->writeExcelFixture([]);
        $config = $this->writeConfigFixture([
            'manual_terminate_cids' => ['CID-TIDAK-ADA'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--actor-id' => $actor->id, '--apply' => true,
        ])->expectsOutputToContain('1 dilewati')
            ->assertSuccessful();
    }

    public function test_manual_terminate_cids_tanpa_actor_id_gagal(): void
    {
        $pop = Pop::factory()->create(['name' => 'Siman']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Yoyok Priyo Handoko', 'cid' => 'D1X6ARQ000997', 'status' => 'active']);
        $this->makeService($customer, null, 110000);

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);
        $config = $this->writeConfigFixture([
            'manual_terminate_cids' => ['D1X6ARQ000997'],
        ]);

        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--config' => $config, '--apply' => true,
        ])->assertFailed();

        $this->assertSame('active', $customer->refresh()->status);
    }

    public function test_terminate_missing_reason_tidak_duplikat_kalau_jalan_dua_kali(): void
    {
        $pop = Pop::factory()->create(['name' => 'Sambit']);
        $actor = User::factory()->create();

        $file = $this->writeExcelFixture([
            ['Nama' => 'Nama Lain', 'Paket' => 50000, 'paket ' => 'TidakAda'],
        ]);

        $customer1 = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Pelanggan Satu', 'status' => 'active']);
        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--terminate-missing' => true, '--actor-id' => $actor->id, '--apply' => true,
        ])->assertSuccessful();

        $customer2 = Customer::factory()->create(['pop_id' => $pop->id, 'full_name' => 'Pelanggan Dua', 'status' => 'active']);
        $this->artisan('app:customer-package-reconcile-excel', [
            'file' => $file, '--pop-id' => $pop->id, '--terminate-missing' => true, '--actor-id' => $actor->id, '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('terminated', $customer1->refresh()->status);
        $this->assertSame('terminated', $customer2->refresh()->status);
        $this->assertSame(1, CustomerTerminationReason::where('name', 'Rekonsiliasi Data Excel')->count());
    }
}
