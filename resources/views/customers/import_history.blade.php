@extends('layouts.app')

@section('title', 'Riwayat Import Pelanggan - Whusnet Operasional')
@section('page_title', 'Riwayat Import Pelanggan')
@section('breadcrumb_parent', 'Pelanggan')
@section('breadcrumb_parent_url', '/customers')

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-text-main tracking-tight">Riwayat Batch Import</h1>
            <p class="text-xs text-text-secondary mt-1">Daftar aktivitas import pelanggan massal</p>
        </div>
        <a href="{{ route('customers.import') }}" class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-2 px-4 rounded transition-colors shadow-sm">
            Import Baru
        </a>
    </div>
</div>

<div class="bg-surface border border-border rounded-lg shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse text-text-secondary">
            <thead>
                <tr class="bg-surface-muted border-b border-border text-text-muted font-semibold text-[10px] uppercase">
                    <th class="px-6 py-4">Waktu</th>
                    <th class="px-6 py-4">Nomor Batch</th>
                    <th class="px-6 py-4">Nama File / Sumber</th>
                    <th class="px-6 py-4">Oleh</th>
                    <th class="px-6 py-4 text-center">Total</th>
                    <th class="px-6 py-4 text-center">Valid / Error</th>
                    <th class="px-6 py-4 text-center">Berhasil</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($batches as $batch)
                <tr class="hover:bg-surface-muted transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-text-muted">
                        {{ $batch->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-text-main">
                        {{ $batch->batch_number }}
                    </td>
                    <td class="px-6 py-4 text-text-secondary max-w-[200px] truncate">
                        {{ $batch->file_name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ $batch->user?->name ?? 'System' }}
                    </td>
                    <td class="px-6 py-4 text-center font-mono font-bold text-text-main">
                        {{ $batch->total_rows }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-success font-bold font-mono">{{ $batch->valid_rows }}</span>
                        <span class="text-text-disabled mx-1">/</span>
                        <span class="text-error font-bold font-mono">{{ $batch->invalid_rows }}</span>
                    </td>
                    <td class="px-6 py-4 text-center font-mono font-bold text-primary">
                        {{ $batch->imported_rows }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($batch->status === 'imported')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-success-bg text-success border border-success-border">IMPORTED</span>
                        @elseif($batch->status === 'failed')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-error-bg text-error border border-error-border">FAILED</span>
                        @elseif($batch->status === 'pending')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-warning-bg text-warning border border-warning-border">PENDING</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-surface-muted text-text-secondary border border-border uppercase">{{ $batch->status }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        <a href="{{ route('customers.import.batch-detail', $batch->id) }}" class="inline-flex items-center justify-center bg-surface-muted hover:bg-surface border border-border text-text-secondary text-[10px] font-bold py-1.5 px-3 rounded transition-colors uppercase tracking-tight">
                            Detail
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-text-muted italic">
                        Belum ada riwayat import data.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($batches->hasPages())
    <div class="px-6 py-4 bg-surface-muted border-t border-border">
        {{ $batches->links() }}
    </div>
    @endif
</div>
@endsection
