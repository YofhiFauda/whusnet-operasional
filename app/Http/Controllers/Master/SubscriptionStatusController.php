<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionStatus;

class SubscriptionStatusController extends Controller
{
    /**
     * Display subscription workflow statuses.
     */
    public function index()
    {
        $statuses = SubscriptionStatus::query()
            ->withCount('customers')
            ->orderBy('workflow_order')
            ->get();

        $terminalStatuses = $statuses->where('is_terminal', true)->count();

        return view('master.status-langganan', compact('statuses', 'terminalStatuses'));
    }
}
