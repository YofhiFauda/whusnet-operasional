@extends('layouts.app')

@section('title', 'Daftar Pembayaran - Whusnet Operasional')
@section('page_title', 'Daftar Pembayaran')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-slate-800 text-sm font-semibold uppercase tracking-wider">Daftar dan Filter Pembayaran</h3>
        <p class="text-xs text-slate-500 mt-1">Pembayaran selalu terhubung ke invoice, pelanggan, dan POP/Cabang.</p>
    </div>
    <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
        Buka Daftar Tagihan
    </a>
</div>

<div class="bg-white border border-slate-200 rounded-lg p-6 mb-6">
    <form action="{{ route('payments.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-7 gap-4 items-end">
        <div class="sm:col-span-2 xl:col-span-2">
            <label for="search" class="block text-xs font-semibold text-slate-500 mb-2">CARI PEMBAYARAN</label>
            <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Pembayaran, invoice, pelanggan, CID, HP..." class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <div>
            <label for="date_from" class="block text-xs font-semibold text-slate-500 mb-2">DARI TANGGAL</label>
            <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <div>
            <label for="date_to" class="block text-xs font-semibold text-slate-500 mb-2">SAMPAI TANGGAL</label>
            <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <div>
            <label for="pop_id" class="block text-xs font-semibold text-slate-500 mb-2">POP / CABANG</label>
            <select name="pop_id" id="pop_id" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $popId === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="method" class="block text-xs font-semibold text-slate-500 mb-2">METODE</label>
            <select name="method" id="method" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Metode</option>
                @foreach($allowedMethods as $paymentMethod)
                    <option value="{{ $paymentMethod }}" {{ $method === $paymentMethod ? 'selected' : '' }}>{{ strtoupper($paymentMethod) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="block text-xs font-semibold text-slate-500 mb-2">STATUS</label>
            <select name="status" id="status" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Status</option>
                @foreach($allowedStatuses as $paymentStatus)
                    <option value="{{ $paymentStatus }}" {{ $status === $paymentStatus ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $paymentStatus)) }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2 xl:col-start-6 xl:col-span-2">
            <button type="submit" class="flex-1 bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500/25">
                Filter
            </button>
            <a href="{{ route('payments.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none">
                Reset
            </a>
        </div>
    </form>
</div>

<div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-slate-700">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-200 text-slate-500 font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">NO. PEMBAYARAN</th>
                    <th class="px-6 py-3.5">INVOICE</th>
                    <th class="px-6 py-3.5">PELANGGAN</th>
                    <th class="px-6 py-3.5">POP</th>
                    <th class="px-6 py-3.5">TANGGAL</th>
                    <th class="px-6 py-3.5">METODE</th>
                    <th class="px-6 py-3.5 text-right">NOMINAL</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($payments as $payment)
                    @php
                        $badgeClass = match($payment->payment_status) {
                            'valid' => 'bg-green-50 text-green-700 border-green-100',
                            'ditolak' => 'bg-red-50 text-red-700 border-red-100',
                            default => 'bg-amber-50 text-amber-700 border-amber-100',
                        };
                    @endphp
                    <tr class="hover:bg-slate-50/45 transition-colors">
                        <td class="px-6 py-3.5 text-center text-slate-400 data-text">{{ ($payments->currentPage() - 1) * $payments->perPage() + $loop->iteration }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <a href="{{ route('payments.show', $payment->id) }}" class="font-mono font-bold text-sky-700 hover:text-sky-900">{{ $payment->payment_number }}</a>
                            <div class="text-[10px] text-slate-400 mt-0.5">{{ $payment->receiver->name ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            @if($payment->invoice)
                                <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="font-mono font-semibold text-slate-800 hover:text-sky-700">{{ $payment->invoice->invoice_number }}</a>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <div class="font-semibold text-slate-900">{{ $payment->customer->full_name ?? '-' }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-medium text-slate-800">{{ $payment->pop->name ?? '-' }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap">{{ optional($payment->payment_date)->format('d/m/Y') }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-semibold">{{ strtoupper($payment->payment_method) }}</td>
                        <td class="px-6 py-3.5 text-right font-mono font-semibold">Rp {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
                        <td class="px-6 py-3.5 text-center whitespace-nowrap">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $badgeClass }}">
                                {{ ucwords(str_replace('_', ' ', $payment->payment_status)) }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="{{ route('payments.show', $payment->id) }}" class="inline-flex items-center px-3 py-1.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md transition-colors text-xs font-semibold">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada pembayaran yang sesuai filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-slate-100">
        {{ $payments->links() }}
    </div>
</div>
@endsection
