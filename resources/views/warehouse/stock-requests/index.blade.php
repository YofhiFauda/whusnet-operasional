@extends('layouts.app')

@section('title', 'Permintaan Stok - Whusnet Operasional')
@section('page_title', 'Permintaan Stok')

@section('content')

<x-warehouse.header active="stock-requests" title="Permintaan Stok Cabang" subtitle="Antrean permintaan barang dari Gudang Cabang — sinyal aktif biar Pusat gak perlu nunggu notice sendiri lewat badge Stok Rendah." />

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 mb-6 shadow-xs">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <form method="GET" action="{{ route('warehouse.stock-requests.index') }}" class="flex items-center gap-2">
            <select name="status" onchange="this.form.submit()" class="px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Menunggu Diproses</option>
                <option value="fulfilled" {{ $statusFilter === 'fulfilled' ? 'selected' : '' }}>Sudah Dipenuhi</option>
                <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Ditolak</option>
                <option value="cancelled" {{ $statusFilter === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Semua Status</option>
            </select>
        </form>

        @if(auth()->user()->hasPermission('warehouse_stock_request.create'))
        <a href="{{ route('warehouse.stock-requests.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-xl shadow-xs transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span>Ajukan Permintaan</span>
        </a>
        @endif
    </div>
</div>

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
    @if($requests->isEmpty())
    <div class="p-16 text-center">
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Gak ada permintaan stok di sini</h4>
        <p class="text-xs text-slate-400 mt-1">Coba ganti filter status.</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">No. Permintaan</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Cabang</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Diajukan Oleh</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Diajukan</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($requests as $req)
                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 cursor-pointer" onclick="window.location='{{ route('warehouse.stock-requests.show', $req) }}'">
                    <td class="px-6 py-3.5 text-sm font-mono font-semibold text-sky-700 dark:text-sky-400">{{ $req->reference_number }}</td>
                    <td class="px-6 py-3.5 text-sm text-slate-700 dark:text-slate-300">{{ $req->cabangPop->name }}</td>
                    <td class="px-6 py-3.5 text-sm text-slate-500 dark:text-slate-400">{{ $req->requestedBy->name }}</td>
                    <td class="px-6 py-3.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ $req->items->take(2)->map(fn ($i) => $i->item->name)->implode(', ') }}{{ $req->items->count() > 2 ? ' +'.($req->items->count() - 2).' lainnya' : '' }}
                    </td>
                    <td class="px-6 py-3.5">
                        @php
                            $badge = match($req->status->value) {
                                'pending' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                                'fulfilled' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                                'rejected' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                                default => 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600',
                            };
                        @endphp
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $badge }}">{{ $req->status->label() }}</span>
                    </td>
                    <td class="px-6 py-3.5 text-xs text-slate-400">{{ $req->created_at->translatedFormat('d M Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40">
        {{ $requests->links() }}
    </div>
    @endif
</div>

@endsection
