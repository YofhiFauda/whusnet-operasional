@extends('layouts.app')

@section('title', 'Permintaan '.$stockRequest->reference_number.' - Whusnet Operasional')
@section('page_title', 'Permintaan '.$stockRequest->reference_number)

@section('content')

<x-warehouse.header active="stock-requests" title="Permintaan Stok #{{ $stockRequest->reference_number }}" subtitle="{{ $stockRequest->cabangPop->name }} — diajukan oleh {{ $stockRequest->requestedBy->name }}." />

<div class="max-w-3xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-6 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
        <div>
            @php
                $badge = match($stockRequest->status->value) {
                    'pending' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                    'fulfilled' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                    'rejected' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                    default => 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600',
                };
            @endphp
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold border {{ $badge }}">{{ $stockRequest->status->label() }}</span>
            <p class="text-xs text-slate-400 mt-2">Diajukan {{ $stockRequest->created_at->translatedFormat('d M Y H:i') }}</p>
        </div>
        <a href="{{ route('warehouse.stock-requests.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">← Kembali</a>
    </div>

    <div class="p-6">
        @if($stockRequest->notes)
        <div class="mb-5 p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 text-xs text-slate-600 dark:text-slate-300">
            <span class="font-bold text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wide block mb-1">Catatan Pengaju</span>
            {{ $stockRequest->notes }}
        </div>
        @endif

        <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">Barang yang Diminta</h4>
        <div class="overflow-x-auto mb-5">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Barang</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Lot</th>
                        <th class="px-4 py-2 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Qty Diminta</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach($stockRequest->items as $line)
                    <tr>
                        <td class="px-4 py-2.5 font-semibold text-slate-800 dark:text-slate-200">{{ $line->item->name }}</td>
                        <td class="px-4 py-2.5 font-mono text-slate-500 dark:text-slate-400">{{ $line->lot_no ?: '-' }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-slate-700 dark:text-slate-300">{{ rtrim(rtrim(number_format((float) $line->qty_requested, 2, ',', '.'), '0'), ',') }} {{ $line->item->unit }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(! $stockRequest->status->isOpen())
        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 text-xs text-slate-600 dark:text-slate-300 mb-5">
            <span class="font-bold text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wide block mb-1">
                {{ $stockRequest->status->label() }} oleh {{ $stockRequest->decidedBy->name ?? '-' }} — {{ $stockRequest->decided_at?->translatedFormat('d M Y H:i') }}
            </span>
            @if($stockRequest->decision_notes)
            {{ $stockRequest->decision_notes }}
            @endif
        </div>
        @endif

        @if($stockRequest->status->isOpen())
        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasPermission('warehouse_stock_request.approve'))
            <form action="{{ route('warehouse.stock-requests.fulfill', $stockRequest) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    <span>Tandai Sudah Dipenuhi</span>
                </button>
            </form>
            <p class="text-[11px] text-slate-400 w-full sm:w-auto">Bikin Transfer barangnya dulu di <a href="{{ route('warehouse.transfers.create') }}" class="text-sky-600 dark:text-sky-400 hover:underline">Buat Transfer</a>, baru tandai di sini.</p>
            @endif

            @if(auth()->user()->hasPermission('warehouse_stock_request.reject'))
            <form action="{{ route('warehouse.stock-requests.reject', $stockRequest) }}" method="POST" class="inline-flex items-center gap-2" x-data="{ open: false }">
                @csrf
                <template x-if="!open">
                    <button type="button" @click="open = true" class="inline-flex items-center gap-1.5 px-4 py-2 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/50 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800 text-xs font-bold rounded-xl transition-colors">
                        <span>Tolak</span>
                    </button>
                </template>
                <template x-if="open">
                    <div class="flex items-center gap-2">
                        <input type="text" name="reason" required placeholder="Alasan penolakan..." class="text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-rose-500/20">
                        <button type="submit" class="px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl">Konfirmasi Tolak</button>
                    </div>
                </template>
            </form>
            @endif

            @if(auth()->user()->hasPermission('warehouse_stock_request.cancel') && (auth()->user()->hasFullAccess() || auth()->id() === $stockRequest->requested_by))
            <form action="{{ route('warehouse.stock-requests.cancel', $stockRequest) }}" method="POST" class="inline" onsubmit="return confirm('Batalkan permintaan ini?')">
                @csrf
                <button type="submit" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    Batalkan
                </button>
            </form>
            @endif
        </div>
        @endif
    </div>
</div>

@endsection
