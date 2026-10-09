@extends('layouts.app')

@section('title', 'Master Kategori Paket - Whusnet Operasional')
@section('page_title', 'Master Kategori Paket')

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Kategori Paket Internet</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-3xl">
                Kolom <span class="font-semibold text-slate-600 dark:text-slate-300">Validasi Biaya Instalasi</span> menentukan ROLE mana yang wajib mengisi "Biaya Instalasi" (modul Busdev, <code class="text-[10px]">/customer-acquisitions</code>) buat pelanggan di kategori ini — kosong berarti kategori itu tidak butuh validasi tambahan, alur tetap seperti biasa.
            </p>
        </div>
        @can('packages.update')
            <a href="{{ route('master.package-categories.create') }}" class="shrink-0 inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl text-white bg-sky-600 hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600 shadow-xs shadow-sky-600/20 transition-all cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah Kategori</span>
            </a>
        @endcan
    </div>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700/60 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                    <th class="px-5 py-2.5">Kategori</th>
                    <th class="px-5 py-2.5 text-center">Status</th>
                    <th class="px-5 py-2.5">Validasi Biaya Instalasi</th>
                    <th class="px-5 py-2.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($categories as $category)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                        <td class="px-5 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ $category->name }}</td>
                        <td class="px-5 py-3 text-center">
                            @if($category->is_active)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">Aktif</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-700/60 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-600 dark:text-slate-300">
                            @if($category->installationFeeApprovalRole)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60">{{ $category->installationFeeApprovalRole->name }}</span>
                            @else
                                <span class="text-slate-400 dark:text-slate-500">Tidak perlu</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @can('packages.update')
                                    <a href="{{ route('master.package-categories.edit', $category) }}" class="px-2.5 py-1 text-[11px] font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
                                        Edit
                                    </a>
                                    <form action="{{ route('master.package-categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('Hapus kategori \'{{ $category->name }}\'? Cuma bisa kalau belum dipakai paket manapun.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1 text-[11px] font-semibold rounded-lg border border-rose-200 dark:border-rose-800/60 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors cursor-pointer">
                                            Hapus
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-6 text-center text-slate-400 dark:text-slate-500">
                            Belum ada kategori paket.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<p class="mt-4 text-[11px] text-slate-400 dark:text-slate-500 max-w-3xl">
    Kategori yang masih dipakai paket internet aktif tidak bisa dihapus — nonaktifkan saja lewat Edit.
</p>
@endsection
