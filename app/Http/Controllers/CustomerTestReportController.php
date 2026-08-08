<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class CustomerTestReportController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.update'), 403);

        $validated = $request->validate([
            'test_date' => 'required|date',
            'test_time' => 'nullable',
            'test_download' => 'required|numeric|min:0',
            'test_upload' => 'required|numeric|min:0',
            'jitter_ms' => 'nullable|numeric|min:0',
            'latency_ms' => 'nullable|numeric|min:0',
            'packet_loss_percent' => 'nullable|numeric|min:0|max:100',
            'quality_score' => 'nullable|integer|min:1|max:5',
            'speedtest_photo' => 'nullable|image|max:2048',
            'actual_attenuation' => 'nullable|string|max:50',
            'initial_attenuation' => 'nullable|string|max:50',
        ]);

        // Calculate speed conformity if package exists
        $package = $customer->internetPackage;
        if ($package && $package->download_speed_mbps > 0) {
            $validated['speed_conformity_percent'] = ($validated['test_download'] / $package->download_speed_mbps) * 100;
        }

        if ($request->hasFile('speedtest_photo')) {
            $validated['speedtest_photo'] = FileUploadService::uploadInstallationPhoto($request->file('speedtest_photo'), $customer, 'speedtest');
        }

        CustomerTechnicalDetail::updateOrCreate(
            ['customer_id' => $customer->id],
            $validated
        );

        return redirect()->back()->with('success', 'Laporan hasil pengujian layanan (Speedtest) berhasil disimpan.');
    }
}
