<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\User;
use App\Services\CustomerBalanceService;
use Illuminate\Http\JsonResponse;
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
        $allowedStatuses = array_column(InvoiceStatus::cases(), 'value');

        $query = Invoice::query()
            ->applyUserScope()
            ->with([
                'customer.collector', 'pop', 'customerService', 'internetPackage',
                // Dipakai baris anak "Cicilan Ke-N" di list. Hanya pembayaran
                // VALID — yang ditolak tak boleh ikut menomori cicilan.
                'payments' => fn ($q) => $q->where('payment_status', PaymentStatus::VALID->value)
                    ->with('collector:id,name')
                    ->orderBy('payment_date')
                    ->orderBy('id'),
            ])
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
                            ->orWhere('primary_phone', 'like', "%{$search}%");
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
            $query->where('invoice_status', InvoiceStatus::LUNAS->value);
        } elseif ($statusGroup === 'belum_lunas') {
            $query->whereIn('invoice_status', [InvoiceStatus::BELUM_DIBAYAR->value, InvoiceStatus::SEBAGIAN->value]);
        } elseif ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $query->where('invoice_status', $status);
        }

        $invoices = $query->paginate(10)->withQueryString();
        $pops = Pop::forUser()->orderBy('name')->get();

        // Ringkasan tunggakan (independen dari filter/search di atas), supaya
        // admin lihat total nunggak AWAL vs BULANAN tanpa hitung manual per baris.
        $unpaidBase = Invoice::query()
            ->applyUserScope()
            ->whereIn('invoice_status', [InvoiceStatus::BELUM_DIBAYAR->value, InvoiceStatus::SEBAGIAN->value]);

        $unpaidAwalTotal = (clone $unpaidBase)
            ->whereIn('invoice_type', ['awal', 'reaktivasi'])
            ->sum('remaining_amount');

        $unpaidBulananTotal = (clone $unpaidBase)
            ->where('invoice_type', 'bulanan')
            ->sum('remaining_amount');

        return view('invoices.index', compact(
            'invoices',
            'pops',
            'search',
            'popId',
            'billingPeriod',
            'status',
            'statusGroup',
            'invoiceType',
            'allowedStatuses',
            'unpaidAwalTotal',
            'unpaidBulananTotal'
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
    public function show(Invoice $invoice): View|JsonResponse
    {
        abort_unless(
            Invoice::query()->applyUserScope()->whereKey($invoice->id)->exists(),
            403,
            'Anda tidak memiliki akses ke tagihan POP ini.'
        );

        $invoice->load([
            'customer.pop',
            'customer.customerAddress',
            'customer.village',
            'customer.district',
            'customer.city',
            'pop',
            'customerService',
            'internetPackage',
            'creator',
            'payments' => function ($query) {
                $query->with(['receiver', 'collector'])->latest('payment_date')->latest('id');
            },
        ]);

        if (request()->wantsJson() || request()->expectsJson()) {
            if ($invoice->customer) {
                $invoice->customer->append('clean_address');
            }

            $payload = $invoice->toArray();

            // Data untuk Modal Bayar (quick-payment-modal.blade.php): saldo
            // pelanggan yang bisa dipakai + daftar kolektor buat metode
            // Kolektor. Ditumpangkan di payload invoice yang sudah dipanggil
            // modal itu — satu fetch, bukan endpoint tambahan.
            $payload['customer_balance'] = $invoice->customer
                ? app(CustomerBalanceService::class)->balance($invoice->customer)
                : 0.0;

            $payload['available_collectors'] = User::query()
                ->whereHas('role', fn ($q) => $q->where('code', 'kolektor'))
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json($payload);
        }

        return view('invoices.show', compact('invoice'));
    }
}
