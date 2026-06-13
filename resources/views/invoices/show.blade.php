@extends('layouts.app')

@section('title', 'Detail Tagihan - Whusnet Operasional')
@section('page_title', 'Detail Tagihan')

@section('content')
@php
    $badgeClass = match($invoice->invoice_status) {
        'lunas' => 'bg-green-50 text-green-700 border-green-100',
        'sebagian' => 'bg-amber-50 text-amber-700 border-amber-100',
        'batal' => 'bg-red-50 text-red-700 border-red-100',
        default => 'bg-slate-50 text-slate-700 border-slate-100',
    };
@endphp

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <nav class="flex text-xs font-semibold text-slate-400 uppercase tracking-wider gap-2 mb-2">
            <a href="{{ route('invoices.index') }}" class="hover:text-slate-700 transition-colors">Daftar Tagihan</a>
            <span>/</span>
            <span class="text-slate-600">{{ $invoice->invoice_number }}</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-800 tracking-tight">Detail Tagihan {{ $invoice->invoice_number }}</h1>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('customers.show', $invoice->customer_id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Detail Pelanggan
        </a>
        <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Kembali
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Informasi Tagihan</h2>
                <p class="text-xs text-slate-500 mt-1">Snapshot tagihan berdasarkan layanan pelanggan saat invoice dibuat.</p>
            </div>
            <span class="px-2.5 py-1 text-xs font-bold rounded-full border {{ $badgeClass }}">
                {{ ucwords(str_replace('_', ' ', $invoice->invoice_status)) }}
            </span>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">No. Tagihan</p>
                <p class="font-mono font-bold text-slate-900 mt-1">{{ $invoice->invoice_number }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Periode</p>
                <p class="font-mono text-slate-900 mt-1">{{ $invoice->billing_period }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tanggal Terbit</p>
                <p class="text-slate-900 mt-1">{{ optional($invoice->issue_date)->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tanggal Jatuh Tempo</p>
                <p class="text-slate-900 mt-1">{{ optional($invoice->due_date)->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Dibuat Oleh</p>
                <p class="text-slate-900 mt-1">{{ $invoice->creator->name ?? 'System' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">POP / Cabang</p>
                <p class="text-slate-900 mt-1">{{ $invoice->pop->name ?? '-' }}</p>
            </div>
        </div>

        <div class="border-t border-slate-100 p-6">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-4">Rincian Biaya</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Subtotal</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format((float) $invoice->subtotal, 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Diskon</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format((float) $invoice->discount, 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">PPN (%)</span>
                    <span class="font-mono text-slate-900">{{ number_format((float) $invoice->ppn, 2, ',', '.') }}%</span>
                </div>
                <div class="flex justify-between gap-4 pt-3 border-t border-slate-100">
                    <span class="font-bold text-slate-800">Total Tagihan</span>
                    <span class="font-mono font-bold text-slate-900">Rp {{ number_format((float) $invoice->total_amount, 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Terbayar</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format((float) $invoice->paid_amount, 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-800">Sisa Tagihan</span>
                    <span class="font-mono font-bold text-slate-900">Rp {{ number_format((float) $invoice->remaining_amount, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-lg p-6">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">Pelanggan</h2>
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</p>
                    <p class="font-semibold text-slate-900 mt-1">{{ $invoice->customer->full_name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">ID Pelanggan</p>
                    <p class="font-mono text-slate-900 mt-1">{{ $invoice->customer->cid ?? $invoice->customer->customer_code ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">No. HP</p>
                    <p class="font-mono text-slate-900 mt-1">{{ $invoice->customer->primary_phone ?? $invoice->customer->phone ?? '-' }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-6">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">Paket</h2>
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama Paket</p>
                    <p class="font-semibold text-slate-900 mt-1">{{ $invoice->customerService->package_name_snapshot ?? $invoice->internetPackage->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Download / Upload</p>
                    <p class="text-slate-900 mt-1">
                        {{ $invoice->customerService->download_speed_snapshot ?? '-' }} /
                        {{ $invoice->customerService->upload_speed_snapshot ?? '-' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Harga Bulanan</p>
                    <p class="font-mono text-slate-900 mt-1">Rp {{ number_format((float) ($invoice->customerService->monthly_price ?? 0), 2, ',', '.') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
