<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the operational dashboard.
     */
    public function index(Request $request): View
    {
        $popId = $request->query('pop_id', '');
        $periodFrom = $this->normalizePeriod($request->query('period_from')) ?? now()->format('Y-m');
        $periodTo = $this->normalizePeriod($request->query('period_to')) ?? $periodFrom;

        if ($periodFrom > $periodTo) {
            [$periodFrom, $periodTo] = [$periodTo, $periodFrom];
        }

        $periodStartDate = Carbon::createFromFormat('Y-m', $periodFrom)->startOfMonth();
        $periodEndDate = Carbon::createFromFormat('Y-m', $periodTo)->endOfMonth();

        $customerQuery = $this->scopedCustomerQuery($popId);
        $invoiceQuery = $this->scopedInvoiceQuery($popId);
        $paymentQuery = $this->scopedPaymentQuery($popId);

        $periodInvoiceQuery = (clone $invoiceQuery)
            ->whereBetween('billing_period', [$periodFrom, $periodTo]);

        $periodPaymentQuery = (clone $paymentQuery)
            ->whereBetween('payment_date', [$periodStartDate->toDateString(), $periodEndDate->toDateString()]);

        $stats = [
            'total_customers' => (clone $customerQuery)->count(),
            'active_customers' => (clone $customerQuery)->where('status', 'active')->count(),
            'incomplete_customers' => (clone $customerQuery)
                ->whereIn('data_completeness_status', ['draft', 'perlu_dilengkapi'])
                ->count(),
            'ready_billing_customers' => (clone $customerQuery)
                ->where('data_completeness_status', 'siap_billing')
                ->count(),
            'total_invoices_amount' => (float) (clone $periodInvoiceQuery)->sum('total_amount'),
            'total_payments_amount' => (float) (clone $periodPaymentQuery)
                ->where('payment_status', 'valid')
                ->sum('amount'),
            'total_unpaid_amount' => (float) (clone $periodInvoiceQuery)
                ->whereNotIn('invoice_status', ['lunas', 'batal'])
                ->sum('remaining_amount'),
            'due_invoices_count' => (clone $invoiceQuery)
                ->whereNotIn('invoice_status', ['lunas', 'batal'])
                ->whereDate('due_date', '<=', now()->toDateString())
                ->count(),
        ];

        $customersByPop = (clone $customerQuery)
            ->with('pop')
            ->selectRaw('pop_id, count(*) as total')
            ->groupBy('pop_id')
            ->orderByDesc('total')
            ->get();

        $dueInvoices = (clone $invoiceQuery)
            ->with(['customer', 'pop'])
            ->whereNotIn('invoice_status', ['lunas', 'batal'])
            ->whereDate('due_date', '<=', now()->toDateString())
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $incompleteCustomers = (clone $customerQuery)
            ->with('pop')
            ->whereIn('data_completeness_status', ['draft', 'perlu_dilengkapi'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $pops = Pop::forUser()->orderBy('name')->get();

        $filters = [
            'pop_id' => $popId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'period_label' => $periodFrom === $periodTo ? $periodFrom : "{$periodFrom} s/d {$periodTo}",
        ];

        return view('dashboard', compact(
            'stats',
            'customersByPop',
            'dueInvoices',
            'incompleteCustomers',
            'pops',
            'filters'
        ));
    }

    private function scopedCustomerQuery(string|int|null $popId)
    {
        return Customer::query()
            ->forUser()
            ->when($popId !== null && $popId !== '', fn ($query) => $query->where('pop_id', $popId));
    }

    private function scopedInvoiceQuery(string|int|null $popId)
    {
        return Invoice::query()
            ->forUser()
            ->when($popId !== null && $popId !== '', fn ($query) => $query->where('pop_id', $popId));
    }

    private function scopedPaymentQuery(string|int|null $popId)
    {
        return Payment::query()
            ->forUser()
            ->when($popId !== null && $popId !== '', fn ($query) => $query->where('pop_id', $popId));
    }

    private function normalizePeriod(mixed $period): ?string
    {
        if (!is_string($period) || !preg_match('/^\d{4}-\d{2}$/', $period)) {
            return null;
        }

        return $period;
    }
}
