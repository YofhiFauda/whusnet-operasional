<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master Rekening Bank (ADHOC-95) — rekening resmi tujuan pembayaran
 * Transfer. GLOBAL (tanpa POP scope), satu daftar untuk semua cabang.
 *
 * Sengaja tanpa delete: payment menunjuk ke baris ini lewat
 * `bank_account_id`. Rekening yang tak dipakai lagi dinonaktifkan — hilang
 * dari dropdown form bayar, sementara riwayat payment lama tetap membaca
 * snapshot `bank_name`/`account_number`-nya sendiri.
 */
class BankAccountController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $accounts = BankAccount::query()
            ->withCount('payments')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('bank_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('account_holder_name', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('bank_name')
            ->orderBy('account_number')
            ->paginate(15)
            ->withQueryString();

        return view('master.rekening.index', compact('accounts', 'search', 'status'));
    }

    public function create(): View
    {
        return view('master.rekening.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAccount($request);

        BankAccount::create($validated);

        return redirect()
            ->route('master.rekening.index')
            ->with('success', "Rekening {$validated['bank_name']} {$validated['account_number']} berhasil ditambahkan.");
    }

    public function edit(BankAccount $bankAccount): View
    {
        return view('master.rekening.edit', ['account' => $bankAccount]);
    }

    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $validated = $this->validateAccount($request, $bankAccount);

        $bankAccount->update($validated);

        return redirect()
            ->route('master.rekening.index')
            ->with('success', "Rekening {$bankAccount->bank_name} {$bankAccount->account_number} berhasil diperbarui.");
    }

    public function toggleStatus(BankAccount $bankAccount): RedirectResponse
    {
        $bankAccount->update(['is_active' => ! $bankAccount->is_active]);

        $statusText = $bankAccount->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Rekening {$bankAccount->bank_name} {$bankAccount->account_number} berhasil {$statusText}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAccount(Request $request, ?BankAccount $account = null): array
    {
        // Nomor rekening dirapikan dulu (spasi/strip dibuang) — "123 456"
        // dan "123-456" yang diketik beda orang tetap satu rekening, jadi
        // unique di bawah benar-benar menahan duplikat.
        $request->merge([
            'account_number' => preg_replace('/[\s\-.]/', '', (string) $request->input('account_number')),
        ]);

        return $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => [
                'required',
                'string',
                'max:50',
                'regex:/^[0-9]+$/',
                Rule::unique('bank_accounts', 'account_number')
                    ->where('bank_name', $request->input('bank_name'))
                    ->ignore($account),
            ],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'label' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ], [
            'account_number.regex' => 'Nomor rekening hanya boleh berisi angka.',
            'account_number.unique' => 'Rekening ini sudah terdaftar di bank yang sama.',
        ]);
    }
}
