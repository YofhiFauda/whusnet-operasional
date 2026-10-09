<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Pop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\SimpleExcel\SimpleExcelWriter;
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
        if (! $user->hasPermission('reports.view')) {
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
            if (! in_array((int) $popId, $allowedPopIds)) {
                $popId = '';
            }
        }

        // Base query
        $query = Invoice::with(['customer', 'pop', 'internetPackage'])
            ->applyUserScope();

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
            // whereDate() membungkus kolom jadi DATE(issue_date) dan mematikan
            // index. Batas ditulis eksplisit startOfDay/endOfDay — lihat alasan
            // lengkapnya di CustomerReportController::index().
            $query->where('issue_date', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate !== '') {
            $query->where('issue_date', '<=', Carbon::parse($endDate)->endOfDay());
        }

        if ($showTunggakan) {
            // Tunggakan = piutang (periode sebelum bulan berjalan).
            $query->piutang();
        }

        // Clone query untuk menghitung agregat ringkasan sebelum dipaginasi
        $summaryQuery = clone $query;
        $totalAmountSum = $summaryQuery->sum('total_amount');
        $totalPaidSum = $summaryQuery->sum('paid_amount');

        // Total tunggakan = sisa piutang saja (periode sebelum bulan berjalan),
        // bukan semua sisa tagihan — tagihan bulan ini yang belum dibayar belum
        // tunggakan. Lihat Invoice::scopePiutang().
        $totalTunggakanSum = (clone $summaryQuery)->piutang()->sum('remaining_amount');

        // Dapatkan data terpaginasi
        $invoices = $query->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $allowedStatuses = array_column(InvoiceStatus::cases(), 'value');

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
        $query = $this->exportQuery($request);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-tagihan-'.now()->format('YmdHis').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM for proper Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, $this->exportHeaderRow());

            // Query dieksekusi di dalam closure stream pakai `lazy()` supaya
            // baris ditarik & ditulis sambil jalan, bukan seluruh hasil
            // dimuat ke memori PHP dulu (240.000 invoice = 240.000 model
            // sekaligus di RAM kalau pakai `get()`).
            foreach ($query->lazy(500) as $invoice) {
                fputcsv($file, $this->exportDataRow($invoice));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export invoice report to XLSX — format asli Excel, konsisten dengan
     * Laporan Pembayaran (`PaymentReportController::exportXlsx`). Repo ini
     * pakai Excel sebagai format arsip, bukan CSV (2026-09-22).
     */
    public function exportXlsx(Request $request)
    {
        $query = $this->exportQuery($request);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laporan-tagihan-'.uniqid().'.xlsx';
        $writer = SimpleExcelWriter::create($path);

        $header = $this->exportHeaderRow();

        $query->chunk(500, function ($invoices) use ($writer, $header) {
            $writer->addRows($invoices->map(fn (Invoice $invoice) => array_combine(
                $header,
                $this->exportDataRow($invoice)
            ))->all());
        });

        return response()->download($path, 'laporan-tagihan-'.now()->format('Ymd-His').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * @return Builder<Invoice>
     */
    private function exportQuery(Request $request)
    {
        $user = auth()->user();

        if (! $user->hasPermission('reports.view')) {
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
            if (! in_array((int) $popId, $allowedPopIds)) {
                abort(403, 'Unauthorized action.');
            }
        }

        $query = Invoice::with(['customer', 'pop', 'internetPackage'])
            ->applyUserScope();

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
            // whereDate() membungkus kolom jadi DATE(issue_date) dan mematikan
            // index. Batas ditulis eksplisit startOfDay/endOfDay — lihat alasan
            // lengkapnya di CustomerReportController::index().
            $query->where('issue_date', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate !== '') {
            $query->where('issue_date', '<=', Carbon::parse($endDate)->endOfDay());
        }

        if ($showTunggakan) {
            $query->piutang();
        }

        return $query->orderByDesc('issue_date')->orderByDesc('id');
    }

    /**
     * @return array<int, string>
     */
    private function exportHeaderRow(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<int, string|float>
     */
    private function exportDataRow(Invoice $invoice): array
    {
        return [
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
            $invoice->invoice_status->label(),
        ];
    }
}
