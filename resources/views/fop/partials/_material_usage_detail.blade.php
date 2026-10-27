{{-- ══ Rincian Pemakaian Material & Alat Kerja Hari Ini ═══════════ --}}
<div class="flex flex-col gap-3 font-sans">
    <div class="flex items-center justify-between">
        <div>
            <h4 class="text-xs font-bold text-text-main uppercase tracking-wider">Rincian Pemakaian Hari Ini</h4>
            <p class="text-[11px] text-text-muted">Barang & alat kerja terpakai — siapa, dipakai pada pelanggan atau infrastruktur</p>
        </div>
        <span class="text-[11px] font-semibold text-text-muted bg-surface-muted px-2 py-0.5 rounded border border-border">
            {{ $materialUsageDetail->count() }} Baris
        </span>
    </div>

    <div id="material-usage-detail-widget" class="bg-surface border border-border rounded-xl shadow-xs overflow-hidden">
        @if($materialUsageDetail->count() > 0)

        {{-- Mobile List (< 768px) --}}
        <div class="block md:hidden divide-y divide-border max-h-80 overflow-y-auto custom-scrollbar">
            @foreach($materialUsageDetail as $row)
            <div class="p-3.5 flex flex-col gap-1.5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded border font-sans {{ $row['kind'] === 'Material' ? 'bg-info-bg text-info border-info-border' : 'bg-primary-soft text-primary border-primary-border' }}">{{ $row['kind'] }}</span>
                        <p class="font-bold text-text-main text-xs font-sans mt-1">{{ $row['label'] }}</p>
                    </div>
                    <span class="text-[10px] font-mono text-text-muted shrink-0">{{ $row['time'] }}</span>
                </div>
                <div class="flex items-center justify-between gap-2 text-[11px] pt-1 border-t border-border/50">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-text-secondary font-medium">{{ $row['technicians'] ? implode(', ', $row['technicians']) : '—' }}</span>
                        @if(!empty($row['task_type']))
                        <x-task.usage-badge :type="$row['task_type']" :detail="$row['task_detail']" />
                        @endif
                    </div>
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold {{ $row['target_type'] === 'pelanggan' ? 'text-success' : 'text-warning' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $row['target_type'] === 'pelanggan' ? 'bg-success' : 'bg-warning' }}"></span>
                        {{ $row['target_label'] }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Desktop Table (>= 768px) --}}
        <div class="hidden md:block overflow-x-auto max-h-80 overflow-y-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-border bg-surface-muted sticky top-0">
                        <th class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans">Waktu</th>
                        <th class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans">Barang / Alat</th>
                        <th class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans">Teknisi</th>
                        <th class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans">Dipakai Pada</th>
                        <th class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans">Task</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($materialUsageDetail as $row)
                    <tr class="hover:bg-surface-muted/50 transition-colors duration-150">
                        <td class="px-4 py-2.5 text-text-muted text-xs font-mono font-sans">{{ $row['time'] }}</td>
                        <td class="px-4 py-2.5">
                            <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded border font-sans mr-1.5 {{ $row['kind'] === 'Material' ? 'bg-info-bg text-info border-info-border' : 'bg-primary-soft text-primary border-primary-border' }}">{{ $row['kind'] }}</span>
                            <span class="text-xs font-semibold text-text-main font-sans">{{ $row['label'] }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-text-secondary font-sans">{{ $row['technicians'] ? implode(', ', $row['technicians']) : '—' }}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold {{ $row['target_type'] === 'pelanggan' ? 'text-success' : 'text-warning' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $row['target_type'] === 'pelanggan' ? 'bg-success' : 'bg-warning' }}"></span>
                                {{ $row['target_label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-[11px] font-mono text-text-muted font-sans">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @if(!empty($row['task_type']))
                                <x-task.usage-badge :type="$row['task_type']" :detail="$row['task_detail']" />
                                @endif
                                @if($row['task_number'] && $row['fop_task_id'])
                                <a href="{{ route('fop-tasks.history.show', $row['fop_task_id']) }}" class="text-primary hover:text-primary-hover hover:underline">{{ $row['task_number'] }}</a>
                                @else
                                <span>{{ $row['task_number'] ?? '—' }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="flex flex-col items-center justify-center py-10 text-text-muted">
            <svg class="h-8 w-8 text-text-disabled mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <p class="text-sm font-medium font-sans">Belum ada pemakaian material/alat tercatat hari ini.</p>
        </div>
        @endif
    </div>
</div>
