<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Display the operational dashboard.
     */
    public function index(Request $request)
    {
        if (auth()->user()->hasRole('fop')) {
            return redirect()->route('fop.dashboard');
        }

        if (! auth()->user()->hasPermission('dashboard.view')) {
            if (auth()->user()->hasPermission('task.view.own')) {
                return redirect()->route('tasks.own');
            }
            if (auth()->user()->hasPermission('customers.view')) {
                return redirect()->route('customers.index');
            }
            // Kolektor bisa punya HANYA kolektor.view (worklist read-only,
            // §B-8 no. 5 — sengaja tanpa dashboard.view/customers.view sama
            // sekali). Tanpa fallback ini, login sukses tapi redirect default
            // ke '/' (dashboard) langsung abort 403 — kelihatan kayak "gagal
            // login" padahal auth-nya sah, cuma landing page-nya salah.
            if (auth()->user()->hasPermission('kolektor.view')) {
                return redirect()->route('collector-worklist.index');
            }
            abort(403, 'Unauthorized action.');
        }

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

        // Cache 60 detik per user+filter — HANYA angka stat cards (nilai
        // skalar, aman diserialize cache store apa pun). Koleksi model
        // (customersByPop/dueInvoices/incompleteCustomers/pops) SENGAJA tidak
        // ikut di-cache: round-trip Eloquent Collection lewat cache store
        // (file/redis) di environment ini korup jadi __PHP_Incomplete_Class
        // (kena reproduksi juga di luar app, langsung lewat Cache::put pada
        // collect([1,2,3]) polos — bukan spesifik ke query dashboard ini).
        // Jangan cache object/Collection sampai bug itu ditelusuri terpisah.
        $cacheKey = sprintf(
            'dashboard:main:stats:%d:%s:%s:%s',
            auth()->id(),
            $popId === null ? '' : $popId,
            $periodFrom,
            $periodTo
        );

        $stats = Cache::remember(
            $cacheKey,
            60,
            fn () => [
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
                    ->where('payment_status', PaymentStatus::VALID->value)
                    ->sum('amount'),
                'total_unpaid_amount' => (float) (clone $periodInvoiceQuery)
                    ->whereNotIn('invoice_status', [InvoiceStatus::LUNAS->value, InvoiceStatus::BATAL->value])
                    ->sum('remaining_amount'),
                'due_invoices_count' => (clone $invoiceQuery)
                    ->whereNotIn('invoice_status', [InvoiceStatus::LUNAS->value, InvoiceStatus::BATAL->value])
                    // whereDate() membungkus kolom jadi DATE(due_date) dan mematikan
                    // index. endOfDay() wajib: sqlite menyimpan kolom date sebagai
                    // '2026-07-22 00:00:00', jadi `<= '2026-07-22'` membuang tagihan
                    // yang jatuh tempo hari ini.
                    ->where('due_date', '<=', now()->endOfDay())
                    ->count(),
            ]
        );

        $customersByPop = (clone $customerQuery)
            ->with('pop')
            ->selectRaw('pop_id, count(*) as total')
            ->groupBy('pop_id')
            ->orderByDesc('total')
            ->get();

        $dueInvoices = (clone $invoiceQuery)
            ->with(['customer', 'pop'])
            ->whereNotIn('invoice_status', [InvoiceStatus::LUNAS->value, InvoiceStatus::BATAL->value])
            ->where('due_date', '<=', now()->endOfDay())
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
            ->applyUserScope()
            ->when($popId !== null && $popId !== '', fn ($query) => $query->where('pop_id', $popId));
    }

    private function scopedInvoiceQuery(string|int|null $popId)
    {
        return Invoice::query()
            ->applyUserScope()
            ->when($popId !== null && $popId !== '', fn ($query) => $query->where('pop_id', $popId));
    }

    private function scopedPaymentQuery(string|int|null $popId)
    {
        return Payment::query()
            ->applyUserScope()
            ->when($popId !== null && $popId !== '', fn ($query) => $query->where('pop_id', $popId));
    }

    private function normalizePeriod(mixed $period): ?string
    {
        if (! is_string($period) || ! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return null;
        }

        return $period;
    }
}
