<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use App\Services\CustomerDeviceSerialGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerAdditionalDeviceController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        // Route middleware permission:customers.detail.devices.create|customers.detail.devices.update
        // sudah menangani otorisasi — lihat routes/web.php. Scope POP belum
        // ditangani middleware itu, jadi dicek manual di sini (pola sama
        // dengan CustomerPackageController::update()) — tanpa ini user bisa
        // menambah perangkat tambahan ke pelanggan cabang lain sepanjang dia
        // punya permission di atas (larangan RBAC #3, CLAUDE.md).
        abort_unless(Customer::query()->applyUserScope()->whereKey($customer->id)->exists(), 403);

        $validated = $request->validate([
            'device_type' => 'required|string|max:50',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'mac_address' => ['nullable', 'string', 'max:17', 'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/'],
            'technical_note' => 'nullable|string',
        ]);

        // Limit perangkat tambahan ikut paket aktif pelanggan (benefit paket,
        // bukan properti pelanggan) — lihat CustomerPackageService::assertAdditionalDevicesWithinLimit()
        // untuk guard yang sama saat ganti paket.
        $package = $customer->customerService?->internetPackage;
        $limit = $package?->max_additional_devices ?? 0;

        if ($customer->additionalDevices()->active()->count() >= $limit) {
            return back()->withErrors(['device_type' => "Sudah mencapai batas {$limit} perangkat tambahan untuk paket pelanggan ini."]);
        }

        // SN ganda: modal ini hidden dan tidak menampilkan error validasi per
        // field, jadi penolakan dikirim sebagai flash 'error' (terlihat di
        // halaman) dengan input dipertahankan.
        $serialCheck = app(CustomerDeviceSerialGuard::class)->checkAdditional($customer, $validated['serial_number'] ?? null);
        if ($serialCheck['blocking']) {
            return redirect()->back()->withInput()->with('error', $serialCheck['blocking']);
        }

        $customer->additionalDevices()->create($validated);

        $response = redirect()->back()->with('success', 'Perangkat tambahan berhasil ditambahkan.');

        return $serialCheck['warning'] ? $response->with('warning', $serialCheck['warning']) : $response;
    }

    public function destroy(Customer $customer, CustomerAdditionalDevice $additionalDevice): RedirectResponse
    {
        // Scope POP — lihat alasan yang sama di store() di atas.
        abort_unless(Customer::query()->applyUserScope()->whereKey($customer->id)->exists(), 403);

        abort_unless($additionalDevice->customer_id === $customer->id, 404);

        // AP/STB dari Gudang yang masih terpasang tidak boleh dihapus dari
        // sini: menghapus baris tidak mengubah `inventory_serials` (tetap
        // INSTALLED ke pelanggan ini) dan tidak ada retur fisik, jadi stok
        // dan Detail Pelanggan langsung menyimpang. Lewat jalur resmi
        // (Ambil Alat/DEAC) barisnya ditandai 'ditarik', baru boleh dihapus.
        if ($additionalDevice->inventory_serial_id !== null && $additionalDevice->status === 'terpasang') {
            return redirect()->back()->with('error', 'Perangkat tambahan dari Gudang yang masih terpasang tidak bisa dihapus dari sini. Tarik lewat "Ambil Alat" (Putus Langganan) supaya stok Gudang ikut kembali.');
        }

        $additionalDevice->delete();

        return redirect()->back()->with('success', 'Perangkat tambahan berhasil dihapus.');
    }
}
