<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDevice;
use Illuminate\Http\Request;

class CustomerDeviceController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('fill_device'), 403);

        $validated = $request->validate([
            'device_type' => 'required|string|in:modem,ont,onu,router,other',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'mac_address' => ['nullable', 'string', 'max:17', 'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/'],
            'pppoe_username' => 'nullable|string|max:150',
            'pppoe_password' => 'nullable|string|max:150',
            'wifi_ssid' => 'nullable|string|max:150',
            'wifi_password' => 'nullable|string|max:150',
            'ip_address' => 'nullable|ip',
            'vlan_id' => 'nullable|integer|min:1|max:4094',
            'odp' => 'nullable|string|max:100',
            'odp_port' => 'nullable|string|max:50',
            'signal_rx_power' => 'nullable|numeric|min:-50|max:10',
            'connection_mode' => 'nullable|string|in:bridge,router,pppoe,static,dhcp,other',
            'technical_note' => 'nullable|string',
        ]);

        $validated['customer_id'] = $customer->id;

        CustomerDevice::updateOrCreate(
            ['customer_id' => $customer->id],
            $validated
        );

        return redirect()->back()->with('success', 'Data perangkat pelanggan berhasil disimpan.');
    }
}
