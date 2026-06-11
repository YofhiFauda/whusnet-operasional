<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\District;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the operational dashboard.
     */
    public function index()
    {
        // Calculate incomplete customers dynamically using model helper
        $incompleteCustomers = Customer::all()->filter(function ($customer) {
            return count($customer->dataCompleteness()['missing_required']) > 0;
        })->count();

        $stats = [
            'total_customers' => Customer::count(),
            'active_customers' => Customer::where('status', 'active')->count(),
            'inactive_customers' => Customer::whereIn('status', ['terminated', 'rejected'])->count(),
            'suspended_customers' => Customer::where('status', 'suspended')->count(),
            'pending_customers' => Customer::whereIn('status', ['registered', 'waiting_survey', 'surveyed', 'waiting_installation', 'installed'])->count(),
            'total_packages' => InternetPackage::count(),
            'total_districts' => District::count(),
            'incomplete_customers' => $incompleteCustomers,
            // Financial placeholders for future sprints (Sprint 5 & 6)
            'total_invoices_amount' => 0,
            'total_payments_amount' => 0,
            'total_unpaid_amount' => 0,
            'due_invoices_count' => 0,
        ];

        // Chart Data: Status distribution
        $statusData = Customer::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Chart Data: Subscriptions by package category
        $categoryData = Customer::join('internet_packages', 'customers.internet_package_id', '=', 'internet_packages.id')
            ->selectRaw('internet_packages.category, count(*) as count')
            ->groupBy('internet_packages.category')
            ->pluck('count', 'internet_packages.category')
            ->toArray();

        // Chart Data: Monthly registration trends (Database-agnostic using Eloquent Collection)
        $trends = Customer::select('registration_date')
            ->orderBy('registration_date', 'asc')
            ->get()
            ->groupBy(function ($customer) {
                return $customer->registration_date ? $customer->registration_date->format('Y-m') : 'N/A';
            })
            ->map(fn ($group) => $group->count())
            ->toArray();

        return view('dashboard', compact('stats', 'statusData', 'categoryData', 'trends'));
    }
}
