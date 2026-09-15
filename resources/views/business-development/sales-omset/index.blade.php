@extends('layouts.app')

@section('title', 'Omset Sales - Whusnet Operasional')
@section('page_title', 'Dashboard Omset Sales')

@section('content')
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Omset per Sales</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-3xl">
Omset = Biaya Langganan × 11% (nilai PPN itu sendiri — BEDA dari kolom "Harga Dikurangi PPN" di halaman Pelanggan Aktif < 30 Hari), diagregasi per Sales yang mendaftarkan pelanggan pada periode terpilih. Klik nama Sales untuk lihat rincian per pelanggan.
            </p>
        </div>
        <div class="text-center shrink-0">
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">TOTAL OMSET</span>
            <span class="text-lg font-bold text-slate-800 dark:text-slate-200 data-text">{{ \App\Helpers\FormatHelper::rupiah($totalOmsetKeseluruhan) }}</span>
        </div>
    </div>
</div>

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 mb-6 shadow-xs">
    <form action="{{ route('business-development.sales-omset.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-3">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Periode</label>
            <select name="periode" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                @foreach($periodeOptions as $opt)
                    <option value="{{ $opt }}" {{ $periode === $opt ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::createFromFormat('Y-m', $opt)->locale('id')->translatedFormat('F Y') }}{{ $opt === now()->format('Y-m') ? ' (Berjalan)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Filter Role & Nama (2026-09-12, permintaan user — pola sama
             /customer-acquisitions), khusus role ber-restriksi paket. --}}
        <div class="md:col-span-3">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Role</label>
            <select name="role_id" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">Semua Role</option>
                @foreach($restrictedRoles as $role)
                    <option value="{{ $role->id }}" {{ (string) $roleId === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-3">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Nama</label>
            <select name="sales_user_id" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">Semua Nama</option>
                @foreach($nameOptions as $person)
                    <option value="{{ $person->id }}" {{ (string) $salesUserId === (string) $person->id ? 'selected' : '' }}>{{ $person->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-3 flex items-center justify-end">
            @if($roleId || $salesUserId)
                <a href="{{ route('business-development.sales-omset.index', ['periode' => $periode]) }}" class="text-[11px] text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 underline">Reset filter</a>
            @endif
        </div>
    </form>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-xs" x-data="{ openSales: null }">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700/60 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                    <th class="px-4 py-2.5">Sales</th>
                    <th class="px-4 py-2.5">Role</th>
                    <th class="px-4 py-2.5 text-right">Jumlah Pelanggan</th>
                    <th class="px-4 py-2.5 text-right">Total Biaya Langganan</th>
                    <th class="px-4 py-2.5 text-right">Total Harga Dikurangi PPN</th>
                    <th class="px-4 py-2.5 text-right">Total Omset</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($bySales as $i => $row)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors cursor-pointer"
                        @click="openSales = openSales === {{ $i }} ? null : {{ $i }}">
                        <td class="px-4 py-3 font-semibold text-sky-600 dark:text-sky-400 underline decoration-dotted">{{ $row['sales_name'] }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $row['role_name'] }}</td>
                        <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ $row['jumlah_pelanggan'] }}</td>
                        <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-300">{{ \App\Helpers\FormatHelper::rupiah($row['total_biaya_langganan']) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-300">{{ \App\Helpers\FormatHelper::rupiah($row['total_harga_dikurangi_ppn']) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">{{ \App\Helpers\FormatHelper::rupiah($row['total_omset']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-400 dark:text-slate-500">Belum ada omset pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal breakdown per pelanggan — view-only, pola "3 pola aksi" CLAUDE.md --}}
    @foreach($bySales as $i => $row)
        <div x-show="openSales === {{ $i }}" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @click.self="openSales = null">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl max-w-2xl w-full max-h-[80vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Rincian Omset — {{ $row['sales_name'] }}</h4>
                    <button @click="openSales = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">&times;</button>
                </div>
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider border-b border-slate-100 dark:border-slate-700/60">
                            <th class="py-2">Pelanggan</th>
                            <th class="py-2">Tanggal Aktivasi</th>
                            <th class="py-2">POP</th>
                            <th class="py-2 text-right">Biaya Langganan</th>
                            <th class="py-2 text-right">Harga Dikurangi PPN</th>
                            <th class="py-2 text-right">Omset</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($row['breakdown'] as $item)
                            <tr>
                                <td class="py-2 font-medium text-slate-700 dark:text-slate-200">{{ $item['nama'] }}</td>
                                <td class="py-2 text-slate-500 dark:text-slate-400">{{ $item['tanggal_aktivasi'] ? \App\Helpers\FormatHelper::tanggal($item['tanggal_aktivasi']) : '—' }}</td>
                                <td class="py-2 text-slate-500 dark:text-slate-400">{{ $item['pop'] }}</td>
                                <td class="py-2 text-right font-mono text-slate-600 dark:text-slate-300">{{ $item['biaya_langganan'] !== null ? \App\Helpers\FormatHelper::rupiah($item['biaya_langganan']) : '—' }}</td>
                                <td class="py-2 text-right font-mono text-slate-600 dark:text-slate-300">{{ $item['harga_dikurangi_ppn'] !== null ? \App\Helpers\FormatHelper::rupiah($item['harga_dikurangi_ppn']) : '—' }}</td>
                                <td class="py-2 text-right font-mono text-slate-700 dark:text-slate-200">{{ $item['omset'] !== null ? \App\Helpers\FormatHelper::rupiah($item['omset']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
