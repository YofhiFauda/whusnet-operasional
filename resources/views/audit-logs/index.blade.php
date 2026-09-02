@extends('layouts.app')

@section('title', 'Audit Log - Whusnet Operasional')
@section('page_title', 'Audit Log')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-slate-800 dark:text-slate-200 text-sm font-semibold uppercase tracking-wider">Riwayat Perubahan Penting</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Audit log mencatat perubahan data pelanggan, master, tagihan, pembayaran, user, role, dan data teknis.</p>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <form action="{{ route('audit-logs.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        <div class="sm:col-span-2">
            <label for="search" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">CARI LOG</label>
            <input type="text" name="search" id="search" value="{{ $search }}" placeholder="User, email, model, atau ID data..." class="w-full font-sans text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <div>
            <label for="module" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">MODUL</label>
            <select name="module" id="module" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Modul</option>
                @foreach($modules as $moduleName)
                    <option value="{{ $moduleName }}" @selected($module === $moduleName)>{{ $moduleName }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="action" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">AKSI</label>
            <select name="action" id="action" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Aksi</option>
                @foreach($actions as $actionName)
                    <option value="{{ $actionName }}" @selected($action === $actionName)>{{ ucwords(str_replace('_', ' ', $actionName)) }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 dark:hover:bg-sky-400 text-white text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500/25">
                Filter
            </button>
            <a href="{{ route('audit-logs.index') }}" class="bg-slate-100 dark:bg-slate-700/50 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none">
                Reset
            </a>
        </div>
    </form>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-slate-700 dark:text-slate-300">
            <thead>
                <tr class="">
                    <th class="px-6 py-3.5">WAKTU</th>
                    <th class="px-6 py-3.5">USER</th>
                    <th class="px-6 py-3.5">MODUL</th>
                    <th class="px-6 py-3.5">AKSI</th>
                    <th class="px-6 py-3.5">DATA</th>
                    <th class="px-6 py-3.5">PERUBAHAN</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                @forelse($auditLogs as $auditLog)
                    <tr class="align-top hover:bg-slate-50/45 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap data-text">
                            <div class="font-semibold text-slate-800 dark:text-slate-200">{{ optional($auditLog->created_at)->format('d/m/Y') }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ optional($auditLog->created_at)->format('H:i:s') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-semibold text-slate-900 dark:text-slate-100">{{ $auditLog->user->name ?? 'Sistem' }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $auditLog->user->email ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap font-semibold text-slate-800 dark:text-slate-200">{{ $auditLog->module }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-2.5 py-0.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {{ ucwords(str_replace('_', ' ', $auditLog->action)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-mono text-xs text-slate-700 dark:text-slate-300">{{ class_basename($auditLog->auditable_type) ?: '-' }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">ID: {{ $auditLog->auditable_id ?? '-' }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">IP: {{ $auditLog->ip_address ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-4 min-w-[24rem]">
                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-3">
                                <div>
                                    <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Sebelum</p>
                                    <pre class="max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-md border border-slate-100 dark:border-slate-700/50 bg-slate-50 dark:bg-slate-800/50 p-2 text-[10px] text-slate-600 dark:text-slate-400">{{ $auditLog->old_values ? json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '-' }}</pre>
                                </div>
                                <div>
                                    <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Sesudah</p>
                                    <pre class="max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-md border border-slate-100 dark:border-slate-700/50 bg-slate-50 dark:bg-slate-800/50 p-2 text-[10px] text-slate-600 dark:text-slate-400">{{ $auditLog->new_values ? json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '-' }}</pre>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada audit log yang sesuai filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/50">
        {{ $auditLogs->links() }}
    </div>
</div>
@endsection
