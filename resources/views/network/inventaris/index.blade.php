@extends('layouts.app')

@section('title', 'Inventaris Infrastruktur')
@section('page_title', 'Inventaris Infrastruktur')

@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-border bg-surface p-5">
        <div>
            <h1 class="text-xl font-bold text-text-main">Inventaris Infrastruktur</h1>
            <p class="mt-1 text-sm text-text-muted">Bukti barang gudang yang terpasang netap di node jaringan: barang yang dipakai, tanggal pasang, teknisi, dan Task asal. Lintas cabang sesuai scope Anda.</p>
        </div>
        <a href="{{ route('network.nodes.index') }}" class="text-xs font-semibold text-primary hover:underline">Master Node Jaringan &amp; Port</a>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-4" data-summary="sn">
            <p class="text-xs font-semibold text-text-secondary">SN terpasang</p>
            <p class="mt-1 font-mono text-2xl font-bold text-text-main">{{ $summary['sn'] }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4" data-summary="qty">
            <p class="text-xs font-semibold text-text-secondary">Pemasangan qty &amp; roll</p>
            <p class="mt-1 font-mono text-2xl font-bold text-text-main">{{ $summary['qty'] }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4" data-summary="nodes">
            <p class="text-xs font-semibold text-text-secondary">Node terisi</p>
            <p class="mt-1 font-mono text-2xl font-bold text-text-main">{{ $summary['nodes'] }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('network.inventaris.index') }}"
          class="grid gap-3 rounded-xl border border-border bg-surface p-5 sm:grid-cols-2 lg:grid-cols-4">
        <label class="text-xs font-semibold text-text-secondary sm:col-span-2">Cari
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" aria-label="Cari serial, barang, lot, lokasi, atau Task"
                   placeholder="Serial, barang, lot, lokasi, atau nomor Task"
                   class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
        </label>
        <label class="text-xs font-semibold text-text-secondary">Cabang
            <select name="pop_id" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                <option value="">Semua cabang dalam scope</option>
                @foreach($cabangs as $cabang)
                <option value="{{ $cabang->id }}" @selected((string) ($filters['pop_id'] ?? '') === (string) $cabang->id)>{{ $cabang->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-text-secondary">Tipe
            <select name="tipe" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                <option value="">Semua tipe</option>
                <option value="sn" @selected(($filters['tipe'] ?? '') === 'sn')>SN (serialized)</option>
                <option value="qty" @selected(($filters['tipe'] ?? '') === 'qty')>Qty (aksesoris)</option>
                <option value="roll" @selected(($filters['tipe'] ?? '') === 'roll')>Roll (kabel)</option>
            </select>
        </label>
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white">Terapkan</button>
            <a href="{{ route('network.inventaris.index') }}" class="rounded-lg border border-border px-4 py-2 text-xs font-semibold text-text-secondary">Reset</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-border bg-surface">
        <table class="w-full min-w-[860px] border-collapse text-left text-xs">
            <thead>
                <tr class="border-b border-border bg-surface-muted text-text-secondary">
                    <th scope="col" class="px-4 py-3 font-semibold">Barang</th>
                    <th scope="col" class="px-4 py-3 font-semibold">SN / Lot / Qty</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Lokasi Node</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Tanggal Pasang</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Teknisi</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Task</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($rows as $row)
                @php
                    $unit = $row->item?->unit;
                    $qtyText = rtrim(rtrim(number_format((float) $row->qty, 2, ',', '.'), '0'), ',');
                @endphp
                <tr class="text-text-main" data-inventaris-row>
                    <td class="px-4 py-3">
                        <p class="font-semibold">{{ $row->item?->name ?? '-' }}</p>
                        <p class="font-mono text-[11px] text-text-muted">{{ $row->item?->code }}</p>
                    </td>
                    <td class="px-4 py-3 font-mono">
                        @if($row->serial)
                            <span>SN {{ $row->serial->serial_number }}</span>
                        @elseif($row->roll_id)
                            <span>Roll {{ $row->lot_no }}</span>
                            <span class="block text-text-muted">{{ $qtyText }} {{ $unit }} terpakai</span>
                        @else
                            <span>{{ $qtyText }} {{ $unit }}</span>
                            @if($row->lot_no)
                                <span class="block text-text-muted">Lot {{ $row->lot_no }}</span>
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-3 text-text-secondary">{{ $row->toNetworkNode?->breadcrumbLabel() ?? '-' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-text-secondary">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-3 text-text-secondary">{{ $row->fromTechnician?->name ?? '-' }}</td>
                    <td class="px-4 py-3 font-mono text-text-secondary">
                        {{ $row->fopTask?->task?->task_number ?? $row->fopTask?->task_number ?? '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-text-muted">Belum ada barang terpasang di infrastruktur dalam scope Anda.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $rows->links() }}</div>
</div>
@endsection
