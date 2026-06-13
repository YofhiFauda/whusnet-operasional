<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Pop;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReportController extends Controller
{
    /**
     * Display the payment report index page.
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
        $paymentMethod = $request->query('payment_method', '');
        $status = $request->query('status', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');

        // POP yang bisa diakses user
        $popsQuery = Pop::query();
        if (!in_array(optional($user->role)->name, ['Owner', 'Admin Pusat'], true)) {
            $popsQuery->whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        $pops = $popsQuery->orderBy('name')->get();
        $allowedPopIds = $pops->pluck('id')->toArray();

        // Jika user memfilter POP tertentu, pastikan POP itu ada di dalam POP yang diizinkan untuknya
        if ($popId !== '') {
            if (!in_array((int)$popId, $allowedPopIds)) {
                $popId = '';
            }
        }

        // Base query
        $query = Payment::with(['customer', 'pop', 'invoice', 'receiver'])
            ->forUser(auth()->user());

        // Menerapkan filters
        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        if ($paymentMethod !== '') {
            $query->where('payment_method', $paymentMethod);
        }

        if ($status !== '') {
            $query->where('payment_status', $status);
        }

        if ($startDate !== '') {
            $query->whereDate('payment_date', '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->whereDate('payment_date', '<=', $endDate);
        }

        // Clone query untuk menghitung agregat ringkasan sebelum dipaginasi
        $summaryQuery = clone $query;
        $totalAmountSum = $summaryQuery->sum('amount');
        $totalValidSum = (clone $summaryQuery)->where('payment_status', 'valid')->sum('amount');
        $totalPendingSum = (clone $summaryQuery)->where('payment_status', 'pending')->sum('amount');

        // Dapatkan data terpaginasi
        $payments = $query->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $allowedMethods = ['cash', 'transfer', 'qris', 'lainnya'];
        $allowedStatuses = ['pending', 'valid', 'ditolak'];

        return view('reports.payments.index', compact(
            'payments',
            'pops',
            'popId',
            'paymentMethod',
            'status',
            'startDate',
            'endDate',
            'totalAmountSum',
            'totalValidSum',
            'totalPendingSum',
            'allowedMethods',
            'allowedStatuses'
        ));
    }

    /**
     * Export payment report to CSV stream.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();

        if (!$user->hasPermission('view_reports_all') && !$user->hasPermission('view_reports_own_pop')) {
            abort(403, 'Unauthorized action.');
        }

        $popId = $request->query('pop_id', '');
        $paymentMethod = $request->query('payment_method', '');
        $status = $request->query('status', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');

        // Pastikan input pop_id divalidasi dengan POP yang diizinkan untuk user ini
        $allowedPopsQuery = Pop::query();
        if (!in_array(optional($user->role)->name, ['Owner', 'Admin Pusat'], true)) {
            $allowedPopsQuery->whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        $allowedPopIds = $allowedPopsQuery->pluck('id')->toArray();
        if ($popId !== '') {
            if (!in_array((int)$popId, $allowedPopIds)) {
                abort(403, 'Unauthorized action.');
            }
        }

        // Query data tanpa pagination
        $query = Payment::with(['customer', 'pop', 'invoice', 'receiver'])
            ->forUser(auth()->user());

        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        if ($paymentMethod !== '') {
            $query->where('payment_method', $paymentMethod);
        }

        if ($status !== '') {
            $query->where('payment_status', $status);
        }

        if ($startDate !== '') {
            $query->whereDate('payment_date', '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->whereDate('payment_date', '<=', $endDate);
        }

        $payments = $query->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-pembayaran-' . now()->format('YmdHis') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($payments) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for proper Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Kolom Header
            fputcsv($file, [
                'No. Kwitansi/Transaksi',
                'No. Invoice',
                'Kode Pelanggan',
                'Nama Pelanggan',
                'POP/Cabang',
                'Tanggal Bayar',
                'Metode Pembayaran',
                'Nominal Pembayaran',
                'Penerima/Petugas',
                'Status Pembayaran',
                'Catatan',
            ]);

            foreach ($payments as $payment) {
                fputcsv($file, [
                    $payment->payment_number,
                    $payment->invoice->invoice_number ?? '-',
                    $payment->customer->customer_code ?? '-',
                    $payment->customer->full_name ?? '-',
                    $payment->pop->name ?? '-',
                    $payment->payment_date ? $payment->payment_date->format('Y-m-d') : '-',
                    strtoupper($payment->payment_method),
                    (float) $payment->amount,
                    $payment->receiver->name ?? '-',
                    ucfirst($payment->payment_status),
                    $payment->note ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
