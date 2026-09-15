{{--
    Badge komparasi periode sebelumnya — dipakai 3x di KPI strip
    (fop/analytics.blade.php): Pemakaian Alat Kerja, Rata-Rata Durasi
    Solving, Task Dibatalkan. Backlog SENGAJA gak ikut (snapshot live,
    independen dari filter periode).

    Params:
    - $delta   : ?float persentase perubahan (null = gak ada baseline buat
                 dibandingkan, entah karena periode sebelumnya kosong atau
                 pembagian nol — lihat FopAnalyticsController::deltaPercent())
    - $invert  : bool — true kalau NAIK berarti BURUK (durasi makin lama,
                 makin banyak dibatalkan) → warna dibalik. false kalau NAIK
                 netral/informatif (pemakaian alat naik = lebih banyak kerja,
                 bukan indikasi baik/buruk).
--}}
@php
    $isUp = $delta !== null && $delta > 0;
    $isDown = $delta !== null && $delta < 0;
    $badgeColor = 'bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400';
    if ($delta !== null && $invert ?? false) {
        $badgeColor = match (true) {
            $isUp => 'bg-error-bg text-error border border-error-border',
            $isDown => 'bg-success-bg text-success border border-success-border',
            default => $badgeColor,
        };
    }
    $arrow = $isUp ? '▲' : ($isDown ? '▼' : '—');
@endphp
@if($delta !== null)
<span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider {{ $badgeColor }}" title="Dibanding periode sebelumnya (panjang jendela sama)">{{ $arrow }} {{ number_format(abs($delta), 1, ',', '.') }}%</span>
@else
<span class="text-[9px] text-slate-300 dark:text-slate-600" title="Periode sebelumnya gak ada data buat dibandingkan">vs sblm: —</span>
@endif
