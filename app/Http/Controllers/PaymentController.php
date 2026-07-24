<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments with operational filters.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $popId = $request->query('pop_id', '');
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $method = trim((string) $request->query('method', ''));
        $status = trim((string) $request->query('status', ''));
        $invoiceType = trim((string) $request->query('invoice_type', ''));
        $allowedMethods = ['cash', 'transfer', 'qris', 'lainnya'];
        $allowedStatuses = array_column(PaymentStatus::cases(), 'value');

        $query = Payment::query()
            ->applyUserScope()
            ->with(['invoice', 'customer', 'pop', 'receiver'])
            ->latest('payment_date')
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('old_payment_id', 'like', "%{$search}%")
                    ->orWhere('old_transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('invoice', function ($invoiceQuery) use ($search) {
                        $invoiceQuery->where('invoice_number', 'like', "%{$search}%")
                            ->orWhere('old_invoice_id', 'like', "%{$search}%")
                            ->orWhere('old_cost_id', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('full_name', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%")
                            ->orWhere('old_customer_id', 'like', "%{$search}%")
                            ->orWhere('cid', 'like', "%{$search}%")
                            ->orWhere('primary_phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        // whereDate() membungkus kolom jadi DATE(payment_date) dan mematikan
        // index. Batas ditulis eksplisit startOfDay/endOfDay — lihat alasan
        // lengkapnya di CustomerReportController::index().
        if ($dateFrom !== '') {
            $query->where('payment_date', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo !== '') {
            $query->where('payment_date', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        if ($method !== '' && in_array($method, $allowedMethods, true)) {
            $query->where('payment_method', $method);
        }

        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $query->where('payment_status', $status);
        }

        if ($invoiceType !== '') {
            $query->whereHas('invoice', function ($invoiceQuery) use ($invoiceType) {
                $invoiceQuery->where('invoice_type', $invoiceType);
            });
        }

        $payments = $query->paginate(10)->withQueryString();
        $pops = Pop::forUser()->orderBy('name')->get();

        return view('payments.index', compact(
            'payments',
            'pops',
            'search',
            'popId',
            'dateFrom',
            'dateTo',
            'method',
            'status',
            'invoiceType',
            'allowedMethods',
            'allowedStatuses'
        ));
    }

    /**
     * Display a single payment detail.
     */
    public function show(Payment $payment): View
    {
        abort_unless(
            Payment::query()->applyUserScope()->whereKey($payment->id)->exists(),
            403,
            'Anda tidak memiliki akses ke pembayaran POP ini.'
        );

        $relations = ['invoice.customerService', 'invoice.internetPackage', 'customer', 'pop', 'receiver'];

        if (auth()->user()->hasPermission('audit_logs.view')) {
            $relations[] = 'auditLogs.user';
        }

        $payment->load($relations);

        return view('payments.show', compact('payment'));
    }

    /**
     * Show payment input form for an invoice.
     */
    public function create(Invoice $invoice): View
    {
        $this->authorizeInvoiceAccess($invoice);

        $invoice->load(['customer', 'pop', 'customerService', 'internetPackage']);

        return view('payments.create', compact('invoice'));
    }

    /**
     * Store payment and update invoice paid/remaining amounts.
     */
    public function store(Request $request, Invoice $invoice): RedirectResponse|JsonResponse
    {
        $this->authorizeInvoiceAccess($invoice);

        if ($invoice->invoice_status === InvoiceStatus::LUNAS) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Tagihan ini sudah lunas.'], 422);
            }

            return redirect()
                ->route('invoices.show', $invoice->id)
                ->withErrors(['amount' => 'Tagihan ini sudah lunas.']);
        }

        if ($invoice->invoice_status === InvoiceStatus::BATAL) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Tagihan yang batal tidak dapat menerima pembayaran.'], 422);
            }

            return redirect()
                ->route('invoices.show', $invoice->id)
                ->withErrors(['amount' => 'Tagihan yang batal tidak dapat menerima pembayaran.']);
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,transfer,qris,lainnya',
            'amount' => 'required|numeric|min:1|max:'.(float) $invoice->remaining_amount,
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'note' => 'nullable|string|max:1000',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof_file')) {
            $invoice->loadMissing('customer');
            $proofPath = FileUploadService::uploadPaymentProof(
                $request->file('proof_file'),
                $invoice->customer,
                $invoice->invoice_type?->value,
                $validated['payment_date']
            );
        }

        $payment = DB::transaction(function () use ($invoice, $validated, $proofPath): Payment {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = round((float) $validated['amount'], 2);

            if ($amount > round((float) $lockedInvoice->remaining_amount, 2)) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran melebihi sisa tagihan.',
                ]);
            }

            $paidAmount = round((float) $lockedInvoice->paid_amount + $amount, 2);
            $remainingAmount = max(0, round((float) $lockedInvoice->total_amount - $paidAmount, 2));
            $invoiceStatus = $remainingAmount <= 0 ? InvoiceStatus::LUNAS->value : InvoiceStatus::SEBAGIAN->value;

            $payment = Payment::create([
                'payment_number' => $this->generatePaymentNumber($validated['payment_date']),
                'invoice_id' => $lockedInvoice->id,
                'customer_id' => $lockedInvoice->customer_id,
                'pop_id' => $lockedInvoice->pop_id,
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'received_by' => auth()->id(),
                'proof_file' => $proofPath,
                'payment_status' => PaymentStatus::VALID->value,
                'note' => $validated['note'] ?? null,
            ]);

            $lockedInvoice->update([
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'invoice_status' => $invoiceStatus,
            ]);

            return $payment;
        });

        if ($request->expectsJson()) {
            $payment->invoice->refresh();

            return response()->json([
                'success' => true,
                'message' => "Pembayaran {$payment->payment_number} berhasil dicatat.",
                'payment' => [
                    'payment_number' => $payment->payment_number,
                    'amount' => (float) $payment->amount,
                ],
                'invoice' => [
                    'id' => $payment->invoice->id,
                    'invoice_status' => $payment->invoice->invoice_status,
                    'paid_amount' => (float) $payment->invoice->paid_amount,
                    'remaining_amount' => (float) $payment->invoice->remaining_amount,
                ],
            ]);
        }

        return redirect()
            ->route('invoices.show', $invoice->id)
            ->with('success', "Pembayaran {$payment->payment_number} berhasil dicatat.");
    }

    /**
     * Bayar massal: lunasi banyak invoice sekaligus (nominal = sisa tagihan
     * masing-masing) dari list global /invoices — dipakai kolektor untuk
     * menyetorkan banyak pembayaran bulanan flat sekaligus, tanpa buka invoice
     * satu-satu.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'integer',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,transfer,qris,lainnya',
            'note' => 'nullable|string|max:1000',
        ]);

        $invoices = Invoice::query()
            ->applyUserScope()
            ->whereIn('id', $validated['invoice_ids'])
            ->whereNotIn('invoice_status', [InvoiceStatus::LUNAS->value, InvoiceStatus::BATAL->value])
            ->get();

        $paid = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            try {
                DB::transaction(function () use ($invoice, $validated) {
                    $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                    $amount = round((float) $lockedInvoice->remaining_amount, 2);
                    if ($amount <= 0) {
                        throw new \RuntimeException('Sisa tagihan sudah nol.');
                    }

                    $paidAmount = round((float) $lockedInvoice->paid_amount + $amount, 2);
                    $remainingAmount = max(0, round((float) $lockedInvoice->total_amount - $paidAmount, 2));

                    Payment::create([
                        'payment_number' => $this->generatePaymentNumber($validated['payment_date']),
                        'invoice_id' => $lockedInvoice->id,
                        'customer_id' => $lockedInvoice->customer_id,
                        'pop_id' => $lockedInvoice->pop_id,
                        'payment_date' => $validated['payment_date'],
                        'payment_method' => $validated['payment_method'],
                        'amount' => $amount,
                        'received_by' => auth()->id(),
                        'payment_status' => PaymentStatus::VALID->value,
                        'note' => $validated['note'] ?? 'Pembayaran massal',
                    ]);

                    $lockedInvoice->update([
                        'paid_amount' => $paidAmount,
                        'remaining_amount' => $remainingAmount,
                        'invoice_status' => $remainingAmount <= 0 ? InvoiceStatus::LUNAS->value : InvoiceStatus::SEBAGIAN->value,
                    ]);
                });

                $paid++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$paid} tagihan berhasil dibayar".($failed > 0 ? ", {$failed} gagal" : '').'.',
            'paid' => $paid,
            'failed' => $failed,
        ]);
    }

    private function authorizeInvoiceAccess(Invoice $invoice): void
    {
        abort_unless(
            Invoice::query()->applyUserScope()->whereKey($invoice->id)->exists(),
            403,
            'Anda tidak memiliki akses ke tagihan POP ini.'
        );
    }

    private function generatePaymentNumber(string $paymentDate): string
    {
        $periodCode = date('Ym', strtotime($paymentDate));

        $lastPayment = Payment::query()
            ->where('payment_number', 'like', "PAY-{$periodCode}-%")
            ->orderBy('payment_number', 'desc')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        if ($lastPayment) {
            $parts = explode('-', $lastPayment->payment_number);
            if (count($parts) === 3) {
                $nextSeq = ((int) $parts[2]) + 1;
            }
        }

        return sprintf('PAY-%s-%04d', $periodCode, $nextSeq);
    }
}
