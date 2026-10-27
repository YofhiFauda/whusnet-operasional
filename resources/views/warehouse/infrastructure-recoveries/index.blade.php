@extends('layouts.app')
@section('title', 'Hasil Cabut Infrastruktur — WHUSNET')
@section('page_title', 'Hasil Cabut Infrastruktur')
@section('content')
<x-warehouse.header active="custody" title="Hasil Cabut Infrastruktur" subtitle="Material dari O-REQ dan INFR-REQ menunggu pemeriksaan sebelum dapat dipakai kembali." />
<div class="mb-5 flex justify-end"><a href="{{ route('warehouse.custody.index') }}" class="text-sm font-medium text-primary hover:underline">Kembali ke custody</a></div>
<div class="overflow-hidden rounded-lg border border-border bg-surface">
<div class="overflow-x-auto"><table class="w-full text-left text-sm">
<thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted"><tr><th class="px-4 py-3">Task / transaksi</th><th class="px-4 py-3">Barang</th><th class="px-4 py-3">Node asal</th><th class="px-4 py-3 text-right">Jumlah</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
<tbody class="divide-y divide-border">
@forelse($recoveries as $recovery)
<tr>
<td class="px-4 py-3 font-mono text-xs text-text-main">{{ $recovery->retrieveTransaction?->fopTask?->task?->task_number ?? '—' }}<span class="mt-1 block text-text-muted">{{ $recovery->retrieveTransaction?->reference_number }}</span></td>
<td class="px-4 py-3 text-text-main">{{ $recovery->sourceTransaction?->item?->name ?? 'Barang' }} @if($recovery->sourceTransaction?->lot_no)<span class="block font-mono text-xs text-text-muted">{{ $recovery->sourceTransaction->lot_no }}</span>@endif</td>
<td class="px-4 py-3 text-text-secondary">{{ $recovery->sourceTransaction?->toNetworkNode?->breadcrumbLabel() ?? '—' }}</td>
<td class="px-4 py-3 text-right font-mono tabular-nums text-text-main">{{ $recovery->qty }}</td>
<td class="px-4 py-3 text-text-secondary">{{ $recovery->status === 'pending_inspection' ? 'Menunggu pemeriksaan' : ($recovery->status === 'reusable' ? 'Layak pakai' : 'Rusak / afkir') }}</td>
<td class="px-4 py-3 text-right"><a class="font-medium text-primary hover:underline" href="{{ route('warehouse.infrastructure-recoveries.show', $recovery) }}">{{ $recovery->status === 'pending_inspection' ? 'Periksa' : 'Lihat' }}</a></td>
</tr>
@empty<tr><td colspan="6" class="px-4 py-10 text-center text-text-muted">Belum ada hasil cabut infrastruktur.</td></tr>@endforelse
</tbody></table></div>
@if($recoveries->hasPages())<div class="border-t border-border px-4 py-3">{{ $recoveries->links() }}</div>@endif
</div>
@endsection
