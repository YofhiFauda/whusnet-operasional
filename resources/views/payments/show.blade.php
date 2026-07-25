@extends('layouts.app')

@section('title', 'Detail Pembayaran - Whusnet Operasional')
@section('page_title', 'Detail Pembayaran')
@section('breadcrumb_parent', 'Pembayaran')
@section('breadcrumb_parent_url', route('payments.index'))

@section('content')
@php
    $badgeClass = match($payment->payment_status->value) {
        'valid' => 'bg-green-500/10 text-green-500 border-green-500/20',
        'ditolak' => 'bg-red-500/10 text-red-500 border-red-500/20',
        default => 'bg-amber-500/10 text-amber-500 border-amber-500/20',
    };
@endphp

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-text-main tracking-tight">Detail Pembayaran {{ $payment->payment_number }}</h1>
    </div>
    <div class="flex gap-2">
        @if($payment->invoice)
            <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary hover:bg-primary-focus text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
                Detail Tagihan
            </a>
        @endif
        <a href="{{ route('payments.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Kembali
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-surface border border-border rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-text-main uppercase tracking-wider">Informasi Pembayaran</h2>
                <p class="text-xs text-text-muted mt-1">Pembayaran ini tercatat pada invoice dan pelanggan berikut.</p>
            </div>
            <span class="px-2.5 py-1 text-xs font-bold rounded-full border {{ $badgeClass }}">
                {{ $payment->payment_status->label() }}
            </span>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
            <div>
                <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">No. Pembayaran</p>
                <p class="font-mono font-bold text-text-main mt-1">{{ $payment->payment_number }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">Tanggal Bayar</p>
                <p class="text-text-main mt-1">{{ optional($payment->payment_date)->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">Metode Bayar</p>
                <p class="font-semibold text-text-main mt-1">{{ strtoupper($payment->payment_method) }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">Nominal Bayar</p>
                <p class="font-mono font-bold text-text-main mt-1">Rp {{ number_format((float) $payment->amount, 2, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">Diterima Oleh</p>
                <p class="text-text-main mt-1">{{ $payment->receiver->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">POP / Cabang</p>
                <p class="text-text-main mt-1">{{ $payment->pop->name ?? '-' }}</p>
            </div>
        </div>

        @if($payment->old_payment_id || $payment->old_transaction_id || $payment->old_request_id)
        <div class="border-t border-border bg-primary/5 p-6">
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary text-white text-[10px] font-bold">i</span>
                <h3 class="text-xs font-bold text-primary uppercase tracking-wider">Audit Visibilitas Data Migrasi</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                @if($payment->old_payment_id)
                <div>
                    <span class="text-text-muted block">ID Bayar Lama:</span>
                    <span class="font-mono font-bold text-text-main">{{ $payment->old_payment_id }}</span>
                </div>
                @endif
                @if($payment->old_transaction_id)
                <div>
                    <span class="text-text-muted block">ID Transaksi Lama:</span>
                    <span class="font-mono font-bold text-text-main">{{ $payment->old_transaction_id }}</span>
                </div>
                @endif
                @if($payment->old_request_id)
                <div>
                    <span class="text-text-muted block">ID Permintaan Lama:</span>
                    <span class="font-mono font-bold text-text-main">{{ $payment->old_request_id }}</span>
                </div>
                @endif
            </div>
        </div>
        @endif

        <div class="border-t border-border p-6">
            <h3 class="text-xs font-bold text-text-main uppercase tracking-wider mb-3">Catatan Pembayaran</h3>
            <p class="text-sm text-text-secondary">{{ $payment->note ?: 'Tidak ada catatan.' }}</p>
        </div>

        <div class="border-t border-border p-6">
            <h3 class="text-xs font-bold text-text-main uppercase tracking-wider mb-3">Bukti Pembayaran</h3>
            @if($payment->proof_file)
                <a href="{{ asset('storage/' . $payment->proof_file) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-primary hover:bg-primary-focus text-white rounded-md transition-colors text-xs font-semibold">
                    Lihat Bukti Pembayaran
                </a>
                <p class="text-[10px] text-text-muted mt-2 font-mono">{{ $payment->proof_file }}</p>
            @else
                <p class="text-sm text-text-muted">Bukti pembayaran belum diupload.</p>
            @endif
        </div>

        @if(auth()->user()->hasPermission('audit_logs.view'))
            <div class="border-t border-border p-6">
                <h3 class="text-xs font-bold text-text-main uppercase tracking-wider mb-3">Riwayat Audit Pembayaran</h3>
                @if($payment->relationLoaded('auditLogs') && $payment->auditLogs->count() > 0)
                    <div class="overflow-x-auto border border-border rounded-lg">
                        <table class="min-w-full divide-y divide-border text-xs">
                            <thead class="bg-surface-muted text-text-muted uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left font-bold">Waktu</th>
                                    <th class="px-4 py-3 text-left font-bold">Aksi</th>
                                    <th class="px-4 py-3 text-left font-bold">User</th>
                                    <th class="px-4 py-3 text-left font-bold">Data Sebelum</th>
                                    <th class="px-4 py-3 text-left font-bold">Data Sesudah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($payment->auditLogs as $auditLog)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-text-secondary">
                                            {{ optional($auditLog->created_at)->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-text-main">
                                            {{ ucwords(str_replace('_', ' ', $auditLog->action)) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-text-secondary">
                                            {{ $auditLog->user->name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <pre class="max-w-xs whitespace-pre-wrap break-words text-[10px] text-text-muted bg-surface border border-border rounded p-2">{{ $auditLog->old_values ? json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '-' }}</pre>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <pre class="max-w-xs whitespace-pre-wrap break-words text-[10px] text-text-muted bg-surface border border-border rounded p-2">{{ $auditLog->new_values ? json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '-' }}</pre>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-text-muted">Belum ada riwayat audit pembayaran.</p>
                @endif
            </div>
        @endif
    </div>

    <div class="space-y-6">
        <div class="bg-surface border border-border rounded-lg p-6">
            <h2 class="text-sm font-bold text-text-main uppercase tracking-wider mb-4">Tagihan</h2>
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">No. Tagihan</p>
                    @if($payment->invoice)
                        <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="font-mono font-semibold text-primary hover:text-primary-focus mt-1 inline-block">{{ $payment->invoice->invoice_number }}</a>
                    @else
                        <p class="text-text-muted mt-1">-</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">Periode</p>
                    <p class="font-mono text-text-main mt-1">{{ $payment->invoice->billing_period ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">Total Tagihan</p>
                    <p class="font-mono text-text-main mt-1">Rp {{ number_format((float) ($payment->invoice->total_amount ?? 0), 2, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">Sisa Tagihan</p>
                    <p class="font-mono text-text-main mt-1">Rp {{ number_format((float) ($payment->invoice->remaining_amount ?? 0), 2, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-lg p-6">
            <h2 class="text-sm font-bold text-text-main uppercase tracking-wider mb-4">Pelanggan</h2>
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">Nama</p>
                    <p class="font-semibold text-text-main mt-1">{{ $payment->customer->full_name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">ID Pelanggan</p>
                    <p class="font-mono text-text-main mt-1">{{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-text-muted uppercase tracking-wider">No. HP</p>
                    <p class="font-mono text-text-main mt-1">{{ $payment->customer->primary_phone ?? $payment->customer->phone ?? '-' }}</p>
                </div>
                @if($payment->customer)
                    <a href="{{ route('customers.show', $payment->customer_id) }}" class="inline-flex items-center px-3 py-1.5 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-md transition-colors text-xs font-semibold mt-2">
                        Detail Pelanggan
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
