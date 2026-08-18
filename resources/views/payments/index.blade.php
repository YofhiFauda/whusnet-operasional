@extends('layouts.app')

@section('title', 'Riwayat Transaksi Pembayaran - Whusnet Operasional')
@section('page_title', 'Riwayat Transaksi Pembayaran')
{{-- Tanpa breadcrumb_parent — halaman ini puncak grup "Pembayaran" di nav,
     sama seperti invoices.index puncak grup "Tagihan" (lihat pola sana). --}}

@section('content')
<div class="space-y-6">
    @include('payments.partials.riwayat-banner')

    <!-- Naked Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-text-main tracking-tight">Riwayat Transaksi Pembayaran</h1>
            <p class="text-xs text-text-muted mt-1">
                Seluruh riwayat transaksi pembayaran tagihan pelanggan ISP terhubung dengan Invoice & POP.
            </p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <a href="{{ route('payments.overpay') }}" class="inline-flex items-center gap-2 px-3.5 py-2 border border-amber-200 dark:border-amber-500/30 bg-amber-50/80 dark:bg-amber-500/10 hover:bg-amber-100 dark:hover:bg-amber-500/20 text-amber-700 dark:text-amber-300 rounded-lg transition-colors text-xs font-semibold shadow-xs">
                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Lebih Bayar</span>
            </a>
            <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-2 px-3.5 py-2 border border-border bg-surface hover:bg-surface-muted text-text-main rounded-lg transition-colors text-xs font-semibold shadow-xs">
                <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Daftar Tagihan</span>
            </a>
        </div>
    </div>

    <!-- Stat Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Payments Count -->
        <div class="bg-surface border border-border rounded-xl p-4 transition-all hover:border-primary/40 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">Total Transaksi</span>
                <span class="p-2 rounded-lg bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold font-mono text-text-main">{{ number_format($payments->total()) }}</span>
                <span class="text-[11px] text-text-muted">transaksi</span>
            </div>
        </div>

        <!-- Total Page Amount -->
        <div class="bg-surface border border-border rounded-xl p-4 transition-all hover:border-emerald-500/40 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">Nominal Hal. Ini</span>
                <span class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2">
                <span class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                    Rp {{ number_format((float) $payments->sum('amount'), 0, ',', '.') }}
                </span>
            </div>
        </div>

        <!-- Payment Methods Breakdown (Current Page) -->
        <div class="bg-surface border border-border rounded-xl p-4 transition-all hover:border-violet-500/40 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">Metode Bayar</span>
                <span class="p-2 rounded-lg bg-violet-50 dark:bg-violet-500/10 text-violet-600 dark:text-violet-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-center gap-3 text-xs">
                @php
                    $cashCount = $payments->filter(fn($p) => strtolower($p->payment_method) === 'cash')->count();
                    $transferCount = $payments->filter(fn($p) => in_array(strtolower($p->payment_method), ['transfer', 'qris']))->count();
                @endphp
                <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold font-mono">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Cash: {{ $cashCount }}
                </span>
                <span class="inline-flex items-center gap-1 text-sky-600 dark:text-sky-400 font-semibold font-mono">
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span> Non-Cash: {{ $transferCount }}
                </span>
            </div>
        </div>

        <!-- Overpay Highlight -->
        <div class="bg-surface border border-border rounded-xl p-4 transition-all hover:border-amber-500/40 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">Lebih Bayar (Hal. Ini)</span>
                <span class="p-2 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2">
                <span class="text-xl font-bold font-mono text-amber-600 dark:text-amber-400">
                    Rp {{ number_format((float) $payments->sum('overpay_amount'), 0, ',', '.') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Filter Panel Card -->
    <div class="bg-surface border border-border rounded-xl p-5 shadow-2xs">
        <form action="{{ route('payments.index') }}" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-3">
                <!-- Search Field -->
                <div class="sm:col-span-2 lg:col-span-2">
                    <label for="search" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Cari Pembayaran</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Nama, CID, No. Pembayaran, Invoice..." 
                               class="w-full pl-9 pr-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 placeholder:text-text-muted/60 transition-colors">
                    </div>
                </div>

                <!-- Date From -->
                <div>
                    <label for="date_from" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}" 
                           class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 transition-colors font-mono">
                </div>

                <!-- Date To -->
                <div>
                    <label for="date_to" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}" 
                           class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 transition-colors font-mono">
                </div>

                <!-- POP Select -->
                <div>
                    <label for="pop_id" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">POP / Cabang</label>
                    <select name="pop_id" id="pop_id" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 transition-colors">
                        <option value="">Semua POP</option>
                        @foreach($pops as $pop)
                            <option value="{{ $pop->id }}" {{ (string) $popId === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Method Select -->
                <div>
                    <label for="method" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Metode Bayar</label>
                    <select name="method" id="method" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 transition-colors">
                        <option value="">Semua Metode</option>
                        @foreach($allowedMethods as $paymentMethod)
                            <option value="{{ $paymentMethod }}" {{ $method === $paymentMethod ? 'selected' : '' }}>{{ strtoupper($paymentMethod) }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Select -->
                <div>
                    <label for="status" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Status Validasi</label>
                    <select name="status" id="status" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 transition-colors">
                        <option value="">Semua Status</option>
                        @foreach($allowedStatuses as $paymentStatus)
                            <option value="{{ $paymentStatus }}" {{ $status === $paymentStatus ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $paymentStatus)) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Action Filter Buttons -->
            <div class="flex items-center justify-between pt-2 border-t border-border/60">
                <div class="text-[11px] text-text-muted">
                    Menampilkan <span class="font-semibold text-text-main">{{ $payments->firstItem() ?? 0 }}</span> - <span class="font-semibold text-text-main">{{ $payments->lastItem() ?? 0 }}</span> dari <span class="font-semibold text-text-main">{{ $payments->total() }}</span> pembayaran
                </div>
                <div class="flex items-center gap-2">
                    @if($search !== '' || $popId !== '' || $dateFrom !== '' || $dateTo !== '' || $method !== '' || $status !== '' || $invoiceType !== '')
                        <a href="{{ route('payments.index') }}" class="px-3.5 py-1.5 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-lg transition-colors text-xs font-semibold text-center">
                            Reset Filter
                        </a>
                    @endif
                    <button type="submit" class="px-4 py-1.5 bg-primary hover:bg-primary-focus text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer shadow-2xs focus:outline-none focus:ring-2 focus:ring-primary/25 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span>Terapkan Filter</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Payments Table View -->
    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs text-text-secondary">
                <thead>
                    <tr class="bg-surface-muted/60 border-b border-border text-text-muted font-bold text-[10px] uppercase tracking-wider">
                        <th class="px-4 py-3.5 w-10 text-center">No</th>
                        <th class="px-4 py-3.5">No. Transaksi</th>
                        <th class="px-4 py-3.5">No. Invoice</th>
                        <th class="px-4 py-3.5">Pelanggan</th>
                        <th class="px-4 py-3.5">POP / Cabang</th>
                        <th class="px-4 py-3.5">Tanggal</th>
                        <th class="px-4 py-3.5">Metode</th>
                        <th class="px-4 py-3.5">Penerima / Kolektor</th>
                        <th class="px-4 py-3.5 text-right">Nominal (Rp)</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                        <th class="px-4 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($payments as $payment)
                        @php
                            $statusVal = is_object($payment->payment_status) ? $payment->payment_status->value : $payment->payment_status;
                            $statusLabel = is_object($payment->payment_status) ? $payment->payment_status->label() : ucwords(str_replace('_', ' ', $payment->payment_status));

                            $badgeClass = match($statusVal) {
                                'valid' => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                                'ditolak' => 'bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800',
                                default => 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
                            };

                            $methodBadge = match(strtolower($payment->payment_method)) {
                                'cash' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20',
                                'transfer' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-500/20',
                                'qris' => 'bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-500/20',
                                default => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
                            };
                        @endphp
                        <tr class="hover:bg-surface-muted/50 transition-colors">
                            <td class="px-4 py-3.5 text-center text-text-muted font-mono">{{ ($payments->currentPage() - 1) * $payments->perPage() + $loop->iteration }}</td>
                            
                            <!-- Payment Number -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <a href="{{ route('payments.show', $payment->id) }}" class="font-mono font-bold text-primary hover:text-primary-focus transition-colors">
                                    {{ $payment->payment_number }}
                                </a>
                                @if($payment->old_payment_id)
                                    <div class="mt-0.5">
                                        <span title="Data Migrasi (ID Bayar Lama: {{ $payment->old_payment_id }})" class="inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold rounded border bg-primary/10 text-primary border-primary/20">
                                            Migrasi #{{ $payment->old_payment_id }}
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <!-- Invoice Number -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                @if($payment->invoice)
                                    <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="font-mono font-semibold text-text-main hover:text-primary transition-colors flex items-center gap-1">
                                        <svg class="w-3 h-3 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        <span>{{ $payment->invoice->invoice_number }}</span>
                                    </a>
                                @else
                                    <span class="text-text-muted italic">-</span>
                                @endif
                            </td>

                            <!-- Customer -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <div class="font-semibold text-text-main">{{ $payment->customer->full_name ?? '-' }}</div>
                                <div class="text-[10px] text-text-muted font-mono flex items-center gap-1">
                                    <span>CID: {{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}</span>
                                </div>
                            </td>

                            <!-- POP -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted text-text-secondary border border-border">
                                    {{ $payment->pop->name ?? '-' }}
                                </span>
                            </td>

                            <!-- Payment Date -->
                            <td class="px-4 py-3.5 whitespace-nowrap font-mono text-text-secondary">
                                {{ optional($payment->payment_date)->format('d/m/Y') }}
                            </td>

                            <!-- Payment Method -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md border uppercase tracking-wider {{ $methodBadge }}">
                                    {{ strtoupper($payment->payment_method) }}
                                </span>
                            </td>

                            <!-- Receiver / Collector -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                @if($payment->collector)
                                    <div class="flex flex-col">
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-500/20 w-fit">
                                            Kolektor: {{ $payment->collector->name }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-text-muted text-[11px]">Direct / Kasir</span>
                                @endif
                                <div class="text-[10px] text-text-muted mt-0.5">by {{ $payment->receiver->name ?? 'System' }}</div>
                            </td>

                            <!-- Amount & Overpay -->
                            <td class="px-4 py-3.5 text-right font-mono whitespace-nowrap">
                                <div class="font-bold text-text-main text-xs">
                                    Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                </div>
                                @if((float) $payment->overpay_amount > 0)
                                    <span class="inline-block text-[10px] font-bold text-amber-600 dark:text-amber-400" title="Uang lebih yang diserahkan pelanggan">
                                        +{{ number_format((float) $payment->overpay_amount, 0, ',', '.') }} lebih
                                    </span>
                                @endif
                            </td>

                            <!-- Status Badge -->
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] font-bold rounded-full border {{ $badgeClass }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    {{ $statusLabel }}
                                </span>
                            </td>

                            <!-- Action Buttons -->
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('payments.show', $payment->id) }}" class="inline-flex items-center gap-1 px-2.5 py-1 border border-border bg-surface hover:bg-surface-muted text-text-main rounded-md transition-colors text-xs font-semibold shadow-2xs">
                                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>Detail</span>
                                    </a>
                                    <a href="{{ route('payments.receipt', $payment->id) }}" target="_blank" class="p-1 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-md transition-colors text-xs" title="Cetak Struk Thermal">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-6 py-12 text-center text-text-muted">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <svg class="w-10 h-10 mx-auto text-text-muted/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" />
                                    </svg>
                                    <p class="text-xs font-semibold text-text-main">Tidak ada pembayaran ditemukan</p>
                                    <p class="text-[11px] text-text-muted">Coba ubah kata kunci pencarian atau tanggal filter di atas.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-border bg-surface-muted/30">
            {{ $payments->links() }}
        </div>
    </div>
</div>
@endsection
