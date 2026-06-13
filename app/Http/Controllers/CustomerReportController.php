<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\SubscriptionStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerReportController extends Controller
{
    /**
     * Display the customer report page with filters.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Pengecekan permission: harus punya salah satu
        if (!$user->hasPermission('view_reports_all') && !$user->hasPermission('view_reports_own_pop')) {
            abort(403, 'Unauthorized action.');
        }

        // Ambil data filter
        $popId = $request->query('pop_id', '');
        $completenessStatus = $request->query('completeness_status', '');
        $status = $request->query('status', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');

        // POP yang bisa diakses user
        $pops = Pop::query()->forUser()->orderBy('name')->get();
        $allowedPopIds = $pops->pluck('id')->toArray();

        // Jika user memfilter POP tertentu, pastikan POP itu ada di dalam POP yang diizinkan untuknya
        if ($popId !== '') {
            if (!in_array((int)$popId, $allowedPopIds)) {
                $popId = '';
            }
        }

        // Query customers menggunakan scope forUser
        $query = Customer::with(['pop', 'internetPackage', 'subscriptionStatus'])
            ->forUser();

        // Menerapkan filters
        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        if ($completenessStatus !== '') {
            $query->where('data_completeness_status', $completenessStatus);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($startDate !== '') {
            $query->whereDate('registration_date', '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->whereDate('registration_date', '<=', $endDate);
        }

        // Urutkan berdasarkan tanggal registrasi terbaru
        $customers = $query->orderByDesc('registration_date')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // Ambil list status langganan dinamis
        $statuses = SubscriptionStatus::all();

        return view('reports.customers.index', compact(
            'customers',
            'pops',
            'statuses',
            'popId',
            'completenessStatus',
            'status',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Export customer report to CSV stream.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();
        if (!$user->hasPermission('view_reports_all') && !$user->hasPermission('view_reports_own_pop')) {
            abort(403, 'Unauthorized action.');
        }

        $popId = $request->query('pop_id', '');
        $completenessStatus = $request->query('completeness_status', '');
        $status = $request->query('status', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');

        // Pastikan input pop_id divalidasi dengan POP yang diizinkan untuk user ini
        $allowedPopIds = Pop::query()->forUser()->pluck('id')->toArray();
        if ($popId !== '') {
            if (!in_array((int)$popId, $allowedPopIds)) {
                abort(403, 'Unauthorized action.');
            }
        }

        // Query data tanpa pagination
        $query = Customer::with(['pop', 'internetPackage', 'subscriptionStatus'])
            ->forUser();

        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        if ($completenessStatus !== '') {
            $query->where('data_completeness_status', $completenessStatus);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($startDate !== '') {
            $query->whereDate('registration_date', '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->whereDate('registration_date', '<=', $endDate);
        }

        $customers = $query->orderByDesc('registration_date')
            ->orderByDesc('created_at')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-pelanggan-' . now()->format('YmdHis') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($customers) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for proper Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Kolom Header
            fputcsv($file, [
                'Kode Pelanggan',
                'ID Pelanggan Lama',
                'CID',
                'Nama Lengkap',
                'No. HP',
                'Gender',
                'Email',
                'POP/Cabang',
                'Paket Internet',
                'Kecepatan Download',
                'Kecepatan Upload',
                'Kelengkapan Data',
                'Status Pelanggan',
                'Tanggal Registrasi',
            ]);

            foreach ($customers as $customer) {
                fputcsv($file, [
                    $customer->customer_code ?? '-',
                    $customer->old_customer_id ?? '-',
                    $customer->cid ?? '-',
                    $customer->full_name,
                    $customer->primary_phone ?? $customer->phone ?? '-',
                    $customer->gender ?? '-',
                    $customer->email ?? '-',
                    $customer->pop->name ?? '-',
                    $customer->internetPackage->name ?? '-',
                    $customer->internetPackage ? ($customer->internetPackage->download_speed_mbps . ' Mbps') : '-',
                    $customer->internetPackage ? ($customer->internetPackage->upload_speed_mbps . ' Mbps') : '-',
                    ucfirst(str_replace('_', ' ', $customer->data_completeness_status)),
                    $customer->subscriptionStatus->name ?? ucfirst(str_replace('_', ' ', $customer->status)),
                    $customer->registration_date ? $customer->registration_date->format('Y-m-d') : '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
