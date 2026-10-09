{{--
    Saldo lapangan per teknisi (analisa-ui-ux-warehouse.md §U6). Satu baris per
    teknisi: barang yang dipegang + jumlah, lama dipegang, dan link ke daftar
    per barang yang sudah terfilter ke teknisi itu. Data dari controller
    (buildPerTechnician), sudah di-scope POP sama seperti mode per barang.
--}}
@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $ageLabel = function ($at) {
        if ($at === null) {
            return null;
        }
        $hours = (int) $at->diffInHours(now());

        return $hours < 24 ? $hours.' jam' : ((int) $at->diffInDays(now())).' hari';
    };
@endphp

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs overflow-hidden">
    @if($perTechnician->isEmpty())
    <div class="p-12 sm:p-16 text-center">
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada teknisi yang memegang barang</h4>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Dalam cakupan dan filter saat ini, tidak ada custody aktif di tangan teknisi.</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
            <thead class="bg-slate-50/80 dark:bg-slate-900/40">
                <tr>
                    <th scope="col" class="px-5 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Teknisi</th>
                    <th scope="col" class="px-5 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang Dipegang</th>
                    <th scope="col" class="px-5 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Perangkat (SN)</th>
                    <th scope="col" class="px-5 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Dipegang Sejak</th>
                    <th scope="col" class="px-5 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Rincian</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-900 divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($perTechnician as $row)
                <tr class="align-top hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-5 py-4">
                        <div class="font-bold text-slate-900 dark:text-slate-100">{{ $row['technician']?->name ?? 'Tanpa nama' }}</div>
                        @if($row['pops']->isNotEmpty())
                        <div class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ $row['pops']->join(', ') }}</div>
                        @endif
                        @if($row['returned_count'] > 0)
                        <div class="mt-1"><x-ui.badge variant="warning">{{ $row['returned_count'] }} retur menunggu gudang</x-ui.badge></div>
                        @endif
                    </td>

                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($row['items'] as $item)
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                                <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $item['name'] }}</span>
                                <span class="font-mono font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ $fmt($item['qty']) }}</span>
                                <span class="text-slate-400">{{ $item['unit'] }}</span>
                            </span>
                            @endforeach
                        </div>
                    </td>

                    <td class="px-5 py-4 text-right whitespace-nowrap">
                        @if($row['serial_count'] > 0)
                        <span class="font-mono font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ $row['serial_count'] }}</span>
                        <span class="text-xs text-slate-400 ml-0.5">unit</span>
                        @else
                        <span class="text-xs text-slate-400">—</span>
                        @endif
                    </td>

                    <td class="px-5 py-4 text-left whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                        @if($ageLabel($row['oldest_issued_at']))
                        <span title="{{ $row['oldest_issued_at']->translatedFormat('d M Y H:i') }}">{{ $ageLabel($row['oldest_issued_at']) }}</span>
                        @else
                        <span>—</span>
                        @endif
                    </td>

                    <td class="px-5 py-4 text-right whitespace-nowrap">
                        <a href="{{ route('warehouse.custody.index', ['technician_id' => $row['technician']?->id]) }}"
                           class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                            Lihat per barang
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
