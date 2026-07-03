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
        // The route middleware permission:customers.devices.create|customers.devices.update already handles authorization.

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
            'olt_number'          => 'nullable|string|max:50',
            'olt_slot'            => 'nullable|string|max:20',
            'olt_port'            => 'nullable|string|max:50',
            'tech_vlan'           => 'nullable|string|max:20',
            'passive_device'      => 'nullable|string|max:150',
            'passive_device_type' => 'nullable|string|in:splitter_odp,kabel_dropcore,patch_cord,media_converter,antena_radio,aksesoris_pasang,lainnya',
            'passive_device_qty'  => 'nullable|string|max:50',
            'passive_device_note' => 'nullable|string|max:255',
            'branch_number'       => 'nullable|string|max:50',
            'pop_number'          => 'nullable|string|max:50',
            'router_number'       => 'nullable|string|max:50',
            'initial_attenuation' => 'nullable|string|max:50',
            'actual_attenuation'  => 'nullable|string|max:50',
        ]);

        if (!auth()->user()->hasPermission('customers.detail.devices.update_sensitive')) {
            unset($deviceValidated['pppoe_password']);
            unset($deviceValidated['wifi_password']);
            unset($deviceValidated['ip_address']);
            unset($deviceValidated['vlan_id']);
            unset($techValidated['tech_vlan']);
        }

         $deviceValidated['customer_id'] = $customer->id;

        CustomerDevice::updateOrCreate(
            ['customer_id' => $customer->id],
            $deviceValidated
        );

        // Simpan field teknis jaringan ke customer_technical_details
        CustomerTechnicalDetail::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'olt_number'          => $techValidated['olt_number'] ?? null,
                'olt_slot'            => $techValidated['olt_slot'] ?? null,
                'olt_port'            => $techValidated['olt_port'] ?? null,
                'vlan'                => $techValidated['tech_vlan'] ?? null,
                'passive_device'      => $techValidated['passive_device'] ?? null,
                'passive_device_type' => $techValidated['passive_device_type'] ?? null,
                'passive_device_qty'  => $techValidated['passive_device_qty'] ?? null,
                'passive_device_note' => $techValidated['passive_device_note'] ?? null,
                'branch_number'       => $techValidated['branch_number'] ?? null,
                'pop_number'          => $techValidated['pop_number'] ?? null,
                'router_number'       => $techValidated['router_number'] ?? null,
                'initial_attenuation' => $techValidated['initial_attenuation'] ?? null,
                'actual_attenuation'  => $techValidated['actual_attenuation'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'Data perangkat dan teknis jaringan pelanggan berhasil disimpan.');
    }
}
