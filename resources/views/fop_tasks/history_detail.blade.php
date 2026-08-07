@extends('layouts.app')

@section('title', 'Detail Riwayat — ' . $fopTask->task_number)

@section('content')
<div class="px-4 py-6 max-w-5xl mx-auto space-y-5">

    {{-- ══ Breadcrumb ══ --}}
    <nav class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 font-ui">
        <a href="{{ route('fop-tasks.history') }}" class="hover:text-blue-600 transition-colors">Riwayat Task FOP</a>
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-mono">{{ $fopTask->task_number }}</span>
    </nav>

    {{-- ══ Header ══ --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 flex-wrap mb-1.5">
                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border font-ui {{ $fopTask->category instanceof \App\Enums\TaskType ? $fopTask->category->badgeClasses() : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                    {{ $fopTask->category instanceof \App\Enums\TaskType ? $fopTask->category->value : $fopTask->category }}
                </span>
                @php
                    $statusBadge = match ($fopTask->status->value) {
                        'selesai' => 'bg-green-50 text-green-700 border-green-200',
                        'dibatalkan' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200',
                        default => 'bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                    };
                    $statusLabel = $fopTask->task
                        ? $fopTask->task->status->displayLabel($fopTask->task->report_deferred)
                        : $fopTask->status->displayLabel();
                @endphp
                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border font-ui {{ $statusBadge }}">
                    {{ $statusLabel }}
                </span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100 tracking-tight font-ui">{{ $fopTask->tugas }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-ui font-mono">{{ $fopTask->task_number }}</p>
        </div>
    </div>

    @php
        // MTN & C-REQ yang asalnya dari Ticketing — Issue/Gangguan & Catatan
        // Teknis buat tipe ini WAJIB dibaca dari $ticket (utuh, proper),
        // bukan $fopTask->issue (kepotong 255 char) — dipindah ke atas Info
        // Task biar baris Issue generik bisa disembunyikan buat tipe ini
        // (dipindah/diganti versi robust di section "Detail Ticket" bawah).
        $isTicketOriginType = in_array($fopTask->category, [\App\Enums\TaskType::MAINTENANCE, \App\Enums\TaskType::CREQ], true);
        $ticket = $fopTask->ticket;
        $showTicketDetail = $isTicketOriginType && $ticket && auth()->user()->hasPermission('tickets.view');
    @endphp

    {{-- ══ Info Task ══ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Info Task</h2>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 text-[11px] font-ui">
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Tanggal</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data">{{ $fopTask->task_date?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Area</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $fopTask->village?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">POP / Cabang</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $fopTask->pop?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Prioritas</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $fopTask->priority->value }}</p>
            </div>
            {{-- Issue generik ($fopTask->issue, kepotong 255 char) cuma relevan
                 buat tipe NON-Ticketing. MTN/C-REQ dari Ticketing nampilin versi
                 utuh & proper di section "Detail Ticket" di bawah — lihat situ,
                 bukan di sini, biar gak ada 2 versi (satu kepotong, satu utuh)
                 dari konten yang sama. --}}
            @unless($showTicketDetail)
            <div class="col-span-2">
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Issue</p>
                <p class="font-medium text-red-600 dark:text-red-400">{{ $fopTask->issue ?? '—' }}</p>
            </div>
            @endunless
            @if($fopTask->status->value === 'dibatalkan')
            <div class="col-span-2">
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Alasan Cancel</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $fopTask->cancel_reason ?? '—' }}</p>
            </div>
            @endif
            @if($fopTask->notes && !$showTicketDetail)
            <div class="col-span-2 sm:col-span-4">
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Catatan</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $fopTask->notes }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ══ Detail Registrasi (SRV/PSB/MTN-C-REQ native) — data pelanggan live
         dari Customer model, setara sama "Data Pelanggan" yang MTN/C-REQ asal
         Ticketing tampilkan dari snapshot Ticket di bawah. Gak dipakai buat
         tipe ticket-origin karena datanya udah ada di blok "Detail Ticket". ══ --}}
    @unless($showTicketDetail)
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Detail Registrasi</h2>
        </div>

        @if($fopTask->customer)
        @php $regCustomer = $fopTask->customer; @endphp

        {{-- Teknisi & Team ditonjolkan di sini (dipindah dari Info Task),
             setara posisi "Assigned by" di blok Detail Ticket. --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 px-4 pt-3 text-[11px] font-ui">
            <p><span class="text-slate-400 dark:text-slate-500">Teknisi:</span> <span class="font-medium text-slate-800 dark:text-slate-200">{{ $fopTask->technicians->pluck('name')->implode(', ') ?: '—' }}</span></p>
            <p><span class="text-slate-400 dark:text-slate-500">Team:</span> <span class="font-medium text-slate-800 dark:text-slate-200">{{ $fopTask->team?->name ?? '—' }}</span></p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 text-[11px] font-ui">
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">CID</p>
                <p class="font-medium text-blue-700 font-data">{{ $regCustomer->display_id ?: '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Nama</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $regCustomer->full_name ?: '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">No. HP</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data">{{ $regCustomer->primary_phone ?: ($regCustomer->phone ?: '—') }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">ODP</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data">{{ $regCustomer->customerTechnicalDetail?->odp_number ?: '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Paket</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $regCustomer->internetPackage ? $regCustomer->internetPackage->package_code . ' - ' . $regCustomer->internetPackage->name : '—' }}</p>
            </div>
            <div class="col-span-2">
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Alamat</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $regCustomer->address ?: ($regCustomer->customerAddress?->village ?: '—') }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Perangkat Pelanggan</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">
                    {{ $regCustomer->customerDevice ? trim(($regCustomer->customerDevice->device_type ?? '') . ' ' . ($regCustomer->customerDevice->brand ?? '') . ' ' . ($regCustomer->customerDevice->model ?? '')) ?: '—' : '—' }}
                </p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Koordinat</p>
                @if($regCustomer->latitude && $regCustomer->longitude)
                    <a href="https://www.google.com/maps/search/?api=1&query={{ $regCustomer->latitude }},{{ $regCustomer->longitude }}" target="_blank" rel="noopener" class="font-medium text-blue-600 hover:underline font-data">
                        {{ $regCustomer->latitude }}, {{ $regCustomer->longitude }}
                    </a>
                @else
                    <p class="font-medium text-slate-800 dark:text-slate-200">—</p>
                @endif
            </div>
        </div>
        @else
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 px-4 py-3 text-[11px] font-ui">
            <p><span class="text-slate-400 dark:text-slate-500">Teknisi:</span> <span class="font-medium text-slate-800 dark:text-slate-200">{{ $fopTask->technicians->pluck('name')->implode(', ') ?: '—' }}</span></p>
            <p><span class="text-slate-400 dark:text-slate-500">Team:</span> <span class="font-medium text-slate-800 dark:text-slate-200">{{ $fopTask->team?->name ?? '—' }}</span></p>
        </div>
        <p class="px-4 pb-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Task ini tidak terhubung ke pelanggan tertentu.</p>
        @endif
    </div>
    @endunless

    @if($showTicketDetail)
    {{-- ══ Detail dari Ticketing (mengikuti /tickets/{ticket}) ══ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-between">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Detail Ticket</h2>
            <a href="{{ route('tickets.show', $ticket) }}" class="text-[11px] font-medium text-blue-600 hover:underline font-ui">
                {{ $ticket->ticket_number }} — Buka di Ticketing →
            </a>
        </div>

        {{-- Klasifikasi — Kategori Issue (Master Issue) belum pernah muncul di
             halaman ini sama sekali (baru ditambah bareng modul Master Issue),
             disamping Tipe & Prioritas yang udah ada tapi juga belum tampil
             di Detail Task, cuma di /tickets. --}}
        <div class="flex flex-wrap items-center gap-2 px-4 pt-3 text-[11px] font-ui">
            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded border {{ $ticket->type->badgeClasses() }}">
                {{ $ticket->type->value }}
            </span>
            @if($ticket->issueCategory)
                <span class="text-[10px] font-bold px-2 py-0.5 rounded border border-sky-200 dark:border-sky-900 text-sky-700 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/50">
                    {{ $ticket->issueCategory->name }}
                </span>
            @endif
            @if($ticket->priority)
                <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                    Prioritas: {{ $ticket->priority->value }}
                </span>
            @endif
            <span class="text-[10px] font-bold px-2 py-0.5 rounded border {{ $ticket->statusBadgeClasses() }}">
                {{ $ticket->statusLabel() }}
            </span>
        </div>

        {{-- Assign by & Created at — sebelumnya gak ada sama sekali di halaman
             Detail Task, padahal ini yang pertama dilihat di /tickets/{ticket}. --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 px-4 pt-3 text-[11px] font-ui">
            <p><span class="text-slate-400 dark:text-slate-500">Assigned by:</span> <span class="font-medium text-slate-800 dark:text-slate-200">{{ $ticket->creator->name ?? '—' }}</span></p>
            <p><span class="text-slate-400 dark:text-slate-500">Created:</span> <span class="font-medium text-slate-800 dark:text-slate-200 font-data">{{ \App\Support\IndonesianDate::dateTime($ticket->created_at) }}</span></p>
        </div>

        {{-- Data Pelanggan — snapshot saat ticket dibuat, sama kayak panel di /tickets --}}
        <div class="px-4 pt-3">
            <p class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider font-ui mb-2">Data Pelanggan (saat ticket dibuat)</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 px-4 pb-4 text-[11px] font-ui">
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">CID</p>
                <p class="font-medium text-blue-700 font-data">
                    {{ $ticket->customer?->display_id ?: ($ticket->customer?->cid ?: ($ticket->customer?->customer_code ?: '—')) }}
                </p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Nama</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $ticket->customer_name ?: '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">No. HP</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data">{{ $ticket->customer_phone ?: '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">ODP</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data">{{ $ticket->customer_odp ?: '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Paket</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $ticket->customer_package ?: '—' }}</p>
            </div>
            <div class="col-span-2">
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Alamat</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $ticket->customer_address ?: '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Perangkat Pelanggan</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $ticket->customer_device ?: '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Koordinat</p>
                @if($ticket->customerMapsUrl())
                    <a href="{{ $ticket->customerMapsUrl() }}" target="_blank" rel="noopener" class="font-medium text-blue-600 hover:underline font-data">
                        {{ $ticket->customer_latitude }}, {{ $ticket->customer_longitude }}
                    </a>
                @else
                    <p class="font-medium text-slate-800 dark:text-slate-200">—</p>
                @endif
            </div>
        </div>

        {{-- Issue/Gangguan & Catatan Teknis — dipisah jadi 2 blok utuh sendiri
             (bukan berbagi 1 baris grid), masing-masing sumbernya beda dan
             gak boleh ketuker: Issue/Gangguan dari $ticket->detail_keluhan
             (keluhan PELANGGAN, wajib diisi), Catatan Teknis dari
             $ticket->catatan_teknis (asesmen TEKNIS awal NOC, opsional).
             Versi utuh — BUKAN $fopTask->issue yang kepotong 255 karakter. --}}
        <div class="px-4 pb-4 border-t border-slate-100 dark:border-slate-700/50 pt-3 space-y-3">
            <div class="border border-amber-200 dark:border-amber-800/50 bg-amber-50/60 rounded overflow-hidden">
                <p class="px-3 py-1.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider font-ui border-b border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-900/20">
                    Issue / Gangguan
                </p>
                <p class="px-3 py-2.5 text-[11px] font-medium text-slate-800 dark:text-slate-200 whitespace-pre-line font-ui">{{ $ticket->detail_keluhan }}</p>
            </div>
            <div class="border border-slate-200 dark:border-slate-700 bg-slate-50/60 rounded overflow-hidden">
                <p class="px-3 py-1.5 text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider font-ui border-b border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-700/50">
                    Catatan Teknis
                </p>
                <p class="px-3 py-2.5 text-[11px] font-medium text-slate-800 dark:text-slate-200 whitespace-pre-line font-ui">{{ $ticket->catatan_teknis ?: '— Belum ada catatan teknis.' }}</p>
            </div>
        </div>

        @if($ticket->attachments->isNotEmpty())
        <div class="px-4 pb-4 border-t border-slate-100 dark:border-slate-700/50 pt-3">
            <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-1.5 font-ui">Lampiran ({{ $ticket->attachments->count() }})</p>
            <div class="flex flex-wrap gap-3">
                @foreach($ticket->attachments as $attachment)
                    <a href="{{ route('tickets.attachments.download', $attachment) }}"
                       class="inline-flex items-center gap-1.5 text-[11px] text-blue-600 hover:underline font-ui">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        {{ $attachment->original_name }}
                    </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- ══ Riwayat Ticketing (dari sisi pengirim, kembaran Histori Status di bawah) ══ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Riwayat Ticketing</h2>
        </div>
        @if($ticket->histories->isNotEmpty())
        <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
            @foreach($ticket->histories as $ticketHistory)
            <div class="px-4 py-2.5 flex items-center justify-between gap-3 text-[11px] font-ui">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $ticketHistory->action->label() }}</span>
                    @if($ticketHistory->reason)
                    <span class="text-slate-400 dark:text-slate-500">— {{ $ticketHistory->reason }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3 text-slate-500 dark:text-slate-400 font-data">
                    <span>{{ $ticketHistory->actor?->name ?? 'Sistem' }}</span>
                    <span>{{ $ticketHistory->happened_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada riwayat ticketing.</p>
        @endif
    </div>
    @endif

    {{-- ══ Durasi & SLA Pengerjaan ══ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Durasi & SLA Pengerjaan</h2>
        </div>
        @php $report = $fopTask->task?->report; @endphp
        @if($report)
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 text-[11px] font-ui">
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Mulai</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data">{{ $report->started_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Pending Terakhir</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data">{{ $report->pending_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Resume Terakhir</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data">{{ $report->resumed_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Selesai</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data">{{ $report->completed_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Durasi Aktual</p>
                <p class="font-semibold text-slate-800 dark:text-slate-200 font-data">{{ $report->accumulatedDurationMinutes() }} menit</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Target SLA</p>
                <p class="font-semibold text-slate-800 dark:text-slate-200 font-data">{{ $report->sla_target_minutes ?? '—' }} menit</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Status SLA</p>
                @if($report->sla_status === 'on_time')
                    <p class="font-semibold text-green-700">Tepat Waktu</p>
                @elseif($report->sla_status === 'over')
                    <p class="font-semibold text-red-600 dark:text-red-400">Lewat SLA ({{ $report->sla_overrun_minutes }} mnt)</p>
                @else
                    <p class="font-medium text-slate-400 dark:text-slate-500">—</p>
                @endif
            </div>
        </div>
        @else
        <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada data siklus pengerjaan (teknisi belum pernah klik Mulai).</p>
        @endif
    </div>

    {{-- ══ Laporan ══ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Laporan</h2>
        </div>

        @if($fopTask->category === \App\Enums\TaskType::SURVEY)
            @if($survey)
            <div class="grid grid-cols-2 gap-4 p-4 text-[11px] font-ui">
                <div class="col-span-2">
                    {{-- Catatan alat kerja, bukan material habis pakai. Material ada di
                         daftar Estimasi Kebutuhan Alat di bawah. --}}
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Alat Khusus / Kendala Peralatan</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200">{{ $survey->required_tools ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Estimasi Kabel</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200">{{ $survey->cable_estimation_meter ? $survey->cable_estimation_meter . ' meter' : '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">ODP Terdekat</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200">{{ $survey->nearest_odp ?? '—' }}</p>
                </div>
                @if($survey->requested_installation_date)
                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Request Pemasangan Pelanggan</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200 font-mono">{{ \App\Support\IndonesianDate::date($survey->requested_installation_date) }}</p>
                </div>
                @endif
                @php
                    $estimasiMaterial = $fopTask->materials()->estimasi()->orderBy('id')->get();
                @endphp
                @if($estimasiMaterial->isNotEmpty())
                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-1">Estimasi Kebutuhan Alat</p>
                    @foreach($estimasiMaterial as $material)
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-700/50 py-1">
                        <span class="text-slate-600 dark:text-slate-400">{{ $material->item_name }}</span>
                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }} {{ $material->unit }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Catatan Survey</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200 whitespace-pre-line">{{ $survey->survey_note ?? '—' }}</p>
                </div>
                @if($survey->survey_photo || $survey->house_photo)
                <div class="col-span-2 flex gap-3 flex-wrap">
                    @if($survey->survey_photo)
                    <a href="{{ Storage::url($survey->survey_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto ODP →</a>
                    @endif
                    @if($survey->house_photo)
                    <a href="{{ Storage::url($survey->house_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto Rumah →</a>
                    @endif
                </div>
                @endif
            </div>
            @else
            <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada laporan survey.</p>
            @endif
        @elseif($fopTask->category === \App\Enums\TaskType::PEMASANGAN)
            @if($installation || $technicalDetail)
            <div class="grid grid-cols-2 gap-4 p-4 text-[11px] font-ui">
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">ODP</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200">{{ $technicalDetail?->odp_number ?? '—' }} / Port {{ $technicalDetail?->odp_port ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Redaman</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200">{{ $technicalDetail?->actual_attenuation ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Speedtest</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200 font-data">↓{{ $technicalDetail?->test_download ?? '—' }} / ↑{{ $technicalDetail?->test_upload ?? '—' }} Mbps</p>
                </div>
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Kualitas Sinyal</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200">Jitter {{ $technicalDetail?->jitter_ms ?? '—' }}ms, Loss {{ $technicalDetail?->packet_loss_percent ?? '—' }}%</p>
                </div>
                @php
                    $materialTerpakai = $fopTask->materials()->terpakai()->orderBy('id')->get();
                @endphp
                @if($materialTerpakai->isNotEmpty())
                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-1">Perangkat Pasif Terpakai</p>
                    @foreach($materialTerpakai as $material)
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-700/50 py-1">
                        <span class="text-slate-600 dark:text-slate-400">{{ $material->item_name }}</span>
                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }} {{ $material->unit }}</span>
                    </div>
                    @endforeach
                </div>
                @endif

                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Catatan Pemasangan</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200 whitespace-pre-line">{{ $installation?->installation_note ?? '—' }}</p>
                </div>
                @if($installation?->installation_photo || $installation?->signature_photo || $installation?->contract_photo)
                <div class="col-span-2 flex gap-3 flex-wrap">
                    @if($installation->installation_photo)
                    <a href="{{ Storage::url($installation->installation_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto Pemasangan →</a>
                    @endif
                    @if($installation->contract_photo)
                    <a href="{{ Storage::url($installation->contract_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto Kontrak →</a>
                    @endif
                    @if($installation->signature_photo)
                    <a href="{{ Storage::url($installation->signature_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto Tanda Tangan →</a>
                    @endif
                </div>
                @endif
            </div>
            @else
            <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada laporan pemasangan.</p>
            @endif
        @elseif($fopTask->category === \App\Enums\TaskType::MAINTENANCE)
            @if($maintenance)
            <div class="grid grid-cols-2 gap-4 p-4 text-[11px] font-ui">
                <div class="col-span-2 min-w-0 max-w-full overflow-hidden">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Kendala Teknis</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200 whitespace-pre-line break-words [word-break:break-word]">{{ $maintenance->kendala_teknis }}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Alat Dipakai</p>
                    <div class="flex flex-wrap gap-1.5 mt-1">
                        @foreach(['kabel' => 'Kabel', 'modem' => 'Modem', 'patchcord' => 'Patchcord', 'sleeve' => 'Sleeve', 'lainnya' => 'Lainnya'] as $field => $label)
                            @if($maintenance->{$field})
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100">{{ $label }}: {{ $maintenance->{$field} }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
                @if($maintenance->opm_photo || $maintenance->speedtest_photo)
                <div class="col-span-2 flex gap-3 flex-wrap">
                    @if($maintenance->opm_photo)
                    <a href="{{ Storage::url($maintenance->opm_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto OPM →</a>
                    @endif
                    @if($maintenance->speedtest_photo)
                    <a href="{{ Storage::url($maintenance->speedtest_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto Speedtest →</a>
                    @endif
                </div>
                @endif
            </div>
            @else
            <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada laporan maintenance.</p>
            @endif
        @else
            <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Tipe task ini tidak punya laporan lapangan terstruktur.</p>
        @endif
    </div>

    {{-- ══ Histori Status ══ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Histori Status</h2>
        </div>
        @if($fopTask->statusHistories->isNotEmpty())
        <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
            @foreach($fopTask->statusHistories as $history)
            <div class="px-4 py-2.5 flex items-center justify-between gap-3 text-[11px] font-ui">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $history->label() }}</span>
                    @if($history->from_status)
                    <span class="text-slate-400 dark:text-slate-500">dari {{ $history->from_status }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3 text-slate-500 dark:text-slate-400 font-data">
                    <span>{{ $history->changedByUser?->name ?? 'Sistem' }}</span>
                    <span>{{ $history->changed_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada histori transisi status.</p>
        @endif
    </div>
</div>
@endsection
