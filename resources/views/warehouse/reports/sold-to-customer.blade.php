@extends('layouts.app')

@section('title', 'Laporan Barang Terjual ke Pelanggan - Whusnet Operasional')
@section('page_title', 'Laporan Barang Terjual ke Pelanggan')

@section('content')

<x-warehouse.header
    active="sold-to-customer"
    title="Laporan Barang Terjual ke Pelanggan"
    subtitle="Daftar unit SN yang dijual (Beli dari Gudang) ke pelanggan — dibedakan dari Sewa, data mentahnya dari pencatatan ownership modem sewa/beli."
    :back-url="route('warehouse.reports.index')"
/>

{{-- Filter periode/POP — bentuknya sama persis form di Laporan Gudang
     (index.blade.php), biar hasil di tab "Barang Terjual" dan halaman
     standalone ini selalu konsisten untuk filter yang sama (2026-10-12). --}}
<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-3.5 sm:p-4 shadow-xs">
    <form action="{{ route('warehouse.reports.sold-to-customer') }}" method="GET" class="space-y-3 sm:space-y-0 sm:flex sm:flex-wrap sm:items-end sm:gap-3">
        <div class="w-full sm:w-auto">
            <label for="period_input" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                Periode (Bulan)
            </label>
            <input type="month" name="period" id="period_input" value="{{ $period }}"
                   class="w-full sm:w-44 h-9 px-3 py-1.5 text-xs font-semibold border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/80 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
        </div>

        <div class="w-full sm:flex-1 sm:min-w-[200px] lg:max-w-xs">
            <label for="pop_id" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                Gudang / Cabang
            </label>
            <select name="pop_id" id="pop_id"
                    class="w-full h-9 px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/80 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">— Semua Gudang Terjangkau —</option>
                @foreach($pops as $pop)
                <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>
                    {{ $pop->name }} ({{ strtoupper($pop->type?->value ?? $pop->type) }})
                </option>
                @endforeach
            </select>
        </div>

        <div class="w-full sm:w-auto pt-1 sm:pt-0">
            <button type="submit"
                    class="h-9 w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer">
                <span>Terapkan</span>
            </button>
        </div>
    </form>
</div>

@include('warehouse.reports.partials._sold_to_customer_table')

@endsection
