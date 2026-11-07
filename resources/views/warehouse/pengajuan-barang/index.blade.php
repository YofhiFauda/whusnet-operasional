@extends('layouts.app')

@section('title', 'Surat Pengajuan Barang - Whusnet Operasional')
@section('page_title', 'Surat Pengajuan Barang')

@section('content')

<x-warehouse.header active="pengajuan-barang" title="Surat Pengajuan Barang" subtitle="Pengajuan barang yang belum ada di gudang — perlu persetujuan FOP sebelum diproses." />

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-5 mb-6 shadow-xs">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <form method="GET" action="{{ route('warehouse.pengajuan-barang.index') }}" class="flex items-center gap-2">
            <select name="status" onchange="this.form.submit()" class="px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="open" {{ $statusFilter === 'open' ? 'selected' : '' }}>Menunggu Persetujuan FOP</option>
                <option value="disetujui" {{ $statusFilter === 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                <option value="ditolak" {{ $statusFilter === 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Semua Status</option>
            </select>
        </form>

        @if(auth()->user()->hasPermission('warehouse_pengajuan_barang.create'))
        <a href="{{ route('warehouse.pengajuan-barang.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span>Ajukan Barang</span>
        </a>
        @endif
    </div>
</div>

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
    @if($pengajuans->isEmpty())
    <div class="p-16 text-center">
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Gak ada pengajuan di sini</h4>
        <p class="text-xs text-slate-400 mt-1">Coba ganti filter status.</p>
    </div>
    @else
    <div class="overflow-x-auto scroll-smooth">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">No. Request</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Kegiatan</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">OLT/Cabang</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pengaju</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Diajukan</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($pengajuans as $pengajuan)
                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 cursor-pointer" onclick="window.location='{{ route('warehouse.pengajuan-barang.show', $pengajuan) }}'">
                    <td class="px-6 py-3.5 text-sm font-mono font-semibold text-sky-700 dark:text-sky-400">{{ $pengajuan->nomor_request }}</td>
                    <td class="px-6 py-3.5 text-sm text-slate-700 dark:text-slate-300">{{ $pengajuan->nama_kegiatan }}</td>
                    <td class="px-6 py-3.5 text-sm text-slate-700 dark:text-slate-300">{{ $pengajuan->pop->name }}</td>
                    <td class="px-6 py-3.5 text-sm text-slate-500 dark:text-slate-400">{{ $pengajuan->pengaju->name }}</td>
                    <td class="px-6 py-3.5">
                        <x-warehouse.status-badge :status="$pengajuan->status" />
                    </td>
                    <td class="px-6 py-3.5 text-xs text-slate-400">{{ $pengajuan->created_at->translatedFormat('d M Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40">
        {{ $pengajuans->links() }}
    </div>
    @endif
</div>

@endsection
