<div id="emergency-ticker-container" class="w-full">
    @if(!empty($emergencyAlerts) && count($emergencyAlerts) > 0)
        <div class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl border border-error-border bg-error-bg/70 backdrop-blur-xs text-error shadow-2xs font-sans">
            <span class="flex h-2.5 w-2.5 relative shrink-0">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-error opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-error"></span>
            </span>
            <span class="text-[11px] font-bold uppercase tracking-wider bg-error text-white px-2 py-0.5 rounded-md shrink-0">
                Perhatian Operasional
            </span>
            <div class="flex-1 overflow-x-auto no-scrollbar flex items-center gap-4 text-xs font-medium text-text-main divide-x divide-error-border/60">
                @foreach($emergencyAlerts as $alert)
                    <div class="flex items-center gap-1.5 shrink-0 pl-3 first:pl-0">
                        <span class="font-bold text-error">{{ $alert['title'] }}:</span>
                        <span class="text-text-secondary">{{ $alert['message'] }}</span>
                        @if(!empty($alert['link']))
                            <a href="{{ $alert['link'] }}" class="text-[11px] font-bold text-primary hover:underline ml-1">Lihat &rarr;</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="flex items-center justify-between px-3.5 py-1.5 rounded-lg border border-border bg-surface-muted/50 text-text-muted text-[11px] font-sans">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-success"></span>
                <span class="font-semibold text-text-secondary">Status Operasional Lapangan:</span>
                <span>Semua antrean tiket dan SLA survey dalam batas normal</span>
            </div>
            <span class="text-[10px] text-text-muted hidden sm:inline-block">Auto-sync aktif · Shift berjalan</span>
        </div>
    @endif
</div>
