@extends('layouts.app')

@section('title', 'Daftar Transfer Gudang - Whusnet Operasional')
@section('page_title', 'Daftar Transfer')

@section('content')

<x-warehouse.header active="transfers" title="Daftar Transfer Gudang" subtitle="Semua transfer antar gudang — dalam perjalanan maupun yang sudah diterima — beserta surat jalan & invoice-nya." />

@php
    $canInvoice = auth()->user()->hasPermission('warehouse_transfer_invoice.view');
    $hasFilter = $statusFilter || $fromFilter || $toFilter || $dateFrom || $dateTo;
@endphp

<div class="space-y-4">

    {{-- Filter --}}
    <form method="GET" action="{{ route('warehouse.transfers.index') }}" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</label>
                <select name="status" onchange="this.form.submit()" class="w-full h-9 px-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                    <option value="">— Semua Status —</option>
                    @foreach(\App\Enums\TransferStatus::cases() as $st)
                    <option value="{{ $st->value }}" {{ $statusFilter === $st->value ? 'selected' : '' }}>{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Dari Gudang</label>
                <select name="from_pop_id" onchange="this.form.submit()" class="w-full h-9 px-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                    <option value="">— Semua —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $fromFilter === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Ke Gudang</label>
                <select name="to_pop_id" onchange="this.form.submit()" class="w-full h-9 px-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                    <option value="">— Semua —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $toFilter === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Tgl Kirim Dari</label>
                <input type="date" name="date_from" value="{{ $dateFrom?->format('Y-m-d') }}" class="w-full h-9 px-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 h-9 px-3 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-bold rounded-lg transition-colors cursor-pointer">Terapkan</button>
                @if($hasFilter)
                <a href="{{ route('warehouse.transfers.index') }}" class="h-9 px-3 inline-flex items-center rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-700">Reset</a>
                @endif
            </div>
            <input type="hidden" name="date_to" value="{{ $dateTo?->format('Y-m-d') }}">
        </div>
    </form>

    {{-- Daftar --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-lg shadow-xs overflow-hidden">
        @if($transfers->isEmpty())
        <div class="p-12 sm:p-16 text-center">
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Belum ada transfer</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">Tidak ada transfer yang cocok dengan filter saat ini dalam cakupan Anda.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                <thead class="bg-slate-50/80 dark:bg-slate-800/60">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Nomor</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Rute</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Baris</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Kirim / Terima</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Dokumen</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-900 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($transfers as $t)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                        <td class="px-4 py-3.5 whitespace-nowrap">
                            <a href="{{ route('warehouse.transfers.show', $t) }}" class="font-mono text-xs font-bold text-sky-600 dark:text-sky-400 hover:underline">{{ $t->reference_number }}</a>
                        </td>
                        <td class="px-4 py-3.5 text-xs text-slate-700 dark:text-slate-300 whitespace-nowrap">
                            <span class="font-medium">{{ $t->fromPop->name ?? '—' }}</span>
                            <span class="text-slate-400 mx-1">→</span>
                            <span class="font-medium">{{ $t->toPop->name ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-3.5 whitespace-nowrap">
                            <x-ui.badge :variant="$t->status->badgeVariant()">{{ $t->status->label() }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono tabular-nums text-slate-700 dark:text-slate-300 text-xs">{{ $t->line_count }}</td>
                        <td class="px-4 py-3.5 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            <div class="font-medium text-slate-700 dark:text-slate-300">{{ $t->created_at?->translatedFormat('d M Y') ?? '—' }}</div>
                            <div class="text-xs {{ $t->received_at ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                                {{ $t->received_at ? 'Diterima '.$t->received_at->translatedFormat('d M Y') : 'Belum diterima' }}
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                <a href="{{ route('warehouse.transfers.surat-jalan', $t) }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center justify-center px-3 py-1.5 min-h-[32px] text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">Surat Jalan</a>
                                @if($canInvoice)
                                <a href="{{ route('warehouse.transfers.invoice', $t) }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center justify-center px-3 py-1.5 min-h-[32px] text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">Invoice</a>
                                @endif
                                <a href="{{ route('warehouse.transfers.show', $t) }}"
                                   class="inline-flex items-center justify-center px-3 py-1.5 min-h-[32px] text-xs font-medium rounded-lg bg-sky-600 hover:bg-sky-700 text-white shadow-2xs transition-colors">Detail</a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($transfers->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-800">
            {{ $transfers->links() }}
        </div>
        @endif
        @endif
    </div>
</div>

@endsection
