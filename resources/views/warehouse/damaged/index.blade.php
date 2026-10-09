@extends('layouts.app')

@section('title', 'Barang Rusak — Whusnet Operasional')
@section('page_title', 'Barang Rusak')

@section('content')

<x-warehouse.header active="returns" title="Barang Rusak" subtitle="Rekap gabungan semua koreksi kerugian gudang — SN, roll kabel, saldo bulk, dan custody teknisi." />

<x-warehouse.returns-nav active="damaged" />

<div class="mb-5 flex items-center gap-1.5 border-b border-slate-200/80 dark:border-slate-700/80 flex-wrap">
    @php
        $tabs = [
            'serial' => 'SN Rusak/Hilang',
            'roll' => 'Roll Kabel',
            'balance' => 'Saldo Bulk',
            'custody' => 'Custody Teknisi',
        ];
    @endphp
    @foreach($tabs as $key => $label)
    <a href="{{ route('warehouse.damaged.index', array_merge(request()->except(['tab', 'serial_page', 'roll_page', 'balance_page', 'custody_page']), ['tab' => $key])) }}"
       class="px-4 py-2.5 text-xs font-bold rounded-t-lg -mb-px border-b-2 {{ $tab === $key ? 'border-sky-600 text-sky-600 dark:text-sky-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

@if(in_array($tab, ['serial', 'roll']))
<form method="GET" action="{{ route('warehouse.damaged.index') }}" class="mb-5 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 flex items-center gap-3 flex-wrap">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="text" name="q" value="{{ $search }}" placeholder="Cari SN/kode roll atau nama barang..."
           class="flex-1 min-w-[180px] text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">

    <select name="pop_id" class="text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">
        <option value="">Semua Gudang</option>
        @foreach($pops as $pop)
        <option value="{{ $pop->id }}" @selected((string) $popFilter === (string) $pop->id)>{{ $pop->name }}</option>
        @endforeach
    </select>

    <select name="status" class="text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">
        <option value="">Semua Status</option>
        @foreach(['damaged' => 'Rusak', 'lost' => 'Hilang', 'quarantine' => 'Karantina', 'scrapped' => 'Dimusnahkan'] as $value => $label)
        <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <button type="submit" class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold">Filter</button>
</form>
@else
<form method="GET" action="{{ route('warehouse.damaged.index') }}" class="mb-5 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 flex items-center gap-3 flex-wrap">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama barang{{ $tab === 'custody' ? ' atau teknisi' : '' }}..."
           class="flex-1 min-w-[180px] text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">

    <select name="pop_id" class="text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">
        <option value="">Semua Gudang</option>
        @foreach($pops as $pop)
        <option value="{{ $pop->id }}" @selected((string) $popFilter === (string) $pop->id)>{{ $pop->name }}</option>
        @endforeach
    </select>

    <button type="submit" class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold">Filter</button>
</form>
@endif

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">

@if($tab === 'serial')
    @if($serials->isEmpty())
    <div class="p-12 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada SN rusak/hilang yang tercatat.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-200/80 dark:border-slate-700/80 font-bold">
                <tr>
                    <th class="text-left px-4 py-3.5">SN & Model</th>
                    <th class="text-left px-4 py-3.5">Asal</th>
                    <th class="text-left px-4 py-3.5">Status</th>
                    <th class="text-left px-4 py-3.5">Kondisi</th>
                    <th class="text-left px-4 py-3.5">Lokasi</th>
                    <th class="text-right px-4 py-3.5">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($serials as $serial)
                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                    <td class="px-4 py-3.5">
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400">{{ $serial->serial_number }}</span>
                        <span class="text-slate-700 dark:text-slate-300 block">{{ $serial->item?->name ?? 'Model belum ditentukan' }}</span>
                    </td>
                    <td class="px-4 py-3.5"><x-warehouse.origin-badge :log="$serial->latestRetrievalLog" /></td>
                    <td class="px-4 py-3.5"><x-ui.badge :variant="$serial->status->badgeVariant()">{{ $serial->status->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3.5"><x-warehouse.condition-badge :condition="$serial->condition" :checked="$serial->condition_checked_at !== null" /></td>
                    <td class="px-4 py-3.5 font-semibold">{{ $serial->currentPop?->name ?? $serial->issuedFromPop?->name ?? '-' }}</td>
                    <td class="px-4 py-3.5 text-right">
                        <a href="{{ route('warehouse.traceability.index', ['q' => $serial->serial_number]) }}"
                           class="text-sky-600 dark:text-sky-400 font-bold hover:underline">Lacak →</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($serials->hasPages())
    <div class="px-4 py-3.5 border-t border-slate-100 dark:border-slate-700/60">{{ $serials->links() }}</div>
    @endif
    @endif

@elseif($tab === 'roll')
    @if($rolls->isEmpty())
    <div class="p-12 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada roll kabel rusak/hilang yang tercatat.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-200/80 dark:border-slate-700/80 font-bold">
                <tr>
                    <th class="text-left px-4 py-3.5">Kode Roll & Model</th>
                    <th class="text-left px-4 py-3.5">Status</th>
                    <th class="text-left px-4 py-3.5">Sisa Meter</th>
                    <th class="text-left px-4 py-3.5">Lokasi</th>
                    <th class="text-right px-4 py-3.5">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($rolls as $roll)
                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                    <td class="px-4 py-3.5">
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400">{{ $roll->roll_code }}</span>
                        <span class="text-slate-700 dark:text-slate-300 block">{{ $roll->item?->name ?? 'Model belum ditentukan' }}</span>
                    </td>
                    <td class="px-4 py-3.5"><x-ui.badge :variant="$roll->status->badgeVariant()">{{ $roll->status->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3.5 font-mono">{{ rtrim(rtrim(number_format((float) $roll->length_remaining, 2, ',', '.'), '0'), ',') }} m</td>
                    <td class="px-4 py-3.5 font-semibold">{{ $roll->currentPop?->name ?? $roll->issuedFromPop?->name ?? '-' }}</td>
                    <td class="px-4 py-3.5 text-right">
                        <a href="{{ route('warehouse.traceability.index', ['q' => $roll->roll_code]) }}"
                           class="text-sky-600 dark:text-sky-400 font-bold hover:underline">Lacak →</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($rolls->hasPages())
    <div class="px-4 py-3.5 border-t border-slate-100 dark:border-slate-700/60">{{ $rolls->links() }}</div>
    @endif
    @endif

@elseif($tab === 'balance')
    @if($balanceAdjustments->isEmpty())
    <div class="p-12 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada koreksi saldo rugi yang tercatat.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-200/80 dark:border-slate-700/80 font-bold">
                <tr>
                    <th class="text-left px-4 py-3.5">Dokumen</th>
                    <th class="text-left px-4 py-3.5">Barang</th>
                    <th class="text-left px-4 py-3.5">Gudang</th>
                    <th class="text-left px-4 py-3.5">Qty</th>
                    <th class="text-left px-4 py-3.5">Alasan</th>
                    <th class="text-left px-4 py-3.5">Tanggal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($balanceAdjustments as $txn)
                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                    <td class="px-4 py-3.5 font-mono font-bold text-sky-600 dark:text-sky-400">{{ $txn->reference_number }}</td>
                    <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">{{ $txn->item?->name ?? '-' }}</td>
                    <td class="px-4 py-3.5 font-semibold">{{ $txn->toPop?->name ?? '-' }}</td>
                    <td class="px-4 py-3.5 font-mono font-bold text-rose-600 dark:text-rose-400">{{ rtrim(rtrim(number_format((float) $txn->qty, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="px-4 py-3.5">{{ $txn->reason ?? '-' }}</td>
                    <td class="px-4 py-3.5 text-slate-500 dark:text-slate-400">{{ \App\Support\IndonesianDate::dateTime($txn->created_at) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($balanceAdjustments->hasPages())
    <div class="px-4 py-3.5 border-t border-slate-100 dark:border-slate-700/60">{{ $balanceAdjustments->links() }}</div>
    @endif
    @endif

@elseif($tab === 'custody')
    @if($custodyAdjustments->isEmpty())
    <div class="p-12 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada klaim rusak/hilang custody teknisi yang tercatat.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-200/80 dark:border-slate-700/80 font-bold">
                <tr>
                    <th class="text-left px-4 py-3.5">Dokumen</th>
                    <th class="text-left px-4 py-3.5">Teknisi</th>
                    <th class="text-left px-4 py-3.5">Barang</th>
                    <th class="text-left px-4 py-3.5">Qty</th>
                    <th class="text-left px-4 py-3.5">Kategori</th>
                    <th class="text-left px-4 py-3.5">Bukti</th>
                    <th class="text-left px-4 py-3.5">Tanggal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($custodyAdjustments as $txn)
                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                    <td class="px-4 py-3.5 font-mono font-bold text-sky-600 dark:text-sky-400">{{ $txn->reference_number }}</td>
                    <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">{{ $txn->fromTechnician?->name ?? '-' }}</td>
                    <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">{{ $txn->item?->name ?? '-' }}</td>
                    <td class="px-4 py-3.5 font-mono font-bold text-rose-600 dark:text-rose-400">{{ rtrim(rtrim(number_format((float) $txn->qty, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="px-4 py-3.5"><x-ui.badge variant="error">{{ \App\Services\InventoryAdjustmentService::REASON_CATEGORIES[$txn->reason] ?? $txn->reason }}</x-ui.badge></td>
                    <td class="px-4 py-3.5">
                        @if($txn->evidence_file_path)
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($txn->evidence_file_path) }}" target="_blank" class="text-emerald-600 dark:text-emerald-400 font-bold hover:underline">Lihat Foto</a>
                        @else
                        <span class="text-slate-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 text-slate-500 dark:text-slate-400">{{ \App\Support\IndonesianDate::dateTime($txn->created_at) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($custodyAdjustments->hasPages())
    <div class="px-4 py-3.5 border-t border-slate-100 dark:border-slate-700/60">{{ $custodyAdjustments->links() }}</div>
    @endif
    @endif
@endif

</div>

@endsection
