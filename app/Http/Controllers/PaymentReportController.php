<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPeriodType;
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
        // Filter Jenis (ADHOC-84 §8.2) — Bulanan/Piutang/Cicilan/Lebih Bayar,
        // dihitung dari data yang sama dengan Payment::classification(),
        // BUKAN kolom tersimpan.
        $classification = $request->query('classification', '');

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
        $this->applyClassificationFilter($query, $classification);

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

        // Dulu hardcode ['cash','transfer','qris','lainnya'] — ketinggalan
        // 'kolektor' (PaymentMethod::KOLEKTOR), jadi dropdown filter Metode
        // tak pernah bisa memilih transaksi kolektor. Baca dari enum supaya
        // metode baru (mis. 'saldo' — ADHOC-92) otomatis ikut tanpa disentuh lagi.
        $allowedMethods = array_column(PaymentMethod::cases(), 'value');
        $allowedStatuses = array_column(PaymentStatus::cases(), 'value');
        $allowedClassifications = PaymentPeriodType::cases();

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
            'classification',
            'totalAmountSum',
            'totalValidSum',
            'totalDitolakSum',
            'allowedMethods',
            'allowedStatuses',
            'allowedClassifications'
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
        $classification = $request->query('classification', '');

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
        $this->applyClassificationFilter($query, $classification);

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
     * Filter "Jenis" (ADHOC-84 §8.2/§8.1) — SQL setara `Payment::classification()`,
     * bukan diketik ulang beda logika.
     *
     * SENGAJA tidak `join`/`leftJoin` ke `invoices`: query ini sudah lewat
     * `applyUserScope()` (HasPopScope) yang menulis `where('pop_id', ...)`
     * TANPA prefix tabel — `invoices` juga punya kolom `pop_id`, jadi sebuah
     * join di sini akan membuat filter POP scope error "ambiguous column"
     * (atau, lebih parah, diam-diam salah tabel). `whereHas`/subquery
     * korelasi TIDAK menambah tabel ke FROM utama, jadi aman dipakai
     * berdampingan dengan scope manapun.
     *
     * Base (Bulanan/Piutang) turun dari posisi `invoices.billing_period`
     * terhadap bulan payment SENDIRI (`collected_date` ?: `payment_date`,
     * string 'Y-m-d' → 'Y-m' lewat SUBSTR, portable sqlite/MySQL — bukan
     * `DATE_FORMAT()`). Cicilan turun dari subquery jumlah berjalan payment
     * VALID invoice yang sama dibanding total tagihan — replika
     * `Payment::installmentContext()` tanpa memuat model satu-satu.
     *
     * @param  Builder<Payment>  $query
     */
    private function applyClassificationFilter($query, string $classification): void
    {
        if ($classification === '' || ! in_array($classification, array_column(PaymentPeriodType::cases(), 'value'), true)) {
            return;
        }

        $referenceMonthExpr = 'SUBSTR(COALESCE(payments.collected_date, payments.payment_date), 1, 7)';

        match ($classification) {
            'bulanan' => $query->where(fn ($q) => $q->whereDoesntHave('invoice')
                ->orWhereHas('invoice', fn ($iq) => $iq->whereRaw("invoices.billing_period >= {$referenceMonthExpr}"))),
            'piutang' => $query->whereHas('invoice', fn ($iq) => $iq->whereRaw("invoices.billing_period < {$referenceMonthExpr}")),
            'lebih_bayar' => $query->where('payments.overpay_amount', '>', 0),
            'cicilan' => $query->where(fn ($q) => $q->whereNull('payments.overpay_amount')->orWhere('payments.overpay_amount', '<=', 0))
                ->whereRaw(
                    'COALESCE((SELECT SUM(p2.amount) FROM payments p2 WHERE p2.invoice_id = payments.invoice_id AND p2.payment_status = ? AND (p2.payment_date < payments.payment_date OR (p2.payment_date = payments.payment_date AND p2.id <= payments.id))), 0)'
                    .' < COALESCE((SELECT total_amount FROM invoices WHERE invoices.id = payments.invoice_id), 0)',
                    [PaymentStatus::VALID->value]
                ),
            default => null,
        };
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
            'Jenis',
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
            implode(' + ', array_map(fn (PaymentPeriodType $label) => $label->label(), $payment->classification())),
            $payment->receiver->name ?? '-',
            $payment->payment_status->label(),
            $payment->note ?? '-',
        ];
    }
}
