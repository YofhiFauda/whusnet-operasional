<?php

namespace Tests\Feature;

use App\Models\CustomerDevice;
use App\Models\CustomerTechnicalDetail;
use App\Services\CustomerDeviceProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPindahPopScenario;
use Tests\TestCase;

/**
 * Gejala (laporan user 2026-09-29): Siti Juariyah punya SN `ZTEGC7DD8857` di
 * Detail Pelanggan, tapi field SN di Edit Pelanggan kosong. SN-nya cuma ada di
 * kolom lama (`customers.ont_sn`, `customer_technical_details.
 * router_or_ont_serial`) — Detail membaca urutan cadangan, Edit dulu cuma
 * membaca `customer_devices` (1.681 pelanggan dev terdampak).
 *
 * Keputusan user (opsi A): Edit diisi nilai yang sama dengan Detail, dan saat
 * disimpan nilainya masuk ke `customer_devices`. Satu sumber urutan cadangan:
 * CustomerDeviceProfileService.
 */
class EditPelangganMenampilkanDataPerangkatLamaTest extends TestCase
{
    use BuildsPindahPopScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPindahPop();
    }

    #[Test]
    public function sn_dari_kolom_lama_tampil_di_edit_seperti_di_detail(): void
    {
        $this->loginAsAdmin();
        $siti = $this->pelangganAktifDiJetis(['ont_sn' => 'ZTEGC7DD8857']);
        CustomerTechnicalDetail::create([
            'customer_id' => $siti->id,
            'router_or_ont_serial' => 'ZTEGC7DD8857',
            'connection_type' => 'KABEL',
            'odp_number' => 'Odp 8 jtwn',
            'odp_port' => '1',
        ]);

        $this->get(route('customers.show', $siti->id))->assertOk()->assertSee('ZTEGC7DD8857');

        $this->get(route('customers.edit', $siti->id))
            ->assertOk()
            ->assertSee('value="ZTEGC7DD8857"', false)
            ->assertSee('value="Odp 8 jtwn"', false)
            // Jenis perangkat diturunkan dari tipe koneksi KABEL → ONT, sama dengan Detail.
            ->assertSee('<option value="ont" selected', false);
    }

    #[Test]
    public function simpan_edit_memindahkan_data_lama_ke_tabel_perangkat(): void
    {
        $this->loginAsAdmin();
        $siti = $this->pelangganAktifDiJetis(['ont_sn' => 'ZTEGC7DD8857']);
        CustomerTechnicalDetail::create(['customer_id' => $siti->id, 'router_or_ont_serial' => 'ZTEGC7DD8857', 'connection_type' => 'KABEL']);

        $prefill = CustomerDeviceProfileService::editPrefill($siti->fresh())['values'];
        $this->put(route('customers.update', $siti->id), $this->payloadEdit($siti, array_merge(
            ['primary_phone' => '084444444444'],
            array_filter($prefill, fn ($v) => $v !== null),
        )))->assertSessionHasNoErrors();

        $device = CustomerDevice::where('customer_id', $siti->id)->first();
        $this->assertNotNull($device);
        $this->assertSame('ZTEGC7DD8857', $device->serial_number);
        $this->assertSame('ont', $device->device_type);
        // Kolom lama tidak dihapus.
        $this->assertSame('ZTEGC7DD8857', $siti->fresh()->ont_sn);
    }

    #[Test]
    public function mac_format_lama_dirapikan_dan_nilai_tidak_valid_jadi_keterangan(): void
    {
        $customer = $this->pelangganAktifDiJetis();
        CustomerTechnicalDetail::create([
            'customer_id' => $customer->id,
            'router_mac' => 'aa-bb-cc-dd-ee-ff',
            'passive_device' => str_repeat('X', 120),
        ]);

        $prefill = CustomerDeviceProfileService::editPrefill($customer->fresh());

        $this->assertSame('AA:BB:CC:DD:EE:FF', $prefill['values']['mac_address']);
        // Teks lebih panjang dari batas field Merk tidak diisi (kalau diisi,
        // simpan Edit tertolak validasi di field yang tidak disentuh admin).
        $this->assertNull($prefill['values']['brand']);
        $this->assertSame(str_repeat('X', 120), $prefill['hints']['brand']);

        CustomerTechnicalDetail::where('customer_id', $customer->id)->update(['router_mac' => 'bukan-mac']);
        $prefill = CustomerDeviceProfileService::editPrefill($customer->fresh());
        $this->assertNull($prefill['values']['mac_address']);
        $this->assertSame('bukan-mac', $prefill['hints']['mac_address']);
    }

    #[Test]
    public function edit_dengan_nilai_prefill_tidak_tertolak_validasi(): void
    {
        $this->loginAsAdmin();
        $customer = $this->pelangganAktifDiJetis();
        CustomerTechnicalDetail::create([
            'customer_id' => $customer->id,
            'router_or_ont_serial' => 'SN-TANPA-JENIS',
            'router_mac' => 'aabbccddeeff',
            'passive_device' => str_repeat('Y', 150),
        ]);

        $prefill = CustomerDeviceProfileService::editPrefill($customer->fresh())['values'];
        // SN ada tapi jenis tidak bisa diturunkan → "Lainnya", bukan kosong.
        $this->assertSame('other', $prefill['device_type']);

        $this->put(route('customers.update', $customer->id), $this->payloadEdit($customer, array_merge(
            ['primary_phone' => '083333333333'],
            array_map(fn ($v) => $v ?? '', $prefill),
        )))->assertSessionHasNoErrors();

        $device = CustomerDevice::where('customer_id', $customer->id)->first();
        $this->assertSame('other', $device->device_type);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $device->mac_address);
    }

    #[Test]
    public function memilih_jenis_belum_diisi_tidak_menghapus_jenis_perangkat_yang_ada(): void
    {
        $this->loginAsAdmin();
        $customer = $this->pelangganAktifDiJetis();
        CustomerDevice::create(['customer_id' => $customer->id, 'device_type' => 'router', 'serial_number' => 'SN-1']);

        // Dulu: device_type NULL ditulis ke kolom NOT NULL → error 500.
        $this->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
            'device_type' => '',
            'serial_number' => 'SN-1',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('router', CustomerDevice::where('customer_id', $customer->id)->value('device_type'));
    }
}
