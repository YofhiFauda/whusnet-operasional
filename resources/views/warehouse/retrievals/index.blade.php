@extends('layouts.app')

@section('title', 'Riwayat Pengambilan Alat - Whusnet Operasional')
@section('page_title', 'Riwayat Pengambilan Alat')

@section('content')
@php
    $inputClass = 'w-full text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500';
    $canReceive = auth()->user()->hasPermission('warehouse_reassign.create');
@endphp

<x-warehouse.header active="history" title="Riwayat Pengambilan Alat" subtitle="Siapa yang menarik modem, dari pelanggan mana, dan kapan diterima gudang." />

<form method="GET" class="mb-4 grid grid-cols-2 lg:grid-cols-6 gap-2 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4">
    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="SN / nama / kode pelanggan" class="{{ $inputClass }} col-span-2">
    <select name="technician" class="{{ $inputClass }}">
        <option value="">Semua Teknisi/Petugas</option>
        @foreach($technicians as $tech)
        <option value="{{ $tech->id }}" @selected((string) ($filters['technician'] ?? '') === (string) $tech->id)>{{ $tech->name }}</option>
        @endforeach
    </select>
    <select name="status" class="{{ $inputClass }}">
        <option value="">Semua Status</option>
        <option value="transit" @selected(($filters['status'] ?? '') === 'transit')>Transit (di teknisi)</option>
        <option value="diterima" @selected(($filters['status'] ?? '') === 'diterima')>Sudah diterima gudang</option>
    </select>
    <select name="source" class="{{ $inputClass }}">
        <option value="">Semua Sumber</option>
        @foreach($sources as $source)
        <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
        @endforeach
    </select>
    <div class="flex gap-2">
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $inputClass }}" title="Dari tanggal">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $inputClass }}" title="Sampai tanggal">
    </div>
    <div class="col-span-2 lg:col-span-6 flex items-center justify-between">
        <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-bold">Terapkan Filter</button>
        @if($canReceive)
        <span class="flex items-center gap-4 text-xs font-bold">
            <a href="{{ route('warehouse.returns.index') }}" class="text-sky-600 dark:text-sky-400">Terima Retur (transit) →</a>
            <a href="{{ route('warehouse.returns.from-customer.create') }}" class="text-sky-600 dark:text-sky-400">Terima modem dari pelanggan →</a>
        </span>
        @endif
    </div>
</form>

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg shadow-xs overflow-hidden">
    @if($logs->isEmpty())
    <div class="p-8 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada riwayat pengambilan alat yang cocok.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px]">
                <tr>
                    <th class="text-left px-4 py-3 font-bold">Diambil</th>
                    <th class="text-left px-4 py-3 font-bold">Pelanggan</th>
                    <th class="text-left px-4 py-3 font-bold">SN / Model</th>
                    <th class="text-left px-4 py-3 font-bold">Teknisi/Petugas</th>
                    <th class="text-left px-4 py-3 font-bold">Sumber</th>
                    <th class="text-left px-4 py-3 font-bold">Status</th>
                    <th class="text-left px-4 py-3 font-bold">Kondisi</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($logs as $log)
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-700 dark:text-slate-200">{{ $log->retrieved_at->translatedFormat('d M Y H:i') }}</td>
                    <td class="px-4 py-3">
                        @if($log->customer)
                        <a href="{{ route('customers.show', $log->customer) }}" class="font-bold text-sky-600 dark:text-sky-400">{{ $log->customer->full_name }}</a>
                        @else - @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="block font-mono font-bold text-slate-800 dark:text-slate-100">{{ $log->serial_number }}</span>
                        <span class="text-slate-500 dark:text-slate-400">{{ $log->item?->name ?? '-' }}</span>
                    </td>
                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $log->retrievedBy?->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $log->source->label() }}</td>
                    <td class="px-4 py-3">
                        @if($log->isReceived())
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">Diterima</span>
                        <span class="block text-slate-500 dark:text-slate-400">{{ $log->warehousePop?->name }} · {{ $log->receivedBy?->name ?? '-' }} · {{ $log->received_at->translatedFormat('d M Y') }}</span>
                        @else
                        <span class="font-bold text-amber-600 dark:text-amber-400">Transit (di teknisi)</span>
                        <span class="block text-slate-500 dark:text-slate-400">Tujuan: {{ $log->warehousePop?->name ?? '-' }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $log->condition?->label() ?? '-' }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($log->photoPath())
                        <a href="{{ Storage::disk('public')->url($log->photoPath()) }}" target="_blank" rel="noopener" class="font-bold text-sky-600 dark:text-sky-400">Foto</a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700/60">{{ $logs->links() }}</div>
    @endif
</div>

@endsection
