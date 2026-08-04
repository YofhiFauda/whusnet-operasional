<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hub Kolektor — pengganti "Atur Kolektor" (halaman generik lintas-kolektor)
 * + "Tab Kolektor" (dropdown terpisah di Tagihan). Satu tempat: daftar
 * semua kolektor → detail per-kolektor dengan 2 tab:
 *   - Worklist & Bayar: pelanggan tanggung jawab kolektor ini, bayar
 *     1-by-1 (submit batch isi 1 baris) atau massal (checkbox banyak baris),
 *     dua-duanya lewat endpoint yang sama, CollectorBatchController::store()
 *     — tak ada jalur baru, cuma cara pakainya beda di UI.
 *   - Atur Pelanggan: assign/reassign/lepas rute permanen
 *     `customers.collector_id`, di-scope ke kolektor ini (bukan pilih
 *     kolektor dari dropdown di tengah proses).
 *
 * Tiga guard §B-3 tetap sama: (1) target wajib ber-role kolektor — implisit
 * benar di sini karena {collector} sudah diverifikasi di authorizeCollector(),
 * (2) POP pelanggan wajib masuk scope kolektor, (3) larangan nonaktifkan
 * kolektor bermuatan — tetap di UserController::update(), tak berubah.
 */
class CollectorController extends Controller
{
    public function index(): View
    {
        $collectors = User::query()
            ->whereHas('role', fn ($q) => $q->where('code', 'kolektor'))
            ->withCount(['assignedCustomers as customer_count' => function ($q) {
                $q->applyUserScope();
            }])
            ->orderBy('name')
            ->get();

        // Total tunggakan per kolektor. SENGAJA bukan JOIN + GROUP BY di SQL —
        // HasPopScope::scopeApplyUserScope() menulis `pop_id` TANPA qualifier
        // tabel, jadi begitu di-JOIN dengan `customers` (yang juga punya
        // `pop_id`) langsung ambiguous di MySQL/SQLite. Volume datanya kecil
        // (tunggakan per kolektor), agregasi di PHP lebih aman ketimbang
        // menambal tiap query yang kebetulan JOIN sama trait scope ini.
        $unpaidTotals = Invoice::query()
            ->applyUserScope()
            ->whereIn('invoice_status', [InvoiceStatus::BELUM_DIBAYAR->value, InvoiceStatus::SEBAGIAN->value])
            ->whereHas('customer', fn ($q) => $q->whereNotNull('collector_id'))
            ->with('customer:id,collector_id')
            ->get(['id', 'customer_id', 'remaining_amount'])
            ->groupBy(fn (Invoice $invoice) => $invoice->customer->collector_id)
            ->map(fn ($group) => $group->sum('remaining_amount'));

        foreach ($collectors as $collector) {
            $collector->unpaid_total = (float) ($unpaidTotals[$collector->id] ?? 0);
        }

        return view('collectors.index', compact('collectors'));
    }

    public function show(Request $request, User $collector): View
    {
        $this->authorizeCollector($collector);

        $tab = $request->query('tab', 'worklist') === 'assign' ? 'assign' : 'worklist';

        $invoices = Invoice::query()
            ->applyUserScope()
            ->whereIn('invoice_status', [InvoiceStatus::BELUM_DIBAYAR->value, InvoiceStatus::SEBAGIAN->value])
            ->whereHas('customer', fn ($q) => $q->where('collector_id', $collector->id))
            ->with(['customer'])
            ->orderBy('customer_id')
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate(150, ['*'], 'invoice_page');

        $assignedCustomers = Customer::query()
            ->applyUserScope()
            ->where('collector_id', $collector->id)
            ->with('pop')
            ->orderBy('full_name')
            ->paginate(50, ['*'], 'assigned_page');

        $search = trim((string) $request->query('search', ''));
        $searchResults = null;
        if ($tab === 'assign' && $search !== '') {
            $searchResults = Customer::query()
                ->applyUserScope()
                ->where(function ($q) use ($collector) {
                    $q->whereNull('collector_id')
                        ->orWhere('collector_id', '!=', $collector->id);
                })
                ->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('cid', 'like', "%{$search}%");
                })
                ->with(['pop', 'collector'])
                ->orderBy('full_name')
                ->paginate(50, ['*'], 'search_page')
                ->withQueryString();
        }

        return view('collectors.show', compact('collector', 'tab', 'invoices', 'assignedCustomers', 'search', 'searchResults'));
    }

    /**
     * Assign banyak pelanggan sekaligus ke kolektor ini. Kolektor sudah
     * fixed dari route {collector} — bukan dipilih dari dropdown di
     * tengah proses seperti "Atur Kolektor" versi lama.
     */
    public function assign(Request $request, User $collector): RedirectResponse
    {
        $this->authorizeCollector($collector);

        $validated = $request->validate([
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'integer|exists:customers,id',
        ]);

        $customers = Customer::query()
            ->applyUserScope()
            ->whereIn('id', $validated['customer_ids'])
            ->get();

        if ($customers->isEmpty()) {
            return redirect()
                ->route('collectors.show', ['collector' => $collector->id, 'tab' => 'assign'])
                ->withErrors(['customer_ids' => 'Tidak ada pelanggan valid dalam scope Anda yang dipilih.']);
        }

        // Guard 2: POP tiap pelanggan wajib masuk scope kolektor.
        $accessService = app(EffectiveAccessService::class);
        $hasAllPop = $accessService->hasAllPopAccess($collector);
        $allowedPopIds = $hasAllPop ? [] : $accessService->getAllowedPopIds($collector);

        $outOfScope = $customers->filter(function (Customer $customer) use ($hasAllPop, $allowedPopIds) {
            if ($hasAllPop) {
                return false;
            }

            return ! in_array($customer->pop_id, $allowedPopIds, true);
        });

        if ($outOfScope->isNotEmpty()) {
            $names = $outOfScope->pluck('full_name')->implode(', ');

            return redirect()
                ->route('collectors.show', ['collector' => $collector->id, 'tab' => 'assign'])
                ->withErrors([
                    'customer_ids' => "Kolektor {$collector->name} tak punya akses POP untuk: {$names}. Assign dibatalkan untuk seluruh batch.",
                ]);
        }

        foreach ($customers as $customer) {
            $customer->update(['collector_id' => $collector->id]);
        }

        return redirect()
            ->route('collectors.show', ['collector' => $collector->id, 'tab' => 'assign'])
            ->with('success', "{$customers->count()} pelanggan berhasil di-assign ke kolektor {$collector->name}.");
    }

    /**
     * Lepas SATU pelanggan dari kolektor ini (collector_id → null).
     */
    public function release(User $collector, Customer $customer): RedirectResponse
    {
        $this->authorizeCollector($collector);

        abort_unless(
            Customer::query()->applyUserScope()->whereKey($customer->id)->exists(),
            403,
            'Anda tidak memiliki akses ke pelanggan POP ini.'
        );

        if ((int) $customer->collector_id !== $collector->id) {
            return redirect()
                ->route('collectors.show', ['collector' => $collector->id, 'tab' => 'assign'])
                ->withErrors(['customer_ids' => 'Pelanggan ini sudah bukan tanggung jawab kolektor ini.']);
        }

        $customer->update(['collector_id' => null]);

        return redirect()
            ->route('collectors.show', ['collector' => $collector->id, 'tab' => 'assign'])
            ->with('success', "{$customer->full_name} dilepas dari kolektor {$collector->name}.");
    }

    private function authorizeCollector(User $collector): void
    {
        abort_unless($collector->hasRole('kolektor'), 404, 'User ini bukan kolektor.');
    }
}
