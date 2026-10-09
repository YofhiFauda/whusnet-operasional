@extends('layouts.app')

@section('title', 'Verifikasi Biaya C-REQ - Whusnet Operasional')
@section('page_title', 'Verifikasi Biaya C-REQ')

@php
    $pendingCount = $statusCounts->pending_count ?? 0;
    $verifiedCount = $statusCounts->verified_count ?? 0;
    $rejectedCount = $statusCounts->rejected_count ?? 0;
    $totalCount = $statusCounts->total_count ?? 0;
@endphp

@section('content')
<div class="max-w-[1400px] mx-auto space-y-5 pb-16 md:pb-8" x-data="creqIndexView()">

    {{-- ══ LAYER 1: NAKED PAGE HEADER ══════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">
                    Verifikasi Biaya C-REQ
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60">
                    {{ $tasks->total() }} Data
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">
                Antrean verifikasi biaya pekerjaan perubahan layanan (Change Request) yang diajukan teknisi untuk penerbitan Tagihan Manual.
            </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            @can('invoices.view')
            <a href="{{ route('invoices.index') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-all shadow-2xs active:scale-95">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/></svg>
                <span>Daftar Tagihan</span>
            </a>
            @endcan
        </div>
    </div>

    {{-- ══ LAYER 2: SUMMARY STRIP (FLAT BAR WITH VERTICAL DIVIDERS) ════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 divide-x divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-2xs">
        
        {{-- Metric 1: Menunggu Verifikasi --}}
        <a href="{{ route('tasks.creq-billing.index', ['status' => 'pending', 'q' => $search]) }}"
           class="p-4 flex flex-col justify-between transition-colors {{ $statusFilter === 'pending' ? 'bg-amber-50/50 dark:bg-amber-950/20 ring-1 ring-inset ring-amber-400/40' : 'hover:bg-slate-50/70 dark:hover:bg-slate-700/30' }}">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">
                MENUNGGU VERIFIKASI
            </span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-xl sm:text-2xl font-bold font-mono text-amber-600 dark:text-amber-400">
                    {{ $pendingCount }}
                </span>
                <span class="text-[10px] font-semibold text-amber-700 dark:text-amber-400 bg-amber-100/70 dark:bg-amber-900/40 px-2 py-0.5 rounded-full">
                    Perlu Tindakan
                </span>
            </div>
        </a>

        {{-- Metric 2: Diverifikasi --}}
        <a href="{{ route('tasks.creq-billing.index', ['status' => 'verified', 'q' => $search]) }}"
           class="p-4 flex flex-col justify-between transition-colors {{ $statusFilter === 'verified' ? 'bg-emerald-50/50 dark:bg-emerald-950/20 ring-1 ring-inset ring-emerald-400/40' : 'hover:bg-slate-50/70 dark:hover:bg-slate-700/30' }}">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">
                DIVERIFIKASI (TAGIHAN TERBIT)
            </span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-xl sm:text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                    {{ $verifiedCount }}
                </span>
                <span class="text-[10px] font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-100/70 dark:bg-emerald-900/40 px-2 py-0.5 rounded-full">
                    Selesai
                </span>
            </div>
        </a>

        {{-- Metric 3: Ditolak --}}
        <a href="{{ route('tasks.creq-billing.index', ['status' => 'rejected', 'q' => $search]) }}"
           class="p-4 flex flex-col justify-between transition-colors {{ $statusFilter === 'rejected' ? 'bg-rose-50/50 dark:bg-rose-950/20 ring-1 ring-inset ring-rose-400/40' : 'hover:bg-slate-50/70 dark:hover:bg-slate-700/30' }}">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">
                DITOLAK (TANPA BIAYA)
            </span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-xl sm:text-2xl font-bold font-mono text-rose-600 dark:text-rose-400">
                    {{ $rejectedCount }}
                </span>
                <span class="text-[10px] font-semibold text-rose-700 dark:text-rose-400 bg-rose-100/70 dark:bg-rose-900/40 px-2 py-0.5 rounded-full">
                    Dibatalkan
                </span>
            </div>
        </a>

        {{-- Metric 4: Total Task Berbayar --}}
        <a href="{{ route('tasks.creq-billing.index', ['status' => 'all', 'q' => $search]) }}"
           class="p-4 flex flex-col justify-between transition-colors {{ $statusFilter === 'all' ? 'bg-sky-50/50 dark:bg-sky-950/20 ring-1 ring-inset ring-sky-400/40' : 'hover:bg-slate-50/70 dark:hover:bg-slate-700/30' }}">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">
                TOTAL TASK C-REQ BERBAYAR
            </span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-slate-100">
                    {{ $totalCount }}
                </span>
                <span class="text-[10px] font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded-full">
                    Semua Status
                </span>
            </div>
        </a>

    </div>

    {{-- ══ LAYER 3: NAKED FILTER BAR ═══════════════════════════════════════════ --}}
    <form method="GET" action="{{ route('tasks.creq-billing.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 flex-1 max-w-xl">
            {{-- Pill Search Input --}}
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" name="q" value="{{ $search }}" placeholder="Cari No. Task, Nama Pelanggan, atau CID..."
                       class="w-full pl-10 pr-9 py-2 text-xs font-sans rounded-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all shadow-2xs">
                @if($search)
                <a href="{{ route('tasks.creq-billing.index', ['status' => $statusFilter]) }}" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600" title="Bersihkan Pencarian">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </a>
                @endif
            </div>

            {{-- Status Dropdown Filter --}}
            <div class="w-44 shrink-0">
                <select name="status" onchange="this.form.submit()"
                        class="w-full text-xs font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors shadow-2xs cursor-pointer">
                    <option value="all" @selected($statusFilter === 'all')>Semua Status</option>
                    @foreach(\App\Enums\CReqVerificationStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($search || $statusFilter !== \App\Enums\CReqVerificationStatus::PENDING->value)
        <a href="{{ route('tasks.creq-billing.index') }}"
           class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            <span>Reset Filter</span>
        </a>
        @endif
    </form>

    {{-- ══ LAYER 4: TABLE PANEL (CARD BUDGET = 1) ══════════════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/75 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider select-none">
                        <th class="px-4 py-3 w-12 text-center">No.</th>
                        <th class="px-4 py-3 min-w-[180px]">Task / Pelanggan</th>
                        <th class="px-4 py-3 min-w-[120px]">POP / Cabang</th>
                        <th class="px-4 py-3 min-w-[140px]">Kategori C-REQ</th>
                        <th class="px-4 py-3 min-w-[200px]">Catatan Biaya Teknisi</th>
                        <th class="px-4 py-3 min-w-[130px]">Waktu Selesai</th>
                        <th class="px-4 py-3 min-w-[120px] text-center">Status</th>
                        <th class="px-4 py-3 min-w-[120px] text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($tasks as $i => $task)
                        @php
                            $detail = $task->creqDetail;
                            $status = $detail?->verification_status?->value ?? 'pending';
                            $statusBadge = match($status) {
                                'verified' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/60',
                                'rejected' => 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800/60',
                                default => 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800/60',
                            };
                            $statusDot = match($status) {
                                'verified' => 'bg-emerald-500',
                                'rejected' => 'bg-rose-500',
                                default => 'bg-amber-500 animate-pulse',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20 transition-colors">
                            {{-- No --}}
                            <td class="px-4 py-3.5 text-center text-slate-400 dark:text-slate-500 font-mono text-[11px]">
                                {{ $tasks->firstItem() + $i }}
                            </td>

                            {{-- Task / Pelanggan --}}
                            <td class="px-4 py-3.5">
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $task->task_number }}</span>
                                        <button type="button" @click="copyText('{{ $task->task_number }}', 'No. Task')" class="text-slate-400 hover:text-sky-600 transition-colors cursor-pointer" title="Salin No. Task">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        </button>
                                    </div>
                                    @if($task->customer)
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <a href="{{ route('customers.show', $task->customer) }}" class="font-semibold text-slate-800 dark:text-slate-100 hover:text-sky-600 dark:hover:text-sky-400 transition-colors">
                                            {{ $task->customer->full_name }}
                                        </a>
                                        <span class="font-mono text-[10px] text-slate-400 bg-slate-100 dark:bg-slate-700/60 px-1 rounded">
                                            {{ $task->customer->cid ?? $task->customer->customer_code }}
                                        </span>
                                    </div>
                                    @else
                                    <span class="text-slate-400 italic">Pelanggan tidak diketahui</span>
                                    @endif
                                </div>
                            </td>

                            {{-- POP / Cabang --}}
                            <td class="px-4 py-3.5">
                                <span class="font-medium text-slate-700 dark:text-slate-300 block truncate">{{ $task->pop?->name ?? '—' }}</span>
                                <span class="text-[10px] text-slate-400 block mt-0.5">{{ $task->pop?->city ?? 'Area Operasional' }}</span>
                            </td>

                            {{-- Kategori C-REQ --}}
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60">
                                    {{ $detail?->category?->label() ?? 'C-REQ' }}
                                </span>
                                @if($detail?->category_custom_name)
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400 mt-1 truncate" title="{{ $detail->category_custom_name }}">
                                    {{ $detail->category_custom_name }}
                                </span>
                                @endif
                            </td>

                            {{-- Catatan Biaya Teknisi --}}
                            <td class="px-4 py-3.5">
                                @if($detail?->billing_note)
                                <p class="text-slate-700 dark:text-slate-300 line-clamp-2 text-xs leading-relaxed" title="{{ $detail->billing_note }}">
                                    {{ $detail->billing_note }}
                                </p>
                                @else
                                <span class="text-slate-400 italic">Tanpa catatan khusus</span>
                                @endif
                            </td>

                            {{-- Waktu Selesai --}}
                            <td class="px-4 py-3.5">
                                <span class="font-mono text-slate-700 dark:text-slate-300 block text-[11px]">
                                    {{ $task->completed_at ? \App\Support\IndonesianDate::dateTime($task->completed_at) : '—' }}
                                </span>
                                @if($detail?->verified_at)
                                <span class="text-[10px] text-slate-400 block mt-0.5">
                                    Verif: {{ \App\Support\IndonesianDate::date($detail->verified_at) }}
                                </span>
                                @endif
                            </td>

                            {{-- Status Badge --}}
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $statusBadge }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusDot }}"></span>
                                    {{ $detail?->verification_status?->label() ?? 'Pending' }}
                                </span>
                                @if($detail?->invoice)
                                <span class="block font-mono text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 mt-1">
                                    Rp {{ number_format((float) $detail->invoice->total_amount, 0, ',', '.') }}
                                </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3.5 text-right">
                                <a href="{{ route('tasks.creq-billing.show', $task) }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all active:scale-95 shadow-2xs {{ $status === 'pending' ? 'bg-sky-600 hover:bg-sky-700 text-white border-transparent' : 'bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700' }}">
                                    <span>{{ $status === 'pending' ? 'Tinjau & Verifikasi' : 'Lihat Detail' }}</span>
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center text-slate-400">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Tidak ada antrean task C-REQ</p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        @if($search)
                                            Tidak ditemukan data yang cocok dengan kata kunci "{{ $search }}".
                                        @else
                                            Tidak ada task C-REQ berbayar pada filter status yang dipilih saat ini.
                                        @endif
                                    </p>
                                    @if($search || $statusFilter !== 'pending')
                                    <div class="pt-2">
                                        <a href="{{ route('tasks.creq-billing.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:underline">
                                            Lihat Antrean Pending &rarr;
                                        </a>
                                    </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($tasks->hasPages())
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-900/30">
            {{ $tasks->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Alpine Copy Helper --}}
<script>
function creqIndexView() {
    return {
        copyText(text, label) {
            if (!text) return;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    if (window.$toast) {
                        window.$toast.success(`${label} berhasil disalin: ${text}`);
                    }
                }).catch(() => {
                    this.fallbackCopy(text, label);
                });
            } else {
                this.fallbackCopy(text, label);
            }
        },
        fallbackCopy(text, label) {
            const temp = document.createElement('textarea');
            temp.value = text;
            temp.style.position = 'fixed';
            temp.style.left = '-9999px';
            document.body.appendChild(temp);
            temp.select();
            try {
                document.execCommand('copy');
                if (window.$toast) {
                    window.$toast.success(`${label} berhasil disalin: ${text}`);
                }
            } catch (e) {
                console.error('Copy failed', e);
            }
            document.body.removeChild(temp);
        }
    };
}
</script>
@endsection
