@extends('layouts.app')

@section('title', 'Riwayat Import Pelanggan - Whusnet Operasional')
@section('page_title', 'Riwayat Import Pelanggan')

@section('content')
<div class="mb-6">
    <nav class="flex text-xs font-semibold text-slate-400 uppercase tracking-wider gap-2 mb-2">
        <a href="/customers" class="hover:text-slate-700 transition-colors">Daftar Pelanggan</a>
        <span>/</span>
        <a href="{{ route('customers.import') }}" class="hover:text-slate-700 transition-colors">Batch Import</a>
        <span>/</span>
        <span class="text-slate-600">Riwayat</span>
    </nav>
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Riwayat Batch Import</h1>
            <p class="text-xs text-slate-500 mt-1">Daftar aktivitas import pelanggan massal</p>
        </div>
        <a href="{{ route('customers.import') }}" class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-2 px-4 rounded transition-colors shadow-sm">
            Import Baru
        </a>
    </div>
</div>

<div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse text-slate-700">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold text-[10px] uppercase">
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
            <tbody class="divide-y divide-slate-100">
                @forelse($batches as $batch)
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-slate-500">
                        {{ $batch->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-slate-700">
                        {{ $batch->batch_number }}
                    </td>
                    <td class="px-6 py-4 text-slate-600 max-w-[200px] truncate">
                        {{ $batch->file_name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ $batch->user?->name ?? 'System' }}
                    </td>
                    <td class="px-6 py-4 text-center font-mono font-bold text-slate-800">
                        {{ $batch->total_rows }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-green-600 font-bold font-mono">{{ $batch->valid_rows }}</span>
                        <span class="text-slate-300 mx-1">/</span>
                        <span class="text-red-600 font-bold font-mono">{{ $batch->invalid_rows }}</span>
                    </td>
                    <td class="px-6 py-4 text-center font-mono font-bold text-sky-600">
                        {{ $batch->imported_rows }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($batch->status === 'imported')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-100">IMPORTED</span>
                        @elseif($batch->status === 'failed')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-700 border border-red-100">FAILED</span>
                        @elseif($batch->status === 'pending')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100">PENDING</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-50 text-slate-700 border border-slate-100 uppercase">{{ $batch->status }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        <a href="{{ route('customers.import.batch-detail', $batch->id) }}" class="inline-flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-bold py-1.5 px-3 rounded transition-colors uppercase tracking-tight">
                            Detail
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-slate-400 italic">
                        Belum ada riwayat import data.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($batches->hasPages())
    <div class="px-6 py-4 bg-slate-50 border-t border-slate-200">
        {{ $batches->links() }}
    </div>
    @endif
</div>
@endsection
