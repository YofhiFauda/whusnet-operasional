{{-- Tabel leaderboard teknisi — dipakai 2x di fop/analytics.blade.php (beban tugas & solving terbanyak) --}}
@if(empty($rows))
<div class="p-10 text-center">
    <p class="text-xs text-text-muted">{{ $emptyText }}</p>
</div>
@else
<div class="overflow-x-auto scroll-smooth">
    <table class="min-w-full divide-y divide-border">
        <thead class="bg-surface-muted">
            <tr>
                <th class="px-5 py-2.5 text-left text-[10px] font-bold text-text-muted uppercase tracking-wider">Teknisi</th>
                <th class="px-5 py-2.5 text-right text-[10px] font-bold text-text-muted uppercase tracking-wider">Jumlah</th>
            </tr>
        </thead>
        <tbody class="bg-surface divide-y divide-border">
            @foreach($rows as $row)
            <tr class="hover:bg-surface-muted/60 transition-colors">
                <td class="px-5 py-2.5 text-xs text-text-main font-medium"><a href="{{ route('fop.analytics.drilldown', ['kind' => $kind, 'value' => $row['user_id'], 'from' => $periodFrom, 'to' => $periodTo, 'pop_id' => $popFilter]) }}" class="text-primary hover:underline">{{ $row['name'] }}</a></td>
                <td class="px-5 py-2.5 text-right font-mono text-xs font-semibold text-text-main">{{ $row['total'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
{{-- `$total` opsional (dilempar caller) — kalau leaderboard dipotong top 20
     dari total lebih banyak, kasih tahu eksplisit (dulu `limit(20)` mentah
     tanpa indikasi ini). --}}
@isset($total)
@if($total > count($rows))
<p class="px-5 py-2.5 text-[10px] text-text-muted border-t border-border">Menampilkan top {{ count($rows) }} dari {{ $total }} teknisi.</p>
@endif
@endisset
@endif
