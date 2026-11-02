<?php

namespace App\Services;

use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use App\Models\CustomerDevice;
use App\Models\InventorySerial;

/**
 * Penjaga SN perangkat saat Edit Pelanggan. Cuma membaca — tidak menulis apa pun.
 *
 * Kenapa ada: `customer_devices.serial_number` tidak punya unique index (data
 * legacy hampir pasti sudah berisi duplikat), jadi dua pelanggan bisa memegang
 * modem yang sama tanpa ada yang menolak. Rancangan:
 * docs/superpowers/specs/2026-10-31-validasi-sn-perangkat-pelanggan-design.md
 *
 * Hanya bereaksi pada SN yang BERUBAH. Form Edit di-prefill SN efektif dari
 * `CustomerDeviceProfileService::resolve()` (bisa berasal dari Gudang atau
 * kolom legacy), jadi simpan ulang tanpa menyentuh SN tidak boleh ketahan
 * duplikat lama yang bukan urusan admin saat itu.
 *
 * Pencarian pelanggan lain SENGAJA lintas cabang tanpa POP scope: tujuannya
 * mendeteksi tabrakan global. Yang bocor ke pesan cuma ID + nama pemegang SN.
 */
class CustomerDeviceSerialGuard
{
    public function normalize(?string $serial): ?string
    {
        $serial = strtoupper(trim((string) $serial));

        return $serial === '' ? null : $serial;
    }

    /**
     * @return array{blocking: ?string, warning: ?string}
     */
    public function check(Customer $customer, ?string $serial): array
    {
        $none = ['blocking' => null, 'warning' => null];
        $input = $this->normalize($serial);

        if ($input === null) {
            return $none;
        }

        $profile = CustomerDeviceProfileService::resolve($customer);

        $unchanged = [
            $this->normalize($profile['serialNumber']),
            $this->normalize($customer->customerDevice?->serial_number),
        ];
        if (in_array($input, $unchanged, true)) {
            return $none;
        }

        // Perangkat utama dikelola Gudang: resolve() mendahulukan serial Gudang
        // berstatus INSTALLED di atas customer_devices, jadi SN yang diketik di
        // Edit tidak akan pernah tampil di Detail. Lebih baik ditolak jelas
        // daripada tersimpan tapi tak berefek.
        $installed = $profile['installedInventorySerial'];
        if ($installed) {
            return ['blocking' => "Perangkat utama pelanggan ini dikelola Gudang (SN {$installed->serial_number}). Ganti lewat Penggantian Modem atau retur, bukan dari Edit.", 'warning' => null];
        }

        $holder = CustomerDevice::query()
            ->where('customer_id', '!=', $customer->id)
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [$input])
            ->with('customer')
            ->first();
        if ($holder) {
            return ['blocking' => $this->takenMessage($holder->customer), 'warning' => null];
        }

        // Perangkat tambahan (AP/STB) bukan modem utama: SN-nya hidup di
        // `customer_additional_devices` dan tidak dilihat query customer_devices
        // di atas. Tanpa cek ini, SN sebuah AP — termasuk milik pelanggan ini
        // sendiri — bisa diketik sebagai SN modem utama dan lolos.
        $extra = CustomerAdditionalDevice::query()
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [$input])
            ->with('customer')
            ->first();
        if ($extra) {
            $owner = $extra->customer_id === $customer->id ? 'pelanggan ini' : "pelanggan {$extra->customer?->display_id} – {$extra->customer?->full_name}";

            return ['blocking' => "SN sudah terdaftar sebagai perangkat tambahan ({$extra->device_type}) milik {$owner}, bukan perangkat utama.", 'warning' => null];
        }

        $inventory = InventorySerial::query()
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [$input])
            ->with(['customer', 'item.category'])
            ->get();

        // AP/STB gudang yang terpasang ke pelanggan ini sendiri (baris
        // perangkat tambahannya bisa saja hilang/terhapus) tetap bukan modem.
        $ownExtra = $inventory->first(fn (InventorySerial $row) => $row->status === SerialStatus::INSTALLED
            && $row->item?->category?->is_customer_extra_device);
        if ($ownExtra) {
            return ['blocking' => "SN {$input} adalah perangkat tambahan (AP/STB) dari Gudang, bukan perangkat utama.", 'warning' => null];
        }

        $installedElsewhere = $inventory->first(fn (InventorySerial $row) => $row->status === SerialStatus::INSTALLED
            && $row->customer_id !== null
            && $row->customer_id !== $customer->id);
        if ($installedElsewhere) {
            return ['blocking' => $this->takenMessage($installedElsewhere->customer), 'warning' => null];
        }

        $inStock = $inventory->first();
        if ($inStock) {
            return ['blocking' => null, 'warning' => "SN {$input} tercatat di Gudang berstatus {$inStock->status->value}. Stok Gudang tidak berubah oleh Edit ini."];
        }

        return $none;
    }

    /**
     * Padanan `check()` untuk modal "Tambah Perangkat Tambahan" di Detail
     * Pelanggan (teks bebas, tanpa tautan Gudang). Arah sebaliknya dari
     * `check()`: SN sebuah AP tidak boleh sama dengan perangkat utama siapa pun,
     * perangkat tambahan aktif siapa pun, atau serial Gudang yang terpasang di
     * pelanggan lain. SN yang tercatat di Gudang dengan status lain cuma
     * peringatan. Perangkat 'ditarik' sudah bukan di pelanggan, jadi diabaikan.
     *
     * @return array{blocking: ?string, warning: ?string}
     */
    public function checkAdditional(Customer $customer, ?string $serial): array
    {
        $input = $this->normalize($serial);

        if ($input === null) {
            return ['blocking' => null, 'warning' => null];
        }

        $main = CustomerDevice::query()
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [$input])
            ->with('customer')
            ->first();
        if ($main) {
            $owner = $main->customer_id === $customer->id ? 'pelanggan ini' : "pelanggan {$main->customer?->display_id} – {$main->customer?->full_name}";

            return ['blocking' => "SN sudah terdaftar sebagai perangkat utama milik {$owner}.", 'warning' => null];
        }

        $extra = CustomerAdditionalDevice::query()
            ->active()
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [$input])
            ->with('customer')
            ->first();
        if ($extra) {
            $owner = $extra->customer_id === $customer->id ? 'pelanggan ini' : "pelanggan {$extra->customer?->display_id} – {$extra->customer?->full_name}";

            return ['blocking' => "SN sudah terdaftar sebagai perangkat tambahan milik {$owner}.", 'warning' => null];
        }

        $inventory = InventorySerial::query()
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [$input])
            ->with('customer')
            ->get();

        $installed = $inventory->first(fn (InventorySerial $row) => $row->status === SerialStatus::INSTALLED && $row->customer_id !== null);
        if ($installed) {
            $owner = $installed->customer_id === $customer->id ? 'pelanggan ini' : "pelanggan {$installed->customer?->display_id} – {$installed->customer?->full_name}";

            return ['blocking' => "SN tercatat terpasang di Gudang atas nama {$owner}.", 'warning' => null];
        }

        if ($inStock = $inventory->first()) {
            return ['blocking' => null, 'warning' => "SN {$input} tercatat di Gudang berstatus {$inStock->status->value}. Stok Gudang tidak berubah oleh penambahan ini."];
        }

        return ['blocking' => null, 'warning' => null];
    }

    private function takenMessage(?Customer $holder): string
    {
        if (! $holder) {
            return 'SN sudah terdaftar di pelanggan lain.';
        }

        return "SN sudah terdaftar di pelanggan {$holder->display_id} – {$holder->full_name}.";
    }
}
