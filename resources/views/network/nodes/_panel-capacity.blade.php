{{--
    Tiga angka kapasitas satu baris panel Manajemen Node: terpakai / kapasitas
    / sisa (+ satuan). `$cap` = bentuk `MasterNodeInspector::capacity()` atau
    `PonCapacityService::summary()` (kunci used/capacity/remaining sama).
    Kapasitas null ditulis "Belum didata" — tidak pernah "?"/NaN, dan sisa
    tidak ditebak (null ≠ nol ≠ aman).
--}}
@php
    $redZone = $cap['red_zone'] ?? null;
    $isFull = (bool) ($cap['is_full'] ?? false);
@endphp
<div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-text-muted" data-capacity-used="{{ $cap['used'] }}" data-capacity-total="{{ $cap['capacity'] ?? '' }}">
    <span>Terpakai <strong class="font-mono tabular-nums text-text-main">{{ $cap['used'] }}</strong></span>
    @if($cap['capacity'] === null)
        <span class="rounded-full border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/50 px-2 py-0.5 font-semibold text-amber-700 dark:text-amber-300">Kapasitas belum didata</span>
    @else
        <span>Kapasitas <strong class="font-mono tabular-nums text-text-main">{{ $cap['capacity'] }}</strong></span>
        <span>Sisa <strong class="font-mono tabular-nums {{ $isFull ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $cap['remaining'] }}</strong></span>
    @endif
    <span>{{ $unit }}</span>
    @if($isFull)
        <span class="rounded-full border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/50 px-2 py-0.5 font-semibold text-rose-700 dark:text-rose-300">Penuh</span>
    @endif
    @if($cap['over'] ?? false)
        <span class="rounded-full border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/50 px-2 py-0.5 font-semibold text-rose-700 dark:text-rose-300">Melebihi kapasitas</span>
    @endif
    @if($redZone === true)
        <span class="rounded-full border border-rose-300 dark:border-rose-900 bg-rose-100 dark:bg-rose-950/60 px-2 py-0.5 font-bold text-rose-700 dark:text-rose-300" data-red-zone="1">Red Zone</span>
    @endif
</div>
