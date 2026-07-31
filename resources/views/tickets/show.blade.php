@extends('layouts.app')

@section('title', 'Detail Ticket ' . $ticket->ticket_number . ' — ISP NOC')
@section('page_title', 'Detail Ticket ' . $ticket->ticket_number)

@section('content')
@php
    $customer = $ticket->customer;
    $koordinat = ($ticket->customer_latitude && $ticket->customer_longitude)
        ? "{$ticket->customer_latitude}, {$ticket->customer_longitude}"
        : null;
@endphp

<div class="space-y-6 max-w-5xl mx-auto pb-12">

    {{-- Top Navigation & Breadcrumb --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-xs text-text-muted">
            <a href="{{ route('tickets.create') }}" class="hover:text-sky-600 transition-colors flex items-center gap-1">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Ticketing
            </a>
            <span>/</span>
            <span class="text-text-main font-semibold">Detail Ticket</span>
            <span>/</span>
            <span class="font-mono font-bold text-sky-600 dark:text-sky-400">{{ $ticket->ticket_number }}</span>
        </div>

        <a href="{{ route('tickets.create') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border bg-surface text-xs font-semibold text-text-secondary hover:text-text-main transition-colors shadow-xs">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali ke Inbox
        </a>
    </div>

    {{-- Header Banner Ticket --}}
    <div class="bg-surface border border-border rounded-xl p-6 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            
            {{-- Ticket Number & Badges --}}
            <div class="min-w-0 space-y-2">
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h1 class="text-xl font-extrabold font-mono text-sky-600 dark:text-sky-400 tracking-tight data-text">
                        {{ $ticket->ticket_number }}
                    </h1>

                    <span class="text-xs font-mono font-bold px-2.5 py-0.5 rounded border {{ $ticket->type->badgeClasses() }}">
                        {{ $ticket->type->value }} — {{ $ticket->type->label() }}
                    </span>

                    <span class="text-xs font-bold px-2.5 py-0.5 rounded border {{ $ticket->statusBadgeClasses() }}">
                        {{ $ticket->statusLabel() }}
                    </span>

                    @if($ticket->priority)
                        <span class="text-xs font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border border-border">
                            Prioritas: {{ $ticket->priority->value }}
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-2 text-xs text-text-muted">
                    <span class="font-bold text-text-main">{{ $ticket->customer_name ?? '—' }}</span>
                    <span>•</span>
                    <span class="font-medium text-text-secondary">{{ $ticket->pop->name ?? '—' }}</span>
                </div>
            </div>

            {{-- Creator & Timestamp Info --}}
            <div class="shrink-0 text-left md:text-right space-y-1 text-xs text-text-muted border-t md:border-t-0 pt-3 md:pt-0 border-border">
                <div class="flex items-center md:justify-end gap-1.5">
                    <span class="font-semibold text-text-secondary">Assigned by:</span>
                    <span class="font-bold text-text-main">{{ $ticket->creator->name ?? '—' }}</span>
                </div>
                <div class="flex items-center md:justify-end gap-1.5">
                    <span class="font-semibold text-text-secondary">Created:</span>
                    <span class="font-mono text-text-main">{{ \App\Support\IndonesianDate::dateTime($ticket->created_at) }}</span>
                </div>
            </div>
        </div>

        {{-- Linked Task FOP Info Box --}}
        <div class="pt-4 border-t border-border">
            @if($ticket->fopTask)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-sky-50/50 dark:bg-slate-900/40 border border-sky-200 dark:border-sky-900/50 rounded-lg p-3.5">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 shrink-0">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div class="text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-text-main">Task FOP Lapangan Terkait:</span>
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400 data-text">{{ $ticket->fopTask->task_number }}</span>
                            </div>
                            <p class="text-text-muted mt-0.5">
                                @if($ticket->fopTask->technicians->isNotEmpty())
                                    Teknisi: <span class="font-medium text-text-main">{{ $ticket->fopTask->technicians->pluck('name')->join(', ') }}</span>
                                @else
                                    Belum ada teknisi FOP ditugaskan
                                @endif
                            </p>
                        </div>
                    </div>

                    @if(auth()->user()->hasPermission('fop_tasks.view'))
                        <a href="{{ route('fop-tasks.index') }}"
                           class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded bg-sky-600 text-white text-xs font-bold hover:bg-sky-700 transition-colors shadow-xs">
                            Buka Task FOP →
                        </a>
                    @endif
                </div>
            @else
                <div class="p-3 bg-slate-50 dark:bg-slate-900/30 border border-border rounded-lg text-xs text-text-muted flex items-center gap-2">
                    <svg class="h-4 w-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Task FOP untuk ticket ini sudah tidak aktif — data ticket tetap tersimpan sebagai riwayat audit NOC.</span>
                </div>
            @endif
        </div>
    </div>

    {{--
        Aksi Tiket — Close/Escalate (docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD).
        Cuma tampil kalau tiket belum nyampe FOP, masih terbuka, DAN aktor
        emang lagi megang tiket ini (role-nya cocok sama $ticket->handler) —
        pengecekan role di sini cuma buat UI, otorisasi sungguhan tetap di
        TicketService (lihat assertActorOwnsTicket()).
    --}}
    @php
        // Ticket::actionFlagsFor() — SATU-SATUNYA sumber logic ini (dipakai
        // juga di worksheet panel & index bucket), jangan duplikasi ulang di sini.
        $ticketActions = $ticket->actionFlagsFor(auth()->user());
    @endphp

    @if($ticketActions['can_close'] || $ticketActions['can_escalate_noc'] || $ticketActions['can_escalate_fop'] || $ticketActions['can_return_to_helpdesk'] || $ticketActions['can_cancel'])
    <div class="bg-surface border border-amber-200 dark:border-amber-900/50 rounded-xl p-5 shadow-sm">
        <div class="flex items-center gap-2 mb-3">
            <span class="w-1 h-4 bg-amber-500 rounded-full"></span>
            <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Aksi Tiket — Ditangani {{ $ticket->handler->label() }}</h2>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if($ticketActions['can_close'])
            <button type="button"
                onclick="confirmTicketDetailAction('{{ route('tickets.close', $ticket) }}', null, 'Selesaikan Tiket', 'Apa yang sudah dikerjakan? (opsional)', false, 'Tandai tiket ini selesai?')"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">
                Selesaikan Sendiri
            </button>
            @endif

            @if($ticketActions['can_escalate_noc'])
            <button type="button"
                onclick="confirmTicketDetailAction('{{ route('tickets.escalate', $ticket) }}', 'noc', 'Kirim Tiket ke NOC', 'Catatan buat NOC (opsional)', false, 'Kirim tiket ini ke NOC?')"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-600 text-white text-xs font-bold hover:bg-amber-700 transition-colors cursor-pointer">
                Kirim ke NOC
            </button>
            @endif

            @if($ticketActions['can_escalate_fop'])
            <button type="button"
                onclick="confirmTicketDetailAction('{{ route('tickets.escalate', $ticket) }}', 'fop', 'Kirim Tiket ke FOP', 'Catatan buat FOP (opsional)', false, 'Kirim tiket ini ke FOP? Task FOP baru akan dibuat.')"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-sky-600 text-white text-xs font-bold hover:bg-sky-700 transition-colors cursor-pointer">
                Kirim ke FOP
            </button>
            @endif

            {{--
                Gap #7 (docs/plan/analisa-efektivitas-worksheet-ticketing.md) —
                jalur pemulihan kalau NOC salah terima/salah pencet. Cuma NOC
                yang bisa "turun" balik ke Helpdesk (lihat Ticket::actionFlagsFor()).
            --}}
            @if($ticketActions['can_return_to_helpdesk'])
            <button type="button"
                onclick="confirmTicketDetailAction('{{ route('tickets.return-to-helpdesk', $ticket) }}', null, 'Kembalikan ke Helpdesk', 'Alasan dikembalikan (opsional)', false, 'Kembalikan tiket ini ke Helpdesk?')"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-600 text-white text-xs font-bold hover:bg-slate-700 transition-colors cursor-pointer">
                Kembalikan ke Helpdesk
            </button>
            @endif

            @if($ticketActions['can_cancel'])
            <button type="button"
                onclick="confirmTicketDetailAction('{{ route('tickets.cancel', $ticket) }}', null, 'Batalkan Tiket', 'Alasan pembatalan (wajib diisi)', true, 'Batalkan tiket ini? Tindakan ini gak bisa dibatalin balik.')"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-red-600 text-white text-xs font-bold hover:bg-red-700 transition-colors cursor-pointer">
                Batalkan
            </button>
            @endif
        </div>

    </div>
    @endif

    {{-- Technical Customer Snapshot Grid (Data Pelanggan Saat Ticket Dibuat) --}}
    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
        <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-1 h-4 bg-sky-600 rounded-full"></span>
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">CUSTOMER TECHNICAL SNAPSHOT (AT CREATION)</h2>
            </div>
            <span class="text-[10px] font-mono text-text-muted uppercase tracking-wider">Historical Snapshot</span>
        </div>

        <div class="p-6">
            <div class="border border-border rounded-lg overflow-hidden bg-border shadow-xs">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-px bg-border">

                    {{-- Cell 1: Customer Name --}}
                    <div class="bg-surface p-3 space-y-1">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Customer Name</span>
                        <div class="text-xs font-semibold text-text-main truncate">{{ $ticket->customer_name ?: '—' }}</div>
                    </div>

                    {{-- Cell 2: CID Number --}}
                    <div class="bg-surface p-3 space-y-1">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">CID Number</span>
                        <div class="text-xs font-bold font-mono text-sky-600 dark:text-sky-400 truncate">
                            {{ $customer?->display_id ?: ($customer?->cid ?: ($customer?->customer_code ?: '—')) }}
                        </div>
                    </div>

                    {{-- Cell 3: Phone / HP --}}
                    <div class="bg-surface p-3 space-y-1">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Phone / HP</span>
                        <div class="text-xs font-mono text-text-main truncate">{{ $ticket->customer_phone ?: '—' }}</div>
                    </div>

                    {{-- Cell 4: Active Package --}}
                    <div class="bg-surface p-3 space-y-1">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Active Package</span>
                        <div class="text-xs font-medium text-text-main truncate">{{ $ticket->customer_package ?: '—' }}</div>
                    </div>

                    {{-- Cell 5: Site Address (2 cols) --}}
                    <div class="bg-surface p-3 space-y-1 md:col-span-2">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Site Address</span>
                        <div class="text-xs text-text-main line-clamp-2">{{ $ticket->customer_address ?: '—' }}</div>
                    </div>

                    {{-- Cell 6: POP / Cabang --}}
                    <div class="bg-surface p-3 space-y-1">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">POP / Cabang</span>
                        <div class="text-xs font-semibold text-text-main">{{ $ticket->pop->name ?? '—' }}</div>
                    </div>

                    {{-- Cell 7: ODP Port --}}
                    <div class="bg-surface p-3 space-y-1">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">ODP Port</span>
                        <div class="text-xs font-mono font-medium text-text-main">{{ $ticket->customer_odp ?: '—' }}</div>
                    </div>

                    {{-- Cell 8: Perangkat Pelanggan --}}
                    <div class="bg-surface p-3 space-y-1 md:col-span-2">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Perangkat Pelanggan (ONT/Router)</span>
                        <div class="text-xs font-mono text-text-main">{{ $ticket->customer_device ?: '—' }}</div>
                    </div>

                    {{-- Cell 9: GPS Coordinates --}}
                    <div class="bg-surface p-3 space-y-1 md:col-span-2">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">GPS Coordinates</span>
                        <div class="text-xs font-mono text-sky-600 dark:text-sky-400 flex items-center gap-1">
                            <svg class="h-3.5 w-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            @if($koordinat)
                                <a href="{{ $ticket->customerMapsUrl() }}" target="_blank" rel="noopener"
                                   class="hover:underline font-bold">{{ $koordinat }}</a>
                            @else
                                <span>—</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail Keluhan & Catatan Teknis --}}
    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs space-y-4">
        <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center gap-2">
            <span class="w-1 h-4 bg-sky-600 rounded-full"></span>
            <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">ISSUE & NOC TECHNICAL ASSESSMENT</h2>
        </div>

        <div class="p-6 space-y-5">
            {{-- Detail Keluhan --}}
            <div class="space-y-1.5">
                <span class="block text-[11px] font-bold text-text-muted uppercase tracking-wider">Detail Keluhan (Customer Complaint)</span>
                <div class="p-4 bg-background border border-border rounded-lg text-xs text-text-main leading-relaxed whitespace-pre-line">
                    {{ $ticket->detail_keluhan }}
                </div>
            </div>

            {{-- Catatan Teknis (NOC Monospace Box) --}}
            <div class="space-y-1.5">
                <span class="block text-[11px] font-bold text-text-muted uppercase tracking-wider">Catatan Teknis (Initial NOC Notes)</span>
                <div class="p-4 bg-slate-900/5 dark:bg-slate-900/40 border border-border rounded-lg text-xs font-mono text-text-main italic leading-relaxed whitespace-pre-line">
                    {{ $ticket->catatan_teknis ?: 'Tidak ada catatan teknis awal.' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Dual-Column History Timelines --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Riwayat Ticketing --}}
        <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Riwayat Ticketing (Service Desk)</h2>
                <p class="text-[10px] text-text-muted mt-0.5">Jejak aktivitas dari sisi pengirim ticket.</p>
            </div>

            <ul class="divide-y divide-border">
                @forelse($ticket->histories as $history)
                    <li class="p-4 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded border {{ $history->action->badgeClasses() }}">
                                {{ $history->action->label() }}
                            </span>
                            <span class="text-[10px] font-mono text-text-muted">
                                {{ \App\Support\IndonesianDate::dateTime($history->happened_at) }}
                            </span>
                        </div>

                        <p class="text-xs font-semibold text-text-secondary mt-2">
                            oleh <span class="text-text-main">{{ $history->actor->name ?? 'Sistem' }}</span>
                        </p>

                        @if($history->reason)
                            <p class="text-xs text-text-muted mt-1 bg-background border border-border rounded-md p-2 font-mono">
                                Alasan: {{ $history->reason }}
                            </p>
                        @endif
                    </li>
                @empty
                    <li class="p-6 text-center text-xs text-text-muted font-mono">Belum ada riwayat ticket.</li>
                @endforelse
            </ul>
        </div>

        {{-- Riwayat Task FOP --}}
        <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Riwayat Task FOP (Teknisi Lapangan)</h2>
                <p class="text-[10px] text-text-muted mt-0.5">Jejak operasional pengerjaan di lapangan.</p>
            </div>

            <ul class="divide-y divide-border">
                @forelse(optional($ticket->fopTask)->statusHistories ?? [] as $history)
                    <li class="p-4 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 border border-border text-text-main">
                                {{ $history->label() }}
                            </span>
                            <span class="text-[10px] font-mono text-text-muted">
                                {{ \App\Support\IndonesianDate::dateTime($history->changed_at) }}
                            </span>
                        </div>

                        <p class="text-xs font-semibold text-text-secondary mt-2">
                            oleh <span class="text-text-main">{{ $history->changedByUser->name ?? 'Sistem' }}</span>
                        </p>
                    </li>
                @empty
                    <li class="p-6 text-center text-xs text-text-muted font-mono">
                        {{ $ticket->fopTask ? 'Belum ada perubahan status FOP.' : 'Task FOP tidak aktif.' }}
                    </li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Lampiran Panel --}}
    @if($ticket->attachments->isNotEmpty())
    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
        <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-1 h-4 bg-sky-600 rounded-full"></span>
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">EVIDENCE & ATTACHMENTS ({{ $ticket->attachments->count() }})</h2>
            </div>
        </div>

        <ul class="divide-y divide-border">
            @foreach($ticket->attachments as $attachment)
                <li class="flex items-center justify-between p-4 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-9 w-9 shrink-0 rounded-lg bg-slate-100 dark:bg-slate-800 border border-border flex items-center justify-center text-text-muted">
                            @if($attachment->isImage())
                                <svg class="h-4 w-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            @else
                                <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <p class="text-xs font-bold text-text-main truncate">{{ $attachment->original_name }}</p>
                            <p class="text-[10px] font-mono text-text-muted mt-0.5">
                                {{ $attachment->humanSize() }} • {{ $attachment->uploader->name ?? 'System' }}
                            </p>
                        </div>
                    </div>

                    <a href="{{ route('tickets.attachments.download', $attachment) }}"
                       class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded bg-slate-100 hover:bg-sky-100 dark:hover:bg-slate-800 dark:hover:bg-slate-700 text-sky-600 dark:text-sky-400 text-xs font-bold transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Unduh
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{--
        Konfirmasi + input alasan buat panel "Aksi Tiket" di atas — numpang
        window.Dialog global, sama kayak worksheet & halaman arsip.
    --}}
    @include('tickets.partials.action-dialog')
</div>
@endsection

@push('scripts')
<script>
    /**
     * Panel "Aksi Tiket" di halaman detail — beda dari worksheet/arsip yang
     * fetch() JSON in-place: di sini sengaja POST native biar tetap PRG
     * (redirect balik ke halaman detail dengan state terbaru, lihat
     * docs/PRG_REDIRECT_CONVENTION.md). Form-nya dirakit on the fly, jadi gak
     * perlu markup form tersembunyi per tombol.
     */
    function confirmTicketDetailAction(url, target, title, label, required, confirmText) {
        window.confirmTicketAction({
            title,
            message: confirmText,
            label,
            required,
            confirmText: required ? 'Ya, Batalkan' : 'Ya, Lanjutkan',
            confirmType: required ? 'danger' : 'primary',
            icon: required ? 'error' : 'warning',
            onConfirm: (reason) => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                // Skip global submit-listener di layouts/app.blade.php —
                // konfirmasinya udah lewat dialog di atas, jangan dobel.
                form.classList.add('no-confirm');

                const field = (name, value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                };

                field('_token', document.querySelector('meta[name="csrf-token"]').content);
                field('reason', reason);
                if (target) {
                    field('target', target);
                }

                document.body.appendChild(form);
                form.submit();
            },
        });
    }
</script>
@endpush

