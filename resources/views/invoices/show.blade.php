@extends('layouts.app')

@section('title', 'Detail Tagihan - Whusnet Operasional')
@section('page_title', 'Detail Tagihan')

@section('content')
@php
    $badgeClass = match($invoice->invoice_status->value) {
        'lunas' => 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400 border-green-100 dark:border-green-500/20',
        'sebagian' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-100 dark:border-amber-500/20',
        'batal' => 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 border-red-100 dark:border-red-500/20',
        default => 'bg-slate-50 dark:bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-100 dark:border-slate-500/20',
    };
@endphp

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <nav class="flex text-xs font-semibold text-slate-400 dark:text-slate-500 dark:text-slate-400 uppercase tracking-wider gap-2 mb-2">
            <a href="{{ route('invoices.index') }}" class="hover:text-slate-700 dark:hover:text-slate-200 transition-colors">Daftar Tagihan</a>
            <span>/</span>
            <span class="text-slate-600 dark:text-slate-300">{{ $invoice->invoice_number }}</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-200 tracking-tight">Detail Tagihan {{ $invoice->invoice_number }}</h1>
    </div>
    <div class="flex gap-2">
        @if(auth()->user()->hasPermission('create_payments') && !in_array($invoice->invoice_status->value, ['lunas', 'batal'], true))
            <a href="{{ route('invoices.payments.create', $invoice->id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
                Input Pembayaran
            </a>
        @endif
        <a href="{{ route('customers.show', $invoice->customer_id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Detail Pelanggan
        </a>
        <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Kembali
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Informasi Tagihan</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Snapshot tagihan berdasarkan layanan pelanggan saat invoice dibuat.</p>
            </div>
            <div class="flex items-center gap-2">
                @if($invoice->invoice_type)
                <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-100 dark:border-sky-500/20">
                    {{ $invoice->invoice_type->label() }}
                </span>
                @endif
                <span class="px-2.5 py-1 text-xs font-bold rounded-full border {{ $badgeClass }}">
                    {{ $invoice->invoice_status->label() }}
                </span>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">No. Tagihan</p>
                <p class="font-mono font-bold text-slate-900 dark:text-slate-200 mt-1">{{ $invoice->invoice_number }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Periode</p>
                <p class="font-mono text-slate-900 dark:text-slate-200 mt-1">{{ $invoice->billing_period }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tanggal Terbit</p>
                <p class="text-slate-900 dark:text-slate-200 mt-1">{{ optional($invoice->issue_date)->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tanggal Jatuh Tempo</p>
                <p class="text-slate-900 dark:text-slate-200 mt-1">{{ optional($invoice->due_date)->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Dibuat Oleh</p>
                <p class="text-slate-900 dark:text-slate-200 mt-1">{{ $invoice->creator->name ?? 'System' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">POP / Cabang</p>
                <p class="text-slate-900 dark:text-slate-200 mt-1">{{ $invoice->pop->name ?? '-' }}</p>
            </div>
        </div>

        @if($invoice->old_invoice_id || $invoice->old_cost_id || $invoice->old_request_id)
        <div class="border-t border-slate-100 dark:border-slate-700 bg-sky-50/50 dark:bg-sky-900/20 p-6">
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-sky-600 text-white text-[10px] font-bold">i</span>
                <h3 class="text-xs font-bold text-sky-900 dark:text-sky-400 uppercase tracking-wider">Audit Visibilitas Data Migrasi</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                @if($invoice->old_invoice_id)
                <div>
                    <span class="text-slate-500 dark:text-slate-400 block">ID Invoice Lama:</span>
                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $invoice->old_invoice_id }}</span>
                </div>
                @endif
                @if($invoice->old_cost_id)
                <div>
                    <span class="text-slate-500 dark:text-slate-400 block">ID Biaya Lama:</span>
                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $invoice->old_cost_id }}</span>
                </div>
                @endif
                @if($invoice->old_request_id)
                <div>
                    <span class="text-slate-500 dark:text-slate-400 block">ID Permintaan Lama:</span>
                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $invoice->old_request_id }}</span>
                </div>
                @endif
            </div>
        </div>
        @endif

        <div class="border-t border-slate-100 dark:border-slate-700 p-6">
            <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-4">Rincian Biaya</h3>
            <div class="space-y-3 text-sm">
                {{-- Biaya Pokok --}}
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Harga Paket (Subtotal)</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}</span>
                </div>

                @if((float)$invoice->discount > 0)
                <div class="flex justify-between gap-4 text-green-700 dark:text-green-400">
                    <span>Potongan Diskon</span>
                    <span class="font-mono">- Rp {{ number_format((float) $invoice->discount, 0, ',', '.') }}</span>
                </div>
                @endif

                @php
                    $afterDiscount = max(0, (float)$invoice->subtotal - (float)$invoice->discount);
                    $ppnRate = (float)$invoice->ppn;
                    $ppnAmount = round($afterDiscount * ($ppnRate / 100), 2);
                @endphp

                @if($ppnRate > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">PPN ({{ number_format($ppnRate, 0) }}%)</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp {{ number_format($ppnAmount, 0, ',', '.') }}</span>
                </div>
                @else
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">PPN</span>
                    <span class="font-mono text-slate-400 dark:text-slate-500">Tidak dikenakan</span>
                </div>
                @endif

                {{-- Biaya Tambahan Di Luar Standar --}}
                @if((float)($invoice->prorate_amount ?? 0) > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Tagihan Prorate</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp {{ number_format((float) $invoice->prorate_amount, 0, ',', '.') }}</span>
                </div>
                @endif

                @if((float)($invoice->extra_cable_fee ?? 0) > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Biaya Kabel Tambahan</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp {{ number_format((float) $invoice->extra_cable_fee, 0, ',', '.') }}</span>
                </div>
                @endif

                @if((float)($invoice->other_fee ?? 0) > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Biaya Lain-lain</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp {{ number_format((float) $invoice->other_fee, 0, ',', '.') }}</span>
                </div>
                @endif

                @if((float)($invoice->extra_installation_fee ?? 0) > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Jasa Instalasi Tambahan</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp {{ number_format((float) $invoice->extra_installation_fee, 0, ',', '.') }}</span>
                </div>
                @endif

                @if((float)($invoice->extra_pole_fee ?? 0) > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Tambahan Tiang</span>
                    <span class="font-mono text-slate-900 dark:text-slate-200">Rp {{ number_format((float) $invoice->extra_pole_fee, 0, ',', '.') }}</span>
                </div>
                @endif

                {{-- Total & Pembayaran --}}
                <div class="flex justify-between gap-4 pt-3 border-t border-slate-200 dark:border-slate-700">
                    <span class="font-bold text-slate-800 dark:text-slate-200">Total Tagihan</span>
                    <span class="font-mono font-bold text-slate-900 text-base">Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500 dark:text-slate-400 dark:text-slate-500">Sudah Terbayar</span>
                    <span class="font-mono text-emerald-700 dark:text-emerald-400">Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-4 pt-2 border-t border-slate-100 dark:border-slate-700">
                    <span class="font-bold text-slate-800 dark:text-slate-200">Sisa Tagihan</span>
                    <span class="font-mono font-bold {{ (float)$invoice->remaining_amount > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                        Rp {{ number_format((float) $invoice->remaining_amount, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Riwayat Pembayaran</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Pembayaran yang sudah dicatat untuk invoice ini.</p>
            </div>
        </div>

        @if($invoice->payments->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider text-[10px]">
                            <th class="px-4 py-3">No. Pembayaran</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Metode</th>
                            <th class="px-4 py-3 text-right">Nominal</th>
                            <th class="px-4 py-3">Penerima</th>
                            <th class="px-4 py-3">Bukti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-slate-700 dark:text-slate-300">
                        @foreach($invoice->payments as $payment)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/25 transition-colors">
                                <td class="px-4 py-3 font-mono font-bold text-slate-800 dark:text-slate-200">{{ $payment->payment_number }}</td>
                                <td class="px-4 py-3">{{ optional($payment->payment_date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ strtoupper($payment->payment_method) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold">Rp {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
                                <td class="px-4 py-3">{{ $payment->receiver->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if($payment->proof_file)
                                        <a href="{{ asset('storage/' . $payment->proof_file) }}" target="_blank" class="text-sky-700 dark:text-sky-400 hover:text-sky-900 dark:hover:text-sky-300 font-semibold">Lihat bukti</a>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-6 text-center text-sm text-slate-500 dark:text-slate-400 dark:text-slate-500">
                Belum ada pembayaran untuk invoice ini.
            </div>
        @endif
    </div>

    <div class="space-y-6">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider mb-4">Pelanggan</h2>
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama</p>
                    <p class="font-semibold text-slate-900 dark:text-slate-200 mt-1">{{ $invoice->customer->full_name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ID Pelanggan</p>
                    <p class="font-mono text-slate-900 dark:text-slate-200 mt-1">{{ $invoice->customer->cid ?? $invoice->customer->customer_code ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">No. HP</p>
                    <p class="font-mono text-slate-900 dark:text-slate-200 mt-1">{{ $invoice->customer->primary_phone ?? $invoice->customer->phone ?? '-' }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6">
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider mb-4">Paket</h2>
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Paket</p>
                    <p class="font-semibold text-slate-900 dark:text-slate-200 mt-1">{{ $invoice->customerService->package_name_snapshot ?? $invoice->internetPackage->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Download / Upload</p>
                    <p class="text-slate-900 dark:text-slate-200 mt-1">
                        {{ $invoice->customerService->download_speed_snapshot ?? '-' }} /
                        {{ $invoice->customerService->upload_speed_snapshot ?? '-' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Harga Bulanan</p>
                    <p class="font-mono text-slate-900 dark:text-slate-200 mt-1">Rp {{ number_format((float) ($invoice->customerService->monthly_price ?? 0), 2, ',', '.') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
