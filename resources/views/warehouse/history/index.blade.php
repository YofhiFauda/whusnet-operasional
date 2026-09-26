@extends('layouts.app')

@section('title', 'Riwayat Mutasi Gudang - Whusnet Operasional')
@section('page_title', 'Riwayat Mutasi Gudang')

@section('content')

<x-warehouse.header active="history" />

@php
    // Warna badge per tipe — dipakai dobel (chip legenda + badge kolom Tipe),
    // satu sumber biar gak ketinggalan pas nambah tipe baru. Sebelumnya
    // 'stock_opname' & 'transfer_custody' gak ke-cover di match() lama →
    // selalu jatuh ke badge abu-abu default (ketauan pas restyle 2026-09-07).
    $typeColor = [
        'receive' => ['bg' => 'bg-emerald-50 dark:bg-emerald-950/40', 'text' => 'text-emerald-700 dark:text-emerald-400', 'border' => 'border-emerald-200 dark:border-emerald-800', 'dot' => 'bg-emerald-500'],
        'transfer' => ['bg' => 'bg-sky-50 dark:bg-sky-950/40', 'text' => 'text-sky-700 dark:text-sky-400', 'border' => 'border-sky-200 dark:border-sky-800', 'dot' => 'bg-sky-500'],
        'issue' => ['bg' => 'bg-indigo-50 dark:bg-indigo-950/40', 'text' => 'text-indigo-700 dark:text-indigo-400', 'border' => 'border-indigo-200 dark:border-indigo-800', 'dot' => 'bg-indigo-500'],
        'return' => ['bg' => 'bg-teal-50 dark:bg-teal-950/40', 'text' => 'text-teal-700 dark:text-teal-400', 'border' => 'border-teal-200 dark:border-teal-800', 'dot' => 'bg-teal-500'],
        'adjustment' => ['bg' => 'bg-amber-50 dark:bg-amber-950/40', 'text' => 'text-amber-700 dark:text-amber-400', 'border' => 'border-amber-200 dark:border-amber-800', 'dot' => 'bg-amber-500'],
        'stock_opname' => ['bg' => 'bg-amber-50 dark:bg-amber-950/40', 'text' => 'text-amber-700 dark:text-amber-400', 'border' => 'border-amber-200 dark:border-amber-800', 'dot' => 'bg-amber-500'],
        'transfer_custody' => ['bg' => 'bg-fuchsia-50 dark:bg-fuchsia-950/40', 'text' => 'text-fuchsia-700 dark:text-fuchsia-400', 'border' => 'border-fuchsia-200 dark:border-fuchsia-800', 'dot' => 'bg-fuchsia-500'],
        'install' => ['bg' => 'bg-violet-50 dark:bg-violet-950/40', 'text' => 'text-violet-700 dark:text-violet-400', 'border' => 'border-violet-200 dark:border-violet-800', 'dot' => 'bg-violet-500'],
    ];
    $defaultColor = ['bg' => 'bg-slate-100 dark:bg-slate-700', 'text' => 'text-slate-700 dark:text-slate-300', 'border' => 'border-slate-200 dark:border-slate-600', 'dot' => 'bg-slate-400'];
@endphp

<!-- Filter & Search Toolbar (Naked Filter Bar) -->
<div class="mb-5"
     x-data="{
         currentType: '{{ $typeFilter }}',
         filterByType(t) {
             this.currentType = t;
             $refs.typeInput.value = t;
             $refs.filterForm.submit();
         }
     }">
    <form x-ref="filterForm" action="{{ route('warehouse.history.index') }}" method="GET" class="space-y-3">
        <input type="hidden" name="type" x-ref="typeInput" value="{{ $typeFilter }}">

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <!-- Search Input -->
            <div class="md:col-span-4">
                <label for="search" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Cari Barang / SN / No Ref</label>
                <div class="relative">
                    <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Ketik SN / SKU / Ref..."
                           class="w-full pl-9 pr-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- POP Dropdown -->
            <div class="md:col-span-3">
                <label for="pop_id" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Gudang POP</label>
                <select name="pop_id" id="pop_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="">— Semua Gudang —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>
                        {{ $pop->name }} ({{ strtoupper($pop->type) }})
                    </option>
                    @endforeach
                </select>
            </div>

            <!-- Dari Tanggal -->
            <div class="md:col-span-2">
                <label for="date_from" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Dari Tanggal</label>
                <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
            </div>

            <!-- Sampai Tanggal -->
            <div class="md:col-span-2">
                <label for="date_to" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Sampai Tanggal</label>
                <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
            </div>

            <!-- Filter Kondisi (SERIALIZED, analisa-gap-kondisi-barang.md poin 6) -->
            <div class="md:col-span-2">
                <label for="condition" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Kondisi</label>
                <select name="condition" id="condition" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="">— Semua Kondisi —</option>
                    <option value="new" {{ $conditionFilter === 'new' ? 'selected' : '' }}>Baru</option>
                    <option value="unchecked" {{ $conditionFilter === 'unchecked' ? 'selected' : '' }}>Bekas — Belum Dicek</option>
                    <option value="checked_good" {{ $conditionFilter === 'checked_good' ? 'selected' : '' }}>Bekas — Sudah Dicek</option>
                    <option value="damaged" {{ $conditionFilter === 'damaged' ? 'selected' : '' }}>Bekas — Rusak</option>
                </select>
            </div>

            <!-- Filter Alasan (cuma relevan buat type=adjustment, poin 7) -->
            <div class="md:col-span-2" x-show="currentType === 'adjustment'" x-cloak>
                <label for="adjustment_reason" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Alasan</label>
                <select name="adjustment_reason" id="adjustment_reason" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="">— Semua Alasan —</option>
                    <option value="lost" {{ $adjustmentReasonFilter === 'lost' ? 'selected' : '' }}>Hilang</option>
                    <option value="damaged" {{ $adjustmentReasonFilter === 'damaged' ? 'selected' : '' }}>Rusak</option>
                    <option value="scrapped" {{ $adjustmentReasonFilter === 'scrapped' ? 'selected' : '' }}>Scrap</option>
                    <option value="quarantine" {{ $adjustmentReasonFilter === 'quarantine' ? 'selected' : '' }}>Karantina</option>
                    <option value="shrinkage_on_return" {{ $adjustmentReasonFilter === 'shrinkage_on_return' ? 'selected' : '' }}>Selisih Saat Return</option>
                    <option value="other" {{ $adjustmentReasonFilter === 'other' ? 'selected' : '' }}>Lainnya / Tidak Diketahui</option>
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="md:col-span-1 flex items-center gap-2 justify-end">
                <button type="submit" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer" title="Terapkan Filter">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    <span>Filter</span>
                </button>

                @if($typeFilter || $popFilter || $search || $dateFrom || $dateTo || $conditionFilter || $adjustmentReasonFilter)
                <a href="{{ route('warehouse.history.index') }}" class="inline-flex items-center justify-center p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors shrink-0" title="Reset Filter">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                </a>
                @endif
            </div>
        </div>

        <!-- Legenda & Filter Tipe Cepat (rancangan-layout.md §4.4) -->
        <div class="flex flex-wrap items-center gap-1.5 pt-2.5 border-t border-slate-100 dark:border-slate-700/60">
            <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mr-1">Filter Tipe:</span>
            <button type="button" @click="filterByType('')"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border transition-all cursor-pointer"
                :class="currentType === '' ? 'bg-slate-800 dark:bg-slate-600 text-white border-slate-800 dark:border-slate-600 shadow-xs' : 'bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700/60'">
                Semua Tipe
            </button>
            @foreach($types as $t)
            @php $c = $typeColor[$t->value] ?? $defaultColor; @endphp
            <button type="button" @click="filterByType('{{ $t->value }}')"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border transition-all cursor-pointer {{ $c['bg'] }} {{ $c['text'] }} {{ $c['border'] }}"
                :class="currentType === '{{ $t->value }}' ? 'ring-2 ring-offset-1.5 dark:ring-offset-slate-800 ring-slate-400 shadow-xs' : 'opacity-85 hover:opacity-100'">
                <span class="w-1.5 h-1.5 rounded-full {{ $c['dot'] }}"></span>
                <span>{{ $t->label() }}</span>
            </button>
            @endforeach
        </div>
    </form>
</div>

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
    @if($ledger->isEmpty())
    <div class="p-16 text-center">
        <p class="text-sm font-bold text-slate-700 dark:text-slate-300">Gak ada mutasi yang cocok filter ini.</p>
    </div>
    @else
    <div class="overflow-x-auto scroll-smooth">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Waktu</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tipe</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / SN</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Asal &amp; Tujuan</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aktor &amp; Bukti</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($ledger as $group)
                @php
                    $txn = $group->representative;
                    $rowColor = $typeColor[$txn->type->value ?? ''] ?? $defaultColor;

                    // Satu dokumen (Input/Transfer/Serah Terima) bisa punya
                    // puluhan baris ledger — dikelompokkan jadi 1 kartu di
                    // sini, kliknya masuk ke halaman detail yang SUDAH nampilin
                    // rincian per barang (lihat docblock WarehouseHistoryController).
                    $detailRoute = match($txn->type->value ?? '') {
                        'receive' => auth()->user()->hasPermission('warehouse_transfer.view') && $txn->reference_number
                            ? route('warehouse.receive.show', $txn->reference_number) : null,
                        'transfer' => auth()->user()->hasPermission('warehouse_transfer.view') && $txn->inventory_transfer_id
                            ? route('warehouse.transfers.show', $txn->inventory_transfer_id) : null,
                        'issue' => auth()->user()->hasPermission('warehouse_issue.view') && $txn->reference_number
                            ? route('warehouse.issues.show', $txn->reference_number) : null,
                        default => null,
                    };
                @endphp
                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors {{ $detailRoute ? 'cursor-pointer' : '' }}" @if($detailRoute) onclick="window.location='{{ $detailRoute }}'" @endif>
                    <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $group->createdAt->translatedFormat('d M Y') }}</span>
                        <span class="text-slate-400">{{ $group->createdAt->format('H:i') }}</span>
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $rowColor['bg'] }} {{ $rowColor['text'] }} {{ $rowColor['border'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $rowColor['dot'] }}"></span>
                            {{ $group->typeLabel }}
                        </span>
                    </td>
                    <td class="px-6 py-3.5">
                        @if($group->lineCount > 1)
                        {{-- Digabung 1 kartu (ADHOC 2026-09-16) — klik buat lihat
                             rincian tiap barang di halaman dokumennya. --}}
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                            @if($detailRoute)
                            <a href="{{ $detailRoute }}" class="hover:underline hover:text-sky-600 dark:hover:text-sky-400">{{ $group->itemCount }} Jenis Barang</a>
                            @else
                            {{ $group->itemCount }} Jenis Barang
                            @endif
                        </div>
                        <div class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                            {{ $group->lines->pluck('item.name')->unique()->take(2)->implode(', ') }}{{ $group->itemCount > 2 ? ', +'.($group->itemCount - 2).' lainnya' : '' }}
                        </div>
                        @else
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                            @if($detailRoute)
                            <a href="{{ $detailRoute }}" class="hover:underline hover:text-sky-600 dark:hover:text-sky-400">{{ $txn->item->name }}</a>
                            @else
                            {{ $txn->item->name }}
                            @endif
                        </div>
                        @if($txn->serial)
                        <div class="text-xs font-mono text-sky-600 dark:text-sky-400 mt-0.5">
                            @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                            <a href="{{ route('warehouse.traceability.index', ['sn' => $txn->serial->serial_number]) }}" onclick="event.stopPropagation()" class="hover:underline">SN: {{ $txn->serial->serial_number }}</a>
                            @else
                            <span>SN: {{ $txn->serial->serial_number }}</span>
                            @endif
                        </div>
                        @php
                            $serialConditionVal = $txn->serial?->condition?->value ?? 'new';
                            $conditionBadge = match(true) {
                                $serialConditionVal === 'new' => ['label' => 'Baru', 'class' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'],
                                $serialConditionVal === 'used_damaged' => ['label' => 'Bekas — Rusak', 'class' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800'],
                                $txn->serial?->condition_checked_at !== null => ['label' => 'Bekas — Sudah Dicek', 'class' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800'],
                                default => ['label' => 'Bekas — Belum Dicek', 'class' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800'],
                            };
                        @endphp
                        <span class="inline-flex mt-1 px-1.5 py-0.5 rounded text-[10px] font-bold border {{ $conditionBadge['class'] }}">{{ $conditionBadge['label'] }}</span>
                        @elseif($txn->lot_no)
                        <div class="text-xs font-mono text-slate-400 mt-0.5">Lot: {{ $txn->lot_no }}</div>
                        @endif
                        @endif
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700/60 font-medium">{{ $txn->fromPop->name ?? ($txn->fromTechnician->name ?? 'Pengadaan (Baru)') }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            @if($txn->to_pop_id === null && $txn->to_technician_id === null && $txn->type->value === 'transfer' && $txn->transfer?->toPop)
                            {{-- Leg dispatch TRANSFER — to_pop_id BELUM keisi (nunggu
                                 konfirmasi Cabang), bukan berarti gak ada tujuan sama
                                 sekali. Fallback "Pelanggan / Luar" salah di sini. --}}
                            <span class="px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 font-medium">{{ $txn->transfer->toPop->name }} (menunggu konfirmasi)</span>
                            @elseif($txn->type->value === 'install')
                            @php
                                $cust = $txn->fopTask?->customer ?? $txn->serial?->customer;
                            @endphp
                            <span class="px-2 py-0.5 rounded bg-violet-50 dark:bg-violet-950/40 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800/50 font-medium">{{ $cust ? 'Pelanggan: '.$cust->full_name : 'Pelanggan' }}</span>
                            @else
                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700/60 font-medium">{{ $txn->toPop->name ?? ($txn->toTechnician->name ?? 'Pelanggan / Luar') }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap text-right font-mono text-sm font-bold text-slate-800 dark:text-slate-200">
                        @if($group->lineCount > 1)
                        {{ $group->lineCount }} <span class="text-xs font-normal text-slate-400">baris</span>
                        @else
                        {{ $txn->serial ? '1 unit' : rtrim(rtrim(number_format((float) $txn->qty, 2, ',', '.'), '0'), ',') }}
                        <span class="text-xs font-normal text-slate-400">{{ $txn->serial ? '' : $txn->item->unit }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                        {{ $txn->createdBy?->name ?? '-' }}
                        @if($txn->evidence_file_path)
                        <button type="button" onclick="event.stopPropagation(); document.getElementById('evidence-{{ $txn->id }}').showModal()"
                            class="mt-1 flex items-center gap-1 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-600 text-[10px] font-semibold text-slate-500 hover:text-rose-600 hover:border-rose-300 dark:hover:text-rose-400 transition-colors">
                            📷 Lihat BAP
                        </button>
                        <dialog id="evidence-{{ $txn->id }}" class="rounded-lg p-0 backdrop:bg-slate-900/50 max-w-sm w-full">
                            <div class="p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-xs font-bold text-slate-700 dark:text-slate-200">Bukti Fisik — {{ $txn->reference_number ?? $txn->reason }}</p>
                                    <button type="button" onclick="document.getElementById('evidence-{{ $txn->id }}').close()" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
                                </div>
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($txn->evidence_file_path) }}" alt="Bukti fisik {{ $txn->reason }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-700">
                                @if($txn->reason)
                                <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">Alasan: {{ $txn->reason }}{{ $txn->notes ? ' — '.$txn->notes : '' }}</p>
                                @endif
                            </div>
                        </dialog>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
        {{ $ledger->links() }}
    </div>
    @endif
</div>

@endsection
