{{--
    Ringkasan Posisi Stok per item (analisa-ui-ux-warehouse.md, Fase 3).
    Dipakai dari `warehouse.stock.index` saat `view=ringkasan`. Satu baris = satu
    barang; rincian per lot (khusus QUANTITY) dibuka lewat drawer slide-over
    (`<x-ui.drawer>`, komponen reusable yang sudah ada di repo — 2026-10-08,
    gantikan `<details>` HTML native yang dipakai sebelumnya) supaya user
    tidak perlu pindah halaman. SN/Roll drill-down LEWAT JALUR LAIN: chip
    "Per Gudang" pada item itu dispatch modal "Daftar Serial Number"/"Daftar
    Roll Kabel" yang sudah ada (lihat kolom Per Gudang di bawah) — drawer ini
    KHUSUS quantity, yang tidak punya modal identitas-per-unit untuk dibuka.
--}}
@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $unitLabel = fn ($item) => match ($item->tracking_type?->value ?? 'quantity') {
        'serialized' => 'SN',
        'roll' => 'meter',
        default => $item->unit,
    };
    $popName = fn ($popId) => optional($pops->firstWhere('id', $popId))->name ?? '—';

    // Sort header mode ringkasan (analisa-ui-ux §A5, sisa Fase 3). view=ringkasan
    // dipertahankan di link.
    $sumSort = $summarySort ?? null;
    $sumDir = $summaryDir ?? 'asc';
    $sumLink = fn (string $key) => request()->fullUrlWithQuery([
        'view' => 'ringkasan',
        'sort' => $key,
        'dir' => ($sumSort === $key && $sumDir === 'asc') ? 'desc' : 'asc',
        'page' => null,
    ]);
    $sumAria = fn (string $key) => $sumSort !== $key ? 'none' : ($sumDir === 'desc' ? 'descending' : 'ascending');
    $sumIcon = fn (string $key) => $sumSort !== $key ? '↕' : ($sumDir === 'desc' ? '↓' : '↑');
    $sumHead = fn (string $key) => $sumSort === $key ? 'text-sky-600 dark:text-sky-400' : 'text-slate-300 dark:text-slate-600';
@endphp

@if($summary->isEmpty())
<div class="p-12 sm:p-16 text-center">
    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada posisi stok yang cocok</h4>
    <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Ubah filter atau kata kunci pencarian. Ringkasan hanya menampilkan barang yang punya stok, dipegang teknisi, atau bermasalah.</p>
</div>
@else
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
        <thead class="bg-slate-50/80 dark:bg-slate-800/60">
            <tr>
                <th scope="col" aria-sort="{{ $sumAria('item') }}" class="px-5 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <a href="{{ $sumLink('item') }}" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">Barang <span class="font-mono {{ $sumHead('item') }}" aria-hidden="true">{{ $sumIcon('item') }}</span></a>
                </th>
                <th scope="col" aria-sort="{{ $sumAria('total') }}" class="px-5 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <a href="{{ $sumLink('total') }}" class="inline-flex items-center gap-1 justify-end w-full hover:text-slate-800 dark:hover:text-slate-200">Total Tersedia <span class="font-mono {{ $sumHead('total') }}" aria-hidden="true">{{ $sumIcon('total') }}</span></a>
                </th>
                <th scope="col" class="px-5 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Per Gudang</th>
                <th scope="col" aria-sort="{{ $sumAria('held') }}" class="px-5 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <a href="{{ $sumLink('held') }}" class="inline-flex items-center gap-1 justify-end w-full hover:text-slate-800 dark:hover:text-slate-200">Dipegang Teknisi <span class="font-mono {{ $sumHead('held') }}" aria-hidden="true">{{ $sumIcon('held') }}</span></a>
                </th>
                <th scope="col" aria-sort="{{ $sumAria('in_transit') }}" class="px-5 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <a href="{{ $sumLink('in_transit') }}" class="inline-flex items-center gap-1 justify-end w-full hover:text-slate-800 dark:hover:text-slate-200">In Transit <span class="font-mono {{ $sumHead('in_transit') }}" aria-hidden="true">{{ $sumIcon('in_transit') }}</span></a>
                </th>
                <th scope="col" class="px-5 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kondisi</th>
                <th scope="col" class="px-5 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Terakhir Diubah</th>
                <th scope="col" aria-sort="{{ $sumAria('problem') }}" class="px-5 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <a href="{{ $sumLink('problem') }}" class="inline-flex items-center gap-1 justify-end w-full hover:text-slate-800 dark:hover:text-slate-200">Karantina / Rusak <span class="font-mono {{ $sumHead('problem') }}" aria-hidden="true">{{ $sumIcon('problem') }}</span></a>
                </th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-slate-900 divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($summary as $row)
            @php
                $item = $row['item'];
                $unit = $unitLabel($item);
                $trackingType = $item->tracking_type?->value ?? 'quantity';
            @endphp
            <tr class="align-top hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                <td class="px-5 py-4">
                    <div class="font-bold text-slate-900 dark:text-slate-100">{{ $item->name }}</div>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-xs font-mono text-slate-400 dark:text-slate-500">{{ $item->code }}</span>
                        @if($item->category)
                        <span class="px-1.5 py-0.5 rounded text-[11px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-medium">{{ $item->category->name }}</span>
                        @endif
                    </div>
                </td>

                <td class="px-5 py-4 text-right whitespace-nowrap">
                    <span class="text-lg font-extrabold font-mono tabular-nums text-slate-900 dark:text-slate-100">{{ $fmt($row['total']) }}</span>
                    <span class="text-xs font-semibold text-slate-400 ml-0.5">{{ $unit }}</span>
                </td>

                <td class="px-5 py-4">
                    <div class="flex flex-wrap gap-1.5">
                        @forelse($row['per_pop'] as $slot)
                            @if($trackingType === 'serialized')
                            {{-- Drill-down ke level SN (analisa-ui-ux §U2) — reuse modal
                                 "Daftar Serial Number" yang sama persis dipakai mode per-lot
                                 (event `open-serial-modal`, didengarkan di index.blade.php),
                                 bukan endpoint/komponen baru. --}}
                            <button type="button"
                                @click="$dispatch('open-serial-modal', { popId: {{ $slot['pop_id'] }}, itemId: {{ $item->id }}, itemName: @js($item->name), popName: @js($popName($slot['pop_id'])) })"
                                title="Lihat daftar SN di gudang ini"
                                class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs bg-cyan-50 hover:bg-cyan-100 dark:bg-cyan-950/40 dark:hover:bg-cyan-900/50 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800 cursor-pointer transition-colors">
                                <span class="font-semibold">{{ $popName($slot['pop_id']) }}</span>
                                <span class="font-mono font-bold tabular-nums">{{ $fmt($slot['qty']) }}</span>
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            </button>
                            @elseif($trackingType === 'roll')
                            {{-- Padanan roll — reuse modal "Daftar Roll Kabel" (event
                                 `open-roll-modal`), sama seperti badge di mode per-lot. --}}
                            <button type="button"
                                @click="$dispatch('open-roll-modal', { popId: {{ $slot['pop_id'] }}, itemId: {{ $item->id }}, itemName: @js($item->name), popName: @js($popName($slot['pop_id'])) })"
                                title="Lihat daftar roll di gudang ini"
                                class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 dark:hover:bg-amber-900/50 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 cursor-pointer transition-colors">
                                <span class="font-semibold">{{ $popName($slot['pop_id']) }}</span>
                                <span class="font-mono font-bold tabular-nums">{{ $fmt($slot['qty']) }}</span>
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            </button>
                            @else
                            {{-- Quantity: gak punya identitas per-unit (bukan kekurangan
                                 fitur — barang kuantitas memang gak dilacak per unit).
                                 Drill-down berhenti di lot, dibuka lewat drawer di bawah. --}}
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                                <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $popName($slot['pop_id']) }}</span>
                                <span class="font-mono font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ $fmt($slot['qty']) }}</span>
                            </span>
                            @endif
                        @empty
                        <span class="text-xs text-slate-400">Tidak ada di gudang</span>
                        @endforelse
                    </div>

                    @php
                        // Rincian lot (khusus QUANTITY) — payload buat drawer, dibangun
                        // sekali per baris. Lot tanpa nomor ditulis "Tanpa lot", biar beda
                        // dari lot asli yang kebetulan nama/kodenya kosong.
                        $lotRows = $row['per_pop']
                            ->filter(fn ($s) => ! empty($s['lots']))
                            ->map(fn ($s) => [
                                'pop' => $popName($s['pop_id']),
                                'lots' => collect($s['lots'])->map(fn ($l) => [
                                    'label' => $l['lot_no'] !== '' ? 'Lot '.$l['lot_no'] : 'Tanpa lot',
                                    'qty' => $fmt($l['qty']).' '.$unit,
                                ])->values(),
                            ])->values();
                    @endphp
                    @if($lotRows->isNotEmpty())
                    <button type="button"
                        @click="$dispatch('lot-drawer-data', { itemName: @js($item->name), rows: @js($lotRows) }); $dispatch('open-drawer', 'lot-detail')"
                        class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:underline cursor-pointer">
                        Rincian lot per gudang
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    @endif
                </td>

                <td class="px-5 py-4 text-right">
                    @if($row['held'] > 0)
                    <div class="whitespace-nowrap">
                        <span class="font-mono font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ $fmt($row['held']) }}</span>
                        <span class="text-xs text-slate-400 ml-0.5">{{ $unit }}</span>
                    </div>
                    {{-- Siapa yang pegang: 3 teknisi teratas, sisanya diringkas --}}
                    <div class="mt-1 flex flex-col items-end gap-0.5 text-[11.5px] text-slate-500 dark:text-slate-400">
                        @foreach($row['holders']->take(3) as $holder)
                        <span><span class="font-semibold text-slate-700 dark:text-slate-200">{{ $holder['name'] }}</span> <span class="font-mono tabular-nums">{{ $fmt($holder['qty']) }}</span></span>
                        @endforeach
                        @if($row['holders']->count() > 3)
                        <span class="text-slate-400">+{{ $row['holders']->count() - 3 }} teknisi lain</span>
                        @endif
                    </div>
                    @else
                    <span class="text-xs text-slate-400">Di gudang</span>
                    @endif
                </td>

                <td class="px-5 py-4 text-right whitespace-nowrap">
                    @if($row['in_transit'] > 0)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                        <span class="font-mono tabular-nums">{{ $fmt($row['in_transit']) }}</span> {{ $unit }}
                    </span>
                    @else
                    <span class="text-xs text-slate-400">—</span>
                    @endif
                </td>

                {{-- Kondisi fisik (analisa-ui-ux §U5, keputusan user 2026-10-07):
                     HANYA SN punya kondisi asli — roll gak punya alur retur/cek
                     yang bisa mengubahnya (beda dari SN pas Terima Retur), jadi
                     disamakan QUANTITY: "Tidak dilacak per unit". --}}
                <td class="px-5 py-4 text-left align-top">
                    @if($row['condition_summary'] === null)
                    <span class="text-xs text-slate-400 italic">Tidak dilacak per unit</span>
                    @elseif($row['condition_summary']->isEmpty())
                    <span class="text-xs text-slate-400">—</span>
                    @else
                    <div class="flex flex-wrap gap-1">
                        @foreach($row['condition_summary'] as $c)
                        <x-ui.badge :variant="$c['condition']->badgeVariant()">{{ $c['condition']->label() }}: {{ $c['count'] }}</x-ui.badge>
                        @endforeach
                    </div>
                    @endif
                </td>

                <td class="px-5 py-4 text-left text-[11.5px] text-slate-500 dark:text-slate-400 align-top">
                    @if($row['last_activity'])
                    <div class="font-semibold text-slate-700 dark:text-slate-200">{{ $row['last_activity']['type'] }}</div>
                    <div>oleh <span class="font-semibold">{{ $row['last_activity']['by'] }}</span></div>
                    <div class="font-mono">{{ $row['last_activity']['ref'] ?? '—' }}</div>
                    @if($row['last_activity']['at'])
                    <div title="{{ $row['last_activity']['at']->translatedFormat('d M Y H:i') }}">{{ $row['last_activity']['at']->diffForHumans() }}</div>
                    @endif
                    @else
                    <span>Belum ada transaksi</span>
                    @endif
                </td>

                <td class="px-5 py-4 text-right whitespace-nowrap">
                    @if($row['problem'] > 0)
                    <x-ui.badge variant="error"><span class="font-mono tabular-nums">{{ $fmt($row['problem']) }}</span> {{ $unit }}</x-ui.badge>
                    @else
                    <span class="text-xs text-slate-400">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if($summary->hasPages())
<div class="px-5 py-4 border-t border-slate-100 dark:border-slate-800">
    {{ $summary->links() }}
</div>
@endif

{{-- Drawer rincian lot (analisa-ui-ux §U1/U2, 2026-10-08 — gantikan <details>
     native). SATU instance buat semua baris: isinya ditulis ulang tiap kali
     chip "Rincian lot per gudang" diklik, lewat event `lot-drawer-data`
     (didengarkan di sini, TERPISAH dari `open-drawer` yang cuma mengatur
     tampil/sembunyi — lihat `<x-ui.drawer>`). --}}
<div x-data="{ itemName: '', rows: [] }" x-on:lot-drawer-data.window="itemName = $event.detail.itemName; rows = $event.detail.rows">
    <x-ui.drawer name="lot-detail" title="Rincian Lot per Gudang" max-width="md">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3" x-text="itemName"></h3>
        <div class="space-y-3">
            <template x-for="popRow in rows" :key="popRow.pop">
                <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3">
                    <div class="font-bold text-slate-700 dark:text-slate-200 mb-1.5 text-sm" x-text="popRow.pop"></div>
                    <ul class="space-y-1">
                        <template x-for="lot in popRow.lots" :key="lot.label">
                            <li class="flex items-center justify-between gap-2 text-xs">
                                <span class="font-mono text-slate-500 dark:text-slate-400" x-text="lot.label"></span>
                                <span class="font-mono font-semibold tabular-nums text-slate-800 dark:text-slate-200" x-text="lot.qty"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>
    </x-ui.drawer>
</div>
@endif
