{{-- ══ Tiket Gangguan & C-REQ Lapangan ════════════════════════════ --}}
<div id="tactical-tickets-board" class="flex flex-col gap-3 font-sans">
    <div class="flex items-center justify-between">
        <div>
            <h4 class="text-xs font-bold text-text-main uppercase tracking-wider">Tiket Gangguan & Eskalasi Lapangan</h4>
            <p class="text-[11px] text-text-muted">Komplain pemeliharaan (MTN) & permohonan pelanggan (C-REQ) terbuka</p>
        </div>
        @can('tickets.history.view')
        <a href="{{ route('tickets.history') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary-hover transition-colors">
            <span>Riwayat Tiket</span>
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </a>
        @endcan
    </div>

    <div class="bg-surface border border-border rounded-xl shadow-xs overflow-hidden">
        @if(isset($fieldTickets) && $fieldTickets->count() > 0)
            <div class="divide-y divide-border">
                @foreach($fieldTickets as $t)
                    <div class="p-3.5 sm:p-4 hover:bg-surface-muted/50 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-start gap-3">
                            {{-- Priority Indicator --}}
                            <div class="mt-0.5">
                                @if(in_array($t['priority'], ['urgent', 'emergency', 'high']))
                                    <span class="h-3 w-3 rounded-full bg-error flex items-center justify-center shrink-0">
                                        <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                    </span>
                                @elseif($t['priority'] === 'medium')
                                    <span class="h-3 w-3 rounded-full bg-warning flex items-center justify-center shrink-0">
                                        <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                    </span>
                                @else
                                    <span class="h-3 w-3 rounded-full bg-info flex items-center justify-center shrink-0">
                                        <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                    </span>
                                @endif
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-xs font-bold text-text-main">{{ $t['ticket_number'] }}</span>
                                    <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border {{ $t['priority'] === 'urgent' || $t['priority'] === 'emergency' ? 'bg-error-bg text-error border-error-border' : ($t['priority'] === 'high' ? 'bg-warning-bg text-warning border-warning-border' : 'bg-info-bg text-info border-info-border') }}">
                                        {{ $t['priority'] }}
                                    </span>
                                    <span class="text-[9px] font-semibold uppercase px-1.5 py-0.5 rounded bg-surface-muted text-text-secondary border border-border">
                                        {{ $t['type_label'] }}
                                    </span>
                                </div>
                                <h5 class="text-xs font-bold text-text-main mt-0.5">{{ $t['issue'] }}</h5>
                                <p class="text-[11px] text-text-muted">
                                    {{ $t['customer_name'] }} · <span class="font-mono">{{ $t['customer_code'] }}</span> · {{ $t['pop_name'] }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-border/40">
                            <span class="text-[10px] text-text-muted">{{ $t['created_at_human'] }}</span>
                            @if($t['has_fop_task'])
                                <a href="{{ route('fop-tasks.history.show', $t['fop_task_id']) }}"
                                   class="inline-flex items-center gap-1 text-[11px] font-semibold text-success bg-success-bg border border-success-border px-2.5 py-1 rounded-lg hover:bg-success-bg/70 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    <span>Task FOP Aktif</span>
                                </a>
                            @else
                                <a href="{{ route('tickets.show', $t['id']) }}"
                                   class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 border border-border rounded-lg bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main shadow-2xs transition-all">
                                    <span>Detail Tiket</span>
                                    <svg class="h-3 w-3 text-text-disabled" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-10 text-text-muted">
                <svg class="h-8 w-8 text-text-disabled mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-xs font-medium font-sans">Tidak ada tiket gangguan terbuka di wilayah Anda.</p>
                <p class="text-[11px] text-text-muted mt-0.5 font-sans">Semua tiket telah tertangani atau selesai.</p>
            </div>
        @endif
    </div>
</div>
