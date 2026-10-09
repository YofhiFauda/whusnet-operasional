<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\ManualInvoiceCategory;
use App\Enums\PaymentStatus;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\User;
use App\Services\CustomerBalanceService;
use App\Services\InvoiceWriteOffService;
use App\Services\ManualCategoryInvoiceService;
use App\Support\LikeSearch;
use App\Support\ReasonValidationRule;
use App\Support\RupiahInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $search = LikeSearch::sanitize((string) $request->query('search', ''));
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
        // Tunggakan = piutang (periode sebelum bulan berjalan), bukan semua yang
        // belum dibayar — lihat Invoice::scopePiutang().
        $unpaidBase = Invoice::query()
            ->applyUserScope()
            ->piutang();

        $unpaidAwalTotal = (clone $unpaidBase)
            ->where('invoice_type', 'awal')
            ->sum('remaining_amount');

        $unpaidBulananTotal = (clone $unpaidBase)
            ->where('invoice_type', 'bulanan')
            ->sum('remaining_amount');

        $customerIds = $invoices->pluck('customer_id')->filter()->unique();
        $customerIdsWithPiutang = $customerIds->isNotEmpty()
            ? Invoice::query()
                ->applyUserScope()
                ->whereIn('customer_id', $customerIds)
                ->piutang()
                ->pluck('customer_id')
                ->unique()
                ->flip()
                ->all()
            : [];

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
            'unpaidBulananTotal',
            'customerIdsWithPiutang'
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
     * Form Tagihan Manual (ADHOC-70) — dua pintu masuk: Detail Pelanggan
     * (`?customer_id=…`, pelanggan terkunci) & Halaman Tagihan polos (cari
     * dulu lewat CID/Nama). Pencarian pakai pola `whereHas`/`where` yang sama
     * dengan `index()` di atas — bukan mekanisme ketiga.
     */
    public function create(Request $request): View
    {
        $customer = null;
        $customerError = null;

        $customerId = $request->query('customer_id');

        if ($customerId) {
            $customer = Customer::query()
                ->applyUserScope()
                ->with('customerService')
                ->find($customerId);

            abort_unless($customer, 403, 'Anda tidak memiliki akses ke pelanggan ini, atau pelanggan tidak ditemukan.');

            if (! $customer->customerService) {
                $customerError = 'Pelanggan ini belum memiliki layanan aktif — Tagihan Manual butuh layanan aktif untuk menentukan POP & paket.';
            }
        }

        $search = LikeSearch::sanitize((string) $request->query('q', ''));
        $searchResults = collect();

        if (! $customer && $search !== '') {
            $searchResults = Customer::query()
                ->applyUserScope()
                ->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('cid', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%");
                })
                ->orderBy('full_name')
                ->limit(20)
                ->get(['id', 'full_name', 'cid', 'customer_code']);
        }

        $categories = ManualInvoiceCategory::cases();

        // Prefill dari Verifikasi Biaya C-REQ (docs/plan/task-teknisi/
        // rancangan-biaya-creq-verifikasi-cs.md §4-5) — CS TETAP submit
        // form ini secara sadar, cuma pelanggan/kategori/deskripsinya sudah
        // terisi dari task yang baru disetujui. Nominal TETAP wajib diisi
        // manual, tidak pernah dikirim lewat query.
        $prefill = [
            'manual_category' => $request->query('manual_category'),
            'manual_subtype_name' => $request->query('manual_subtype_name'),
            'description' => $request->query('description'),
        ];

        return view('invoices.create', compact('customer', 'customerError', 'search', 'searchResults', 'categories', 'prefill'));
    }

    /**
     * Terbitkan Tagihan Manual — murni Invoice (`belum_dibayar`), TANPA
     * Payment. Pembayarannya dicatat belakangan lewat jalur normal
     * (List Tagihan → Bayar/Bayar Cicil, `PaymentController::store`) —
     * keputusan user 2026-09-23: metode bayar bukan urusan form Tagihan,
     * itu ranahnya Pembayaran.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(RupiahInput::parseKeys(
            $request->only(['amount']),
            'amount',
        ));

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'manual_category' => ['required', Rule::enum(ManualInvoiceCategory::class)],
            'manual_subtype_name' => 'required_if:manual_category,lainnya|nullable|string|max:150',
            'description' => 'required|string|max:1000',
            'amount' => 'required|numeric|min:1|max:99999999.99',
        ]);

        $customer = Customer::query()->applyUserScope()->with('customerService')->find($validated['customer_id']);

        abort_unless($customer, 403, 'Anda tidak memiliki akses ke pelanggan ini.');

        // Logika penerbitan dipakai bersama Verifikasi Biaya C-REQ — lihat
        // ManualCategoryInvoiceService. ValidationException (pelanggan belum
        // punya layanan) otomatis kembali ke form dengan pesan & input lama.
        $invoice = app(ManualCategoryInvoiceService::class)->issue(
            $customer,
            ManualInvoiceCategory::from($validated['manual_category']),
            $validated['manual_subtype_name'] ?? null,
            $validated['description'],
            $validated['amount'],
            auth()->id(),
        );

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', "Tagihan manual {$invoice->invoice_number} berhasil dibuat.");
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
            // ADHOC-87 — alasan & pembatal kalau invoice ini `batal` lewat
            // Request Putus Langganan/Cuti Berlangganan. Null untuk invoice
            // yang tidak pernah disentuh mekanisme itu (write-off, dedup, dll).
            'billingWaiver.creator',
            // Rincian per kategori pendapatan (ADHOC-60). Bisa kosong untuk
            // tagihan yang terbit sebelum fitur ini — view-nya jatuh balik ke
            // kolom biaya lama, jangan asumsikan selalu terisi.
            'items',
            // Pembayaran yang dikembalikan disembunyikan dari daftar.
            'payments' => function ($query) {
                $query->where('payment_status', PaymentStatus::VALID->value)
                    ->with(['receiver', 'collector'])->latest('payment_date')->latest('id');
            },
        ]);

        // Payment::installmentContext() (dipakai view ini untuk "Cicilan
        // Ke-N") membaca $this->invoice->total_amount — eager-load di atas
        // cuma mengisi arah Invoice->payments, bukan sebaliknya, jadi tanpa
        // ini setiap panggilan lazy-load ulang (ditolak strict mode).
        $invoice->payments->each(fn (Payment $payment) => $payment->setRelation('invoice', $invoice));

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

            // Dropdown rekening tujuan metode Transfer (ADHOC-95) — cuma
            // rekening AKTIF. Ikut payload ini (bukan dioper ke view) karena
            // modalnya dipakai di beberapa halaman sekaligus.
            $payload['available_bank_accounts'] = BankAccount::activeOptions();

            // Peringatan piutang lama (ADHOC-84 §2.4) — non-blokir, murni
            // informasi: tagihan LEBIH LAMA milik pelanggan yang sama yang
            // masih belum lunas. Dihitung server-side, klien cuma merender.
            $payload['older_unpaid_invoices'] = $this->olderUnpaidInvoicesFor($invoice);

            return response()->json($payload);
        }

        return view('invoices.show', compact('invoice'));
    }

    /**
     * Hapus buku piutang → status Tak Tertagih (ADHOC-90). Logika & guard
     * (hanya piutang, periode belum ditutup) ada di InvoiceWriteOffService.
     */
    public function writeOff(Request $request, Invoice $invoice, InvoiceWriteOffService $service): RedirectResponse
    {
        $this->authorizeScope($invoice);

        $validated = $request->validate([
            'reason' => ReasonValidationRule::required(500),
        ]);

        $service->writeOff($invoice, $request->user(), $validated['reason']);

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Piutang dihapus buku (tak tertagih).');
    }

    /**
     * Batalkan hapus buku. Dipanggil dari Detail Tagihan (redirect ke tagihan
     * itu) dan dari kolom Tagihan di List Putus Langganan (`redirect_to` =
     * `customers.terminated`, ADHOC-105). `redirect_to` HANYA dicocokkan dengan
     * satu nilai literal lalu dipetakan ke named route — bukan URL dari klien,
     * jadi bukan open redirect.
     */
    public function reverseWriteOff(Request $request, Invoice $invoice, InvoiceWriteOffService $service): RedirectResponse
    {
        $this->authorizeScope($invoice);

        $fromTerminatedList = $request->input('redirect_to') === 'customers.terminated';

        try {
            $service->reverse($invoice, $request->user());
        } catch (ValidationException $e) {
            // Halaman Putus Langganan tidak menampilkan @error('reason') seperti
            // Detail Tagihan, jadi pesannya dibawa lewat flash `error`.
            if (! $fromTerminatedList) {
                throw $e;
            }

            return redirect()->route('customers.terminated')->with('error', (string) collect($e->errors())->flatten()->first());
        }

        if ($fromTerminatedList) {
            return redirect()
                ->route('customers.terminated')
                ->with('success', "Tagihan {$invoice->invoice_number} dikembalikan ke tab Tagihan.");
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Hapus buku dibatalkan. Tagihan kembali menjadi piutang.');
    }

    /**
     * @return array<int, array{invoice_number: string, billing_period: string, remaining_amount: float}>
     */
    private function olderUnpaidInvoicesFor(Invoice $invoice): array
    {
        return $invoice->olderUnpaidInvoices()
            ->map(fn (Invoice $older) => [
                'invoice_number' => $older->invoice_number,
                'billing_period' => $older->billing_period,
                'remaining_amount' => (float) $older->remaining_amount,
            ])
            ->all();
    }

    private function authorizeScope(Invoice $invoice): void
    {
        abort_unless(
            Invoice::query()->applyUserScope()->whereKey($invoice->id)->exists(),
            403,
            'Anda tidak memiliki akses ke tagihan POP ini.'
        );
    }
}
