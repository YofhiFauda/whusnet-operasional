<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerTechnicalDetail;
use Illuminate\Http\Request;

class CustomerDeviceController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('fill_device'), 403);

        // Validasi field perangkat (customer_devices)
        $deviceValidated = $request->validate([
            'device_type'    => 'required|string|in:modem,ont,onu,router,other',
            'brand'          => 'nullable|string|max:100',
            'model'          => 'nullable|string|max:100',
            'serial_number'  => 'nullable|string|max:100',
            'mac_address'    => ['nullable', 'string', 'max:17', 'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/'],
            'pppoe_username' => 'nullable|string|max:150',
            'pppoe_password' => 'nullable|string|max:150',
            'wifi_ssid'      => 'nullable|string|max:150',
            'wifi_password'  => 'nullable|string|max:150',
            'ip_address'     => 'nullable|ip',
            'vlan_id'        => 'nullable|integer|min:1|max:4094',
            'odp'            => 'nullable|string|max:100',
            'odp_port'       => 'nullable|string|max:50',
            'signal_rx_power'=> 'nullable|numeric|min:-50|max:10',
            'connection_mode'=> 'nullable|string|in:bridge,router,pppoe,static,dhcp,other',
            'technical_note' => 'nullable|string',
        ]);

        // Validasi field teknis jaringan (customer_technical_details)
        $techValidated = $request->validate([
            'olt_number' => 'nullable|string|max:50',
            'olt_slot'   => 'nullable|string|max:20',
            'olt_port'   => 'nullable|string|max:50',
            'tech_vlan'  => 'nullable|string|max:20',
        ]);

         $deviceValidated['customer_id'] = $customer->id;

        CustomerDevice::updateOrCreate(
            ['customer_id' => $customer->id],
            $deviceValidated
        );

        // Simpan field teknis jaringan ke customer_technical_details
        CustomerTechnicalDetail::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'olt_number' => $techValidated['olt_number'] ?? null,
                'olt_slot'   => $techValidated['olt_slot'] ?? null,
                'olt_port'   => $techValidated['olt_port'] ?? null,
                'vlan'       => $techValidated['tech_vlan'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'Data perangkat dan teknis jaringan pelanggan berhasil disimpan.');
    }
}
