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
        @if(auth()->user()->hasPermission('create_payments') && !in_array($invoice->invoice_status, ['lunas', 'batal'], true))
            <a href="{{ route('invoices.payments.create', $invoice->id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
                Input Pembayaran
            </a>
        @endif
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
                {{-- Biaya Pokok --}}
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Harga Paket (Subtotal)</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}</span>
                </div>

                @if((float)$invoice->discount > 0)
                <div class="flex justify-between gap-4 text-green-700">
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
                    <span class="text-slate-500">PPN ({{ number_format($ppnRate, 0) }}%)</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format($ppnAmount, 0, ',', '.') }}</span>
                </div>
                @else
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">PPN</span>
                    <span class="font-mono text-slate-400">Tidak dikenakan</span>
                </div>
                @endif

                {{-- Biaya Tambahan Di Luar Standar --}}
                @if((float)($invoice->prorate_amount ?? 0) > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Tagihan Prorate</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format((float) $invoice->prorate_amount, 0, ',', '.') }}</span>
                </div>
                @endif

                @if((float)($invoice->extra_cable_fee ?? 0) > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Biaya Kabel Tambahan</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format((float) $invoice->extra_cable_fee, 0, ',', '.') }}</span>
                </div>
                @endif

                @if((float)($invoice->other_fee ?? 0) > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Biaya Lain-lain</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format((float) $invoice->other_fee, 0, ',', '.') }}</span>
                </div>
                @endif

                @if((float)($invoice->extra_installation_fee ?? 0) > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Jasa Instalasi Tambahan</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format((float) $invoice->extra_installation_fee, 0, ',', '.') }}</span>
                </div>
                @endif

                @if((float)($invoice->extra_pole_fee ?? 0) > 0)
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Tambahan Tiang</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format((float) $invoice->extra_pole_fee, 0, ',', '.') }}</span>
                </div>
                @endif

                {{-- Total & Pembayaran --}}
                <div class="flex justify-between gap-4 pt-3 border-t border-slate-200">
                    <span class="font-bold text-slate-800">Total Tagihan</span>
                    <span class="font-mono font-bold text-slate-900 text-base">Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Sudah Terbayar</span>
                    <span class="font-mono text-emerald-700">Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-4 pt-2 border-t border-slate-100">
                    <span class="font-bold text-slate-800">Sisa Tagihan</span>
                    <span class="font-mono font-bold {{ (float)$invoice->remaining_amount > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                        Rp {{ number_format((float) $invoice->remaining_amount, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Riwayat Pembayaran</h2>
                <p class="text-xs text-slate-500 mt-1">Pembayaran yang sudah dicatat untuk invoice ini.</p>
            </div>
        </div>

        @if($invoice->payments->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600 uppercase tracking-wider text-[10px]">
                            <th class="px-4 py-3">No. Pembayaran</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Metode</th>
                            <th class="px-4 py-3 text-right">Nominal</th>
                            <th class="px-4 py-3">Penerima</th>
                            <th class="px-4 py-3">Bukti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($invoice->payments as $payment)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-4 py-3 font-mono font-bold text-slate-800">{{ $payment->payment_number }}</td>
                                <td class="px-4 py-3">{{ optional($payment->payment_date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ strtoupper($payment->payment_method) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold">Rp {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
                                <td class="px-4 py-3">{{ $payment->receiver->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if($payment->proof_file)
                                        <a href="{{ asset('storage/' . $payment->proof_file) }}" target="_blank" class="text-sky-700 hover:text-sky-900 font-semibold">Lihat bukti</a>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-6 text-center text-sm text-slate-500">
                Belum ada pembayaran untuk invoice ini.
            </div>
        @endif
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
