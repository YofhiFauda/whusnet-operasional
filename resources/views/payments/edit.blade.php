@extends('layouts.app')

@section('title', 'Edit Pembayaran - Whusnet Operasional')
@section('page_title', 'Edit Pembayaran')
@section('breadcrumb_parent', 'Pembayaran')
@section('breadcrumb_parent_url', route('payments.index'))

@section('content')
<div class="space-y-6">
    <!-- Header Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-text-muted mb-1">
                <a href="{{ route('payments.index') }}" class="hover:text-text-main transition-colors">Riwayat Pembayaran</a>
                <svg class="h-3 w-3 text-text-muted/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="{{ route('payments.show', $payment->id) }}" class="font-mono hover:text-text-main transition-colors">{{ $payment->payment_number }}</a>
                <svg class="h-3 w-3 text-text-muted/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-semibold text-text-main">Edit Pembayaran</span>
            </nav>
            <h1 class="text-xl sm:text-2xl font-bold text-text-main tracking-tight">Edit Pembayaran {{ $payment->payment_number }}</h1>
        </div>
        <div>
            <a href="{{ route('payments.show', $payment->id) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-lg transition-colors text-xs font-semibold shadow-2xs focus:outline-none">
                <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Kembali ke Detail Pembayaran</span>
            </a>
        </div>
    </div>

    <!-- Peringatan batas edit (ADHOC-108) — non-blokir kalau masih boleh
         diedit, tapi menjelaskan batasnya. Server tetap yang menolak
         (Payment::editBlockedReason() / PaymentService::revise()) — banner
         ini murni informasi. -->
    <div class="text-xs text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-lg px-3 py-2.5 space-y-1">
        <p class="font-bold uppercase tracking-wider text-[10px]">Batas Edit Pembayaran</p>
        <ul class="list-disc list-inside space-y-0.5">
            <li>Hanya pembayaran <strong>bulan berjalan</strong> yang bisa diedit — pembayaran bulan lalu wajib lewat Kembalikan + catat ulang.</li>
            <li>Nominal &amp; saldo yang diubah otomatis menghitung ulang sisa tagihan dan saldo pelanggan.</li>
            @if($isFrozenToDeposit)
            <li class="font-semibold">Pembayaran ini sudah masuk setoran — Metode Bayar dan Kolektor terkunci, field lain tetap bisa diedit.</li>
            @endif
        </ul>
    </div>

    <!-- 2-Column Responsive Form & Summary -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form Section (Left Column) -->
        <div class="lg:col-span-2 bg-surface border border-border rounded-xl shadow-2xs overflow-hidden">
            <div class="px-6 py-4 border-b border-border bg-surface-muted/30 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-bold text-text-main uppercase tracking-wider">Form Edit Pembayaran</h2>
                    <p class="text-[11px] text-text-muted mt-0.5">Perubahan otomatis menghitung ulang sisa tagihan, status invoice, dan saldo pelanggan.</p>
                </div>
                <span class="px-2.5 py-1 text-[10px] font-mono font-bold rounded-md bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-500/20">
                    {{ $payment->payment_number }}
                </span>
            </div>

            <form id="payment-edit-form" action="{{ route('payments.update', $payment->id) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                @csrf
                @method('PUT')

                <!-- Tanggal Bayar -->
                <div>
                    <label for="payment_date" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Tanggal Bayar</label>
                    <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', optional($payment->payment_date)->format('Y-m-d')) }}" required
                           min="{{ \App\Support\BookPeriod::firstOpenDate() }}" max="{{ now()->format('Y-m-d') }}"
                           class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs font-mono bg-surface text-text-main transition-colors">
                    @error('payment_date')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Metode Bayar -->
                <div>
                    <label for="payment_method" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Metode Bayar</label>
                    <select name="payment_method" id="payment_method" required onchange="peToggleMethodFields()"
                            @disabled($isFrozenToDeposit)
                            class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs font-semibold bg-surface text-text-main transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                        @foreach(['cash' => 'Cash', 'transfer' => 'Transfer', 'kolektor' => 'Kolektor', 'lainnya' => 'Lainnya'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method', $payment->payment_method) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    {{-- Select disabled tak ikut terkirim form — kunci nilainya lewat hidden input. --}}
                    @if($isFrozenToDeposit)
                        <input type="hidden" name="payment_method" value="{{ old('payment_method', $payment->payment_method) }}">
                        <p class="text-[10px] text-amber-600 dark:text-amber-400 mt-1">Terkunci — pembayaran ini sudah masuk setoran.</p>
                    @endif
                    @error('payment_method')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Transfer: Rekening Tujuan -->
                <div id="pe-transfer-fields" class="hidden space-y-3">
                    <div>
                        <label for="bank_account_id" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Rekening Tujuan</label>
                        <select name="bank_account_id" id="bank_account_id"
                                class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs font-semibold bg-surface text-text-main transition-colors">
                            <option value="">Pilih rekening...</option>
                            @foreach($bankAccounts as $bankAccount)
                                <option value="{{ $bankAccount->id }}" @selected((string) old('bank_account_id', $payment->bank_account_id) === (string) $bankAccount->id)>{{ $bankAccount->displayName() }}</option>
                            @endforeach
                            {{-- Rekening lama yang KINI nonaktif tetap dipertahankan sebagai
                                 snapshot (PaymentService::revise()) — tetap ditampilkan di
                                 dropdown supaya tidak menghilang begitu saja kalau tak diubah. --}}
                            @if($payment->bankAccount && ! $payment->bankAccount->is_active)
                                <option value="{{ $payment->bankAccount->id }}" selected>{{ $payment->bankAccount->displayName() }} (nonaktif — dipertahankan)</option>
                            @endif
                        </select>
                        @error('bank_account_id')<p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>@enderror
                    </div>
                </div>

                <!-- Nama Pengirim (Transfer/Kolektor) -->
                <div id="pe-sender-fields" class="hidden">
                    <label for="sender_name" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Nama Pengirim (opsional)</label>
                    <input type="text" name="sender_name" id="sender_name" value="{{ old('sender_name', $payment->sender_name) }}" maxlength="150"
                           class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs bg-surface text-text-main transition-colors">
                    @error('sender_name')<p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>@enderror
                </div>

                <!-- Kolektor -->
                <div id="pe-collector-fields" class="hidden">
                    <label for="collected_by" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Kolektor Penagih</label>
                    <select name="collected_by" id="collected_by"
                            @disabled($isFrozenToDeposit)
                            class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs font-semibold bg-surface text-text-main transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                        <option value="">— Pilih Kolektor —</option>
                        @foreach($collectors as $collector)
                            <option value="{{ $collector->id }}" @selected((string) old('collected_by', $payment->collected_by) === (string) $collector->id)>{{ $collector->name }}</option>
                        @endforeach
                    </select>
                    @if($isFrozenToDeposit)
                        <input type="hidden" name="collected_by" value="{{ old('collected_by', $payment->collected_by) }}">
                    @endif
                    @error('collected_by')<p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>@enderror
                </div>

                <!-- Nominal Diterima -->
                <div>
                    <label for="amount" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Nominal Diterima dari Pelanggan (Rp)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none font-mono text-xs font-bold text-text-muted">Rp</span>
                        <input type="text" inputmode="decimal" name="amount" id="amount" data-rupiah
                               value="{{ old('amount', \App\Helpers\FormatHelper::rupiahInput($initialTunaiAmount)) }}" required
                               class="w-full pl-9 pr-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs font-mono font-bold bg-surface text-text-main transition-colors">
                    </div>
                    <p class="text-[11px] text-text-muted mt-1.5">
                        Sisa tagihan TANPA pembayaran ini: <span class="font-mono font-bold text-text-main">Rp {{ number_format($remainingWithoutThisPayment, 2, ',', '.') }}</span>. Boleh diisi lebih besar — kelebihannya otomatis tercatat sebagai lebih bayar.
                    </p>
                    @error('amount')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Bukti Pembayaran -->
                <div>
                    <label for="proof_file" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Bukti Pembayaran (Opsional)</label>
                    <input type="file" name="proof_file" id="proof_file" accept=".jpg,.jpeg,.png,.pdf"
                           class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs text-xs bg-surface text-text-main file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-surface-muted file:text-text-main hover:file:bg-border transition-colors">
                    <p class="text-[10px] text-text-muted mt-1">Kosongkan untuk mempertahankan berkas bukti lama. Format: JPG, PNG, atau PDF maksimal 2 MB.</p>
                    @error('proof_file')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Saldo Pelanggan -->
                @if($payment->customer)
                <div class="border border-sky-200 dark:border-sky-500/20 bg-sky-50/60 dark:bg-sky-500/10 rounded-lg px-3 py-2.5 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-sky-800 dark:text-sky-300 font-semibold">Saldo Pelanggan Tersedia (di luar pemakaian pembayaran ini)</span>
                        <span class="font-mono font-bold text-sky-800 dark:text-sky-300">Rp {{ number_format($customerBalance + (float) $payment->balance_used_amount, 0, ',', '.') }}</span>
                    </div>
                    <label class="flex items-center gap-2 text-[11px] text-sky-800 dark:text-sky-300 font-medium cursor-pointer">
                        <input type="checkbox" id="use-balance-toggle" @checked(old('use_balance_amount', $payment->balance_used_amount) > 0) class="rounded border-sky-300 text-sky-600 focus:ring-sky-500">
                        Pakai saldo pelanggan untuk pembayaran ini
                    </label>
                    <div id="use-balance-amount-wrap" class="{{ old('use_balance_amount', $payment->balance_used_amount) > 0 ? '' : 'hidden' }}">
                        <label for="use_balance_amount" class="block text-[10px] font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider mb-1">Nominal Saldo Dipakai</label>
                        <input type="text" inputmode="decimal" name="use_balance_amount" id="use_balance_amount" data-rupiah
                               value="{{ old('use_balance_amount', \App\Helpers\FormatHelper::rupiahInput($payment->balance_used_amount)) }}"
                               class="w-full px-3 py-2 border border-sky-200 dark:border-sky-500/30 rounded-lg shadow-2xs focus:ring-2 focus:ring-sky-500/25 focus:border-sky-500 text-xs font-mono bg-surface text-text-main transition-colors">
                    </div>
                    @error('use_balance_amount')<p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>@enderror
                </div>
                @endif

                <!-- Catatan -->
                <div>
                    <label for="note" id="note-label" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Catatan Pembayaran</label>
                    <textarea name="note" id="note" rows="3" placeholder="Tuliskan catatan transaksi jika ada..."
                              class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs bg-surface text-text-main placeholder:text-text-muted/60 transition-colors">{{ old('note', $payment->note) }}</textarea>
                    @error('note')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Alasan Koreksi (opsional, K2) -->
                <div>
                    <label for="reason" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Alasan Koreksi (Opsional)</label>
                    <textarea name="reason" id="reason" rows="2" placeholder="Jelaskan alasan koreksi — tercatat terpisah di riwayat audit pembayaran ini..."
                              class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs bg-surface text-text-main placeholder:text-text-muted/60 transition-colors">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Form Action Buttons -->
                <div class="flex items-center justify-end gap-2 pt-4 border-t border-border">
                    <a href="{{ route('payments.show', $payment->id) }}" class="px-4 py-2 border border-border text-text-secondary bg-surface hover:bg-surface-muted font-semibold rounded-lg shadow-2xs transition-colors text-xs">
                        Batal
                    </a>
                    <button type="submit" class="px-5 py-2 bg-primary hover:bg-primary-focus text-white font-semibold rounded-lg shadow-2xs transition-colors text-xs focus:outline-none focus:ring-2 focus:ring-primary/25 flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Ringkasan (Right Column) -->
        <div class="bg-surface border border-border rounded-xl p-6 shadow-2xs h-fit space-y-4">
            <h2 class="text-xs font-bold text-text-main uppercase tracking-wider pb-3 border-b border-border">Ringkasan</h2>

            <div class="space-y-3 text-xs">
                <div>
                    <p class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">Pelanggan</p>
                    <p class="font-bold text-text-main text-sm mt-0.5">{{ $payment->customer->full_name ?? '-' }}</p>
                    <p class="text-[10px] text-text-muted font-mono">CID: {{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}</p>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <p class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">No. Tagihan</p>
                        <p class="font-mono font-semibold text-primary mt-0.5">{{ $payment->invoice->invoice_number ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">Periode</p>
                        <p class="font-mono text-text-main mt-0.5">{{ $payment->invoice->billing_period ?? '-' }}</p>
                    </div>
                </div>

                <div class="pt-3 border-t border-dashed border-border space-y-2 text-xs">
                    <div class="flex justify-between gap-2 text-text-secondary">
                        <span>Total Tagihan</span>
                        <span class="font-mono">Rp {{ number_format((float) ($payment->invoice->total_amount ?? 0), 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between gap-2 pt-2 border-t border-border font-bold text-sm text-text-main">
                        <span>Sisa Tagihan Tanpa Pembayaran Ini</span>
                        <span class="font-mono">Rp {{ number_format($remainingWithoutThisPayment, 0, ',', '.') }}</span>
                    </div>
                </div>

                @if($payment->collectorDeposit)
                <div class="pt-3 border-t border-dashed border-border text-[11px] text-text-secondary">
                    Sudah masuk setoran kolektor <strong class="font-mono">{{ $payment->collectorDeposit->deposit_number }}</strong> — status {{ $payment->collectorDeposit->status->label() }}.
                </div>
                @endif
                @if($payment->cashDeposit)
                <div class="pt-3 border-t border-dashed border-border text-[11px] text-text-secondary">
                    Sudah masuk setoran kas <strong class="font-mono">{{ $payment->cashDeposit->deposit_number }}</strong> — status {{ $payment->cashDeposit->status->label() }}.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    /** Sama polanya dengan pcToggleMethodFields() di payments/create.blade.php
     *  — dipertahankan terpisah (bukan file bersama) karena field yang
     *  tersedia berbeda (Edit punya opsi Kolektor, tidak punya Saldo). */
    function peToggleMethodFields() {
        const methodEl = document.getElementById('payment_method');
        const method = methodEl.value;
        const transferFields = document.getElementById('pe-transfer-fields');
        const bankAccount = document.getElementById('bank_account_id');
        const senderFields = document.getElementById('pe-sender-fields');
        const senderName = document.getElementById('sender_name');
        const collectorFields = document.getElementById('pe-collector-fields');
        const collectedBy = document.getElementById('collected_by');
        const note = document.getElementById('note');
        const noteLabel = document.getElementById('note-label');

        const isTransfer = method === 'transfer';
        const isKolektor = method === 'kolektor';
        const isLainnya = method === 'lainnya';

        transferFields.classList.toggle('hidden', !isTransfer);
        bankAccount.required = isTransfer;
        bankAccount.disabled = !isTransfer;

        senderFields.classList.toggle('hidden', !(isTransfer || isKolektor));
        senderName.disabled = !(isTransfer || isKolektor);

        collectorFields.classList.toggle('hidden', !isKolektor);
        if (collectedBy) {
            collectedBy.required = isKolektor;
            if (!collectedBy.disabled) {
                collectedBy.disabled = false;
            }
        }

        note.required = isLainnya;
        note.placeholder = isLainnya ? 'Jelaskan metode pembayaran (mis. OVO, Dana, GoPay)...' : 'Tuliskan catatan transaksi jika ada...';
        noteLabel.textContent = isLainnya ? 'Keterangan Metode (wajib)' : 'Catatan Pembayaran';
    }
    document.addEventListener('DOMContentLoaded', peToggleMethodFields);

    (function () {
        const useBalanceToggle = document.getElementById('use-balance-toggle');
        const useBalanceAmountWrap = document.getElementById('use-balance-amount-wrap');
        const useBalanceAmountInput = document.getElementById('use_balance_amount');

        useBalanceToggle?.addEventListener('change', function (e) {
            useBalanceAmountWrap.classList.toggle('hidden', !e.target.checked);

            if (!e.target.checked) {
                useBalanceAmountInput.value = '';
            }
        });
    })();
</script>
@endsection
