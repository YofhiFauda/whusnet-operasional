@extends('layouts.app')

@section('title', 'Verifikasi Biaya C-REQ - Whusnet Operasional')
@section('page_title', 'Verifikasi Biaya C-REQ')

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Task C-REQ Berbayar</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-3xl">
                Task C-REQ yang ditandai <span class="font-semibold text-slate-600 dark:text-slate-300">"Task ini berbayar"</span> oleh teknisi di Laporan C-REQ. Setujui untuk melanjutkan ke Tagihan Manual, atau tolak kalau biayanya tidak valid.
            </p>
        </div>
        <div class="text-center shrink-0">
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">DITAMPILKAN</span>
            <span class="text-lg font-bold text-slate-800 dark:text-slate-200 data-text">{{ $tasks->total() }}</span>
        </div>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-4 mb-4">
    <form method="GET" action="{{ route('tasks.creq-billing.index') }}" class="flex items-center gap-2">
        <select name="status" onchange="this.form.submit()"
                class="text-xs font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
            @foreach(\App\Enums\CReqVerificationStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700/60 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                    <th class="px-4 py-2.5">No.</th>
                    <th class="px-4 py-2.5">Task</th>
                    <th class="px-4 py-2.5">Pelanggan</th>
                    <th class="px-4 py-2.5">POP/Cabang</th>
                    <th class="px-4 py-2.5">Kategori</th>
                    <th class="px-4 py-2.5">Selesai</th>
                    <th class="px-4 py-2.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($tasks as $i => $task)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $tasks->firstItem() + $i }}</td>
                        <td class="px-4 py-3 font-mono text-slate-500 dark:text-slate-400">{{ $task->task_number }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ $task->customer?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $task->pop?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $task->creqDetail?->category?->label() ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $task->completed_at ? \App\Support\IndonesianDate::dateTime($task->completed_at) : '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('tasks.creq-billing.show', $task) }}" class="px-2.5 py-1 text-[11px] font-semibold rounded-lg border border-sky-200 dark:border-sky-800/60 text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20 transition-colors">
                                Tinjau
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-400 dark:text-slate-500">
                            Tidak ada task C-REQ berbayar dengan status ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tasks->hasPages())
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700/60">
            {{ $tasks->links() }}
        </div>
    @endif
</div>
@endsection
