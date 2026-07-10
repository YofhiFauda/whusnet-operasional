@extends('layouts.app')

@section('title', 'Detail Batch Import - Whusnet Operasional')
@section('page_title', 'Detail Batch Import')

@section('content')
<div class="mb-6">
    <nav class="flex text-xs font-semibold text-slate-400 uppercase tracking-wider gap-2 mb-2">
        <a href="/customers" class="hover:text-slate-700 transition-colors">Daftar Pelanggan</a>
        <span>/</span>
        <a href="{{ route('customers.import') }}" class="hover:text-slate-700 transition-colors">Batch Import</a>
        <span>/</span>
        <a href="{{ route('customers.import.history') }}" class="hover:text-slate-700 transition-colors">Riwayat</a>
        <span>/</span>
        <span class="text-slate-600">Detail</span>
    </nav>
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Detail Batch: {{ $batch->batch_number }}</h1>
            <p class="text-xs text-slate-500 mt-1">Informasi lengkap hasil proses import pelanggan massal</p>
        </div>
        <a href="{{ route('customers.import.history') }}" class="inline-flex items-center justify-center gap-2 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-semibold py-2 px-4 rounded transition-colors shadow-sm">
            Kembali ke Riwayat
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Summary Cards -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm space-y-4">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ringkasan Batch</h4>
            
            <div class="space-y-3">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-500">Nomor Batch:</span>
                    <span class="font-mono font-bold text-slate-800">{{ $batch->batch_number }}</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-500">Status:</span>
                    @if($batch->status === 'imported')
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-100 uppercase">{{ $batch->status }}</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100 uppercase">{{ $batch->status }}</span>
                    @endif
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-500">Waktu:</span>
                    <span class="font-semibold text-slate-700">{{ $batch->created_at->format('d M Y H:i') }}</span>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-500">Oleh:</span>
                    <span class="font-semibold text-slate-700">{{ $batch->user?->name ?? 'System' }}</span>
                </div>
                <div class="pt-3 border-t border-slate-100">
                    <div class="flex justify-between items-center text-xs mb-2">
                        <span class="text-slate-500 font-medium">Total Baris:</span>
                        <span class="font-mono font-bold text-slate-800">{{ $batch->total_rows }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs mb-2">
                        <span class="text-green-600 font-medium">Data Valid:</span>
                        <span class="font-mono font-bold text-green-700">{{ $batch->valid_rows }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs mb-2">
                        <span class="text-red-600 font-medium">Data Error:</span>
                        <span class="font-mono font-bold text-red-700">{{ $batch->invalid_rows }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs pt-2 border-t border-slate-50">
                        <span class="text-sky-600 font-bold uppercase">Berhasil Import:</span>
                        <span class="text-lg font-mono font-extrabold text-sky-700">{{ $batch->imported_rows }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Details -->
    <div class="lg:col-span-2">
        <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Log Detail Error Import</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse text-slate-700">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-200 text-slate-500 font-semibold text-[10px] uppercase">
                            <th class="px-6 py-3 w-20 text-center">BARIS</th>
                            <th class="px-6 py-3 w-40">KOLOM</th>
                            <th class="px-6 py-3">PESAN ERROR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($batch->errors as $error)
                        <tr class="hover:bg-red-50/10 transition-colors">
                            <td class="px-6 py-4 text-center font-mono font-bold text-slate-400">
                                {{ $error->row_number ?? '-' }}
                            </td>
                            <td class="px-6 py-4 font-mono font-bold text-red-600">
                                {{ $error->field_name ?? 'Global/DB' }}
                            </td>
                            <td class="px-6 py-4 text-red-700 leading-relaxed font-medium">
                                {{ $error->error_message }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-slate-400 italic">
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
