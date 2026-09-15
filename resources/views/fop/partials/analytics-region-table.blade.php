{{-- Tabel ranking kecamatan — dipakai 3x di fop/analytics.blade.php (pemasangan/maintenance/dibatalkan) --}}
@if(empty($rows))
<div class="p-10 text-center">
    <p class="text-xs text-slate-400">{{ $emptyText }}</p>
</div>
@else
<div class="overflow-x-auto scroll-smooth">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
        <thead class="bg-slate-50 dark:bg-slate-800/60">
            <tr>
                <th class="px-5 py-2.5 text-left text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kecamatan</th>
                <th class="px-5 py-2.5 text-right text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
            @foreach($rows as $row)
            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30">
                <td class="px-5 py-2.5 text-xs text-slate-700 dark:text-slate-300">{{ $row['district_name'] }}</td>
                <td class="px-5 py-2.5 text-right font-mono text-xs font-semibold text-slate-700 dark:text-slate-300">{{ $row['total'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
{{-- `$total` opsional (dilempar caller) — kalau ranking dipotong top 20 dari
     total lebih banyak, kasih tahu eksplisit biar gak keliatan seolah cuma
     segitu kecamatan yang ada (dulu `limit(20)` mentah tanpa indikasi ini). --}}
@isset($total)
@if($total > count($rows))
<p class="px-5 py-2.5 text-[10px] text-slate-400 border-t border-slate-100 dark:border-slate-700/60">Menampilkan top {{ count($rows) }} dari {{ $total }} kecamatan.</p>
@endif
@endisset
@endif
