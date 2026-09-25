@extends('layouts.app')

@section('title', 'Master Alasan Putus Langganan - Whusnet Operasional')
@section('page_title', 'Master Alasan Putus Langganan')

@section('content')
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6 shadow-sm">
    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Master Alasan Putus Langganan</h3>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Klasifikasi alasan di dropdown form Putus Langganan — bisa difilter/sort di List Pelanggan Putus. Alasan yang masih dipakai minimal 1 pelanggan tidak bisa dihapus.</p>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 mb-6 shadow-sm">
    <form action="{{ route('master.termination-reasons.index') }}" method="GET" class="flex flex-col lg:flex-row items-end lg:items-center justify-between gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full lg:max-w-2xl">
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Pencarian</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Nama alasan..."
                       class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 bg-white dark:bg-slate-800">
                    <option value="">Semua Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full lg:w-auto shrink-0 justify-end">
            <button type="submit" class="flex-1 lg:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors focus:outline-none cursor-pointer">
                Filter
            </button>

            @if($search || $status)
            <a href="{{ route('master.termination-reasons.index') }}" class="inline-flex items-center justify-center p-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors focus:outline-none cursor-pointer" title="Reset Filters">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.28 15m-2.802-5.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                </svg>
            </a>
            @endif

            @if(auth()->user()->hasPermission('termination_reasons.create'))
            <a href="{{ route('master.termination-reasons.create') }}" class="flex-1 lg:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Alasan
            </a>
            @endif
        </div>
    </form>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-sm">
    @if($reasons->isEmpty())
    <div class="p-16 text-center">
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada Alasan Putus Langganan ditemukan</h4>
        <p class="text-xs text-slate-400 dark:text-slate-500 max-w-sm mx-auto mt-1">
            @if($search || $status)
            Silakan reset filter pencarian atau ubah parameter filter Anda.
            @else
            Tambahkan alasan dulu — form Putus Langganan butuh minimal satu alasan aktif.
            @endif
        </p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-16">No</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Alasan</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nilai Awal Denda</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Dipakai</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-32">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($reasons as $index => $reason)
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                        {{ $reasons->firstItem() + $index }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $reason->name }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-mono text-slate-600 dark:text-slate-400">{{ format_rupiah($reason->default_penalty_amount) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-mono text-slate-500 dark:text-slate-400">{{ number_format($reason->customers_count) }} pelanggan</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($reason->is_active)
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">Aktif</span>
                        @else
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">Nonaktif</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <div class="flex items-center justify-center gap-2">
                            @if(auth()->user()->hasPermission('termination_reasons.update'))
                            <a href="{{ route('master.termination-reasons.edit', $reason) }}" class="p-1 text-slate-400 dark:text-slate-500 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-900/20 rounded-md transition-colors" title="Ubah Alasan">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </a>

                            <form action="{{ route('master.termination-reasons.toggle', $reason) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                        class="p-1 text-slate-400 dark:text-slate-500 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-md transition-colors cursor-pointer"
                                        title="{{ $reason->is_active ? 'Nonaktifkan' : 'Aktifkan' }} Alasan"
                                        onclick="event.preventDefault(); window.confirmDelete('Apakah Anda yakin ingin {{ $reason->is_active ? 'menonaktifkan' : 'mengaktifkan' }} alasan {{ $reason->name }}?', this.closest('form'))">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </button>
                            </form>
                            @endif

                            @if(auth()->user()->hasPermission('termination_reasons.delete'))
                            <form action="{{ route('master.termination-reasons.destroy', $reason) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        {{ $reason->customers_count > 0 ? 'disabled' : '' }}
                                        class="p-1 rounded-md transition-colors {{ $reason->customers_count > 0 ? 'text-slate-300 dark:text-slate-700 cursor-not-allowed' : 'text-slate-400 dark:text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 cursor-pointer' }}"
                                        title="{{ $reason->customers_count > 0 ? 'Masih dipakai '.$reason->customers_count.' pelanggan, tidak bisa dihapus' : 'Hapus Alasan' }}"
                                        @if($reason->customers_count === 0)
                                        onclick="event.preventDefault(); window.confirmDelete('Hapus permanen alasan {{ $reason->name }}? Tindakan ini tidak bisa dibatalkan.', this.closest('form'))"
                                        @endif>
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($reasons->hasPages())
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/50 bg-slate-50/50">
        {{ $reasons->links() }}
    </div>
    @endif
    @endif
</div>
@endsection
