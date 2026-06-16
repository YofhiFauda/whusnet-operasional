@extends('layouts.app')

@section('title', 'Daftar Tagihan - Whusnet Operasional')
@section('page_title', 'Daftar Tagihan')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-slate-800 text-sm font-semibold uppercase tracking-wider">Daftar dan Filter Tagihan</h3>
        <p class="text-xs text-slate-500 mt-1">Tagihan berasal dari pelanggan aktif dan layanan pelanggan yang sudah tersimpan.</p>
    </div>
    <a href="/customers" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
        Buka Data Pelanggan
    </a>
</div>

<div class="bg-white border border-slate-200 rounded-lg p-6 mb-6">
    <form action="{{ route('invoices.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        <div>
            <label for="search" class="block text-xs font-semibold text-slate-500 mb-2">CARI TAGIHAN</label>
            <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Cari Nama, No. Tagihan, atau ID Invoice Lama..." class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
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
            <label for="billing_period" class="block text-xs font-semibold text-slate-500 mb-2">PERIODE</label>
            <input type="month" name="billing_period" id="billing_period" value="{{ $billingPeriod }}" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <div>
            <label for="status" class="block text-xs font-semibold text-slate-500 mb-2">STATUS</label>
            <select name="status" id="status" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Status</option>
                @foreach($allowedStatuses as $invoiceStatus)
                    <option value="{{ $invoiceStatus }}" {{ $status === $invoiceStatus ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $invoiceStatus)) }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500/25">
                Filter
            </button>
            <a href="{{ route('invoices.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none">
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
                    <th class="px-6 py-3.5">NO. TAGIHAN</th>
                    <th class="px-6 py-3.5">PELANGGAN</th>
                    <th class="px-6 py-3.5">POP</th>
                    <th class="px-6 py-3.5">PERIODE</th>
                    <th class="px-6 py-3.5 text-right">TOTAL</th>
                    <th class="px-6 py-3.5 text-right">SISA</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($invoices as $invoice)
                    @php
                        $badgeClass = match($invoice->invoice_status) {
                            'lunas' => 'bg-green-50 text-green-700 border-green-100',
                            'sebagian' => 'bg-amber-50 text-amber-700 border-amber-100',
                            'batal' => 'bg-red-50 text-red-700 border-red-100',
                            default => 'bg-slate-50 text-slate-700 border-slate-100',
                        };
                    @endphp
                    <tr class="hover:bg-slate-50/45 transition-colors">
                        <td class="px-6 py-3.5 text-center text-slate-400 data-text">{{ ($invoices->currentPage() - 1) * $invoices->perPage() + $loop->iteration }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <a href="{{ route('invoices.show', $invoice->id) }}" class="font-mono font-bold text-sky-700 hover:text-sky-900">{{ $invoice->invoice_number }}</a>
                            <div class="text-[10px] text-slate-400 mt-0.5">Jatuh tempo {{ optional($invoice->due_date)->format('d/m/Y') }}</div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <div class="font-semibold text-slate-900">{{ $invoice->customer->full_name ?? '-' }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ $invoice->customer->cid ?? $invoice->customer->customer_code ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-medium text-slate-800">{{ $invoice->pop->name ?? '-' }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-mono">{{ $invoice->billing_period }}</td>
                        <td class="px-6 py-3.5 text-right font-mono font-semibold">Rp {{ number_format((float) $invoice->total_amount, 2, ',', '.') }}</td>
                        <td class="px-6 py-3.5 text-right font-mono">Rp {{ number_format((float) $invoice->remaining_amount, 2, ',', '.') }}</td>
                        <td class="px-6 py-3.5 text-center whitespace-nowrap">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $badgeClass }}">
                                {{ ucwords(str_replace('_', ' ', $invoice->invoice_status)) }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="{{ route('invoices.show', $invoice->id) }}" class="inline-flex items-center px-3 py-1.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md transition-colors text-xs font-semibold">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada tagihan yang sesuai filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-slate-100">
        {{ $invoices->links() }}
    </div>
</div>
@endsection
