@extends('layouts.app')

@section('title', 'PIC Gudang per Cabang - Whusnet Operasional')
@section('page_title', 'PIC Gudang per Cabang')

@section('content')

{{--
    Kelola PIC Gudang per Cabang (ADHOC-120) — halaman TERPISAH dari Edit
    User (keputusan §15.2 no. 6 docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md).
    Penunjukan di sini TIDAK sama dengan scope POP user: scope menjawab "data
    mana yang boleh dia lihat", tabel warehouse_pop_pics (halaman ini)
    menjawab "gudang cabang mana dia jadi penanggung jawab" — satu teknisi
    bisa saja scope-nya Seluruh POP (keliling) tapi PIC cuma di 1 cabang.
--}}
<x-warehouse.header active="dashboard" title="PIC Gudang per Cabang" subtitle="Penunjukan PIC Gudang TERPISAH dari scope POP user — lihat docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md §5.3." />

@if(session('error'))
<div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-sm text-rose-700 dark:text-rose-300">
    {{ session('error') }}
</div>
@endif
@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-700 dark:text-emerald-300">
    {{ session('success') }}
</div>
@endif

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-5 mb-6 shadow-xs">
    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-3">Tunjuk PIC Gudang Baru</h3>
    <form method="POST" action="{{ route('warehouse.pic-gudang.store') }}" class="flex flex-col sm:flex-row gap-3">
        @csrf
        <select name="pop_id" required class="flex-1 px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
            <option value="">— Pilih Cabang —</option>
            @foreach($cabangPops as $pop)
                <option value="{{ $pop->id }}" {{ old('pop_id') == $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
            @endforeach
        </select>
        <select name="user_id" required class="flex-1 px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
            <option value="">— Pilih User (role Teknisi PIC Gudang) —</option>
            @foreach($picCandidates as $candidate)
                <option value="{{ $candidate->id }}" {{ old('user_id') == $candidate->id ? 'selected' : '' }}>{{ $candidate->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors">
            Tunjuk PIC
        </button>
    </form>
    @if($picCandidates->isEmpty())
    <p class="mt-2 text-[11px] text-slate-400">Belum ada user dengan role "Teknisi PIC Gudang" yang aktif — buat/ubah role user dulu lewat Manajemen User.</p>
    @endif
</div>

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
        <thead class="bg-slate-50 dark:bg-slate-900/40">
            <tr>
                <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Cabang</th>
                <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">PIC Gudang Aktif</th>
                <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
            @forelse($cabangPops as $pop)
            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30">
                <td class="px-6 py-3.5 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $pop->name }}</td>
                <td class="px-6 py-3.5 text-sm text-slate-600 dark:text-slate-300">
                    @forelse($pop->gudangPics as $pic)
                        <span class="inline-flex items-center gap-1.5 mr-2 mb-1 px-2 py-1 rounded bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 text-xs font-medium">
                            {{ $pic->name }}
                            <form method="POST" action="{{ route('warehouse.pic-gudang.destroy', $pic->pivot->id) }}" onsubmit="return confirm('Cabut penunjukan PIC Gudang {{ $pic->name }} untuk {{ $pop->name }}?')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sky-500 hover:text-rose-600" title="Cabut penunjukan">&times;</button>
                            </form>
                        </span>
                    @empty
                        <span class="text-slate-400 text-xs italic">Belum ada PIC — gudang diurus POP Admin seperti biasa.</span>
                    @endforelse
                </td>
                <td class="px-6 py-3.5 text-right"></td>
            </tr>
            @empty
            <tr><td colspan="3" class="px-6 py-10 text-center text-sm text-slate-400">Belum ada Gudang Cabang.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
