@extends('layouts.app')

@section('title', 'Master Status Pelanggan - Whusnet Operasional')
@section('page_title', 'Master Status Pelanggan')

@section('content')
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Workflow Status Pelanggan WHUSNET</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Master status menjadi dasar alur registrasi, survey, instalasi, aktivasi, isolir, dan terminasi.</p>
        </div>
        <div class="flex items-center gap-6">
            <div class="text-center">
                <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">TOTAL STATUS</span>
                <span class="text-lg font-bold text-slate-800 dark:text-slate-200 data-text">{{ $statuses->count() }}</span>
            </div>
            <div class="h-8 w-px bg-slate-200 dark:bg-slate-700"></div>
            <div class="text-center">
                <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">TERMINAL</span>
                <span class="text-lg font-bold text-slate-800 dark:text-slate-200 data-text">{{ $terminalStatuses }}</span>
            </div>
        </div>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Urutan Workflow</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Alur utama dibaca dari kiri ke kanan sesuai `workflow_order`.</p>
        </div>
    </div>

    <div class="overflow-x-auto pb-2">
        <div class="flex items-stretch min-w-max">
            @foreach($statuses as $status)
                <div class="relative flex items-center">
                    <div class="w-44 h-full border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/45 dark:bg-slate-800/50">
                        <div class="flex items-center justify-between mb-3">
                            <span class="h-7 w-7 rounded-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-xs font-bold text-slate-700 dark:text-slate-300 data-text">
                                {{ $status->workflow_order }}
                            </span>
                            @if($status->is_terminal)
                                <span class="text-[10px] font-semibold text-red-600 dark:text-red-400">Terminal</span>
                            @else
                                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500">Proses</span>
                            @endif
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold border {{ $status->badgeClasses() }}">
                            {{ $status->name }}
                        </span>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 leading-relaxed mt-3">{{ $status->description }}</p>
                    </div>
                    @if(!$loop->last)
                        <div class="w-8 h-px bg-slate-300 dark:bg-slate-600"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-between">
        <div>
            <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Daftar Status</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Kode status dipakai oleh data pelanggan dan workflow aplikasi.</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-slate-700 dark:text-slate-300">
            <thead>
                <tr class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-semibold text-xs">
                    <th class="px-6 py-3.5 w-20 text-center">URUTAN</th>
                    <th class="px-6 py-3.5">STATUS</th>
                    <th class="px-6 py-3.5">KODE</th>
                    <th class="px-6 py-3.5">DESKRIPSI</th>
                    <th class="px-6 py-3.5 text-center">TIPE</th>
                    <th class="px-6 py-3.5 text-right">PELANGGAN</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($statuses as $status)
                    <tr class="hover:bg-slate-50/45 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-6 py-3.5 text-center font-mono data-text">{{ $status->workflow_order }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border {{ $status->badgeClasses() }}">
                                {{ $status->name }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-mono text-xs data-text">{{ $status->code }}</td>
                        <td class="px-6 py-3.5 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $status->description }}</td>
                        <td class="px-6 py-3.5 text-center">
                            @if($status->is_terminal)
                                <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-100 dark:border-red-800/30">Terminal</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 border border-slate-100 dark:border-slate-700/50">Workflow</span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono data-text">{{ $status->customers_count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
