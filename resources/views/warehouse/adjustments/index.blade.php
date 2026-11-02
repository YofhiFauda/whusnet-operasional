@extends('layouts.app')

@section('title', 'Koreksi Stok - Whusnet Operasional')
@section('page_title', 'Koreksi Stok')

@section('content')

{{-- Pintu tunggal koreksi stok gudang. Pilihan berdasar SITUASI lapangan,
     bukan mekanisme (opname/delta/qty/SN) — jenis barang dideteksi di form. --}}
<x-warehouse.header active="stock" title="Koreksi Stok" subtitle="Pilih yang sedang terjadi di gudang — form menyesuaikan jenis barangnya sendiri." backUrl="{{ route('warehouse.stock.index') }}" />

<div class="grid max-w-4xl grid-cols-1 gap-4 sm:grid-cols-2">
    <a href="{{ route('warehouse.adjustments.check.create') }}"
       class="group rounded-lg border border-slate-200 bg-white p-6 shadow-xs transition-colors hover:border-indigo-400 hover:bg-indigo-50/40 dark:border-slate-700 dark:bg-slate-800/90 dark:hover:border-indigo-600 dark:hover:bg-indigo-950/30">
        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-lg border border-indigo-100 bg-indigo-50 text-indigo-600 dark:border-indigo-800/60 dark:bg-indigo-950/50 dark:text-indigo-400">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        </div>
        <h3 class="text-sm font-bold text-slate-800 group-hover:text-indigo-700 dark:text-slate-100 dark:group-hover:text-indigo-300">Cek &amp; cocokkan stok</h3>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Saya menghitung barang di gudang dan mau menyamakan dengan sistem.</p>
        <p class="mt-3 text-[11px] text-slate-400">Contoh: hitung rutin akhir bulan, cek modem di rak, ukur sisa roll kabel.</p>
    </a>

    <a href="{{ route('warehouse.adjustments.problem.create') }}"
       class="group rounded-lg border border-slate-200 bg-white p-6 shadow-xs transition-colors hover:border-amber-400 hover:bg-amber-50/40 dark:border-slate-700 dark:bg-slate-800/90 dark:hover:border-amber-600 dark:hover:bg-amber-950/30">
        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-lg border border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-800/60 dark:bg-amber-950/50 dark:text-amber-400">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <h3 class="text-sm font-bold text-slate-800 group-hover:text-amber-700 dark:text-slate-100 dark:group-hover:text-amber-300">Ada barang bermasalah</h3>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Barang hilang, rusak, dibuang, perlu dikarantina, atau salah catat.</p>
        <p class="mt-3 text-[11px] text-slate-400">Contoh: modem mati, konektor jatuh dan pecah, salah input jumlah terima.</p>
    </a>
</div>

@endsection
