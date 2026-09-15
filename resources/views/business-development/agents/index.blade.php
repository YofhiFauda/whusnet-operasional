@extends('layouts.app')

@section('title', 'Master Agent - Whusnet Operasional')
@section('page_title', 'Master Agent')

@section('content')
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 mb-6 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <form method="GET" class="flex-1 max-w-md">
        <input type="text" name="search" value="{{ $search }}" placeholder="Cari kode/nama Agent..."
               class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
    </form>
    @can('agents.create')
    <a href="{{ route('business-development.agents.create') }}" class="px-4 py-2 text-sm font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition-colors shrink-0">
        + Tambah Agent
    </a>
    @endcan
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700/60 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                    <th class="px-4 py-2.5">Kode</th>
                    <th class="px-4 py-2.5">Nama</th>
                    <th class="px-4 py-2.5">Telepon</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($agents as $agent)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                        <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ $agent->code }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $agent->name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $agent->phone ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($agent->is_active)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">Aktif</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-700/60 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('agents.update')
                            <a href="{{ route('business-development.agents.edit', $agent) }}" class="text-sky-600 hover:underline">Ubah</a>
                            <form action="{{ route('business-development.agents.toggle', $agent) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-amber-600 hover:underline">{{ $agent->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-400 dark:text-slate-500">Belum ada Agent.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $agents->links() }}</div>
</div>
@endsection
