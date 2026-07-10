@extends('layouts.app')

@section('title', 'Laporan Pembayaran - Whusnet Operasional')
@section('page_title', 'Laporan Pembayaran')

@section('content')
@php
    $statusBadges = [
        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
        'valid' => 'bg-green-50 text-green-700 border-green-200',
        'ditolak' => 'bg-rose-50 text-rose-700 border-rose-200',
    ];
    $statusLabels = [
        'pending' => 'Pending',
        'valid' => 'Valid / Disetujui',
        'ditolak' => 'Ditolak',
    ];
    $methodLabels = [
        'cash' => 'Cash',
        'transfer' => 'Transfer Bank',
        'qris' => 'QRIS',
        'lainnya' => 'Lainnya',
    ];
@endphp

<div class="space-y-6">
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Card Total Pembayaran -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-sky-50 flex items-center justify-center text-sky-600 shrink-0">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Pembayaran (Semua)</p>
                <h3 class="text-xl font-bold text-slate-900 mt-0.5">Rp {{ number_format($totalAmountSum, 2, ',', '.') }}</h3>
            </div>
        </div>

        <!-- Card Total Valid -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-green-50 flex items-center justify-center text-green-600 shrink-0">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pembayaran Valid (Lunas/Diterima)</p>
                <h3 class="text-xl font-bold text-slate-900 mt-0.5">Rp {{ number_format($totalValidSum, 2, ',', '.') }}</h3>
            </div>
        </div>

        <!-- Card Total Pending -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pembayaran Pending (Verifikasi)</p>
                <h3 class="text-xl font-bold text-slate-900 mt-0.5">Rp {{ number_format($totalPendingSum, 2, ',', '.') }}</h3>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm">
        <form method="GET" action="{{ route('reports.payments.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Filter POP -->
                <div>
                    <label for="pop_id" class="block text-xs font-semibold text-slate-600 mb-1">POP / Cabang</label>
                    <select id="pop_id" name="pop_id" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua POP Akses</option>
                        @foreach($pops as $pop)
                            <option value="{{ $pop->id }}" @selected((string)$popId === (string)$pop->id)>
                                {{ $pop->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Metode Pembayaran -->
                <div>
                    <label for="payment_method" class="block text-xs font-semibold text-slate-600 mb-1">Metode Pembayaran</label>
                    <select id="payment_method" name="payment_method" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua Metode</option>
                        @foreach($allowedMethods as $item)
                            <option value="{{ $item }}" @selected($paymentMethod === $item)>
                                {{ $methodLabels[$item] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Status Pembayaran -->
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-600 mb-1">Status Pembayaran</label>
                    <select id="status" name="status" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua Status</option>
                        @foreach($allowedStatuses as $item)
                            <option value="{{ $item }}" @selected($status === $item)>
                                {{ $statusLabels[$item] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Tanggal Bayar Dari -->
                <div>
                    <label for="start_date" class="block text-xs font-semibold text-slate-600 mb-1">Tanggal Dari</label>
                    <input id="start_date" type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <!-- Filter Tanggal Bayar Sampai -->
                <div>
                    <label for="end_date" class="block text-xs font-semibold text-slate-600 mb-1">Tanggal Sampai</label>
                    <input id="end_date" type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end items-center pt-4 border-t border-slate-100 gap-2">
                <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filter Laporan
                </button>
                <a href="{{ route('reports.payments.index') }}" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm focus:outline-none">
                    Reset
                </a>
                <a href="{{ route('reports.payments.export', request()->query()) }}" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export CSV
                </a>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
        <!-- Table Header Info -->
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Rincian Laporan Pembayaran</h3>
            <span class="inline-flex items-center rounded-md bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">
                Total: {{ $payments->total() }} Transaksi
            </span>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500 bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-3">No. Kwitansi</th>
                        <th class="px-6 py-3">No. Invoice</th>
                        <th class="px-6 py-3">Pelanggan</th>
                        <th class="px-6 py-3">POP / Cabang</th>
                        <th class="px-6 py-3 text-center">Tanggal Bayar</th>
                        <th class="px-6 py-3 text-center">Metode</th>
                        <th class="px-6 py-3 text-right">Nominal</th>
                        <th class="px-6 py-3">Penerima</th>
                        <th class="px-6 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-800 whitespace-nowrap">
                                <a href="{{ route('payments.show', $payment->id) }}" class="text-sky-600 hover:text-sky-800 hover:underline">
                                    {{ $payment->payment_number }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($payment->invoice)
                                    <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="text-slate-700 hover:text-sky-600 hover:underline font-medium">
                                        {{ $payment->invoice->invoice_number }}
                                    </a>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($payment->customer)
                                    <div class="font-bold text-slate-900">{{ $payment->customer->full_name }}</div>
                                    <div class="text-xs text-slate-500">{{ $payment->customer->customer_code }}</div>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-700 whitespace-nowrap">
                                {{ $payment->pop->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-slate-600 whitespace-nowrap text-center">
                                {{ $payment->payment_date ? $payment->payment_date->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-slate-100 text-slate-800 border border-slate-200">
                                    {{ $methodLabels[$payment->payment_method] ?? strtoupper($payment->payment_method) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-bold text-slate-900 whitespace-nowrap">
                                Rp {{ number_format($payment->amount, 2, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-slate-700 whitespace-nowrap">
                                {{ $payment->receiver->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $statusBadges[$payment->payment_status] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">
                                    {{ $statusLabels[$payment->payment_status] ?? $payment->payment_status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-10 text-center text-slate-500">
                                <svg class="mx-auto h-12 w-12 text-slate-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Tidak ada data pembayaran yang cocok dengan kriteria filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        @if($payments->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
