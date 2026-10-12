{{-- ══ Status Teknisi & Radar Workload ═══════════════════════════ --}}
<div class="flex flex-col gap-3 font-sans">
    <div class="flex items-center justify-between">
        <div>
            <h4 class="text-xs font-bold text-text-main uppercase tracking-wider">Radar Teknisi & Beban Kerja</h4>
            <p class="text-[11px] text-text-muted">Ketersediaan personil lapangan, task aktif, & posisi live</p>
        </div>
        <span class="text-[11px] font-semibold text-text-muted bg-surface-muted px-2 py-0.5 rounded border border-border">
            {{ $teknisiList->where('status', 'aktif')->count() }} Aktif · {{ $teknisiList->where('status', 'standby')->count() }} Standby
        </span>
    </div>

    @php($overloadedCount = $teknisiList->where('overloaded', true)->count())
    @php($standbyCount = $teknisiList->where('status', 'standby')->count())
    @if($overloadedCount > 0 && $standbyCount > 0)
    <div class="flex items-center gap-2 text-[11px] font-semibold text-warning bg-warning-bg border border-warning-border rounded-lg px-3 py-2">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
        <span>Beban tidak merata: {{ $overloadedCount }} teknisi overcapacity (&gt;4 task) sementara {{ $standbyCount }} standby.</span>
    </div>
    @endif

    <div id="status-teknisi-container" class="bg-surface border border-border rounded-xl shadow-xs overflow-hidden">
        @if($teknisiList->count() > 0)
        
        {{-- Mobile List (< 768px) --}}
        <div class="block md:hidden divide-y divide-border">
            @foreach($teknisiList as $tek)
            <div class="p-3.5 flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <div class="h-7 w-7 rounded-full bg-primary-soft text-primary border border-primary-border flex items-center justify-center text-[10px] font-bold shrink-0 font-sans shadow-2xs">
                            {{ $tek['initials'] }}
                        </div>
                        <div>
                            <span class="font-bold text-text-main text-xs font-sans block">{{ $tek['name'] }}</span>
                            <span class="text-[10px] font-mono font-semibold text-text-secondary">{{ $tek['task_count'] }} Task</span>
                            @if(($tek['overdue_count'] ?? 0) > 0)
                                <span class="text-[9px] font-bold text-error">({{ $tek['overdue_count'] }} tertunda)</span>
                            @endif
                        </div>
                    </div>

                    @if($tek['status'] === 'aktif')
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border bg-success-bg text-success border-success-border">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse"></span>
                            <span>Aktif</span>
                        </span>
                    @elseif($tek['status'] === 'terjadwal')
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border bg-info-bg text-info border-info-border">
                            <span>Terjadwal</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold tracking-wider px-2 py-0.5 rounded-full border border-border bg-slate-50 dark:bg-slate-800/50 text-text-muted font-sans">
                            <span>Standby</span>
                        </span>
                    @endif
                </div>
                
                <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                    <div class="h-full transition-all duration-300 {{ $tek['overloaded'] ? 'bg-error' : 'bg-primary' }}" style="width: {{ $tek['workload_percent'] }}%"></div>
                </div>

                @if(!empty($tek['tool_badges']))
                <div class="flex items-center gap-1 flex-wrap">
                    @foreach($tek['tool_badges'] as $tool)
                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded border border-border bg-surface-muted text-text-secondary">{{ $tool }}</span>
                    @endforeach
                </div>
                @endif

                @if($tek['location'] && $tek['location'] !== '-')
                <div class="flex items-center gap-1.5 text-[11px] text-text-muted pt-1 border-t border-border/50">
                    <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="truncate">{{ $tek['location'] }}</span>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Desktop Table (>= 768px) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-border bg-surface-muted">
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-2/5">Teknisi</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-1/5">Status</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-1/5">Task Aktif Hari Ini</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-1/5">Beban & Alat</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-1/5">Lokasi Terakhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($teknisiList as $tek)
                    <tr class="hover:bg-surface-muted/50 transition-colors duration-150">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="h-7 w-7 rounded-full bg-primary-soft text-primary border border-primary-border flex items-center justify-center text-[10px] font-bold shrink-0 font-sans shadow-2xs">
                                    {{ $tek['initials'] }}
                                </div>
                                <span class="font-bold text-text-main text-xs font-sans">{{ $tek['name'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($tek['status'] === 'aktif')
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border bg-success-bg text-success border-success-border">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse"></span>
                                    <span>Aktif</span>
                                </span>
                            @elseif($tek['status'] === 'terjadwal')
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border bg-info-bg text-info border-info-border">
                                    <span>Terjadwal</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold tracking-wider px-2 py-0.5 rounded-full border border-border bg-slate-50 dark:bg-slate-800/50 text-text-muted font-sans">
                                    <span>Standby</span>
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs font-semibold font-mono text-text-secondary">
                            {{ $tek['task_count'] }} Task
                            @if(($tek['overdue_count'] ?? 0) > 0)
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold border border-error-border bg-error-bg text-error font-sans"
                                      title="Task yang jadwalnya sudah lewat">
                                    {{ $tek['overdue_count'] }} tertunda
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="w-24 bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden mb-1.5">
                                <div class="h-full transition-all duration-300 {{ $tek['overloaded'] ? 'bg-error' : 'bg-primary' }}" style="width: {{ $tek['workload_percent'] }}%"></div>
                            </div>
                            @if(!empty($tek['tool_badges']))
                            <div class="flex items-center gap-1 flex-wrap">
                                @foreach($tek['tool_badges'] as $tool)
                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded border border-border bg-surface-muted text-text-secondary">{{ $tool }}</span>
                                @endforeach
                            </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-text-secondary text-xs font-sans">
                            @if($tek['location'] && $tek['location'] !== '-')
                                <div class="flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="truncate max-w-[280px]" title="{{ $tek['location'] }}">{{ $tek['location'] }}</span>
                                </div>
                            @else
                                <span class="text-text-disabled italic font-light font-sans">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="flex flex-col items-center justify-center py-12 text-text-muted border-t border-border">
            <svg class="h-10 w-10 text-text-disabled mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-sm font-medium font-sans">Tidak ada teknisi terdaftar di POP wilayah Anda.</p>
            <p class="text-xs text-text-muted mt-0.5 font-sans">Daftarkan teknisi baru atau sesuaikan wilayah POP pada Manajemen User.</p>
        </div>
        @endif
    </div>
</div>
