@extends('layouts.app')

@section('title', 'Input Pembayaran - Whusnet Operasional')
@section('page_title', 'Input Pembayaran')
@section('breadcrumb_parent', 'Pembayaran')
@section('breadcrumb_parent_url', route('payments.index'))

@section('content')
<div class="space-y-6">
    <!-- Header Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-text-muted mb-1">
                <a href="{{ route('invoices.index') }}" class="hover:text-text-main transition-colors">Daftar Tagihan</a>
                <svg class="h-3 w-3 text-text-muted/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="{{ route('invoices.show', $invoice->id) }}" class="font-mono hover:text-text-main transition-colors">{{ $invoice->invoice_number }}</a>
                <svg class="h-3 w-3 text-text-muted/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-semibold text-text-main">Input Pembayaran</span>
            </nav>
            <h1 class="text-xl sm:text-2xl font-bold text-text-main tracking-tight">Input Pembayaran {{ $invoice->invoice_number }}</h1>
        </div>
        <div>
            <a href="{{ route('invoices.show', $invoice->id) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-lg transition-colors text-xs font-semibold shadow-2xs focus:outline-none">
                <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Kembali ke Detail Tagihan</span>
            </a>
        </div>
    </div>

    <!-- 2-Column Responsive Form & Summary -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form Section (Left Column) -->
        <div class="lg:col-span-2 bg-surface border border-border rounded-xl shadow-2xs overflow-hidden">
            <div class="px-6 py-4 border-b border-border bg-surface-muted/30 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-bold text-text-main uppercase tracking-wider">Form Pembayaran</h2>
                    <p class="text-[11px] text-text-muted mt-0.5">Pembayaran otomatis memperbarui total terbayar, sisa tagihan, dan status invoice.</p>
                </div>
                <span class="px-2.5 py-1 text-[10px] font-mono font-bold rounded-md bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-500/20">
                    {{ $invoice->invoice_number }}
                </span>
            </div>

            <form action="{{ route('invoices.payments.store', $invoice->id) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                @csrf

                {{-- Penahan submit dobel. Kuncinya lahir saat form DIRENDER dan
                     ikut `old()` supaya tetap sama setelah validasi gagal —
                     kalau digenerate ulang tiap render, submit ulang dianggap
                     pembayaran baru dan penahannya tidak menahan apa pun.
                     Server memperlakukan kunci yang sudah dipakai sebagai
                     "sudah tercatat", bukan sebagai error. --}}
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

                <!-- Tanggal Bayar -->
                <div>
                    <label for="payment_date" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Tanggal Bayar</label>
                    {{-- `max` menahan tanggal masa depan di sisi UI; aturan
                         sesungguhnya `before_or_equal:today` di controller. --}}
                    <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" required
                           max="{{ now()->format('Y-m-d') }}"
                           class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs font-mono bg-surface text-text-main transition-colors">
                </div>

                <!-- Metode Bayar -->
                <div>
                    <label for="payment_method" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Metode Bayar</label>
                    <select name="payment_method" id="payment_method" required 
                            class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs font-semibold bg-surface text-text-main transition-colors">
                        @foreach(['cash' => 'Cash', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'lainnya' => 'Lainnya'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Nominal Diterima -->
                <div>
                    <label for="amount" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Nominal Diterima dari Pelanggan (Rp)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none font-mono text-xs font-bold text-text-muted">Rp</span>
                        {{-- type="text" + data-rupiah: browser number input menolak
                             titik ribuan. Batas nominal ditegakkan server
                             (`amount` numeric|min:1|max:99999999.99). --}}
                        <input type="text" inputmode="decimal" name="amount" id="amount" data-rupiah
                               value="{{ old('amount', \App\Helpers\FormatHelper::rupiahInput($invoice->remaining_amount)) }}" required
                               class="w-full pl-9 pr-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs font-mono font-bold bg-surface text-text-main transition-colors">
                    </div>
                    <p class="text-[11px] text-text-muted mt-1.5">
                        Sisa tagihan: <span class="font-mono font-bold text-text-main">Rp {{ number_format((float) $invoice->remaining_amount, 2, ',', '.') }}</span>. Boleh diisi lebih besar — kelebihannya otomatis tercatat sebagai lebih bayar.
                    </p>

                    <!-- Dynamic Payment Hints -->
                    <p id="installment-hint" class="hidden text-xs font-semibold text-amber-700 dark:text-amber-400 mt-2 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20"></p>
                    <p id="settle-hint" class="hidden text-xs font-semibold text-emerald-700 dark:text-emerald-400 mt-2 px-3 py-2 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20"></p>
                    <p id="overpay-hint" class="hidden text-xs font-semibold text-sky-700 dark:text-sky-400 mt-2 px-3 py-2 rounded-lg bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20"></p>
                </div>

                <!-- Bukti Pembayaran Dropzone -->
                <div>
                    <label for="proof_file" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Bukti Pembayaran (Opsional)</label>
                    <input type="file" name="proof_file" id="proof_file" accept=".jpg,.jpeg,.png,.pdf" capture="environment"
                           class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs text-xs bg-surface text-text-main file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-surface-muted file:text-text-main hover:file:bg-border transition-colors">
                    <p class="text-[10px] text-text-muted mt-1">Format: JPG, PNG, atau PDF maksimal 2 MB.</p>
                </div>

                {{-- Saldo Pelanggan: cuma dirender kalau ada saldo aktif
                     (dari SISA BAYAR/lebih bayar sebelumnya —
                     CustomerBalanceService::credit() jalan otomatis tiap kali
                     overpay terjadi). `use_balance_amount` dikirim TERPISAH
                     dari `amount` — server yang menggabungnya
                     (PaymentService::record()), supaya audit tetap jelas asal
                     tiap rupiah. --}}
                @if($customerBalance > 0)
                <div class="border border-sky-200 dark:border-sky-500/20 bg-sky-50/60 dark:bg-sky-500/10 rounded-lg px-3 py-2.5 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-sky-800 dark:text-sky-300 font-semibold">Saldo Pelanggan Tersedia</span>
                        <span class="font-mono font-bold text-sky-800 dark:text-sky-300">Rp {{ number_format($customerBalance, 0, ',', '.') }}</span>
                    </div>
                    <label class="flex items-center gap-2 text-[11px] text-sky-800 dark:text-sky-300 font-medium cursor-pointer">
                        <input type="checkbox" id="use-balance-toggle" @checked(old('use_balance_amount')) class="rounded border-sky-300 text-sky-600 focus:ring-sky-500">
                        Pakai saldo pelanggan untuk pembayaran ini
                    </label>
                    <div id="use-balance-amount-wrap" class="{{ old('use_balance_amount') ? '' : 'hidden' }}">
                        <label for="use_balance_amount" class="block text-[10px] font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider mb-1">Nominal Saldo Dipakai</label>
                        <input type="text" inputmode="decimal" name="use_balance_amount" id="use_balance_amount" data-rupiah
                               value="{{ old('use_balance_amount') }}"
                               class="w-full px-3 py-2 border border-sky-200 dark:border-sky-500/30 rounded-lg shadow-2xs focus:ring-2 focus:ring-sky-500/25 focus:border-sky-500 text-xs font-mono bg-surface text-text-main transition-colors">
                    </div>
                </div>
                @endif

                <!-- Catatan -->
                <div>
                    <label for="note" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Catatan Pembayaran</label>
                    <textarea name="note" id="note" rows="3" placeholder="Tuliskan catatan transaksi jika ada..." 
                              class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs bg-surface text-text-main placeholder:text-text-muted/60 transition-colors">{{ old('note') }}</textarea>
                </div>

                <!-- Form Action Buttons -->
                <div class="flex items-center justify-end gap-2 pt-4 border-t border-border">
                    <a href="{{ route('invoices.show', $invoice->id) }}" class="px-4 py-2 border border-border text-text-secondary bg-surface hover:bg-surface-muted font-semibold rounded-lg shadow-2xs transition-colors text-xs">
                        Batal
                    </a>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg shadow-2xs transition-colors text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/25 flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Simpan Pembayaran</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Ringkasan Tagihan (Right Column) -->
        <div class="bg-surface border border-border rounded-xl p-6 shadow-2xs h-fit space-y-4">
            <h2 class="text-xs font-bold text-text-main uppercase tracking-wider pb-3 border-b border-border">Ringkasan Tagihan</h2>
            
            <div class="space-y-3 text-xs">
                <div>
                    <p class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">Pelanggan</p>
                    <p class="font-bold text-text-main text-sm mt-0.5">{{ $invoice->customer->full_name ?? '-' }}</p>
                    <p class="text-[10px] text-text-muted font-mono">CID: {{ $invoice->customer->cid ?? $invoice->customer->customer_code ?? '-' }}</p>
                </div>

                <div>
                    <p class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">POP / Cabang</p>
                    <p class="font-medium text-text-main mt-0.5">{{ $invoice->pop->name ?? '-' }}</p>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <p class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">No. Tagihan</p>
                        <p class="font-mono font-semibold text-primary mt-0.5">{{ $invoice->invoice_number }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">Periode</p>
                        <p class="font-mono text-text-main mt-0.5">{{ $invoice->billing_period }}</p>
                    </div>
                </div>

                <!-- Rincian Biaya -->
                <div class="pt-3 border-t border-dashed border-border space-y-2 text-xs">
                    <p class="text-[10px] font-bold text-text-muted uppercase tracking-wider mb-2">Rincian Biaya Invoice</p>

                    <div class="flex justify-between gap-2 text-text-secondary">
                        <span>Harga Paket</span>
                        <span class="font-mono">Rp {{ number_format((float)$invoice->subtotal, 0, ',', '.') }}</span>
                    </div>

                    @if((float)$invoice->discount > 0)
                    <div class="flex justify-between gap-2 text-emerald-600 dark:text-emerald-400">
                        <span>Diskon</span>
                        <span class="font-mono">- Rp {{ number_format((float)$invoice->discount, 0, ',', '.') }}</span>
                    </div>
                    @endif

                    @php
                        $ppnRate   = (float)$invoice->ppn;
                        $ppnBase   = max(0, (float)$invoice->subtotal - (float)$invoice->discount);
                        $ppnAmount = round($ppnBase * ($ppnRate / 100), 2);
                    @endphp
                    @if($ppnRate > 0)
                    <div class="flex justify-between gap-2 text-text-secondary">
                        <span>PPN ({{ number_format($ppnRate, 0) }}%)</span>
                        <span class="font-mono">Rp {{ number_format($ppnAmount, 0, ',', '.') }}</span>
                    </div>
                    @endif

                    @if((float)($invoice->prorate_amount ?? 0) > 0)
                    <div class="flex justify-between gap-2 text-text-secondary">
                        <span>Prorate</span>
                        <span class="font-mono">Rp {{ number_format((float)$invoice->prorate_amount, 0, ',', '.') }}</span>
                    </div>
                    @endif

                    @if((float)($invoice->extra_cable_fee ?? 0) > 0)
                    <div class="flex justify-between gap-2 text-text-secondary">
                        <span>Kabel Tambahan</span>
                        <span class="font-mono">Rp {{ number_format((float)$invoice->extra_cable_fee, 0, ',', '.') }}</span>
                    </div>
                    @endif

                    @if((float)($invoice->other_fee ?? 0) > 0)
                    <div class="flex justify-between gap-2 text-text-secondary">
                        <span>Biaya Lain-lain</span>
                        <span class="font-mono">Rp {{ number_format((float)$invoice->other_fee, 0, ',', '.') }}</span>
                    </div>
                    @endif

                    @if((float)($invoice->extra_installation_fee ?? 0) > 0)
                    <div class="flex justify-between gap-2 text-text-secondary">
                        <span>Jasa Instalasi</span>
                        <span class="font-mono">Rp {{ number_format((float)$invoice->extra_installation_fee, 0, ',', '.') }}</span>
                    </div>
                    @endif

                    @if((float)($invoice->extra_pole_fee ?? 0) > 0)
                    <div class="flex justify-between gap-2 text-text-secondary">
                        <span>Tambahan Tiang</span>
                        <span class="font-mono">Rp {{ number_format((float)$invoice->extra_pole_fee, 0, ',', '.') }}</span>
                    </div>
                    @endif

                    <div class="flex justify-between gap-2 pt-2 border-t border-border font-bold text-text-main text-sm">
                        <span>Total Tagihan</span>
                        <span class="font-mono">Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between gap-2 text-emerald-600 dark:text-emerald-400 font-semibold">
                        <span>Sudah Terbayar</span>
                        <span class="font-mono">Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between gap-2 pt-2 border-t border-border font-bold text-sm {{ (float)$invoice->remaining_amount > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                        <span>Sisa Tagihan</span>
                        <span class="font-mono">Rp {{ number_format((float) $invoice->remaining_amount, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const remaining = {{ (float) $invoice->remaining_amount }};
        const customerBalance = {{ (float) $customerBalance }};
        const nextInstallment = {{ (int) $nextInstallmentNumber }};
        const amountInput = document.getElementById('amount');
        const installmentHint = document.getElementById('installment-hint');
        const settleHint = document.getElementById('settle-hint');
        const overpayHint = document.getElementById('overpay-hint');
        const useBalanceToggle = document.getElementById('use-balance-toggle');
        const useBalanceAmountWrap = document.getElementById('use-balance-amount-wrap');
        const useBalanceAmountInput = document.getElementById('use_balance_amount');

        function formatRupiah(value) {
            return 'Rp ' + value.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }

        /** Nominal saldo yang mau dipakai, dibatasi maks saldo tersedia. */
        function useBalanceAmount() {
            if (!useBalanceToggle || !useBalanceToggle.checked) {
                return 0;
            }

            const raw = useBalanceAmountInput.value;
            const nilai = window.Rupiah ? window.Rupiah.angka(raw) : parseFloat(raw);

            return isNaN(nilai) ? 0 : Math.min(nilai, customerBalance);
        }

        /** Saldo dipakai memotong langsung Nominal Diterima — admin tak perlu
         *  hitung manual "sisa tagihan minus saldo". Dipanggil tiap saldo
         *  dipakai berubah (centang/nominal), BUKAN tiap Nominal Diterima
         *  diketik manual (supaya tidak lawan input admin). */
        function applyBalanceToAmount() {
            const nilai = Math.max(0, remaining - useBalanceAmount());
            amountInput.value = window.Rupiah ? window.Rupiah.formatDariServer(String(nilai)) : String(nilai);
            refreshHint();
        }

        function refreshHint() {
            // Input bermasking ribuan — parseFloat('150.000') = 150, jadi
            // petunjuk cicilan/lebih bayar akan berbohong tanpa parser ini.
            // Saldo yang dipakai ikut dihitung — pratinjau harus mencerminkan
            // TOTAL yang menutup tagihan, sama seperti server
            // (PaymentService::record()).
            const amount = (window.Rupiah ? window.Rupiah.angka(amountInput.value) : parseFloat(amountInput.value)) + useBalanceAmount();
            installmentHint.classList.add('hidden');
            settleHint.classList.add('hidden');
            overpayHint.classList.add('hidden');

            if (isNaN(amount) || amount <= 0) {
                return;
            }

            if (amount > remaining) {
                const overpay = Math.round((amount - remaining) * 100) / 100;
                overpayHint.textContent =
                    formatRupiah(remaining) + ' diterapkan ke tagihan (Lunas), ' +
                    formatRupiah(overpay) + ' tercatat sebagai lebih bayar.';
                overpayHint.classList.remove('hidden');
                return;
            }

            const leftover = Math.round((remaining - amount) * 100) / 100;

            if (leftover > 0) {
                installmentHint.textContent =
                    'Tercatat sebagai Cicilan Ke-' + nextInstallment +
                    '. Tagihan jadi berstatus Sebagian, sisa setelah ini: ' + formatRupiah(leftover) + '.';
                installmentHint.classList.remove('hidden');
            } else {
                settleHint.textContent = 'Pembayaran ini melunasi tagihan. Status jadi Lunas.';
                settleHint.classList.remove('hidden');
            }
        }

        amountInput.addEventListener('input', refreshHint);

        useBalanceToggle?.addEventListener('change', function (e) {
            useBalanceAmountWrap.classList.toggle('hidden', !e.target.checked);

            if (e.target.checked) {
                // Default: pakai saldo semaksimal mungkin (dibatasi sisa
                // tagihan) — admin boleh menurunkannya manual.
                const max = Math.min(customerBalance, remaining);
                useBalanceAmountInput.value = window.Rupiah ? window.Rupiah.formatDariServer(String(max)) : String(max);
            } else {
                useBalanceAmountInput.value = '';
            }

            // Nominal Diterima ikut terpotong sebesar saldo dipakai (atau
            // balik ke sisa tagihan penuh kalau saldo dibatalkan).
            applyBalanceToAmount();
        });

        useBalanceAmountInput?.addEventListener('input', applyBalanceToAmount);

        refreshHint();
    })();
</script>
@endsection
