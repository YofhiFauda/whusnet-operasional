<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\User;
use App\Services\CollectorPaymentReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Laporan Bayar Kolektor (ADHOC-90). Tipis: validasi filter, delegasi ke
 * CollectorPaymentReportService. Data dibatasi POP scope di service.
 */
class CollectorPaymentReportController extends Controller
{
    public function index(Request $request, CollectorPaymentReportService $service): View
    {
        $filters = $this->filters($request);
        $report = $service->build($request->user(), $filters['collector_id'], $filters['start_date'], $filters['end_date'], $filters['method']);

        return view('reports.collector-payments.index', $filters + [
            'collectorId' => $filters['collector_id'],
            'groups' => $report['groups'],
            'total' => $report['total'],
            'count' => $report['count'],
            'collectors' => $this->collectors(),
            'methods' => PaymentMethod::cases(),
            'canExport' => $request->user()->hasPermission('collector_payment_report.export'),
        ]);
    }

    public function export(Request $request, CollectorPaymentReportService $service): BinaryFileResponse
    {
        $filters = $this->filters($request);
        $report = $service->build($request->user(), $filters['collector_id'], $filters['start_date'], $filters['end_date'], $filters['method']);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laporan-bayar-kolektor-'.uniqid().'.xlsx';
        $writer = SimpleExcelWriter::create($path);
        $writer->nameCurrentSheet('Bayar Kolektor');

        foreach ($report['groups'] as $group) {
            $last = $group['payments']->count() - 1;

            foreach ($group['payments'] as $i => $payment) {
                $writer->addRow([
                    'Tanggal' => $i === 0 ? $group['date']->format('d-m-y') : '',
                    'Kolektor' => $payment->collector?->name ?? '',
                    'Akun' => $payment->customer?->cid ?? $payment->customer?->customer_code ?? '',
                    'Nama Pelanggan' => $payment->customer?->full_name ?? '',
                    'Alamat' => $payment->customer?->address ?? '',
                    'Metode' => $payment->payment_method instanceof PaymentMethod ? $payment->payment_method->label() : (string) $payment->payment_method,
                    'Jumlah' => (float) $payment->amount,
                    'Total Sub' => $i === $last ? $group['subtotal'] : '',
                    'Keterangan' => (string) $payment->note,
                ]);
            }
        }

        $writer->close();

        return response()->download($path, 'laporan-bayar-kolektor-'.$filters['start_date'].'_'.$filters['end_date'].'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return array{collector_id: ?int, start_date: string, end_date: string, method: ?string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'collector_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'method' => ['nullable', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
        ]);

        return [
            'collector_id' => isset($validated['collector_id']) ? (int) $validated['collector_id'] : null,
            'start_date' => $validated['start_date'] ?? now()->startOfMonth()->toDateString(),
            'end_date' => $validated['end_date'] ?? now()->toDateString(),
            'method' => $validated['method'] ?? null,
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function collectors()
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('code', 'kolektor'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
