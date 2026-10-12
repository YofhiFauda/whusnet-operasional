@props([
    'type' => null,   // string e.g. 'PSB', 'MTN', 'C-REQ', 'DEAC', 'SURVEY', 'INFR', 'O-REQ'
    'detail' => null, // string e.g. 'Ganti Modem', 'Pindah Lokasi', 'Migrasi', 'Pindah Kabel', 'Tambah Modem'
])

@php
    $typeStr = strtoupper(trim((string) $type));

    $typeClass = match ($typeStr) {
        'PSB', 'PEMASANGAN' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200/80 dark:border-emerald-800',
        'MTN', 'MAINTENANCE' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200/80 dark:border-amber-800',
        'C-REQ', 'CREQ' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200/80 dark:border-sky-800',
        'DEAC', 'AMBIL_MODEM' => 'bg-pink-50 dark:bg-pink-950/40 text-pink-700 dark:text-pink-300 border-pink-200/80 dark:border-pink-800',
        'SURVEY' => 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-200/80 dark:border-blue-800',
        'INFR', 'INFR REQ' => 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 border-red-200/80 dark:border-red-800',
        'OREQ', 'O-REQ' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        default => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700',
    };

    $typeLabel = match ($typeStr) {
        'PEMASANGAN' => 'PSB',
        'MAINTENANCE' => 'MTN',
        'AMBIL_MODEM' => 'DEAC',
        'CREQ' => 'C-REQ',
        'INFR REQ' => 'INFR',
        default => $typeStr ?: 'PSB',
    };

    $detailStr = trim((string) $detail);
    $detailKey = strtolower($detailStr);

    $detailClass = match (true) {
        str_contains($detailKey, 'ganti modem') => 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border-purple-200/80 dark:border-purple-800',
        str_contains($detailKey, 'pindah lokasi') => 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200/80 dark:border-indigo-800',
        str_contains($detailKey, 'migrasi') => 'bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-300 border-teal-200/80 dark:border-teal-800',
        str_contains($detailKey, 'pindah kabel') => 'bg-lime-50 dark:bg-lime-950/40 text-lime-700 dark:text-lime-300 border-lime-200/80 dark:border-lime-800',
        str_contains($detailKey, 'tambah modem') => 'bg-fuchsia-50 dark:bg-fuchsia-950/40 text-fuchsia-700 dark:text-fuchsia-300 border-fuchsia-200/80 dark:border-fuchsia-800',
        default => 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
    };
@endphp

@if($typeLabel || $detailStr)
<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 flex-wrap']) }}>
    @if($typeLabel)
    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide uppercase border font-sans {{ $typeClass }}">
        {{ $typeLabel }}
    </span>
    @endif
    @if($detailStr)
    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium border font-sans {{ $detailClass }}">
        {{ $detailStr }}
    </span>
    @endif
</div>
@endif
