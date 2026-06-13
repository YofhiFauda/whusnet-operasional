<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
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
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoiceAccess($invoice);

        if ($invoice->invoice_status === 'lunas') {
            return redirect()
                ->route('invoices.show', $invoice->id)
                ->withErrors(['amount' => 'Tagihan ini sudah lunas.']);
        }

        if ($invoice->invoice_status === 'batal') {
            return redirect()
                ->route('invoices.show', $invoice->id)
                ->withErrors(['amount' => 'Tagihan yang batal tidak dapat menerima pembayaran.']);
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,transfer,qris,lainnya',
            'amount' => 'required|numeric|min:1|max:' . (float) $invoice->remaining_amount,
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'note' => 'nullable|string|max:1000',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof_file')) {
            $proofPath = $request->file('proof_file')->store('payments', 'public');
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
            $invoiceStatus = $remainingAmount <= 0 ? 'lunas' : 'sebagian';

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
                'payment_status' => 'valid',
                'note' => $validated['note'] ?? null,
            ]);

            $lockedInvoice->update([
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'invoice_status' => $invoiceStatus,
            ]);

            return $payment;
        });

        return redirect()
            ->route('invoices.show', $invoice->id)
            ->with('success', "Pembayaran {$payment->payment_number} berhasil dicatat.");
    }

    private function authorizeInvoiceAccess(Invoice $invoice): void
    {
        abort_unless(
            Invoice::query()->forUser()->whereKey($invoice->id)->exists(),
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
