@extends('layouts.app')
@section('title', 'Periksa Hasil Cabut Infrastruktur — WHUSNET')
@section('page_title', 'Periksa Hasil Cabut Infrastruktur')
@section('content')
<x-warehouse.header active="custody" title="Periksa Hasil Cabut Infrastruktur" subtitle="Tentukan kondisi fisik sebelum material kembali tersedia untuk teknisi." />
<div class="mb-5"><a href="{{ route('warehouse.infrastructure-recoveries.index') }}" class="text-sm font-medium text-primary hover:underline">← Daftar hasil cabut</a></div>
<div class="max-w-3xl overflow-hidden rounded-lg border border-border bg-surface">
<div class="grid gap-4 border-b border-border p-5 sm:grid-cols-2">
<div><p class="text-xs uppercase tracking-wide text-text-muted">Task</p><p class="mt-1 font-mono text-sm text-text-main">{{ $recovery->retrieveTransaction?->fopTask?->task?->task_number ?? '—' }}</p></div>
<div><p class="text-xs uppercase tracking-wide text-text-muted">Transaksi cabut</p><p class="mt-1 font-mono text-sm text-text-main">{{ $recovery->retrieveTransaction?->reference_number }}</p></div>
<div><p class="text-xs uppercase tracking-wide text-text-muted">Barang dan jumlah</p><p class="mt-1 text-sm text-text-main">{{ $recovery->sourceTransaction?->item?->name }} · <span class="font-mono tabular-nums">{{ $recovery->qty }} {{ $recovery->sourceTransaction?->item?->unit }}</span></p></div>
<div><p class="text-xs uppercase tracking-wide text-text-muted">Node asal</p><p class="mt-1 text-sm text-text-main">{{ $recovery->sourceTransaction?->toNetworkNode?->breadcrumbLabel() ?? '—' }}</p></div>
</div>
@if($recovery->status === 'pending_inspection')
<form method="POST" action="{{ route('warehouse.infrastructure-recoveries.store', $recovery) }}" class="space-y-4 p-5">@csrf
<p class="text-sm text-text-secondary">Pastikan jumlah fisik sama dengan laporan cabut. Material layak menjadi custody baru; potongan kabel mendapat kode roll baru. Roll asal tidak bertambah.</p>
<div><label for="outcome" class="mb-1.5 block text-xs font-medium text-text-secondary">Kondisi fisik</label><select id="outcome" name="outcome" required class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main"><option value="">Pilih kondisi</option><option value="reusable" @selected(old('outcome') === 'reusable')>Layak pakai</option><option value="damaged" @selected(old('outcome') === 'damaged')>Rusak / afkir</option></select>@error('outcome')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror</div>
<div><label for="verified_qty" class="mb-1.5 block text-xs font-medium text-text-secondary">Jumlah fisik terverifikasi</label><input id="verified_qty" type="number" step="0.01" min="0.01" name="verified_qty" value="{{ old('verified_qty') }}" required class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 font-mono text-sm text-text-main"><p class="mt-1 text-xs text-text-muted">Masukkan hasil hitung fisik. Jika berbeda dari {{ $recovery->qty }}, pemeriksaan ditahan untuk rekonsiliasi.</p>@error('verified_qty')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror</div>
<div><label for="inspection_note" class="mb-1.5 block text-xs font-medium text-text-secondary">Catatan pemeriksaan</label><textarea id="inspection_note" name="inspection_note" rows="3" maxlength="1000" required class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">{{ old('inspection_note') }}</textarea>@error('inspection_note')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror</div>
<button type="submit" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">Simpan pemeriksaan</button></form>
@else<div class="p-5 text-sm text-text-main"><p class="font-semibold">{{ $recovery->status === 'reusable' ? 'Layak pakai' : 'Rusak / afkir' }}</p><p class="mt-2 text-text-secondary">{{ $recovery->inspection_note }}</p><p class="mt-2 text-xs text-text-muted">Diperiksa {{ $recovery->inspected_at?->format('d M Y H:i') }}</p></div>@endif
</div>
@endsection
