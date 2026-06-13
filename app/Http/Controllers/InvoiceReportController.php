<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Pop;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceReportController extends Controller
{
    /**
     * Display the invoice report index page.
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
        $billingPeriod = $request->query('billing_period', '');
        $status = $request->query('status', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');
        $showTunggakan = $request->query('show_tunggakan', '') === '1';

        // POP yang bisa diakses user
        $pops = Pop::forUser()->orderBy('name')->get();
        $allowedPopIds = $pops->pluck('id')->toArray();

        // Jika user memfilter POP tertentu, pastikan POP itu ada di dalam POP yang diizinkan untuknya
        if ($popId !== '') {
            if (!in_array((int)$popId, $allowedPopIds)) {
                $popId = '';
            }
        }

        // Base query
        $query = Invoice::with(['customer', 'pop', 'internetPackage'])
            ->forUser();

        // Menerapkan filters
        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        if ($billingPeriod !== '') {
            $query->where('billing_period', $billingPeriod);
        }

        if ($status !== '') {
            $query->where('invoice_status', $status);
        }

        if ($startDate !== '') {
            $query->whereDate('issue_date', '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->whereDate('issue_date', '<=', $endDate);
        }

        if ($showTunggakan) {
            $query->where('remaining_amount', '>', 0)
                ->where('invoice_status', '!=', 'batal');
        }

        // Clone query untuk menghitung agregat ringkasan sebelum dipaginasi
        $summaryQuery = clone $query;
        $totalAmountSum = $summaryQuery->sum('total_amount');
        $totalPaidSum = $summaryQuery->sum('paid_amount');
        
        // Sisa tunggakan dihitung dari invoice yang tidak batal
        $totalTunggakanSum = $summaryQuery->where('invoice_status', '!=', 'batal')->sum('remaining_amount');

        // Dapatkan data terpaginasi
        $invoices = $query->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $allowedStatuses = ['belum_dibayar', 'sebagian', 'lunas', 'batal'];

        return view('reports.invoices.index', compact(
            'invoices',
            'pops',
            'popId',
            'billingPeriod',
            'status',
            'startDate',
            'endDate',
            'showTunggakan',
            'totalAmountSum',
            'totalPaidSum',
            'totalTunggakanSum',
            'allowedStatuses'
        ));
    }

    /**
     * Export invoice report to CSV stream.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();

        if (!$user->hasPermission('view_reports_all') && !$user->hasPermission('view_reports_own_pop')) {
            abort(403, 'Unauthorized action.');
        }

        $popId = $request->query('pop_id', '');
        $billingPeriod = $request->query('billing_period', '');
        $status = $request->query('status', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');
        $showTunggakan = $request->query('show_tunggakan', '') === '1';

        // Pastikan input pop_id divalidasi dengan POP yang diizinkan untuk user ini
        $allowedPopIds = Pop::forUser()->pluck('id')->toArray();
        if ($popId !== '') {
            if (!in_array((int)$popId, $allowedPopIds)) {
                abort(403, 'Unauthorized action.');
            }
        }

        // Query data tanpa pagination
        $query = Invoice::with(['customer', 'pop', 'internetPackage'])
            ->forUser();

        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        if ($billingPeriod !== '') {
            $query->where('billing_period', $billingPeriod);
        }

        if ($status !== '') {
            $query->where('invoice_status', $status);
        }

        if ($startDate !== '') {
            $query->whereDate('issue_date', '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->whereDate('issue_date', '<=', $endDate);
        }

        if ($showTunggakan) {
            $query->where('remaining_amount', '>', 0)
                ->where('invoice_status', '!=', 'batal');
        }

        $invoices = $query->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-tagihan-' . now()->format('YmdHis') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($invoices) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for proper Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Kolom Header
            fputcsv($file, [
                'No. Invoice',
                'Kode Pelanggan',
                'Nama Pelanggan',
                'POP/Cabang',
                'Periode',
                'Tanggal Terbit',
                'Tanggal Jatuh Tempo',
                'Subtotal',
                'PPN',
                'Total Tagihan',
                'Terbayar',
                'Sisa Tunggakan',
                'Status Tagihan',
            ]);

            foreach ($invoices as $invoice) {
                fputcsv($file, [
                    $invoice->invoice_number,
                    $invoice->customer->customer_code ?? '-',
                    $invoice->customer->full_name ?? '-',
                    $invoice->pop->name ?? '-',
                    $invoice->billing_period,
                    $invoice->issue_date ? $invoice->issue_date->format('Y-m-d') : '-',
                    $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '-',
                    (float) $invoice->subtotal,
                    (float) $invoice->ppn,
                    (float) $invoice->total_amount,
                    (float) $invoice->paid_amount,
                    (float) $invoice->remaining_amount,
                    ucfirst(str_replace('_', ' ', $invoice->invoice_status)),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
