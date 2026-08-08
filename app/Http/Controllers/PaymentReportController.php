<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\SimpleExcel\SimpleExcelWriter;
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
        if (! $user->hasPermission('reports.view')) {
            abort(403, 'Unauthorized action.');
        }

        // Ambil data filter
        $popId = $request->query('pop_id', '');
        $paymentMethod = $request->query('payment_method', '');
        $status = $request->query('status', '');
        $collectorId = $request->query('collector_id', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');

        // POP yang bisa diakses user. Pop::forUser() lewat EffectiveAccessService
        // (paham pop_tree + deny-by-default) — BUKAN whereHas('users') pivot
        // user_pops, yang buta pop_tree dan bikin user ber-scope pop_tree
        // kehilangan POP turunan di dropdown/laporan tanpa error apa pun
        // (docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §D-2 no. 3).
        $pops = Pop::forUser()->orderBy('name')->get();
        $allowedPopIds = $pops->pluck('id')->toArray();

        // Jika user memfilter POP tertentu, pastikan POP itu ada di dalam POP yang diizinkan untuknya
        if ($popId !== '') {
            if (! in_array((int) $popId, $allowedPopIds)) {
                $popId = '';
            }
        }

        // Dropdown filter Kolektor — semua user ber-role kolektor. Tanpa
        // filter aktif, ringkasan/tabel tetap tampilkan semua data (termasuk
        // non-kolektor), tidak disembunyikan (§D-2 no. 2).
        $collectors = User::query()
            ->whereHas('role', fn ($q) => $q->where('code', 'kolektor'))
            ->orderBy('name')
            ->get();

        // Base query
        $query = Payment::with(['customer', 'pop', 'invoice', 'receiver', 'collector'])
            ->applyUserScope(auth()->user());

        $this->applyFilters($query, $popId, $paymentMethod, $status, $collectorId, $startDate, $endDate);

        // Clone query untuk menghitung agregat ringkasan sebelum dipaginasi
        $summaryQuery = clone $query;
        $totalAmountSum = $summaryQuery->sum('amount');
        $totalValidSum = (clone $summaryQuery)->where('payment_status', PaymentStatus::VALID->value)->sum('amount');
        $totalDitolakSum = (clone $summaryQuery)->where('payment_status', PaymentStatus::DITOLAK->value)->sum('amount');

        // Dapatkan data terpaginasi
        $payments = $query->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $allowedMethods = ['cash', 'transfer', 'qris', 'lainnya'];
        $allowedStatuses = array_column(PaymentStatus::cases(), 'value');

        return view('reports.payments.index', compact(
            'payments',
            'pops',
            'popId',
            'paymentMethod',
            'status',
            'collectors',
            'collectorId',
            'startDate',
            'endDate',
            'totalAmountSum',
            'totalValidSum',
            'totalDitolakSum',
            'allowedMethods',
            'allowedStatuses'
        ));
    }

    /**
     * Export payment report to CSV stream.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->exportQuery($request);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-pembayaran-'.now()->format('YmdHis').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM for proper Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, $this->exportHeaderRow());

            // Query dieksekusi di dalam closure stream pakai `lazy()` — lihat
            // alasan yang sama di InvoiceReportController::export().
            foreach ($query->lazy(500) as $payment) {
                fputcsv($file, $this->exportDataRow($payment));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export payment report to XLSX (E2.8) — format asli Excel untuk
     * laporan yang diarsipkan, bukan cuma CSV. Pakai spatie/simple-excel
     * yang sudah jadi dependency (dipakai import pelanggan & history
     * ticketing), bukan dependency baru.
     */
    public function exportXlsx(Request $request)
    {
        $query = $this->exportQuery($request);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laporan-pembayaran-'.uniqid().'.xlsx';
        $writer = SimpleExcelWriter::create($path);

        $header = $this->exportHeaderRow();

        $query->chunk(500, function ($payments) use ($writer, $header) {
            $writer->addRows($payments->map(fn (Payment $payment) => array_combine(
                $header,
                $this->exportDataRow($payment)
            ))->all());
        });

        return response()->download($path, 'laporan-pembayaran-'.now()->format('Ymd-His').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * @return Builder<Payment>
     */
    private function exportQuery(Request $request)
    {
        $user = auth()->user();

        if (! $user->hasPermission('reports.view')) {
            abort(403, 'Unauthorized action.');
        }

        $popId = $request->query('pop_id', '');
        $paymentMethod = $request->query('payment_method', '');
        $status = $request->query('status', '');
        $collectorId = $request->query('collector_id', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');

        // Pastikan input pop_id divalidasi dengan POP yang diizinkan untuk user
        // ini — Pop::forUser(), sama seperti index() di atas, bukan whereHas('users').
        $allowedPopIds = Pop::forUser()->pluck('id')->toArray();
        if ($popId !== '') {
            if (! in_array((int) $popId, $allowedPopIds)) {
                abort(403, 'Unauthorized action.');
            }
        }

        $query = Payment::with(['customer', 'pop', 'invoice', 'receiver', 'collector'])
            ->applyUserScope(auth()->user());

        $this->applyFilters($query, $popId, $paymentMethod, $status, $collectorId, $startDate, $endDate);

        return $query->orderByDesc('payment_date')->orderByDesc('id');
    }

    /**
     * @param  Builder<Payment>  $query
     */
    private function applyFilters(
        $query,
        string $popId,
        string $paymentMethod,
        string $status,
        string $collectorId,
        string $startDate,
        string $endDate
    ): void {
        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        if ($paymentMethod !== '') {
            $query->where('payment_method', $paymentMethod);
        }

        if ($status !== '') {
            $query->where('payment_status', $status);
        }

        if ($collectorId !== '') {
            $query->where('collected_by', $collectorId);
        }

        if ($startDate !== '') {
            // whereDate() membungkus kolom jadi DATE(payment_date) dan mematikan
            // index. Batas ditulis eksplisit startOfDay/endOfDay — lihat alasan
            // lengkapnya di CustomerReportController::index().
            $query->where('payment_date', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate !== '') {
            $query->where('payment_date', '<=', Carbon::parse($endDate)->endOfDay());
        }
    }

    /**
     * @return array<int, string>
     */
    private function exportHeaderRow(): array
    {
        return [
            'No. Kwitansi/Transaksi',
            'No. Invoice',
            'Kode Pelanggan',
            'Nama Pelanggan',
            'POP/Cabang',
            'Tanggal Bayar',
            'Metode Pembayaran',
            'Kolektor',
            'Nominal Pembayaran',
            'Penerima/Petugas',
            'Status Pembayaran',
            'Catatan',
        ];
    }

    /**
     * @return array<int, string|float>
     */
    private function exportDataRow(Payment $payment): array
    {
        return [
            $payment->payment_number,
            $payment->invoice->invoice_number ?? '-',
            $payment->customer->customer_code ?? '-',
            $payment->customer->full_name ?? '-',
            $payment->pop->name ?? '-',
            $payment->payment_date ? $payment->payment_date->format('Y-m-d') : '-',
            strtoupper($payment->payment_method),
            $payment->collector->name ?? 'Langsung',
            (float) $payment->amount,
            $payment->receiver->name ?? '-',
            $payment->payment_status->label(),
            $payment->note ?? '-',
        ];
    }
}
