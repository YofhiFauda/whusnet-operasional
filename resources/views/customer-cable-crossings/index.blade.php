@extends('layouts.app')

@section('title', 'Kabel Melintang Jalan - Whusnet Operasional')
@section('page_title', 'Kabel Melintang Jalan')
@section('breadcrumb_parent', 'Pelanggan')
@section('breadcrumb_parent_url', route('customers.index'))

@section('content')
<div class="space-y-4">
    <div class="rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50/60 dark:bg-amber-950/30 p-4">
        <p class="text-sm text-amber-900 dark:text-amber-200 font-ui">
            Kabel ini melintang jalan saat pemasangan. Rute yang belum ditarik wajib ditindaklanjuti sebelum atau saat Deact. Penarikan tidak masuk stok gudang.
        </p>
    </div>

    <form method="GET" action="{{ route('customer-cable-crossings.index') }}" class="flex flex-wrap items-center gap-2">
        <label for="status" class="text-xs font-semibold text-slate-600 dark:text-slate-300 font-ui">Status</label>
        <select id="status" name="status" onchange="this.form.submit()"
                class="rounded-lg text-xs sm:text-sm font-ui bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 px-3 py-2 focus:ring-2 focus:ring-sky-500 outline-none">
            @foreach($filterOptions as $value => $label)
                <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    <div class="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 overflow-x-auto">
        <table class="min-w-full text-xs sm:text-sm font-ui">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-3 py-2 text-left">Pelanggan</th>
                    <th class="px-3 py-2 text-left">POP</th>
                    <th class="px-3 py-2 text-left">Jalan</th>
                    <th class="px-3 py-2 text-left">Rute</th>
                    <th class="px-3 py-2 text-left">Dicatat</th>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-left">Penyelesaian</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse($crossings as $crossing)
                <tr class="align-top">
                    <td class="px-3 py-2">
                        <a href="{{ route('customers.show', $crossing->customer) }}" class="font-semibold text-sky-700 dark:text-sky-400 hover:underline">{{ $crossing->customer->full_name }}</a>
                        <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $crossing->customer->customer_code }}</div>
                    </td>
                    <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $crossing->customer->pop?->name ?? '—' }}</td>
                    <td class="px-3 py-2">
                        <div class="text-slate-900 dark:text-slate-100">{{ $crossing->nama_jalan }}</div>
                        @if($crossing->jenis_jalan)<div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $crossing->jenis_jalan }}</div>@endif
                    </td>
                    <td class="px-3 py-2 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                        {{ $crossing->points->count() }} titik
                        @if($crossing->points->isNotEmpty())
                            <div>{{ $crossing->points->first()->latitude }}, {{ $crossing->points->first()->longitude }}</div>
                            <div>→ {{ $crossing->points->last()->latitude }}, {{ $crossing->points->last()->longitude }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ $crossing->dicatat_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="px-3 py-2">
                        @php
                            $badgeClass = match ($crossing->status) {
                                \App\Enums\CableCrossingStatus::TERPASANG => 'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/30',
                                \App\Enums\CableCrossingStatus::TIDAK_BISA_DITARIK => 'bg-rose-50 text-rose-700 border-rose-300 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/30',
                                default => 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/30',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded border text-[11px] font-semibold {{ $badgeClass }}">{{ $crossing->status->label() }}</span>
                    </td>
                    <td class="px-3 py-2 text-slate-600 dark:text-slate-400">
                        @if($crossing->deactTask)
                            <a href="{{ route('tasks.show', $crossing->deactTask) }}" class="text-sky-700 dark:text-sky-400 hover:underline">{{ $crossing->deactTask->task_number }}</a>
                            <div class="text-[11px]">{{ $crossing->diselesaikan_at?->format('d/m/Y H:i') }}</div>
                        @else
                            <span class="text-slate-400">Belum ada task Deact</span>
                        @endif
                        @if($crossing->alasan_tidak_ditarik)
                            <div class="mt-1 text-[11px] text-rose-700 dark:text-rose-300">Alasan: {{ $crossing->alasan_tidak_ditarik }}</div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-3 py-8 text-center text-slate-500 dark:text-slate-400">Tidak ada rute kabel melintang pada filter ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $crossings->links() }}</div>
</div>
@endsection
