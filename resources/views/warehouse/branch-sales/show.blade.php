@extends('layouts.app')

@section('title', 'Jual Beli SN '.$sale->reference_number)
@section('page_title', 'Detail Jual Beli SN')

@section('content')
<x-warehouse.header active="branch-sales" title="Jual Beli {{ $sale->reference_number }}" subtitle="{{ $sale->fromPop->name }} → {{ $sale->toPop->name }}" />
<div class="space-y-5">
    <a href="{{ route('warehouse.branch-sales.index') }}" class="text-sm font-semibold text-sky-700 hover:underline">← Daftar jual beli</a>
    @if(session('error'))<div role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    @if(auth()->user()->hasPermission('warehouse_transfer.view'))
        <a href="{{ route('warehouse.transfers.surat-jalan', $sale->transfer) }}" target="_blank" rel="noopener" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cetak surat jalan</a>
    @endif
    <div class="grid gap-3 rounded-xl border border-slate-200 bg-white p-5 text-sm dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-3">
        <div><div class="text-xs uppercase text-slate-500">Status fisik</div><strong>{{ $sale->transfer->status->label() }}</strong></div>
        <div><div class="text-xs uppercase text-slate-500">Dibuat</div><strong>{{ $sale->created_at->format('d/m/Y H:i') }}</strong></div>
        <div><div class="text-xs uppercase text-slate-500">Referensi transfer</div><strong>{{ $sale->transfer->reference_number }}</strong></div>
        @if($canSeePrice)
            <div><div class="text-xs uppercase text-slate-500">Acuan cross-check pembayaran</div><strong>{{ $sale->posted_at ? 'Rp'.number_format($sale->posted_total, 2, ',', '.') : 'Belum final' }}</strong></div>
            <div class="text-xs text-slate-500 dark:text-slate-400">Pembayaran internal dicocokkan melalui laporan; Gudang tidak mencatat status pembayaran.</div>
        @endif
    </div>
    @if($canReceive && $sale->transfer->isInTransit())
        <form method="POST" action="{{ route('warehouse.branch-sales.receive', $sale) }}" id="receive-sale">@csrf</form>
    @endif
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800"><tr><th class="p-3">SN</th><th class="p-3">Barang</th><th class="p-3">Status</th>@if($canSeePrice)<th class="p-3 text-right">HPP Penjual</th><th class="p-3 text-right">Harga jual</th>@endif @if($canReceive && $sale->transfer->isInTransit())<th class="p-3">Terima</th>@endif</tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($sale->lines as $line)
                @php $received = in_array($line->inventory_serial_id, $receivedIds, true); @endphp
                <tr>
                    <td class="p-3 font-mono">{{ $line->serial->serial_number }}</td>
                    <td class="p-3">{{ $line->serial->item->name }}</td>
                    <td class="p-3">{{ $received ? 'Dibeli dan diterima' : ($sale->transfer->isInTransit() ? 'Dalam perjalanan' : 'Hilang') }}</td>
                    @if($canSeePrice)<td class="p-3 text-right">Rp{{ number_format($dispatches->get($line->inventory_serial_id)?->unit_price_snapshot ?? 0, 2, ',', '.') }}</td><td class="p-3 text-right font-semibold">Rp{{ number_format($line->sale_price, 2, ',', '.') }}</td>@endif
                    @if($canReceive && $sale->transfer->isInTransit())<td class="p-3">@unless($received)<input form="receive-sale" type="checkbox" name="serial_ids[]" value="{{ $line->inventory_serial_id }}" aria-label="Terima {{ $line->serial->serial_number }}">@endunless</td>@endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($canReceive && $sale->transfer->isInTransit())
        <button form="receive-sale" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white">Konfirmasi SN terpilih</button>
    @endif
    @if($canClose && $sale->transfer->isInTransit())
        <form method="POST" enctype="multipart/form-data" action="{{ route('warehouse.branch-sales.close-missing', $sale) }}" class="space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-slate-900">
            @csrf
            <h2 class="font-semibold">Tutup sisa SN yang hilang</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400">Hanya SN yang sudah diterima masuk nilai pembelian. Sisa yang hilang tetap tercatat sebagai kerugian pengirim.</p>
            <textarea name="reason" required maxlength="500" placeholder="Alasan kehilangan" class="w-full rounded-lg border border-slate-300 bg-white p-2 dark:border-slate-700 dark:bg-slate-800"></textarea>
            <input type="file" name="evidence" accept="image/*" required aria-label="Bukti kehilangan" class="block text-sm">
            <button class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Tutup sisa dengan bukti</button>
        </form>
    @endif
</div>
@endsection
