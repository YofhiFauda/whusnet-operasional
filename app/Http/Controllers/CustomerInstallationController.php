<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use Illuminate\Http\Request;

class CustomerInstallationController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('fill_installation'), 403);

        $validated = $request->validate([
            'installation_status' => 'required|string|in:scheduled,in_progress,completed,failed',
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'nullable',
            'technician_id' => 'nullable|exists:users,id',
            'finished_date' => 'nullable|date',
            'installation_photo' => 'nullable|image|max:2048',
            'installation_note' => 'nullable|string',
        ]);

        if ($request->hasFile('installation_photo')) {
            $path = $request->file('installation_photo')->store('installations', 'public');
            $validated['installation_photo'] = $path;
        }

        $validated['customer_id'] = $customer->id;
        $installation = CustomerInstallation::create($validated);

        if ($installation->installation_status === 'completed' && in_array($customer->status, ['surveyed', 'waiting_installation'])) {
            $customer->update(['status' => 'installed']);
        } elseif ($installation->installation_status === 'scheduled' && $customer->status === 'surveyed') {
            $customer->update(['status' => 'waiting_installation']);
        }

        return redirect()->back()->with('success', 'Data pemasangan berhasil disimpan.');
    }
}
