@extends('layouts.app')

@section('title', 'Cari Gudang - Whusnet Operasional')
@section('page_title', 'Cari Gudang')

@section('content')

<x-warehouse.header title="Cari Gudang" subtitle="Pencarian universal: SN, roll kabel, nomor transfer, dan nomor surat jalan sekaligus." />

@php
    $typeLabel = fn (string $type) => match ($type) {
        'serial' => 'SN',
        'roll' => 'Roll Kabel',
        'transfer' => 'Transfer',
        default => $type,
    };
    $typeBadge = fn (string $type) => match ($type) {
        'serial' => 'info',
        'roll' => 'warning',
        'transfer' => 'success',
        default => 'neutral',
    };
@endphp

<div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">
            @if($query !== '')
                Hasil untuk "{{ $query }}"
            @else
                Ketik kata kunci di kotak pencarian header
            @endif
        </h3>
        @if($query !== '')
        <span class="text-xs text-slate-400 shrink-0">{{ $results->count() }} ditemukan</span>
        @endif
    </div>

    @if($query === '')
    <div class="p-10 text-center text-xs text-slate-500 dark:text-slate-400">
        Cari nomor SN, kode roll kabel, nomor referensi transfer, atau nomor surat jalan (format <span class="font-mono">SJ/WHUS/2026/10/005</span>).
    </div>
    @elseif($results->isEmpty())
    <div class="p-10 text-center">
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada yang cocok</h4>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Tidak ada SN, roll, atau transfer dalam cakupan Anda yang cocok dengan "{{ $query }}". Coba kata kunci lain.</p>
    </div>
    @else
    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
        @foreach($results as $r)
        <li>
            <a href="{{ $r['url'] }}" class="flex items-center justify-between gap-3 px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <x-ui.badge :variant="$typeBadge($r['type'])">{{ $typeLabel($r['type']) }}</x-ui.badge>
                        <span class="font-mono text-sm font-bold text-slate-800 dark:text-slate-100 truncate">{{ $r['label'] }}</span>
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">{{ $r['sub'] }}</div>
                </div>
                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </li>
        @endforeach
    </ul>
    @endif
</div>

@endsection
