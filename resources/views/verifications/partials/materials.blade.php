{{-- Daftar baris material apa adanya (task_materials) — estimasi di tab Survey,
     realisasi di tab Pemasangan. Sengaja terpisah dari tabel "Estimasi vs
     Terpakai": tabel itu mengagregasi per barang dan membuang catatan per baris,
     padahal yang diinput teknisi adalah daftar barang beserta catatannya.

     @param string $title
     @param string $emptyText
     @param \Illuminate\Support\Collection<int, \App\Models\TaskMaterial> $rows --}}
<div class="mb-6">
    <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        {{ $title }}
    </h4>
    <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5 overflow-x-auto">
        @if($rows->isNotEmpty())
        <table class="w-full text-sm min-w-[520px]">
            <thead>
                <tr class="text-[10px] font-bold uppercase tracking-wider text-text-muted border-b border-border">
                    <th class="text-left pb-2">Barang</th>
                    <th class="text-left pb-2 pl-4">Kategori</th>
                    <th class="text-right pb-2">Qty</th>
                    <th class="text-left pb-2 pl-4">Satuan</th>
                    <th class="text-left pb-2 pl-4">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach($rows as $row)
                <tr>
                    <td class="py-2 text-text-main font-medium">{{ $row->item_name }}</td>
                    <td class="py-2 pl-4 text-text-secondary">{{ $row->category_label }}</td>
                    <td class="py-2 text-right font-mono text-text-main font-semibold">{{ rtrim(rtrim(number_format((float) $row->qty, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="py-2 pl-4 text-text-muted">{{ $row->unit }}</td>
                    <td class="py-2 pl-4 text-text-secondary">{{ $row->note ?: '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="text-sm text-text-muted">{{ $emptyText }}</p>
        @endif
    </div>
</div>
