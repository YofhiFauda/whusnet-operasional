@extends('layouts.app')

@section('title', 'Lebih Bayar - Whusnet Operasional')
@section('page_title', 'Lebih Bayar')
@section('breadcrumb_parent', 'Riwayat Transaksi Pembayaran')
@section('breadcrumb_parent_url', route('payments.index'))

@section('content')
<div class="space-y-6">
    <!-- Naked Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-text-muted mb-1">
                <a href="{{ route('payments.index') }}" class="hover:text-text-main transition-colors">Pembayaran</a>
                <svg class="h-3 w-3 text-text-muted/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-semibold text-text-main">Lebih Bayar</span>
            </nav>
            <h1 class="text-xl sm:text-2xl font-bold text-text-main tracking-tight">Daftar Pembayaran Lebih Bayar</h1>
            {{-- Copy lama bilang "bukan saldo ledger, diselesaikan manual" —
                 sudah TIDAK BENAR sejak Saldo Pelanggan aktif
                 (CustomerBalanceService, 2026-08-18): overpay di sini otomatis
                 jadi kredit yang bisa dipakai pelanggan di pembayaran
                 berikutnya. --}}
            <p class="text-xs text-text-muted mt-1">
                Uang lebih yang diserahkan pelanggan saat membayar tagihan. <span class="font-semibold text-emerald-600 dark:text-emerald-400">Otomatis jadi Saldo Pelanggan</span> — bisa dipakai pelanggan sebagian/seluruhnya di pembayaran tagihan berikutnya.
            </p>
        </div>
        <div>
            <a href="{{ route('payments.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-lg transition-colors text-xs font-semibold shadow-2xs">
                <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Kembali ke Riwayat Transaksi</span>
            </a>
        </div>
    </div>

    <!-- Summary Banner Card -->
    <div class="bg-amber-50/80 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-xl p-5 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="space-y-1">
            <span class="text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider block">Total Kelebihan Pembayaran (Sesuai Filter)</span>
            <div class="text-2xl sm:text-3xl font-bold text-amber-800 dark:text-amber-300 font-mono">
                Rp {{ number_format($totalOverpay, 0, ',', '.') }}
            </div>
        </div>
        <div class="text-xs text-amber-700/80 dark:text-amber-400/80 max-w-md bg-amber-100/60 dark:bg-amber-500/20 p-3 rounded-lg border border-amber-200/60 dark:border-amber-500/30">
            <span class="font-semibold">Informasi Kasir:</span> Catatan lebih bayar tersimpan per transaksi. Bila dilakukan penyesuaian/refund, lakukan pencatatan di catatan manual pelanggan.
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="bg-surface border border-border rounded-xl p-5 shadow-2xs">
        <form action="{{ route('payments.overpay') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <!-- Search Pelanggan -->
            <div class="sm:col-span-2">
                <label for="search" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Cari Pelanggan</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Cari Nama, CID, atau Kode Pelanggan..." 
                           class="w-full pl-9 pr-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 placeholder:text-text-muted/60 transition-colors">
                </div>
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

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-primary hover:bg-primary-focus text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer shadow-2xs focus:outline-none focus:ring-2 focus:ring-primary/25 flex items-center justify-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                </button>
                @if($search !== '' || $popId !== '')
                    <a href="{{ route('payments.overpay') }}" class="px-3.5 py-2 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-lg transition-colors text-xs font-semibold text-center">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Overpay Payments Table Card -->
    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs text-text-secondary">
                <thead>
                    <tr class="bg-surface-muted/60 border-b border-border text-text-muted font-bold text-[10px] uppercase tracking-wider">
                        <th class="px-4 py-3.5 w-10 text-center">No</th>
                        <th class="px-4 py-3.5">No. Transaksi</th>
                        <th class="px-4 py-3.5">Pelanggan</th>
                        <th class="px-4 py-3.5">POP / Cabang</th>
                        <th class="px-4 py-3.5">Tanggal</th>
                        <th class="px-4 py-3.5 text-right">Nominal Bayar</th>
                        <th class="px-4 py-3.5 text-right">Nominal Lebih</th>
                        <th class="px-4 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-surface-muted/50 transition-colors">
                            <td class="px-4 py-3.5 text-center text-text-muted font-mono">{{ ($payments->currentPage() - 1) * $payments->perPage() + $loop->iteration }}</td>
                            
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <a href="{{ route('payments.show', $payment->id) }}" class="font-mono font-bold text-primary hover:text-primary-focus transition-colors">
                                    {{ $payment->payment_number }}
                                </a>
                            </td>

                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <div class="font-semibold text-text-main">{{ $payment->customer->full_name ?? '-' }}</div>
                                <div class="text-[10px] text-text-muted font-mono">CID: {{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}</div>
                            </td>

                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted text-text-secondary border border-border">
                                    {{ $payment->pop->name ?? '-' }}
                                </span>
                            </td>

                            <td class="px-4 py-3.5 whitespace-nowrap font-mono text-text-secondary">
                                {{ optional($payment->payment_date)->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3.5 text-right font-mono font-semibold text-text-main">
                                Rp {{ number_format((float) $payment->amount, 2, ',', '.') }}
                            </td>

                            <td class="px-4 py-3.5 text-right font-mono font-bold text-amber-600 dark:text-amber-400">
                                Rp {{ number_format((float) $payment->overpay_amount, 2, ',', '.') }}
                            </td>

                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <a href="{{ route('payments.show', $payment->id) }}" class="inline-flex items-center gap-1 px-2.5 py-1 border border-border bg-surface hover:bg-surface-muted text-text-main rounded-md transition-colors text-xs font-semibold shadow-2xs">
                                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Detail</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-text-muted">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <svg class="w-10 h-10 mx-auto text-text-muted/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <p class="text-xs font-semibold text-text-main">Tidak Ada Transaksi Lebih Bayar</p>
                                    <p class="text-[11px] text-text-muted">Tidak ditemukan pembayaran dengan nilai lebih bayar sesuai filter di atas.</p>
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
