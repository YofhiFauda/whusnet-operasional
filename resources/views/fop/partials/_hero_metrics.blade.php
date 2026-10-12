{{-- ══ Stat Cards / Hero Operational Pulse ═══════════════════════ --}}
<div id="stat-cards-container" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 sm:gap-3.5">
    
    <!-- Card 1: Antrean Survey -->
    <div class="metric-card {{ ($stats['overdue_survey'] ?? 0) > 0 ? 'status-error' : 'status-info' }} relative overflow-hidden group">
        <div>
            <div class="metric-card-label">
                <span>Antrean Survey</span>
                <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <div class="metric-card-value-container">
                <p class="metric-card-value">{{ $stats['antrian_survey'] }}</p>
                @if(($stats['overdue_survey'] ?? 0) > 0)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold font-sans uppercase tracking-wider bg-error-bg text-error border border-error-border animate-pulse">
                        {{ $stats['overdue_survey'] }} Overdue
                    </span>
                @endif
            </div>
        </div>
        <p class="metric-card-footer">Pelanggan baru terdaftar</p>
    </div>

    <!-- Card 2: Perlu Aksi FOP -->
    <div class="metric-card {{ ($stats['overdue_installation'] ?? 0) > 0 ? 'status-error' : (($stats['perlu_aksi_fop'] ?? 0) > 0 ? 'status-warning' : '') }} relative overflow-hidden group">
        <div>
            <div class="metric-card-label">
                <span>Perlu Aksi FOP</span>
                <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div class="metric-card-value-container">
                <p class="metric-card-value">{{ $stats['perlu_aksi_fop'] }}</p>
                @if(($stats['overdue_installation'] ?? 0) > 0)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold font-sans uppercase tracking-wider bg-error-bg text-error border border-error-border animate-pulse">
                        {{ $stats['overdue_installation'] }} Overdue
                    </span>
                @endif
            </div>
        </div>
        <p class="metric-card-footer">Survey selesai & verifikasi</p>
    </div>

    <!-- Card 3: Task Delivery Hari Ini -->
    <div class="metric-card status-success relative overflow-hidden group">
        <div>
            <div class="metric-card-label">
                <span>Task Selesai Hari Ini</span>
                <span class="text-[10px] font-mono font-bold text-success bg-success-bg px-1.5 py-0.5 rounded border border-success-border">
                    {{ $heroMetrics['delivery_task']['progress_percent'] ?? 0 }}%
                </span>
            </div>
            <div class="metric-card-value-container">
                <p class="metric-card-value">{{ $stats['selesai_hari_ini'] }}</p>
                <span class="text-[11px] font-semibold text-text-muted">/ {{ $stats['total_hari_ini'] }} Task</span>
            </div>
        </div>
        <!-- Progress Bar Visual -->
        <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden mt-1.5">
            <div class="bg-success h-full transition-all duration-300" style="width: {{ $heroMetrics['delivery_task']['progress_percent'] ?? 0 }}%"></div>
        </div>
        <p class="metric-card-footer">{{ $stats['berjalan'] }} task sedang berjalan</p>
    </div>

    <!-- Card 4: Tiket Gangguan & C-REQ -->
    <div class="metric-card {{ ($heroMetrics['tickets']['emergency_count'] ?? 0) > 0 ? 'status-error' : 'status-info' }} relative overflow-hidden group">
        <div>
            <div class="metric-card-label">
                <span>Tiket Gangguan</span>
                <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <div class="metric-card-value-container">
                <p class="metric-card-value">{{ $heroMetrics['tickets']['total_open'] ?? 0 }}</p>
                @if(($heroMetrics['tickets']['emergency_count'] ?? 0) > 0)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold font-sans uppercase tracking-wider bg-error-bg text-error border border-error-border animate-pulse">
                        {{ $heroMetrics['tickets']['emergency_count'] }} Kritis
                    </span>
                @endif
            </div>
        </div>
        <p class="metric-card-footer">Komplain MTN & C-REQ terbuka</p>
    </div>

    <!-- Card 5: SLA Health Index -->
    <div class="metric-card {{ ($heroMetrics['sla_health']['compliance_rate'] ?? 100) < 90 ? 'status-warning' : 'status-success' }} relative overflow-hidden group">
        <div>
            <div class="metric-card-label">
                <span>Kepatuhan SLA Tiket &amp; Task</span>
                <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <div class="metric-card-value-container">
                <p class="metric-card-value">{{ $heroMetrics['sla_health']['compliance_rate'] ?? 100 }}<span class="text-xs font-normal text-text-muted">%</span></p>
            </div>
        </div>
        <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden mt-1.5">
            <div class="bg-primary h-full transition-all duration-300" style="width: {{ $heroMetrics['sla_health']['compliance_rate'] ?? 100 }}%"></div>
        </div>
        <p class="metric-card-footer">{{ $heroMetrics['sla_health']['breached_tickets'] ?? 0 }} tiket + {{ $heroMetrics['sla_health']['breached_tasks'] ?? 0 }} task lewat SLA dari {{ $heroMetrics['sla_health']['total_evaluated'] ?? 0 }} yang dipantau</p>
    </div>

    <!-- Card 6: Armada Teknisi Lapangan -->
    <div class="metric-card status-info relative overflow-hidden group">
        <div>
            <div class="metric-card-label">
                <span>Armada Teknisi</span>
                <span class="text-[10px] font-mono font-bold text-primary bg-primary-soft px-1.5 py-0.5 rounded border border-primary-border">
                    {{ $heroMetrics['fleet_tech']['utilization_percent'] ?? 0 }}%
                </span>
            </div>
            <div class="metric-card-value-container">
                <p class="metric-card-value">{{ $heroMetrics['fleet_tech']['active'] ?? 0 }}</p>
                <span class="text-[11px] font-semibold text-text-muted">/ {{ $heroMetrics['fleet_tech']['total'] ?? 0 }} teknisi</span>
            </div>
        </div>
        <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden mt-1.5">
            <div class="bg-primary h-full transition-all duration-300" style="width: {{ $heroMetrics['fleet_tech']['utilization_percent'] ?? 0 }}%"></div>
        </div>
        <p class="metric-card-footer">{{ $heroMetrics['fleet_tech']['scheduled'] ?? 0 }} terjadwal · {{ $heroMetrics['fleet_tech']['standby'] ?? 0 }} standby</p>
    </div>

</div>
