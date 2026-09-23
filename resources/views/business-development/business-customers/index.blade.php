@extends('layouts.app')

@section('title', 'List Pelanggan Bisnis - Whusnet Operasional')
@section('page_title', 'List Pelanggan Bisnis')

@section('content')
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Pelanggan Paket Bisnis</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-3xl">
                Otomatis dari data pelanggan berkategori paket Bisnis (sesuai Master Kategori Paket) — tidak ada input manual di sini.
                <span class="font-semibold text-slate-600 dark:text-slate-300">Harga Paket</span> = harga bulanan setelah diskon sebelum PPN,
                <span class="font-semibold text-slate-600 dark:text-slate-300">Harga Sesudah PPN</span> = tagihan bulanan pelanggan (mengikuti PPN yang diatur per pelanggan).
                <span class="font-semibold text-slate-600 dark:text-slate-300">Alat</span> diambil dari unit gudang berstatus terpasang di pelanggan.
            </p>
        </div>
        <div class="text-center shrink-0">
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">PELANGGAN</span>
            <span class="text-lg font-bold text-slate-800 dark:text-slate-200 data-text">{{ $customers->total() }}</span>
        </div>
    </div>
</div>

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 mb-6 shadow-xs">
    <form action="{{ route('business-development.business-customers.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-5">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Cari</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="Nama / ID pelanggan / nama alat..."
                   class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
        </div>

        <div class="md:col-span-3">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Tipe Paket</label>
            <select name="category" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">Semua Tipe</option>
                @foreach($businessCategories as $name)
                    <option value="{{ $name }}" {{ $category === $name ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Status</label>
            <select name="status" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">Semua Status</option>
                @foreach($statusOptions as $code => $label)
                    <option value="{{ $code }}" {{ $status === $code ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-2 flex items-center justify-end gap-2">
            @if($search !== '' || $category || $status)
                <a href="{{ route('business-development.business-customers.index') }}" class="text-[11px] text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 underline">Reset</a>
            @endif
            <button type="submit" class="px-3 py-2 text-xs font-semibold rounded-xl bg-sky-600 hover:bg-sky-700 text-white transition-colors cursor-pointer">Cari</button>
        </div>
    </form>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700/60 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                    <th class="px-4 py-2.5">No.</th>
                    <th class="px-4 py-2.5">Nama Pelanggan</th>
                    <th class="px-4 py-2.5">Tipe Paket</th>
                    <th class="px-4 py-2.5 text-right">Harga Paket</th>
                    <th class="px-4 py-2.5 text-right">Harga Sesudah PPN</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5">Alat yang Ditinggalkan</th>
                    <th class="px-4 py-2.5 text-right">Biaya Instalasi</th>
                    <th class="px-4 py-2.5">Tanggal Aktivasi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($customers as $i => $customer)
                    @php
                        $service = $customer->customerService;
                        $hargaPaket = $service ? max(0, (float) $service->monthly_price - (float) $service->discount) : null;
                        // Kelompokkan per nama barang: 3 unit AP jadi "3 × AP", bukan 3 baris.
                        $alat = $customer->inventorySerials
                            ->groupBy(fn ($serial) => $serial->item?->name ?? 'Barang tanpa nama')
                            ->map(fn ($group, $name) => $group->count().' '.$name)
                            ->values();
                        $biayaInstalasi = $customer->customerAcquisition?->installation_fee;
                    @endphp
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $customers->firstItem() + $i }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('customers.show', $customer) }}" class="font-semibold text-slate-700 dark:text-slate-200 hover:text-sky-600 dark:hover:text-sky-400">{{ $customer->full_name }}</a>
                            <span class="block text-[10px] text-slate-400 dark:text-slate-500">{{ $customer->display_id }}{{ $customer->pop ? ' · '.$customer->pop->name : '' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-400 border border-sky-100 dark:border-sky-800/30">{{ $service?->internetPackage?->category ?? '—' }}</span>
                            @if($service?->package_name_snapshot)
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">{{ $service->package_name_snapshot }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">{{ $hargaPaket !== null ? \App\Helpers\FormatHelper::rupiah($hargaPaket) : '—' }}</td>
                        <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">
                            {{ $service ? \App\Helpers\FormatHelper::rupiah($service->total_monthly_bill) : '—' }}
                            @if($service && (float) $service->ppn > 0)
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500">PPN {{ rtrim(rtrim(number_format((float) $service->ppn, 2, ',', '.'), '0'), ',') }}%</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $customer->subscriptionStatus?->badgeClasses() ?? 'bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 border-slate-100 dark:border-slate-700/50' }}">
                                {{ $customer->subscriptionStatus?->name ?? ucwords(str_replace('_', ' ', $customer->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            @if($alat->isEmpty())
                                <span class="text-slate-300 dark:text-slate-600">—</span>
                            @else
                                {{ $alat->implode(', ') }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">
                            {{ $biayaInstalasi !== null ? \App\Helpers\FormatHelper::rupiah($biayaInstalasi) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            {{ $service?->activation_date ? \App\Helpers\FormatHelper::tanggal($service->activation_date) : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-slate-400 dark:text-slate-500">Belum ada pelanggan paket Bisnis{{ $search !== '' || $category || $status ? ' yang cocok dengan filter' : '' }}.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $customers->links() }}</div>
</div>
@endsection
