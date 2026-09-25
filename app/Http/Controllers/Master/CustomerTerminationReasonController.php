<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\CustomerTerminationReason;
use App\Support\RupiahInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master Alasan Putus Langganan (ADHOC-69) — pola sama
 * `TicketIssueCategoryController`, satu beda: punya aksi hapus permanen
 * (§3.4 rancangan) karena alasan ini dikonsumsi FK `restrictOnDelete()`.
 */
class CustomerTerminationReasonController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $reasons = CustomerTerminationReason::query()
            ->withCount('customers')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($status === 'active', function ($query) {
                $query->where('is_active', true);
            })
            ->when($status === 'inactive', function ($query) {
                $query->where('is_active', false);
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('master.customer-termination-reasons.index', compact('reasons', 'search', 'status'));
    }

    public function create(): View
    {
        return view('master.customer-termination-reasons.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateReason($request);

        CustomerTerminationReason::create($validated);

        return redirect()
            ->route('master.termination-reasons.index')
            ->with('success', 'Alasan putus langganan "'.$validated['name'].'" berhasil ditambahkan.');
    }

    public function edit(CustomerTerminationReason $reason): View
    {
        return view('master.customer-termination-reasons.edit', compact('reason'));
    }

    public function update(Request $request, CustomerTerminationReason $reason): RedirectResponse
    {
        $validated = $this->validateReason($request, $reason);

        $reason->update($validated);

        return redirect()
            ->route('master.termination-reasons.index')
            ->with('success', 'Alasan putus langganan "'.$reason->name.'" berhasil diperbarui.');
    }

    public function toggleStatus(CustomerTerminationReason $reason): RedirectResponse
    {
        $reason->update(['is_active' => ! $reason->is_active]);

        $statusText = $reason->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Alasan putus langganan \"{$reason->name}\" berhasil {$statusText}.");
    }

    /**
     * Hapus permanen — TIDAK ada di pola `TicketIssueCategory` (yang cuma
     * toggle). Dicek di sini dulu SEBELUM DELETE dilempar ke DB, supaya
     * errornya pesan jelas, bukan raw FK constraint violation
     * (`restrictOnDelete()` di migration tetap jadi jaring pengaman terakhir
     * untuk jalur lain — tinker, SQL langsung).
     */
    public function destroy(CustomerTerminationReason $reason): RedirectResponse
    {
        $usageCount = $reason->customers()->count();

        if ($usageCount > 0) {
            return back()->with('error', "Alasan \"{$reason->name}\" masih dipakai {$usageCount} pelanggan, tidak bisa dihapus.");
        }

        $reason->delete();

        return redirect()
            ->route('master.termination-reasons.index')
            ->with('success', "Alasan putus langganan \"{$reason->name}\" berhasil dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateReason(Request $request, ?CustomerTerminationReason $reason = null): array
    {
        $request->merge(RupiahInput::parseKeys(
            $request->only(['default_penalty_amount']),
            'default_penalty_amount',
        ));

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('customer_termination_reasons', 'name')->ignore($reason),
            ],
            'default_penalty_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'is_active' => 'required|boolean',
        ]);

        $validated['default_penalty_amount'] = $validated['default_penalty_amount'] ?? 0;

        return $validated;
    }
}
