<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use App\Models\CustomerDevice;
use App\Models\CustomerTechnicalDetail;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Validasi SN perangkat di Edit Pelanggan + sinkron tiga kolom SN
 * (customer_devices.serial_number, customer_technical_details.router_or_ont_serial,
 * customers.ont_sn). Rancangan: docs/superpowers/specs/2026-10-31-validasi-sn-perangkat-pelanggan-design.md
 */
class CustomerEditSerialNumberTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $this->pop = Pop::create([
            'code' => 'SNT1',
            'pop_code' => 'SNT1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP SN Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeCustomer(string $name = 'Pelanggan Uji'): Customer
    {
        $this->counter++;

        return Customer::create([
            'customer_code' => sprintf('C-SNT-%06d', $this->counter),
            'full_name' => $name,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $this->pop->id,
            'status' => 'registered',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Customer $customer, array $extra = []): array
    {
        return array_merge([
            'full_name' => $customer->full_name,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-15',
            'pop_id' => $this->pop->id,
            'status' => 'registered',
        ], $extra);
    }

    private function makeSerial(string $sn, SerialStatus $status, ?Customer $customer = null): InventorySerial
    {
        $category = ItemCategory::firstOrCreate(
            ['code' => 'MODEM-SNT'],
            ['name' => 'Modem SN Test', 'default_unit' => 'pcs'],
        );
        $item = Item::firstOrCreate(
            ['code' => 'MODEM-SNT'],
            ['name' => 'Modem SN Test', 'item_category_id' => $category->id, 'unit' => 'pcs', 'tracking_type' => 'serialized'],
        );

        return InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => $sn,
            'status' => $status,
            'customer_id' => $customer?->id,
            'installed_at' => $status === SerialStatus::INSTALLED ? now() : null,
        ]);
    }

    #[Test]
    public function sn_baru_yang_unik_tersimpan_dan_menyinkronkan_tiga_kolom(): void
    {
        $customer = $this->makeCustomer();
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'router_or_ont_serial' => 'LAMA-111']);
        $customer->update(['ont_sn' => 'LAMA-111']);

        $response = $this->put("/customers/{$customer->id}", $this->payload($customer, [
            'device_type' => 'ont',
            'serial_number' => 'ZTEGC0000001',
        ]));

        $response->assertRedirect("/customers/{$customer->id}");
        $this->assertDatabaseHas('customer_devices', ['customer_id' => $customer->id, 'serial_number' => 'ZTEGC0000001']);
        $this->assertDatabaseHas('customer_technical_details', ['customer_id' => $customer->id, 'router_or_ont_serial' => 'ZTEGC0000001']);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'ont_sn' => 'ZTEGC0000001']);
    }

    #[Test]
    public function sn_yang_dipakai_pelanggan_lain_ditolak(): void
    {
        $lain = $this->makeCustomer('Pemilik Lama');
        CustomerDevice::create(['customer_id' => $lain->id, 'device_type' => 'ont', 'serial_number' => 'ZTEGC0000002']);
        $customer = $this->makeCustomer();

        $response = $this->from("/customers/{$customer->id}/edit")
            ->put("/customers/{$customer->id}", $this->payload($customer, ['serial_number' => 'ZTEGC0000002']));

        $response->assertRedirect("/customers/{$customer->id}/edit");
        $response->assertSessionHasErrors('serial_number');
        $this->assertStringContainsString('Pemilik Lama', session('errors')->first('serial_number'));
        $this->assertDatabaseMissing('customer_devices', ['customer_id' => $customer->id]);
    }

    #[Test]
    public function perbandingan_sn_tidak_peka_huruf_besar_kecil_dan_spasi(): void
    {
        $lain = $this->makeCustomer('Pemilik Lama');
        CustomerDevice::create(['customer_id' => $lain->id, 'device_type' => 'ont', 'serial_number' => 'ZTEGC0000003']);
        $customer = $this->makeCustomer();

        $this->put("/customers/{$customer->id}", $this->payload($customer, ['serial_number' => '  ztegc0000003 ']))
            ->assertSessionHasErrors('serial_number');
    }

    #[Test]
    public function sn_terpasang_di_pelanggan_lain_lewat_gudang_ditolak(): void
    {
        $lain = $this->makeCustomer('Pemegang Gudang');
        $this->makeSerial('GDG-INST-001', SerialStatus::INSTALLED, $lain);
        $customer = $this->makeCustomer();

        $this->put("/customers/{$customer->id}", $this->payload($customer, ['serial_number' => 'GDG-INST-001']))
            ->assertSessionHasErrors('serial_number');
        $this->assertSame(SerialStatus::INSTALLED, InventorySerial::where('serial_number', 'GDG-INST-001')->first()->status);
    }

    #[Test]
    public function sn_perangkat_tambahan_pelanggan_lain_ditolak_sebagai_modem_utama(): void
    {
        $lain = $this->makeCustomer('Pemilik AP');
        CustomerAdditionalDevice::create(['customer_id' => $lain->id, 'device_type' => 'AP', 'serial_number' => 'AP-SN-0001']);
        $customer = $this->makeCustomer();

        $this->put("/customers/{$customer->id}", $this->payload($customer, ['serial_number' => 'ap-sn-0001']))
            ->assertSessionHasErrors('serial_number');
        $this->assertStringContainsString('perangkat tambahan', session('errors')->first('serial_number'));
        $this->assertDatabaseMissing('customer_devices', ['customer_id' => $customer->id]);
    }

    #[Test]
    public function sn_perangkat_tambahan_milik_sendiri_ditolak_sebagai_modem_utama(): void
    {
        $customer = $this->makeCustomer();
        CustomerAdditionalDevice::create(['customer_id' => $customer->id, 'device_type' => 'AP', 'serial_number' => 'AP-SN-0002']);

        $this->put("/customers/{$customer->id}", $this->payload($customer, ['serial_number' => 'AP-SN-0002']))
            ->assertSessionHasErrors('serial_number');
        $this->assertStringContainsString('milik pelanggan ini', session('errors')->first('serial_number'));
    }

    #[Test]
    public function sn_ap_gudang_terpasang_tanpa_baris_perangkat_tambahan_tetap_ditolak(): void
    {
        $customer = $this->makeCustomer();
        $category = ItemCategory::create(['code' => 'AP-SNT', 'name' => 'AP SN Test', 'default_unit' => 'pcs', 'is_customer_extra_device' => true]);
        $item = Item::create(['code' => 'AP-SNT', 'name' => 'AP SN Test', 'item_category_id' => $category->id, 'unit' => 'pcs', 'tracking_type' => 'serialized']);
        InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => 'AP-GDG-0003',
            'status' => SerialStatus::INSTALLED,
            'customer_id' => $customer->id,
            'installed_at' => now(),
        ]);

        $this->put("/customers/{$customer->id}", $this->payload($customer, ['serial_number' => 'AP-GDG-0003']))
            ->assertSessionHasErrors('serial_number');
    }

    #[Test]
    public function sn_tersedia_di_gudang_lolos_dengan_peringatan_dan_stok_tidak_berubah(): void
    {
        $serial = $this->makeSerial('GDG-AVAIL-001', SerialStatus::AVAILABLE);
        $customer = $this->makeCustomer();

        $response = $this->put("/customers/{$customer->id}", $this->payload($customer, ['serial_number' => 'GDG-AVAIL-001']));

        $response->assertRedirect("/customers/{$customer->id}");
        $response->assertSessionHasNoErrors();
        $this->assertStringContainsString('tercatat di Gudang', session('warning'));
        $this->assertDatabaseHas('customer_devices', ['customer_id' => $customer->id, 'serial_number' => 'GDG-AVAIL-001']);
        $serial->refresh();
        $this->assertSame(SerialStatus::AVAILABLE, $serial->status);
        $this->assertNull($serial->customer_id);
    }

    #[Test]
    public function pelanggan_dengan_perangkat_utama_gudang_tidak_boleh_ganti_sn_lewat_edit(): void
    {
        $customer = $this->makeCustomer();
        $this->makeSerial('GDG-UTAMA-001', SerialStatus::INSTALLED, $customer);

        $this->put("/customers/{$customer->id}", $this->payload($customer, ['serial_number' => 'SN-LAIN-999']))
            ->assertSessionHasErrors('serial_number');
        $this->assertDatabaseMissing('customer_devices', ['serial_number' => 'SN-LAIN-999']);
    }

    #[Test]
    public function simpan_ulang_form_dengan_sn_perangkat_gudang_yang_sama_lolos(): void
    {
        $customer = $this->makeCustomer();
        $this->makeSerial('GDG-UTAMA-002', SerialStatus::INSTALLED, $customer);

        $this->put("/customers/{$customer->id}", $this->payload($customer, ['serial_number' => 'GDG-UTAMA-002']))
            ->assertSessionHasNoErrors()
            ->assertRedirect("/customers/{$customer->id}");
    }

    #[Test]
    public function edit_data_lain_tidak_terblokir_duplikat_sn_legacy_yang_tidak_diubah(): void
    {
        $lain = $this->makeCustomer('Duplikat Legacy A');
        CustomerDevice::create(['customer_id' => $lain->id, 'device_type' => 'ont', 'serial_number' => 'DUP-LEGACY-1']);
        $customer = $this->makeCustomer('Duplikat Legacy B');
        CustomerDevice::create(['customer_id' => $customer->id, 'device_type' => 'ont', 'serial_number' => 'DUP-LEGACY-1']);

        $this->put("/customers/{$customer->id}", $this->payload($customer, [
            'full_name' => 'Duplikat Legacy B Revisi',
            'serial_number' => 'DUP-LEGACY-1',
        ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect("/customers/{$customer->id}");
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'full_name' => 'Duplikat Legacy B Revisi']);
    }

    #[Test]
    public function sn_dikosongkan_mengosongkan_tiga_kolom(): void
    {
        $customer = $this->makeCustomer();
        CustomerDevice::create(['customer_id' => $customer->id, 'device_type' => 'ont', 'serial_number' => 'HAPUS-001']);
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'router_or_ont_serial' => 'HAPUS-001']);
        $customer->update(['ont_sn' => 'HAPUS-001']);

        $this->put("/customers/{$customer->id}", $this->payload($customer, ['serial_number' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(CustomerDevice::where('customer_id', $customer->id)->value('serial_number'));
        $this->assertNull(CustomerTechnicalDetail::where('customer_id', $customer->id)->value('router_or_ont_serial'));
        $this->assertNull(Customer::find($customer->id)->ont_sn);
    }

    #[Test]
    public function field_sn_tidak_dikirim_tidak_menyentuh_tiga_kolom(): void
    {
        $customer = $this->makeCustomer();
        CustomerDevice::create(['customer_id' => $customer->id, 'device_type' => 'ont', 'serial_number' => 'TETAP-001']);
        CustomerTechnicalDetail::create(['customer_id' => $customer->id, 'router_or_ont_serial' => 'TETAP-001']);
        $customer->update(['ont_sn' => 'TETAP-001']);

        $this->put("/customers/{$customer->id}", $this->payload($customer))->assertSessionHasNoErrors();

        $this->assertSame('TETAP-001', CustomerDevice::where('customer_id', $customer->id)->value('serial_number'));
        $this->assertSame('TETAP-001', CustomerTechnicalDetail::where('customer_id', $customer->id)->value('router_or_ont_serial'));
        $this->assertSame('TETAP-001', Customer::find($customer->id)->ont_sn);
    }

    #[Test]
    public function baris_detail_teknis_tidak_dibuat_tapi_ont_sn_tetap_tersinkron(): void
    {
        $customer = $this->makeCustomer();

        $this->put("/customers/{$customer->id}", $this->payload($customer, ['serial_number' => 'BARU-TANPA-TEKNIS']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('customer_technical_details', ['customer_id' => $customer->id]);
        $this->assertSame('BARU-TANPA-TEKNIS', Customer::find($customer->id)->ont_sn);
    }
}
