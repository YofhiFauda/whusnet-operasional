@extends('layouts.app')

@section('title', 'Detail Pembayaran - Whusnet Operasional')
@section('page_title', 'Detail Pembayaran')

@section('content')
@php
    $badgeClass = match($payment->payment_status->value) {
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
                {{ $payment->payment_status->label() }}
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

        @if($payment->old_payment_id || $payment->old_transaction_id || $payment->old_request_id)
        <div class="border-t border-slate-100 bg-sky-50/50 p-6">
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-sky-600 text-white text-[10px] font-bold">i</span>
                <h3 class="text-xs font-bold text-sky-900 uppercase tracking-wider">Audit Visibilitas Data Migrasi</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                @if($payment->old_payment_id)
                <div>
                    <span class="text-slate-500 block">ID Bayar Lama:</span>
                    <span class="font-mono font-bold text-slate-800">{{ $payment->old_payment_id }}</span>
                </div>
                @endif
                @if($payment->old_transaction_id)
                <div>
                    <span class="text-slate-500 block">ID Transaksi Lama:</span>
                    <span class="font-mono font-bold text-slate-800">{{ $payment->old_transaction_id }}</span>
                </div>
                @endif
                @if($payment->old_request_id)
                <div>
                    <span class="text-slate-500 block">ID Permintaan Lama:</span>
                    <span class="font-mono font-bold text-slate-800">{{ $payment->old_request_id }}</span>
                </div>
                @endif
            </div>
        </div>
        @endif

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

        @if(auth()->user()->hasPermission('audit_logs.view'))
            <div class="border-t border-slate-100 p-6">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">Riwayat Audit Pembayaran</h3>
                @if($payment->relationLoaded('auditLogs') && $payment->auditLogs->count() > 0)
                    <div class="overflow-x-auto border border-slate-100 rounded-lg">
                        <table class="min-w-full divide-y divide-slate-100 text-xs">
                            <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left font-bold">Waktu</th>
                                    <th class="px-4 py-3 text-left font-bold">Aksi</th>
                                    <th class="px-4 py-3 text-left font-bold">User</th>
                                    <th class="px-4 py-3 text-left font-bold">Data Sebelum</th>
                                    <th class="px-4 py-3 text-left font-bold">Data Sesudah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($payment->auditLogs as $auditLog)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-slate-700">
                                            {{ optional($auditLog->created_at)->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-slate-800">
                                            {{ ucwords(str_replace('_', ' ', $auditLog->action)) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-slate-700">
                                            {{ $auditLog->user->name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <pre class="max-w-xs whitespace-pre-wrap break-words text-[10px] text-slate-600 bg-slate-50 border border-slate-100 rounded p-2">{{ $auditLog->old_values ? json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '-' }}</pre>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <pre class="max-w-xs whitespace-pre-wrap break-words text-[10px] text-slate-600 bg-slate-50 border border-slate-100 rounded p-2">{{ $auditLog->new_values ? json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '-' }}</pre>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-slate-500">Belum ada riwayat audit pembayaran.</p>
                @endif
            </div>
        @endif
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
