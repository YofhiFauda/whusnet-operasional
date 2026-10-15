<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerAdditionalDeviceController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        // Route middleware permission:customers.detail.devices.create|customers.detail.devices.update
        // sudah menangani otorisasi — lihat routes/web.php.
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

        if ($customer->additionalDevices()->count() >= $limit) {
            return back()->withErrors(['device_type' => "Sudah mencapai batas {$limit} perangkat tambahan untuk paket pelanggan ini."]);
        }

        $customer->additionalDevices()->create($validated);

        return redirect()->back()->with('success', 'Perangkat tambahan berhasil ditambahkan.');
    }

    public function destroy(Customer $customer, CustomerAdditionalDevice $additionalDevice): RedirectResponse
    {
        abort_unless($additionalDevice->customer_id === $customer->id, 404);

        $additionalDevice->delete();

        return redirect()->back()->with('success', 'Perangkat tambahan berhasil dihapus.');
    }
}
