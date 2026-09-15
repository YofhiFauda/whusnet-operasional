{{--
    Badge delta % period-over-period buat stat card Dashboard NOC.
    $delta null = filter "Semua Waktu" (gak ada window pembanding), badge gak dirender.
    $invert = true buat metrik yang "naik = buruk" (mis. Dibatalkan) — warna dibalik.
--}}
@php
    $invert = $invert ?? false;
@endphp
@if($delta !== null)
    @php
        $isUp = $delta > 0;
        $isFlat = $delta == 0;
        $isGood = $isFlat ? null : ($invert ? ! $isUp : $isUp);
        $colorClasses = $isFlat
            ? 'bg-slate-100 dark:bg-slate-800 text-text-muted'
            : ($isGood ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400');
    @endphp
    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded font-mono font-bold text-[10px] {{ $colorClasses }}" title="vs periode sebelumnya">
        @if(! $isFlat)
            <svg class="w-2.5 h-2.5 {{ $isUp ? '' : 'rotate-180' }}" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l5 5a1 1 0 01-1.414 1.414L11 6.414V16a1 1 0 11-2 0V6.414L5.707 9.707a1 1 0 01-1.414-1.414l5-5A1 1 0 0110 3z" clip-rule="evenodd" />
            </svg>
        @endif
        {{ $isUp ? '+' : '' }}{{ $delta }}%
    </span>
@endif
