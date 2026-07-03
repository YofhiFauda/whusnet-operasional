<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Pop;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $popId = $request->query('pop_id', '');
        $billingPeriod = trim((string) $request->query('billing_period', ''));
        $status = trim((string) $request->query('status', ''));
        $statusGroup = trim((string) $request->query('status_group', ''));
        $invoiceType = trim((string) $request->query('invoice_type', ''));
        $allowedStatuses = ['belum_dibayar', 'sebagian', 'lunas', 'batal'];

        $query = Invoice::query()
            ->applyUserScope()
            ->with(['customer', 'pop', 'customerService', 'internetPackage'])
            ->latest('issue_date')
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('old_invoice_id', 'like', "%{$search}%")
                    ->orWhere('old_cost_id', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('full_name', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%")
                            ->orWhere('old_customer_id', 'like', "%{$search}%")
                            ->orWhere('cid', 'like', "%{$search}%")
                            ->orWhere('primary_phone', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        if ($billingPeriod !== '') {
            $query->where('billing_period', $billingPeriod);
        }

        if ($invoiceType !== '') {
            $query->where('invoice_type', $invoiceType);
        }

        if ($statusGroup === 'lunas') {
            $query->where('invoice_status', 'lunas');
        } elseif ($statusGroup === 'belum_lunas') {
            $query->whereIn('invoice_status', ['belum_dibayar', 'sebagian']);
        } elseif ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $query->where('invoice_status', $status);
        }

        $invoices = $query->paginate(10)->withQueryString();
        $pops = Pop::forUser()->orderBy('name')->get();

        return view('invoices.index', compact(
            'invoices',
            'pops',
            'search',
            'popId',
            'billingPeriod',
            'status',
            'statusGroup',
            'invoiceType',
            'allowedStatuses'
        ));
    }

    public function lunas(Request $request): View
    {
        $request->merge(['status_group' => 'lunas']);
        return $this->index($request);
    }

    public function belumLunas(Request $request): View
    {
        $request->merge(['status_group' => 'belum_lunas']);
        return $this->index($request);
    }

    /**
     * Display a single invoice detail.
     */
    public function show(Invoice $invoice): View
    {
        abort_unless(
            Invoice::query()->applyUserScope()->whereKey($invoice->id)->exists(),
            403,
            'Anda tidak memiliki akses ke tagihan POP ini.'
        );

        $invoice->load([
            'customer.pop',
            'pop',
            'customerService',
            'internetPackage',
            'creator',
            'payments' => function ($query) {
                $query->with('receiver')->latest('payment_date')->latest('id');
            },
        ]);

        return view('invoices.show', compact('invoice'));
    }
}
