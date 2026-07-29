@extends('layouts.app')

@section('title', 'Dashboard NOC — Ticket Service Desk')
@section('page_title', 'Dashboard NOC')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto pb-12" x-data="nocDashboardHandler()">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-extrabold text-text-main tracking-tight">Dashboard NOC</h1>
            <p class="text-xs text-text-muted mt-1 font-medium">Tracking tiket yang lagi ditangani NOC.</p>
        </div>
        <button type="button" id="noc-dashboard-refresh-btn" onclick="window.nocDashboardRefresh && window.nocDashboardRefresh()"
                class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1 cursor-pointer">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>Refresh</span>
        </button>
    </div>

    {{-- Stat Cards --}}
    <div id="stat-cards-container" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $statCards = [
                ['label' => 'Pending NOC', 'value' => $stats['pending_noc'], 'classes' => 'text-sky-600 dark:text-sky-400'],
                ['label' => 'OnCheck NOC', 'value' => $stats['on_check_noc'], 'classes' => 'text-amber-600 dark:text-amber-400'],
                ['label' => 'Selesai Hari Ini', 'value' => $stats['selesai_hari_ini'], 'classes' => 'text-emerald-600 dark:text-emerald-400'],
                ['label' => 'Dibatalkan Hari Ini', 'value' => $stats['dibatalkan_hari_ini'], 'classes' => 'text-red-600 dark:text-red-400'],
            ];
        @endphp
        @foreach($statCards as $card)
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs">
            <p class="text-[10px] font-bold text-text-muted uppercase tracking-wider">{{ $card['label'] }}</p>
            <p class="text-2xl font-extrabold mt-1 {{ $card['classes'] }}">{{ $card['value'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- List Tiket Aktif + Aging --}}
        <div id="active-tickets-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs lg:col-span-2">
            <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Tiket Aktif NOC (paling lama nunggu di atas)</h2>
            </div>
            <ul class="divide-y divide-border">
                @forelse($activeTickets as $ticket)
                    <li class="p-4 flex items-center justify-between gap-3 hover:bg-amber-50/50 dark:hover:bg-slate-900/30 transition-colors">
                        <a href="{{ route('tickets.show', $ticket) }}" class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 text-xs">
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400">{{ $ticket->ticket_number }}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded border {{ $ticket->statusBadgeClasses() }}">{{ $ticket->statusLabel() }}</span>
                            </div>
                            <p class="text-xs font-semibold text-text-main truncate mt-0.5">{{ $ticket->customer->full_name ?? $ticket->customer_name ?? '—' }}</p>
                            <p class="text-[10px] text-text-muted mt-0.5">{{ $ticket->issueCategory?->name ?? \Illuminate\Support\Str::limit($ticket->detail_keluhan, 60) }} — dikirim {{ $ticket->creator->name ?? '—' }}</p>
                        </a>
                        <span class="shrink-0 text-[10px] font-mono font-bold text-amber-700 dark:text-amber-400">
                            {{ $ticket->created_at->diffForHumans(null, true) }}
                        </span>
                    </li>
                @empty
                    <li class="p-8 text-center text-xs text-text-muted">Gak ada tiket aktif di NOC.</li>
                @endforelse
            </ul>
        </div>

        {{-- Feed Aktivitas --}}
        <div id="activity-feed-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Aktivitas Terbaru</h2>
            </div>
            <ul class="divide-y divide-border max-h-96 overflow-y-auto">
                @forelse($activityFeed as $history)
                    <li class="p-3 text-xs">
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400">{{ $history->ticket->ticket_number ?? '—' }}</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border ml-1 {{ $history->action->badgeClasses() }}">{{ $history->action->label() }}</span>
                        <p class="text-text-muted mt-0.5">oleh {{ $history->actor->name ?? 'Sistem' }} — {{ \App\Support\IndonesianDate::dateTime($history->happened_at) }}</p>
                    </li>
                @empty
                    <li class="p-8 text-center text-xs text-text-muted">Belum ada aktivitas.</li>
                @endforelse
            </ul>
        </div>

        {{-- Statistik Issue --}}
        <div id="issue-stats-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Statistik per Issue</h2>
            </div>
            <ul class="divide-y divide-border">
                @forelse($issueStats as $issueName => $total)
                    <li class="p-3 flex items-center justify-between text-xs">
                        <span class="text-text-main font-medium">{{ $issueName }}</span>
                        <span class="font-mono font-bold text-text-secondary">{{ $total }}</span>
                    </li>
                @empty
                    <li class="p-8 text-center text-xs text-text-muted">Belum ada data.</li>
                @endforelse
            </ul>
        </div>

        {{-- Statistik Daerah --}}
        <div id="region-stats-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Statistik per Daerah</h2>
            </div>
            <ul class="divide-y divide-border">
                @forelse($regionStats as $regionName => $total)
                    <li class="p-3 flex items-center justify-between text-xs">
                        <span class="text-text-main font-medium">{{ $regionName }}</span>
                        <span class="font-mono font-bold text-text-secondary">{{ $total }}</span>
                    </li>
                @empty
                    <li class="p-8 text-center text-xs text-text-muted">Belum ada data.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function nocDashboardHandler() {
        return { init() { initNocDashboardEcho(); } };
    }

    (function () {
        const allowedPopIds = @json($allowedPopIds ?? []);
        let refreshing = false;

        async function refetchAndSwap() {
            if (refreshing) return;
            refreshing = true;
            const btn = document.getElementById('noc-dashboard-refresh-btn');
            btn?.classList.add('opacity-50');
            try {
                const res = await fetch(window.location.href, { headers: { 'Accept': 'text/html' } });
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');

                ['stat-cards-container', 'active-tickets-container', 'activity-feed-container', 'issue-stats-container', 'region-stats-container'].forEach(id => {
                    const el = document.getElementById(id);
                    const newEl = doc.getElementById(id);
                    if (el && newEl) el.innerHTML = newEl.innerHTML;
                });
            } catch (e) {
                // Diam-diam gagal — tombol Refresh manual tetap bisa dicoba lagi.
            } finally {
                refreshing = false;
                btn?.classList.remove('opacity-50');
            }
        }

        window.nocDashboardRefresh = refetchAndSwap;

        window.initNocDashboardEcho = function () {
            let attempts = 0;
            const setupEcho = () => {
                if (typeof window.Echo === 'undefined' || !window.Echo) {
                    attempts++;
                    if (attempts < 20) setTimeout(setupEcho, 100);
                    return;
                }
                allowedPopIds.forEach(popId => {
                    window.Echo.private(`tickets.${popId}`).listen('.TicketQueueUpdated', () => refetchAndSwap());
                });
            };
            setupEcho();
        };
    })();
</script>
@endpush
