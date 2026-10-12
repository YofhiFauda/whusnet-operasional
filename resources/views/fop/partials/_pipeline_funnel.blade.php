{{-- ══ Customer Delivery Pipeline (Stepper Funnel) ════════════════ --}}
<div id="customer-pipeline-funnel" class="bg-surface border border-border rounded-xl p-4 sm:p-5 shadow-xs font-sans">
    <div class="flex items-center justify-between mb-3.5">
        <div class="flex items-center gap-2">
            <div class="h-6 w-6 rounded-md bg-primary/10 text-primary flex items-center justify-center">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                </svg>
            </div>
            <div>
                <h3 class="text-xs font-bold text-text-main uppercase tracking-wider">Customer Delivery Pipeline (Funnel Onboarding)</h3>
                <p class="text-[11px] text-text-muted">Alur pemenuhan layanan pelanggan dari registrasi hingga aktif operasional</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[11px] font-semibold text-text-secondary bg-surface-muted px-2.5 py-1 rounded-lg border border-border">
                Total In-Delivery: <strong class="text-primary font-mono">{{ $pipelineSummary['total_in_delivery'] ?? 0 }}</strong>
            </span>
        </div>
    </div>

    {{-- Horizontal Funnel Stepper --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2.5">
        
        {{-- Step 1: Registrasi --}}
        <div class="flex flex-col p-2.5 rounded-lg border border-border bg-surface-muted/40 hover:bg-surface-muted transition-colors">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">1. Registrasi</span>
                <span class="h-2 w-2 rounded-full bg-slate-400"></span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold font-mono text-text-main">{{ $pipelineSummary['registered'] ?? 0 }}</span>
                <span class="text-[10px] text-text-muted">Verif CS</span>
            </div>
            <div class="mt-1 text-[10px] text-text-secondary truncate">Data awal masuk</div>
        </div>

        {{-- Step 2: Antrean Survey --}}
        <div class="flex flex-col p-2.5 rounded-lg border {{ ($stats['overdue_survey'] ?? 0) > 0 ? 'border-error-border bg-error-bg/30' : 'border-info-border bg-info-bg/30' }} hover:shadow-xs transition-all">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider {{ ($stats['overdue_survey'] ?? 0) > 0 ? 'text-error' : 'text-info' }}">2. Antrean Survey</span>
                <span class="h-2 w-2 rounded-full {{ ($stats['overdue_survey'] ?? 0) > 0 ? 'bg-error animate-ping' : 'bg-info' }}"></span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold font-mono text-text-main">{{ $pipelineSummary['waiting_survey'] ?? 0 }}</span>
                @if(($stats['overdue_survey'] ?? 0) > 0)
                    <span class="text-[9px] font-bold text-error">{{ $stats['overdue_survey'] }} overdue</span>
                @else
                    <span class="text-[10px] text-info">SLA 24j</span>
                @endif
            </div>
            <div class="mt-1 text-[10px] text-text-secondary truncate">Siap ditugaskan</div>
        </div>

        {{-- Step 3: Review Survey --}}
        <div class="flex flex-col p-2.5 rounded-lg border border-border bg-surface-muted/40 hover:bg-surface-muted transition-colors">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">3. Review ACC</span>
                <span class="h-2 w-2 rounded-full bg-warning"></span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold font-mono text-text-main">{{ $pipelineSummary['survey_review'] ?? 0 }}</span>
                <span class="text-[10px] text-warning">Validasi</span>
            </div>
            <div class="mt-1 text-[10px] text-text-secondary truncate">Hasil survey di-review</div>
        </div>

        {{-- Step 4: Antrean Pasang --}}
        <div class="flex flex-col p-2.5 rounded-lg border border-border bg-surface-muted/40 hover:bg-surface-muted transition-colors">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">4. Antrean Pasang</span>
                <span class="h-2 w-2 rounded-full bg-primary"></span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold font-mono text-text-main">{{ $pipelineSummary['waiting_installation'] ?? 0 }}</span>
                <span class="text-[10px] text-text-muted">Siap Jadwal</span>
            </div>
            <div class="mt-1 text-[10px] text-text-secondary truncate">Menunggu slot teknisi</div>
        </div>

        {{-- Step 5: Sedang Dipasang --}}
        <div class="flex flex-col p-2.5 rounded-lg border border-warning-border bg-warning-bg/30 hover:shadow-xs transition-all">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-warning">5. Pemasangan</span>
                <span class="h-2 w-2 rounded-full bg-warning animate-pulse"></span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold font-mono text-text-main">{{ $pipelineSummary['installation_in_progress'] ?? 0 }}</span>
                <span class="text-[10px] text-warning font-medium">Di Lapangan</span>
            </div>
            <div class="mt-1 text-[10px] text-text-secondary truncate">Teknisi sedang proses</div>
        </div>

        {{-- Step 6: Verifikasi Admin --}}
        <div class="flex flex-col p-2.5 rounded-lg border {{ ($stats['overdue_installation'] ?? 0) > 0 ? 'border-error-border bg-error-bg/30' : 'border-border bg-surface-muted/40' }} hover:shadow-xs transition-all">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider {{ ($stats['overdue_installation'] ?? 0) > 0 ? 'text-error' : 'text-text-muted' }}">6. Verifikasi Admin</span>
                <span class="h-2 w-2 rounded-full {{ ($stats['overdue_installation'] ?? 0) > 0 ? 'bg-error' : 'bg-purple-500' }}"></span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold font-mono text-text-main">{{ $pipelineSummary['verification_admin'] ?? 0 }}</span>
                @if(($stats['overdue_installation'] ?? 0) > 0)
                    <span class="text-[9px] font-bold text-error">{{ $stats['overdue_installation'] }} overdue</span>
                @else
                    <span class="text-[10px] text-purple-600 dark:text-purple-400">Cek Redaman</span>
                @endif
            </div>
            <div class="mt-1 text-[10px] text-text-secondary truncate">Validasi teknis & SN</div>
        </div>

        {{-- Step 7: Aktif (Siap Billing) --}}
        <div class="flex flex-col p-2.5 rounded-lg border border-success-border bg-success-bg/30 hover:shadow-xs transition-all">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-success">7. Aktif</span>
                <span class="h-2 w-2 rounded-full bg-success"></span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold font-mono text-text-main">{{ $pipelineSummary['active'] ?? 0 }}</span>
                <span class="text-[10px] text-success">Operasional</span>
            </div>
            <div class="mt-1 text-[10px] text-text-secondary truncate">Layanan aktif</div>
        </div>

    </div>
</div>
