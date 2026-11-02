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
            <div>
                <div class="text-xs uppercase text-slate-500">Acuan cross-check pembayaran</div>
                <strong>{{ $sale->posted_at ? 'Rp'.number_format($sale->posted_total, 2, ',', '.') : 'Belum final' }}</strong>
                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pembayaran internal dicocokkan melalui laporan; Gudang tidak mencatat status pembayaran.</div>
            </div>
        @endif
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800"><tr><th class="p-3">SN</th><th class="p-3">Barang</th><th class="p-3">Kondisi</th><th class="p-3">Status</th>@if($canSeePrice)<th class="p-3 text-right">HPP Penjual</th><th class="p-3 text-right">Harga jual</th>@endif</tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($sale->lines as $line)
                @php $received = in_array($line->inventory_serial_id, $receivedIds, true); @endphp
                <tr>
                    <td class="p-3 font-mono">{{ $line->serial->serial_number }}</td>
                    <td class="p-3">{{ $line->serial->item->name }}</td>
                    <td class="p-3"><x-warehouse.condition-badge :condition="$line->serial->condition ?? \App\Enums\ItemCondition::NEW" :checked="$line->serial->condition_checked_at !== null" /></td>
                    <td class="p-3">{{ $received ? 'Dibeli dan diterima' : 'Dalam perjalanan' }}</td>
                    @if($canSeePrice)<td class="p-3 text-right">Rp{{ number_format($dispatches->get($line->inventory_serial_id)?->unit_price_snapshot ?? 0, 2, ',', '.') }}</td><td class="p-3 text-right font-semibold">Rp{{ number_format($line->sale_price, 2, ',', '.') }}</td>@endif                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($canReceive && $sale->transfer->isInTransit())
        <form x-data="{ help: false }" method="POST" action="{{ route('warehouse.branch-sales.receive', $sale) }}" class="space-y-3">
            @csrf
            <div class="flex items-center gap-2">
                <button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white">Terima semua SN</button>
                <button type="button" @click="help = ! help" :aria-expanded="help.toString()" aria-controls="bantuan-konfirmasi-sn" aria-label="Apa arti terima semua SN?"
                        class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-emerald-500 text-xs font-bold text-emerald-800 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-800">?</button>
            </div>
            <p class="text-sm text-slate-600 dark:text-slate-400">Tekan setelah semua SN pada daftar di atas sudah Anda terima secara fisik.</p>
            <div id="bantuan-konfirmasi-sn" x-show="help" x-collapse x-cloak class="space-y-2 rounded-lg border border-emerald-200 bg-white p-4 text-sm text-slate-700 dark:border-emerald-900 dark:bg-slate-800 dark:text-slate-300">
                <p class="font-semibold">Apa yang terjadi bila diterima?</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li>Semua SN pada dokumen masuk <strong>stok Cabang Anda</strong> dengan nilai sebesar <strong>harga jual</strong> pada dokumen ini, dan dihitung sebagai nilai pembelian.</li>
                    <li>Dokumen menjadi final dan total nominalnya dihitung.</li>
                    <li>Penerimaan tidak bisa diulang untuk SN yang sama.</li>
                </ul>
                <p class="font-semibold text-emerald-800 dark:text-emerald-300">Periksa fisik semua unit lebih dulu. Bila ada yang belum tiba, jangan tekan tombol ini dan hubungi Cabang pengirim.</p>
            </div>
        </form>
    @endif
</div>
@endsection
