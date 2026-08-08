@extends('layouts.app')

@section('title', 'Detail Batch Import - Whusnet Operasional')
@section('page_title', 'Detail Batch Import')
@section('breadcrumb_parent', 'Pelanggan')
@section('breadcrumb_parent_url', '/customers')

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-text-main tracking-tight">Detail Batch: {{ $batch->batch_number }}</h1>
            <p class="text-xs text-text-secondary mt-1">Informasi lengkap hasil proses import pelanggan massal</p>
        </div>
        <a href="{{ route('customers.import.history') }}" class="inline-flex items-center justify-center gap-2 bg-surface border border-border text-text-secondary hover:bg-surface-muted text-xs font-semibold py-2 px-4 rounded transition-colors shadow-sm">
            Kembali ke Riwayat
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Summary Cards -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-surface border border-border rounded-lg p-5 shadow-sm space-y-4">
            <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider">Ringkasan Batch</h4>
            
            <div class="space-y-3">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-text-secondary">Nomor Batch:</span>
                    <span class="font-mono font-bold text-text-main">{{ $batch->batch_number }}</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-text-secondary">Status:</span>
                    @if($batch->status === 'imported')
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-success-bg text-success border border-success-border uppercase">{{ $batch->status }}</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-warning-bg text-warning border border-warning-border uppercase">{{ $batch->status }}</span>
                    @endif
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-text-secondary">Waktu:</span>
                    <span class="font-semibold text-text-secondary">{{ $batch->created_at->format('d M Y H:i') }}</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-text-secondary">Oleh:</span>
                    <span class="font-semibold text-text-secondary">{{ $batch->user?->name ?? 'System' }}</span>
                </div>
                <div class="pt-3 border-t border-border">
                    <div class="flex justify-between items-center text-xs mb-2">
                        <span class="text-text-secondary font-medium">Total Baris:</span>
                        <span class="font-mono font-bold text-text-main">{{ $batch->total_rows }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs mb-2">
                        <span class="text-success font-medium">Data Valid:</span>
                        <span class="font-mono font-bold text-success">{{ $batch->valid_rows }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs mb-2">
                        <span class="text-error font-medium">Data Error:</span>
                        <span class="font-mono font-bold text-error">{{ $batch->invalid_rows }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs pt-2 border-t border-border">
                        <span class="text-primary font-bold uppercase">Berhasil Import:</span>
                        <span class="text-lg font-mono font-extrabold text-primary">{{ $batch->imported_rows }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Details -->
    <div class="lg:col-span-2">
        <div class="bg-surface border border-border rounded-lg shadow-sm overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-surface-muted border-b border-border">
                <h3 class="text-xs font-bold text-text-main uppercase tracking-wider">Log Detail Error Import</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse text-text-secondary">
                    <thead>
                        <tr class="bg-surface-muted/50 border-b border-border text-text-muted font-semibold text-[10px] uppercase">
                            <th class="px-6 py-3 w-20 text-center">BARIS</th>
                            <th class="px-6 py-3 w-40">KOLOM</th>
                            <th class="px-6 py-3">PESAN ERROR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($batch->errors as $error)
                        <tr class="hover:bg-error-bg/10 transition-colors">
                            <td class="px-6 py-4 text-center font-mono font-bold text-text-disabled">
                                {{ $error->row_number ?? '-' }}
                            </td>
                            <td class="px-6 py-4 font-mono font-bold text-error">
                                {{ $error->field_name ?? 'Global/DB' }}
                            </td>
                            <td class="px-6 py-4 text-error leading-relaxed font-medium">
                                {{ $error->error_message }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-text-muted italic">
                                Tidak ada error yang dicatat untuk batch ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
