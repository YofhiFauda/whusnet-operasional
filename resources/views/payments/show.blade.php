@extends('layouts.app')

@section('title', 'Detail Pembayaran - Whusnet Operasional')
@section('page_title', 'Detail Pembayaran')

@section('content')
@php
    $badgeClass = match($payment->payment_status) {
        'valid' => 'bg-green-50 text-green-700 border-green-100',
        'ditolak' => 'bg-red-50 text-red-700 border-red-100',
        default => 'bg-amber-50 text-amber-700 border-amber-100',
    };
@endphp

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <nav class="flex text-xs font-semibold text-slate-400 uppercase tracking-wider gap-2 mb-2">
            <a href="{{ route('payments.index') }}" class="hover:text-slate-700 transition-colors">Daftar Pembayaran</a>
            <span>/</span>
            <span class="text-slate-600">{{ $payment->payment_number }}</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-800 tracking-tight">Detail Pembayaran {{ $payment->payment_number }}</h1>
    </div>
    <div class="flex gap-2">
        @if($payment->invoice)
            <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
                Detail Tagihan
            </a>
        @endif
        <a href="{{ route('payments.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Kembali
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Informasi Pembayaran</h2>
                <p class="text-xs text-slate-500 mt-1">Pembayaran ini tercatat pada invoice dan pelanggan berikut.</p>
            </div>
            <span class="px-2.5 py-1 text-xs font-bold rounded-full border {{ $badgeClass }}">
                {{ ucwords(str_replace('_', ' ', $payment->payment_status)) }}
            </span>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">No. Pembayaran</p>
                <p class="font-mono font-bold text-slate-900 mt-1">{{ $payment->payment_number }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tanggal Bayar</p>
                <p class="text-slate-900 mt-1">{{ optional($payment->payment_date)->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Metode Bayar</p>
                <p class="font-semibold text-slate-900 mt-1">{{ strtoupper($payment->payment_method) }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nominal Bayar</p>
                <p class="font-mono font-bold text-slate-900 mt-1">Rp {{ number_format((float) $payment->amount, 2, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Diterima Oleh</p>
                <p class="text-slate-900 mt-1">{{ $payment->receiver->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">POP / Cabang</p>
                <p class="text-slate-900 mt-1">{{ $payment->pop->name ?? '-' }}</p>
            </div>
        </div>

        <div class="border-t border-slate-100 p-6">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">Catatan Pembayaran</h3>
            <p class="text-sm text-slate-700">{{ $payment->note ?: 'Tidak ada catatan.' }}</p>
        </div>

        <div class="border-t border-slate-100 p-6">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">Bukti Pembayaran</h3>
            @if($payment->proof_file)
                <a href="{{ asset('storage/' . $payment->proof_file) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold">
                    Lihat Bukti Pembayaran
                </a>
                <p class="text-[10px] text-slate-500 mt-2 font-mono">{{ $payment->proof_file }}</p>
            @else
                <p class="text-sm text-slate-500">Bukti pembayaran belum diupload.</p>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-lg p-6">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">Tagihan</h2>
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">No. Tagihan</p>
                    @if($payment->invoice)
                        <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="font-mono font-semibold text-sky-700 hover:text-sky-900 mt-1 inline-block">{{ $payment->invoice->invoice_number }}</a>
                    @else
                        <p class="text-slate-400 mt-1">-</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Periode</p>
                    <p class="font-mono text-slate-900 mt-1">{{ $payment->invoice->billing_period ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Tagihan</p>
                    <p class="font-mono text-slate-900 mt-1">Rp {{ number_format((float) ($payment->invoice->total_amount ?? 0), 2, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Sisa Tagihan</p>
                    <p class="font-mono text-slate-900 mt-1">Rp {{ number_format((float) ($payment->invoice->remaining_amount ?? 0), 2, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-6">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">Pelanggan</h2>
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</p>
                    <p class="font-semibold text-slate-900 mt-1">{{ $payment->customer->full_name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">ID Pelanggan</p>
                    <p class="font-mono text-slate-900 mt-1">{{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">No. HP</p>
                    <p class="font-mono text-slate-900 mt-1">{{ $payment->customer->primary_phone ?? $payment->customer->phone ?? '-' }}</p>
                </div>
                @if($payment->customer)
                    <a href="{{ route('customers.show', $payment->customer_id) }}" class="inline-flex items-center px-3 py-1.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md transition-colors text-xs font-semibold">
                        Detail Pelanggan
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
