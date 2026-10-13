{{-- Kapasitas ODP berasal dari port child; Distribusi CID bukan infrastruktur. --}}
<div id="odp-capacity-widget" class="flex flex-col gap-3 font-sans">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h4 class="text-xs font-bold text-text-main uppercase tracking-wider">Kapasitas ODP</h4>
            <p class="text-[11px] text-text-muted">Status port diisi manual oleh petugas pada POP yang dapat diakses.</p>
        </div>
        @can('network_nodes.view')
        <a href="{{ route('network.nodes.index') }}" class="text-xs font-semibold text-primary hover:underline">Kelola ODP</a>
        @endcan
    </div>
    <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="text-[11px] text-text-muted">ODP Terdaftar</p>
                <p class="text-xl font-bold text-text-main">{{ number_format($odpSummary['total_odps'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="text-right">
                <p class="text-[11px] text-text-muted">Red Zone</p>
                <p class="text-xl font-bold text-error">{{ $odpSummary['red_zone_count'] ?? 0 }}</p>
            </div>
        </div>
        @if(!empty($odpSummary['red_zone']))
        <div class="mt-3 divide-y divide-border border-t border-border">
            @foreach($odpSummary['red_zone'] as $odp)
            <div class="flex items-center justify-between gap-3 py-2 text-xs">
                <span class="font-semibold text-text-main">{{ $odp['code'] }}</span>
                <span class="font-mono text-error">{{ $odp['used'] }}/{{ $odp['capacity'] }} port · {{ $odp['percent'] }}%</span>
            </div>
            @endforeach
        </div>
        @if(($odpSummary['red_zone_count'] ?? 0) > count($odpSummary['red_zone']))
        <p class="mt-2 text-[11px] text-text-muted">Menampilkan 5 ODP terpadat dari {{ $odpSummary['red_zone_count'] }} Red Zone.</p>
        @endif
        @else
        <p class="mt-3 border-t border-border pt-3 text-xs text-text-muted">Belum ada ODP yang masuk Red Zone.</p>
        @endif
    </div>
</div>
