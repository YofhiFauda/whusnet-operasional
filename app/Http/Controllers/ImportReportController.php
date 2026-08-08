<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportReportController extends Controller
{
    /**
     * Display the import report list page with statistics.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Pengecekan permission: harus punya salah satu
        if (! $user->hasPermission('reports.view')) {
            abort(403, 'Unauthorized action.');
        }

        // Ambil data filter
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');

        // Base query
        $query = ImportBatch::with('user');

        // Batasi akses jika bukan Owner atau Admin Pusat
        if (! $user->hasFullAccess()) {
            $query->where('uploaded_by', $user->id);
        }

        // Menerapkan filters
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        // created_at bertipe timestamp. Batas awal dipakai apa adanya (>= 00:00:00),
        // batas akhir WAJIB endOfDay() — `<= '2026-07-22'` diartikan MySQL sebagai
        // `<= '2026-07-22 00:00:00'` sehingga seluruh isi hari terakhir hilang dari
        // hasil. Itu bug yang tidak ada di versi whereDate(), jadi jangan
        // disederhanakan jadi where() polos.
        if ($startDate !== '') {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate !== '') {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        // Hitung metrik agregat ringkasan berdasarkan query terfilter
        $summaryQuery = clone $query;
        $totalBatchesCount = $summaryQuery->count();
        $totalRowsSum = $summaryQuery->sum('total_rows');
        $totalImportedSum = $summaryQuery->sum('imported_rows');
        $totalInvalidSum = $summaryQuery->sum('invalid_rows');

        // Dapatkan data terpaginasi
        $batches = $query->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $allowedStatuses = ['pending', 'imported', 'failed'];

        return view('reports.imports.index', compact(
            'batches',
            'search',
            'status',
            'startDate',
            'endDate',
            'totalBatchesCount',
            'totalRowsSum',
            'totalImportedSum',
            'totalInvalidSum',
            'allowedStatuses'
        ));
    }

    /**
     * Display detail of a specific import batch and its errors.
     */
    public function show($id)
    {
        $user = auth()->user();

        if (! $user->hasPermission('reports.view')) {
            abort(403, 'Unauthorized action.');
        }

        $batch = ImportBatch::with(['user', 'errors'])->findOrFail($id);

        // Batasi akses jika bukan Owner atau Admin Pusat
        if (! $user->hasFullAccess()) {
            if ($batch->uploaded_by !== $user->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        return view('reports.imports.show', compact('batch'));
    }

    /**
     * Export error logs for a specific batch.
     */
    public function export($id): StreamedResponse
    {
        $user = auth()->user();

        if (! $user->hasPermission('reports.view')) {
            abort(403, 'Unauthorized action.');
        }

        $batch = ImportBatch::with('errors')->findOrFail($id);

        // Batasi akses jika bukan Owner/Admin Pusat
        if (! $user->hasFullAccess()) {
            if ($batch->uploaded_by !== $user->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-error-import-'.$batch->batch_number.'-'.now()->format('YmdHis').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($batch) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM for proper Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Kolom Header
            fputcsv($file, [
                'Nomor Batch',
                'Nama File / Sumber',
                'Baris Excel/CSV',
                'Nama Kolom Bermasalah',
                'Pesan Error',
                'Data Mentah (JSON)',
            ]);

            foreach ($batch->errors as $error) {
                fputcsv($file, [
                    $batch->batch_number,
                    $batch->file_name,
                    $error->row_number ?? '-',
                    $error->field_name ?? 'Global/DB',
                    $error->error_message,
                    json_encode($error->raw_data),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
