@extends('layouts.app')

@section('title', 'Daftar Pembayaran - Whusnet Operasional')
@section('page_title', 'Daftar Pembayaran')
@section('breadcrumb_parent', 'Pembayaran')
@section('breadcrumb_parent_url', route('payments.index'))

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-text-main text-sm font-semibold uppercase tracking-wider">Daftar dan Filter Pembayaran</h3>
        <p class="text-xs text-text-muted mt-1">Pembayaran selalu terhubung ke invoice, pelanggan, dan POP/Cabang.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('payments.overpay') }}" class="inline-flex items-center gap-1.5 px-4 py-2 border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 hover:bg-amber-100 dark:hover:bg-amber-500/20 text-amber-700 dark:text-amber-400 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Lebih Bayar
        </a>
        <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Buka Daftar Tagihan
        </a>
    </div>
</div>

@include('payments.partials.riwayat-banner')

<div class="bg-surface border border-border rounded-lg p-6 mb-6">
    <form action="{{ route('payments.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-7 gap-4 items-end">
        <div class="sm:col-span-2 xl:col-span-2">
            <label for="search" class="block text-xs font-semibold text-text-secondary mb-2">CARI PEMBAYARAN</label>
            <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Cari Nama, ID Transaksi, atau ID Pembayaran Lama..." class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
        </div>

        <div>
            <label for="date_from" class="block text-xs font-semibold text-text-secondary mb-2">DARI TANGGAL</label>
            <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
        </div>

        <div>
            <label for="date_to" class="block text-xs font-semibold text-text-secondary mb-2">SAMPAI TANGGAL</label>
            <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
        </div>

        <div>
            <label for="pop_id" class="block text-xs font-semibold text-text-secondary mb-2">POP / CABANG</label>
            <select name="pop_id" id="pop_id" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $popId === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="method" class="block text-xs font-semibold text-text-secondary mb-2">METODE</label>
            <select name="method" id="method" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                <option value="">Semua Metode</option>
                @foreach($allowedMethods as $paymentMethod)
                    <option value="{{ $paymentMethod }}" {{ $method === $paymentMethod ? 'selected' : '' }}>{{ strtoupper($paymentMethod) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="invoice_type" class="block text-xs font-semibold text-text-secondary mb-2">JENIS TAGIHAN</label>
            <select name="invoice_type" id="invoice_type" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                <option value="">Semua Jenis</option>
                <option value="awal" {{ ($invoiceType ?? '') === 'awal' ? 'selected' : '' }}>Tagihan Awal (PSB)</option>
                <option value="bulanan" {{ ($invoiceType ?? '') === 'bulanan' ? 'selected' : '' }}>Tagihan Bulanan Rutin</option>
                <option value="reaktivasi" {{ ($invoiceType ?? '') === 'reaktivasi' ? 'selected' : '' }}>Tagihan Reaktivasi</option>
            </select>
        </div>

        <div>
            <label for="status" class="block text-xs font-semibold text-text-secondary mb-2">STATUS</label>
            <select name="status" id="status" class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                <option value="">Semua Status</option>
                @foreach($allowedStatuses as $paymentStatus)
                    <option value="{{ $paymentStatus }}" {{ $status === $paymentStatus ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $paymentStatus)) }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2 xl:col-start-6 xl:col-span-2">
            <button type="submit" class="flex-1 bg-primary hover:bg-primary-focus text-white text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/25">
                Filter
            </button>
            <a href="{{ route('payments.index') }}" class="bg-surface-muted hover:bg-surface-muted/80 text-text-secondary text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none border border-border">
                Reset
            </a>
        </div>
    </form>
</div>

<div class="bg-surface border border-border rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-text-secondary">
            <thead>
                <tr class="bg-surface-muted/50 border-b border-border text-text-secondary font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">NO. PEMBAYARAN</th>
                    <th class="px-6 py-3.5">INVOICE</th>
                    <th class="px-6 py-3.5">PELANGGAN</th>
                    <th class="px-6 py-3.5">POP</th>
                    <th class="px-6 py-3.5">TANGGAL</th>
                    <th class="px-6 py-3.5">METODE</th>
                    <th class="px-6 py-3.5">KOLEKTOR</th>
                    <th class="px-6 py-3.5 text-right">NOMINAL</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($payments as $payment)
                    @php
                        $badgeClass = match($payment->payment_status->value) {
                            'valid' => 'bg-green-500/10 text-green-500 border-green-500/20',
                            'ditolak' => 'bg-red-500/10 text-red-500 border-red-500/20',
                            default => 'bg-amber-500/10 text-amber-500 border-amber-500/20',
                        };
                    @endphp
                    <tr class="hover:bg-surface-muted/40 transition-colors">
                        <td class="px-6 py-3.5 text-center text-text-muted data-text">{{ ($payments->currentPage() - 1) * $payments->perPage() + $loop->iteration }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <a href="{{ route('payments.show', $payment->id) }}" class="font-mono font-bold text-primary hover:text-primary-focus">{{ $payment->payment_number }}</a>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                @if($payment->old_payment_id)
                                <span title="Data Migrasi (ID Bayar Lama: {{ $payment->old_payment_id }})" class="px-1.5 py-0.5 text-[9px] font-bold rounded border bg-primary/10 text-primary border-primary/20">
                                    Migrasi #{{ $payment->old_payment_id }}
                                </span>
                                @endif
                                <span class="text-[10px] text-text-muted">{{ $payment->receiver->name ?? '-' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            @if($payment->invoice)
                                <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="font-mono font-semibold text-text-main hover:text-primary">{{ $payment->invoice->invoice_number }}</a>
                            @else
                                <span class="text-text-muted">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <div class="font-semibold text-text-main">{{ $payment->customer->full_name ?? '-' }}</div>
                            <div class="text-[10px] text-text-muted font-mono">{{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-medium text-text-main">{{ $payment->pop->name ?? '-' }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap">{{ optional($payment->payment_date)->format('d/m/Y') }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-semibold">{{ strtoupper($payment->payment_method) }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            @if($payment->collector)
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-100 dark:border-violet-500/20">
                                    {{ $payment->collector->name }}
                                </span>
                            @else
                                <span class="text-text-muted text-xs">Langsung</span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono font-semibold">
                            Rp {{ number_format((float) $payment->amount, 2, ',', '.') }}
                            @if((float) $payment->overpay_amount > 0)
                                <span class="block text-[10px] font-semibold text-sky-600 dark:text-sky-400" title="Uang lebih yang diserahkan pelanggan — catatan saja, tidak menambah pembayaran tagihan">
                                    +{{ number_format((float) $payment->overpay_amount, 0, ',', '.') }} lebih
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-center whitespace-nowrap">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $badgeClass }}">
                                {{ $payment->payment_status->label() }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="{{ route('payments.show', $payment->id) }}" class="inline-flex items-center px-3 py-1.5 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-md transition-colors text-xs font-semibold">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-6 py-10 text-center text-sm text-text-muted">Belum ada pembayaran yang sesuai filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-border">
        {{ $payments->links() }}
    </div>
</div>
@endsection
