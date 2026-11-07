@extends('layouts.app')

@section('title', $pengajuan->nomor_request.' - Whusnet Operasional')
@section('page_title', 'Surat Pengajuan Barang')

@section('content')

<x-warehouse.header active="pengajuan-barang" title="{{ $pengajuan->nomor_request }}" subtitle="{{ $pengajuan->nama_kegiatan }}" backUrl="{{ route('warehouse.pengajuan-barang.index') }}" />

@if(session('success'))
<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100">{{ session('error') }}</div>
@endif

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-6 mb-6 shadow-xs">
    <div class="flex items-start justify-between gap-4 mb-5">
        <div>
            <x-warehouse.status-badge :status="$pengajuan->status" />
        </div>
        <a href="{{ route('warehouse.pengajuan-barang.pdf', $pengajuan) }}" target="_blank"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg text-white bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 shadow-xs transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span>Lihat PDF</span>
        </a>
    </div>

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm mb-6">
        <div>
            <dt class="text-xs font-bold text-slate-500 dark:text-slate-400">No. Request</dt>
            <dd class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ $pengajuan->nomor_request }}</dd>
        </div>
        <div>
            <dt class="text-xs font-bold text-slate-500 dark:text-slate-400">Tanggal</dt>
            <dd class="text-slate-700 dark:text-slate-300">{{ \App\Support\IndonesianDate::date($pengajuan->created_at) }}</dd>
        </div>
        <div>
            <dt class="text-xs font-bold text-slate-500 dark:text-slate-400">Nama Pengaju</dt>
            <dd class="text-slate-700 dark:text-slate-300">{{ $pengajuan->pengaju->name }}</dd>
        </div>
        <div>
            <dt class="text-xs font-bold text-slate-500 dark:text-slate-400">OLT/Cabang/POP</dt>
            <dd class="text-slate-700 dark:text-slate-300">{{ $pengajuan->pop->name }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-xs font-bold text-slate-500 dark:text-slate-400">Nama Kegiatan</dt>
            <dd class="text-slate-700 dark:text-slate-300">{{ $pengajuan->nama_kegiatan }}</dd>
        </div>
    </dl>

    <div class="overflow-x-auto scroll-smooth border border-slate-200 dark:border-slate-700 rounded-lg mb-6">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">No.</th>
                    <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Barang</th>
                    <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Spesifikasi</th>
                    <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah</th>
                    <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Satuan</th>
                    <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Keterangan</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($pengajuan->items as $item)
                <tr>
                    <td class="px-4 py-2.5 text-sm text-slate-500">{{ $loop->iteration }}</td>
                    <td class="px-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $item->nama_barang }}</td>
                    <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-400">{{ $item->spesifikasi ?? '-' }}</td>
                    <td class="px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300">{{ rtrim(rtrim(number_format((float) $item->jumlah, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300">{{ $item->satuan }}</td>
                    <td class="px-4 py-2.5 text-sm">
                        @if($item->keterangan_link)
                        <a href="{{ $item->keterangan_link }}" target="_blank" rel="noopener" class="text-sky-600 hover:underline break-all">{{ $item->keterangan_link }}</a>
                        @else
                        <span class="text-slate-400">-</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Riwayat (keputusan FOP) --}}
    <div class="border-t border-slate-100 dark:border-slate-700/60 pt-5">
        <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-3">Riwayat Keputusan</h3>
        <ul class="space-y-2 text-sm">
            <li class="flex items-start gap-2">
                <span class="w-2 h-2 mt-1.5 rounded-full bg-sky-500 shrink-0"></span>
                <span class="text-slate-700 dark:text-slate-300">Diajukan oleh <strong>{{ $pengajuan->pengaju->name }}</strong> — {{ $pengajuan->created_at->translatedFormat('d M Y H:i') }}</span>
            </li>
            @if($pengajuan->decided_at)
            <li class="flex items-start gap-2">
                <span class="w-2 h-2 mt-1.5 rounded-full {{ $pengajuan->status->value === 'disetujui' ? 'bg-emerald-500' : 'bg-rose-500' }} shrink-0"></span>
                <span class="text-slate-700 dark:text-slate-300">
                    {{ $pengajuan->status->label() }} oleh <strong>{{ $pengajuan->decidedBy->name }}</strong> (FOP) — {{ $pengajuan->decided_at->translatedFormat('d M Y H:i') }}
                    @if($pengajuan->rejection_reason)
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Alasan: {{ $pengajuan->rejection_reason }}</div>
                    @endif
                </span>
            </li>
            @endif
        </ul>
    </div>

    @can('warehouse_pengajuan_barang.approve')
    @if((auth()->user()->hasFullAccess() || auth()->user()->is_pengajuan_barang_approver) && $pengajuan->status->value === 'menunggu_fop')
    <div class="border-t border-slate-100 dark:border-slate-700/60 pt-5 mt-5 flex items-center gap-3" x-data="{ showRejectForm: false }">
        <form action="{{ route('warehouse.pengajuan-barang.approve', $pengajuan) }}" method="POST">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition-colors">
                Setujui Pengajuan
            </button>
        </form>
        <button type="button" @click="showRejectForm = !showRejectForm" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold shadow-xs transition-colors">
            Tolak Pengajuan
        </button>

        <div x-show="showRejectForm" x-cloak class="w-full mt-3">
            <form action="{{ route('warehouse.pengajuan-barang.reject', $pengajuan) }}" method="POST" class="flex items-start gap-2">
                @csrf
                <textarea name="reason" required rows="2" placeholder="Alasan penolakan (wajib)"
                          class="flex-1 text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all"></textarea>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold shadow-xs transition-colors shrink-0">Kirim</button>
            </form>
        </div>
    </div>
    @endif
    @endcan
</div>

@endsection
