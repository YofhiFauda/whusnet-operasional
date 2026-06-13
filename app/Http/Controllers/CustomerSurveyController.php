<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CustomerSurveyController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('fill_survey'), 403);

        $validated = $request->validate([
            'survey_status' => 'required|string|in:pending,completed,failed',
            'survey_date' => 'required|date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'technician_id' => 'nullable|exists:users,id',
            'required_tools' => 'nullable|string',
            'cable_estimation_meter' => 'nullable|integer|min:0',
            'nearest_odp' => 'nullable|string',
            'survey_photo' => 'nullable|image|max:2048',
            'survey_note' => 'nullable|string',
        ]);

        if ($request->hasFile('survey_photo')) {
            $path = $request->file('survey_photo')->store('surveys', 'public');
            $validated['survey_photo'] = $path;
        }

        $validated['customer_id'] = $customer->id;
        $survey = CustomerSurvey::create($validated);

        // Update customer status to surveyed if completed
        if ($survey->survey_status === 'completed' && $customer->status === 'waiting_survey') {
            $customer->update(['status' => 'surveyed']);
        }

        return redirect()->back()->with('success', 'Data survey berhasil disimpan.');
    }
}
