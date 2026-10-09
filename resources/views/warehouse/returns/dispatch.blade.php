@extends('layouts.app')

@section('title', 'Kirim Retur ke Pusat — Whusnet Operasional')
@section('page_title', 'Kirim Retur ke Pusat')

@section('content')

<x-warehouse.header active="returns" title="Kirim Retur ke Pusat" subtitle="Modem hasil retur yang sudah diterima Cabang dari teknisi/pelanggan (Tahap 1), menunggu dikirim ke Gudang Pusat untuk verifikasi final." />

<x-warehouse.returns-nav active="dispatch" />

@if(session('error'))
<div class="mb-4 p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300">
    {{ session('error') }}
</div>
@endif

<form method="GET" action="{{ route('warehouse.returns.dispatch.index') }}" class="mb-4 flex items-center gap-2">
    <select name="pop_id" onchange="this.form.submit()" class="text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200">
        <option value="">Semua Cabang Saya</option>
        @foreach($popOptions as $pop)
        <option value="{{ $pop->id }}" @selected((string) $popFilter === (string) $pop->id)>{{ $pop->name }}</option>
        @endforeach
    </select>
</form>

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">
    @if($serials->isEmpty())
    <div class="p-12 text-center text-sm text-slate-500 dark:text-slate-400">
        Tidak ada modem retur yang menunggu dikirim ke Pusat.
    </div>
    @else
    <form method="POST" action="{{ route('warehouse.returns.dispatch.store') }}">
        @csrf
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50/80 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-200/80 dark:border-slate-700/80 font-bold">
                    <tr>
                        <th class="px-4 py-3.5"><input type="checkbox" onclick="document.querySelectorAll('.dispatch-sn-check').forEach(c => c.checked = this.checked)"></th>
                        <th class="text-left px-4 py-3.5">SN & Model</th>
                        <th class="text-left px-4 py-3.5">Asal</th>
                        <th class="text-left px-4 py-3.5">Ada di Cabang</th>
                        <th class="text-left px-4 py-3.5">Kondisi Observasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @foreach($serials as $serial)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                        <td class="px-4 py-3.5"><input type="checkbox" name="serial_ids[]" value="{{ $serial->id }}" class="dispatch-sn-check"></td>
                        <td class="px-4 py-3.5">
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400">{{ $serial->serial_number }}</span>
                            <span class="text-slate-700 dark:text-slate-300 block">{{ $serial->item?->name ?? 'Model belum ditentukan' }}</span>
                        </td>
                        <td class="px-4 py-3.5"><x-warehouse.origin-badge :log="$serial->latestRetrievalLog" /></td>
                        <td class="px-4 py-3.5 font-semibold">{{ $serial->currentPop?->name ?? '-' }}</td>
                        <td class="px-4 py-3.5"><x-warehouse.condition-badge :condition="$serial->condition" :checked="false" /></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-3 flex-wrap">
            <label class="text-xs font-bold text-slate-700 dark:text-slate-200">Kirim ke Gudang Pusat:</label>
            <select name="pusat_id" required class="text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">
                <option value="">Pilih Pusat...</option>
                @foreach($pusatOptions as $pop)
                <option value="{{ $pop->id }}">{{ $pop->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="ml-auto inline-flex items-center gap-1.5 px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/25 transition-all">
                Kirim SN Terpilih
            </button>
        </div>
    </form>
    @endif
</div>

@endsection
