<?php

namespace App\Services;

use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerTechnicalDetail;
use App\Models\InventorySerial;

/**
 * Satu sumber "data perangkat pelanggan yang berlaku" — dipakai tab Perangkat
 * di Detail Pelanggan DAN prefill step 7 Edit Pelanggan.
 *
 * Kenapa perlu: data perangkat tersebar di empat tempat — Inventori Gudang
 * (serial terpasang), `customer_devices` (tabel terstruktur), `customer_
 * technical_details` (laporan pemasangan & hasil migrasi legacy), dan kolom
 * lama di `customers` (`ont_sn`, `vlan_id`, `odp_code`). Detail membaca
 * keempatnya dengan urutan cadangan, tapi Edit dulu cuma membaca
 * `customer_devices` — di DB dev 1.681 pelanggan SN-nya tampil di Detail tapi
 * kosong di Edit (kasus Siti Juariyah, SN `ZTEGC7DD8857` cuma ada di `ont_sn`
 * & `router_or_ont_serial`). Dua salinan urutan cadangan pasti menyimpang
 * lagi, jadi urutannya dipusatkan di sini.
 *
 * Keputusan user 2026-09-29 (opsi A): Edit diisi dengan nilai yang SAMA
 * seperti Detail; begitu disimpan, nilainya masuk ke `customer_devices` /
 * `customer_technical_details` — data legacy pindah ke tabel terstruktur
 * pelan-pelan tiap pelanggan di-edit. Kolom lama TIDAK dihapus. Detail tetap
 * mendahulukan Inventori Gudang, jadi kalau Gudang mengganti SN belakangan,
 * Detail menampilkan SN Gudang.
 */
class CustomerDeviceProfileService
{
    /**
     * Batas panjang field Edit — SAMA dengan rule di CustomerController::
     * update(). Nilai cadangan yang melanggar tidak diisi otomatis (lihat
     * editPrefill()).
     */
    private const MAX_LENGTH = [
        'brand' => 100,
        'model' => 100,
        'serial_number' => 100,
        'wifi_ssid' => 150,
        'odp_number' => 100,
        'odp_port' => 50,
        'vlan' => 20,
    ];

    /**
     * Nilai tampilan tab Perangkat (urutan cadangan persis seperti sebelum
     * dipindah dari customers/tabs/_device.blade.php).
     *
     * @return array{
     *     device: ?CustomerDevice,
     *     tech: ?CustomerTechnicalDetail,
     *     installedInventorySerial: ?InventorySerial,
     *     deviceType: ?string,
     *     brandModel: ?string,
     *     serialNumber: ?string,
     *     macAddress: ?string,
     *     vlanId: ?string,
     *     ssid: ?string,
     *     odpCode: ?string,
     *     odpPort: ?string,
     *     rxPower: ?string,
     *     technicalNote: ?string,
     *     isFallbackOnly: bool,
     *     hasAnyDeviceData: bool
     * }
     */
    public static function resolve(Customer $customer): array
    {
        $device = $customer->customerDevice;
        $tech = $customer->customerTechnicalDetail;
        $installedInventorySerial = InventorySerial::where('customer_id', $customer->id)
            ->where('status', SerialStatus::INSTALLED->value)
            ->with('item')
            ->latest('installed_at')
            ->first();

        // Jenis perangkat: kalau tabel perangkat belum terisi, diturunkan dari
        // tipe koneksi migrasi (wireless → ROUTER, fiber/ont → ONT).
        $deviceType = $device?->device_type ? strtoupper($device->device_type) : null;
        if (! $deviceType) {
            $connType = strtolower((string) $tech?->connection_type);
            if ($connType && (str_contains($connType, 'wireless') || str_contains($connType, 'radio'))) {
                $deviceType = 'ROUTER';
            } elseif ($connType && (str_contains($connType, 'fiber') || str_contains($connType, 'ont') || str_contains($connType, 'onu') || str_contains($connType, 'kabel'))) {
                $deviceType = 'ONT';
            } elseif ($customer->ont_sn || $installedInventorySerial) {
                $deviceType = 'ONT';
            }
        }

        $isFallbackOnly = ! $device && ($tech || $customer->ont_sn);

        return [
            'device' => $device,
            'tech' => $tech,
            'installedInventorySerial' => $installedInventorySerial,
            'deviceType' => $deviceType,
            'brandModel' => ($installedInventorySerial?->item?->name ?? trim(($device?->brand ?? '').' '.($device?->model ?? ''))) ?: ($tech?->passive_device ?: null),
            'serialNumber' => $installedInventorySerial?->serial_number ?: ($device?->serial_number ?: ($tech?->router_or_ont_serial ?: $customer->ont_sn)),
            'macAddress' => $installedInventorySerial?->mac_address ?: ($device?->mac_address ?: ($tech?->router_mac ?: $tech?->antenna_mac)),
            'vlanId' => $device?->vlan_id ?: ($tech?->vlan ?: $customer->vlan_id),
            'ssid' => $device?->wifi_ssid ?: $tech?->ssid,
            'odpCode' => $device?->odp ?: ($tech?->odp_number ?: $customer->odp_code),
            'odpPort' => $device?->odp_port ?: $tech?->odp_port,
            'rxPower' => $device?->signal_rx_power !== null
                ? $device->signal_rx_power.' dBm'
                : (($tech?->fiber_signal ?: $tech?->wireless_signal) ?: null),
            'technicalNote' => $device?->technical_note ?: $tech?->note,
            'isFallbackOnly' => $isFallbackOnly,
            'hasAnyDeviceData' => $device || $isFallbackOnly,
        ];
    }

    /**
     * Nilai awal field step 7 Edit Pelanggan — SAMA dengan yang tampil di
     * Detail (resolve()), dipetakan ke nama & format field form. Field yang
     * cuma ada di satu tabel (PPPoE, password, OLT, router, redaman) dibaca
     * langsung dari tabelnya karena memang tidak punya cadangan.
     *
     * Merk/Model: Detail menampilkan satu teks gabungan. Kalau perangkat belum
     * punya merk/model sendiri, teks cadangan (nama barang Gudang /
     * `passive_device`) dimasukkan ke field Merk, Model dibiarkan kosong.
     *
     * Nilai cadangan yang TIDAK lolos validasi Edit (MAC di luar format
     * AA:BB:CC:DD:EE:FF, teks lebih panjang dari batas field) TIDAK diisi ke
     * field — kalau diisi, admin yang cuma mengganti nomor HP akan tertolak
     * validasi oleh field yang tidak ia sentuh. Nilainya dikembalikan di
     * `hints` supaya tetap terlihat sebagai keterangan "Data lama".
     *
     * @return array{values: array<string, string|null>, hints: array<string, string>}
     */
    public static function editPrefill(Customer $customer): array
    {
        $profile = self::resolve($customer);
        $device = $profile['device'];
        $tech = $profile['tech'];

        $hasOwnBrandModel = filled($device?->brand) || filled($device?->model);

        $values = [
            // Opsi select di form memakai huruf kecil (ont/router/...).
            'device_type' => $profile['deviceType'] ? strtolower($profile['deviceType']) : null,
            'brand' => $hasOwnBrandModel ? $device->brand : $profile['brandModel'],
            'model' => $hasOwnBrandModel ? $device->model : null,
            'serial_number' => $profile['serialNumber'],
            'mac_address' => self::normalizeMac($profile['macAddress']),
            'connection_mode' => $device?->connection_mode,
            'pppoe_username' => $device?->pppoe_username,
            'pppoe_password' => $device?->pppoe_password,
            'wifi_ssid' => $profile['ssid'],
            'wifi_password' => $device?->wifi_password,
            'odp_number' => $profile['odpCode'],
            'odp_port' => $profile['odpPort'],
            'olt_number' => $tech?->olt_number,
            'olt_slot' => $tech?->olt_slot,
            'olt_port' => $tech?->olt_port,
            'vlan' => $profile['vlanId'] !== null ? (string) $profile['vlanId'] : null,
            'router_number' => $tech?->router_number,
            'initial_attenuation' => $tech?->initial_attenuation,
        ];

        $hints = [];

        if (filled($profile['macAddress']) && $values['mac_address'] === null) {
            $hints['mac_address'] = (string) $profile['macAddress'];
        }

        foreach (self::MAX_LENGTH as $field => $max) {
            if ($values[$field] !== null && mb_strlen((string) $values[$field]) > $max) {
                $hints[$field] = (string) $values[$field];
                $values[$field] = null;
            }
        }

        // `customer_devices.device_type` NOT NULL tanpa default. Kalau ada
        // data perangkat yang akan tersimpan ke tabel itu tapi jenisnya tidak
        // bisa diturunkan (mis. SN cuma di detail teknis, tipe koneksi
        // kosong), isi "Lainnya" — terlihat & bisa diganti admin, bukan
        // tebakan ONT/Router. Dicek SETELAH nilai tidak valid dikosongkan di
        // atas, supaya tidak membuat baris perangkat yang isinya cuma jenis.
        $deviceTableFields = ['brand', 'model', 'serial_number', 'mac_address', 'connection_mode', 'pppoe_username', 'pppoe_password', 'wifi_ssid', 'wifi_password'];
        if ($values['device_type'] === null && collect($deviceTableFields)->contains(fn ($f) => filled($values[$f]))) {
            $values['device_type'] = 'other';
        }

        return ['values' => $values, 'hints' => $hints];
    }

    /**
     * MAC dari data lama kadang ditulis dengan `-`/`.`/tanpa pemisah atau huruf
     * kecil. Kalau isinya tetap 12 digit heksa, rapikan ke format yang diterima
     * validasi Edit (`AA:BB:CC:DD:EE:FF`) — MAC-nya sama, cuma cara tulisnya.
     * Selain itu null (tidak diisi otomatis).
     */
    private static function normalizeMac(?string $mac): ?string
    {
        $mac = trim((string) $mac);
        if ($mac === '') {
            return null;
        }

        $hex = preg_replace('/[\s:\-.]/', '', $mac);
        if (! preg_match('/^[0-9A-Fa-f]{12}$/', (string) $hex)) {
            return null;
        }

        return implode(':', str_split(strtoupper($hex), 2));
    }
}
