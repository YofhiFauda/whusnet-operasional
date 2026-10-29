@extends('layouts.app')

@section('title', 'Peta Infrastruktur ISP — Barang Terpasang')
@section('page_title', 'Peta Infrastruktur ISP')

@section('content')
{{--
    Tab ini DULU numpang di index.blade.php bareng kanvas Topologi: satu `x-data` Alpine
    raksasa (puluhan method, object literal ratusan baris) + ratusan directive di markup
    kanvas/drawer dipaksa ikut dirender & di-walk Alpine SETIAP load tab ini, walau
    semuanya disembunyikan (x-show) & payloadnya dikosongkan. DOM-size/network transfer
    sudah kecil (lihat investigasi Lighthouse ADHOC), tapi TBT tetap ~5-6 dtk karena cost
    ITU — beban Alpine parse+bind, bukan data. View ini dipisah TOTAL: tanpa x-data, tanpa
    kanvas, tanpa drawer, tanpa kotak cari (butuh canvas buat fokus hasil) — murni
    form GET + tabel server-rendered. Jangan disatukan balik ke index.blade.php.
--}}
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
<style>
    .isp-infra { font-family: 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif; }
    .isp-infra .font-display { font-family: 'IBM Plex Sans Condensed', sans-serif; }
    .isp-infra .font-mono { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
</style>

@php
    $tabUrl = fn (string $tab): string => route('network.map.index', array_filter(['pop_id' => $selectedPopId, 'sort' => $sort, 'tab' => $tab]));
@endphp

<div class="isp-infra relative flex h-full w-full flex-col bg-[#f8fafc] text-slate-900 dark:bg-[#0c1319] dark:text-slate-100 overflow-auto">
    <!-- TOP BAR: BRAND, TAB SWITCHER, CABANG FILTER -->
    <div class="sticky top-0 z-10 flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white/95 px-4 py-2.5 backdrop-blur-md dark:border-slate-800 dark:bg-[#131d24]/95">
        <div class="flex items-center gap-2">
            <span class="inline-flex size-2 rounded-full bg-emerald-500"></span>
            <h1 class="font-display text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-slate-100">Peta Infrastruktur ISP</h1>
        </div>

        <div role="tablist" aria-label="Tampilan Utama" class="flex items-center rounded-xl border border-slate-200/80 bg-slate-50 p-1 dark:border-slate-800 dark:bg-slate-900/60">
            <a href="{{ $tabUrl('topologi') }}" role="tab" aria-selected="false"
               class="font-display inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                Topologi
            </a>
            <a href="{{ $tabUrl('pohon') }}" role="tab" aria-selected="false"
               class="font-display inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                Per POP (Pohon)
            </a>
            <a href="{{ $tabUrl('inventaris') }}" role="tab" aria-selected="false"
               class="font-display inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                Inventaris
            </a>
            <a href="{{ $tabUrl('terpasang') }}" role="tab" aria-selected="true"
               class="font-display inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-2.5 py-1 text-xs font-bold text-white shadow-xs">
                Barang Terpasang
            </a>
        </div>

        <form method="GET" action="{{ route('network.map.index') }}" class="flex items-center gap-1.5">
            <input type="hidden" name="tab" value="terpasang">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <label for="map-pop-terpasang" class="sr-only">Cabang</label>
            <select id="map-pop-terpasang" name="pop_id" onchange="this.form.submit()"
                    class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <option value="">Semua Cabang</option>
                @foreach($cabangs as $cabang)
                    <option value="{{ $cabang->id }}" @selected($selectedPopId === $cabang->id)>{{ $cabang->name }}</option>
                @endforeach
            </select>
        </form>

        <span class="font-mono ml-auto text-[11px] text-slate-500 dark:text-slate-400">{{ $assetTotalRows }} baris aset</span>
    </div>

    <!-- TAB: BARANG TERPASANG (HISTORI BARANG GUDANG PER SUBTREE) -->
    <section class="flex-1 px-5 pb-5 pt-5 overflow-auto custom-scroll">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-[#131d24]">
            <!-- FILTER CASCADING: POP -> MINI POP -> OLT -> SLOT -> PON -> ODP -->
            <div class="border-b border-slate-200 bg-slate-50/70 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/40">
                <h2 class="font-display mb-0.5 text-sm font-bold text-slate-900 dark:text-slate-100">Barang Terpasang</h2>
                <p class="mb-2.5 text-[11px] text-slate-500">Default "Semua" — tampil seluruh barang dalam scope Anda. Pilih titik di hierarki untuk mempersempit ke naungannya saja.</p>
                <form method="GET" action="{{ route('network.map.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="terpasang">
                    @if($selectedPopId)<input type="hidden" name="pop_id" value="{{ $selectedPopId }}">@endif
                    <input type="hidden" name="sort" value="{{ $sort }}">

                    <select name="asset_pop_id" onchange="this.form.submit()" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Semua POP</option>
                        @foreach($cabangs as $cabang)
                            <option value="{{ $cabang->id }}" @selected($assetSelected['pop_id'] === $cabang->id)>{{ $cabang->name }}</option>
                        @endforeach
                    </select>

                    <select name="asset_mini_id" onchange="this.form.submit()" @disabled($assetMiniPops->isEmpty()) class="rounded border border-slate-300 bg-white px-2 py-1 text-xs disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Semua Mini POP</option>
                        @foreach($assetMiniPops as $mini)
                            <option value="{{ $mini->id }}" @selected($assetSelected['mini_id'] === $mini->id)>{{ $mini->name }}</option>
                        @endforeach
                    </select>

                    <select name="asset_olt_id" onchange="this.form.submit()" @disabled($assetOltOptions->isEmpty()) class="rounded border border-slate-300 bg-white px-2 py-1 text-xs disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Semua OLT</option>
                        @foreach($assetOltOptions as $olt)
                            <option value="{{ $olt->id }}" @selected($assetSelected['olt_id'] === $olt->id)>{{ $olt->code }}</option>
                        @endforeach
                    </select>

                    <select name="asset_slot_id" onchange="this.form.submit()" @disabled($assetSlotOptions->isEmpty()) class="rounded border border-slate-300 bg-white px-2 py-1 text-xs disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Semua Slot</option>
                        @foreach($assetSlotOptions as $slot)
                            <option value="{{ $slot->id }}" @selected($assetSelected['slot_id'] === $slot->id)>{{ $slot->code }}</option>
                        @endforeach
                    </select>

                    <select name="asset_pon_id" onchange="this.form.submit()" @disabled($assetPonOptions->isEmpty()) class="rounded border border-slate-300 bg-white px-2 py-1 text-xs disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Semua PON</option>
                        @foreach($assetPonOptions as $pon)
                            <option value="{{ $pon->id }}" @selected($assetSelected['pon_id'] === $pon->id)>{{ $pon->code }}</option>
                        @endforeach
                    </select>

                    <select name="asset_odp_id" onchange="this.form.submit()" @disabled($assetOdpOptions->isEmpty()) class="rounded border border-slate-300 bg-white px-2 py-1 text-xs disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Semua ODP</option>
                        @foreach($assetOdpOptions as $odp)
                            <option value="{{ $odp->id }}" @selected($assetSelected['odp_id'] === $odp->id)>{{ $odp->code }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <!-- TABLE -->
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-xs">
                    <thead class="font-display bg-slate-100/80 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:bg-slate-800/80 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3">Barang</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">SN</th>
                            <th class="px-4 py-3">Jumlah</th>
                            <th class="px-4 py-3">Posisi</th>
                            <th class="px-4 py-3">Tanggal Pasang</th>
                            <th class="px-4 py-3">Teknisi</th>
                            <th class="px-4 py-3">Task</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($assetRows as $row)
                            <tr data-asset-row class="hover:bg-sky-50/70 dark:hover:bg-sky-950/30">
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ $row['name'] ?? 'Barang tanpa nama' }}</td>
                                <td class="px-4 py-3">{{ $row['kind'] }}</td>
                                <td class="font-mono px-4 py-3">{{ $row['reference'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $row['amount'] ?? '—' }}</td>
                                <td class="max-w-[260px] px-4 py-3 truncate font-mono text-[11px] text-slate-600 dark:text-slate-300">{{ $row['path'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $row['installed_at'] ?? 'belum tercatat' }}</td>
                                <td class="px-4 py-3">{{ $row['technician'] ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($row['trace_url'] ?? null)
                                        <a href="{{ $row['trace_url'] }}" class="text-sky-700 hover:underline dark:text-sky-400">{{ $row['task'] ?? 'Riwayat Gudang' }}</a>
                                    @else
                                        {{ $row['task'] ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-xs text-slate-500">Belum ada barang gudang yang tercatat terpasang.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{--
                Paginasi WAJIB: scope besar (subtree Jetis ±6000 baris, "Semua" ±12000
                baris) kalau dirender sekaligus = DOM raksasa, itu sumber tab browser
                1-1,6GB & Lighthouse ambruk — gak nyambung ke Alpine lagi (tab ini sudah
                0 Alpine). Jangan hapus balik ke render semua baris.
            --}}
            @if($assetTotalRows > 0)
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-2.5 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <span>
                        Baris {{ (($assetPage - 1) * $assetPerPage) + 1 }}–{{ min($assetPage * $assetPerPage, $assetTotalRows) }}
                        dari {{ $assetTotalRows }}
                        (hal. {{ $assetPage }}/{{ $assetLastPage }})
                    </span>
                    <div class="flex items-center gap-1.5">
                        @if($assetPage > 1)
                            <a href="{{ request()->fullUrlWithQuery(['asset_page' => $assetPage - 1]) }}"
                               class="rounded border border-slate-300 px-2 py-1 font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">← Sebelumnya</a>
                        @endif
                        @if($assetPage < $assetLastPage)
                            <a href="{{ request()->fullUrlWithQuery(['asset_page' => $assetPage + 1]) }}"
                               class="rounded border border-slate-300 px-2 py-1 font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Selanjutnya →</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
