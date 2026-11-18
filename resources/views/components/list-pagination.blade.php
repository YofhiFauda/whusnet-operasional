{{-- Footer tabel: "Menampilkan X–Y dari Z {label}" → pemilih Baris → tombol halaman.

     Pengganti `$paginator->links()` bawaan Laravel yang teksnya Inggris
     ("Showing 1 to 10 of 340 results") dan susunannya tidak sejajar dengan
     pemilih Baris. Tampilannya sengaja meniru customers/partials/_list_pagination
     supaya semua halaman list terasa sama. --}}
@props(['paginator', 'label' => 'data'])
@php
    $cur = $paginator->currentPage();
    $last = $paginator->lastPage();
    $winStart = max(1, $cur - 2);
    $winEnd = min($last, $cur + 2);
    $btnBase = 'px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-xs font-semibold transition-colors';
    $btnDisabled = 'px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-400 dark:text-slate-600 text-xs font-semibold opacity-40 cursor-not-allowed';
    $btnActive = 'px-3 py-1.5 rounded-lg bg-sky-600 text-white font-semibold text-xs font-mono';
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-600 dark:text-slate-400']) }}>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <div>
            @if($paginator->total() > 0)
                Menampilkan
                <strong class="text-slate-800 dark:text-white font-mono">{{ number_format($paginator->firstItem(), 0, ',', '.') }}&ndash;{{ number_format($paginator->lastItem(), 0, ',', '.') }}</strong>
                dari
                <strong class="text-slate-800 dark:text-white font-mono">{{ number_format($paginator->total(), 0, ',', '.') }}</strong>
                {{ $label }}
            @else
                Tidak ada data
            @endif
        </div>

        <x-per-page-select :paginator="$paginator" />
    </div>

    @if($last > 1)
        <nav aria-label="Navigasi halaman {{ $label }}" class="flex flex-wrap items-center gap-1.5">
            @if($paginator->onFirstPage())
                <span class="{{ $btnDisabled }}" aria-disabled="true">Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya" class="{{ $btnBase }}">Sebelumnya</a>
            @endif

            @if($winStart > 1)
                <a href="{{ $paginator->url(1) }}" aria-label="Halaman 1" class="{{ $btnBase }} font-mono">1</a>
                @if($winStart > 2)
                    <span class="px-1 text-slate-500 dark:text-slate-400" aria-hidden="true">&hellip;</span>
                @endif
            @endif

            @for($n = $winStart; $n <= $winEnd; $n++)
                @if($n === $cur)
                    <span aria-current="page" class="{{ $btnActive }}">{{ $n }}</span>
                @else
                    <a href="{{ $paginator->url($n) }}" aria-label="Halaman {{ $n }}" class="{{ $btnBase }} font-mono">{{ $n }}</a>
                @endif
            @endfor

            @if($winEnd < $last)
                @if($winEnd < $last - 1)
                    <span class="px-1 text-slate-500 dark:text-slate-400" aria-hidden="true">&hellip;</span>
                @endif
                <a href="{{ $paginator->url($last) }}" aria-label="Halaman terakhir, halaman {{ $last }}" class="{{ $btnBase }} font-mono">{{ $last }}</a>
            @endif

            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya" class="{{ $btnBase }}">Berikutnya</a>
            @else
                <span class="{{ $btnDisabled }}" aria-disabled="true">Berikutnya</span>
            @endif
        </nav>
    @endif
</div>
