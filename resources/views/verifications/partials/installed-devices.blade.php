{{-- Perangkat Aktif (SERIALIZED) yang terpasang lewat FopTask ini (ADHOC-54).
     SENGAJA partial terpisah dari materials.blade.php — sumber datanya beda
     (inventory_serials, bukan task_materials), unit SERIALIZED gak pernah
     masuk task_materials sama sekali (§3.4 rancangan-ui.md).

     @param \Illuminate\Support\Collection<int, \App\Models\InventorySerial> $rows --}}
<div class="mb-6">
    <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        Perangkat Aktif Terpasang (dari Gudang)
    </h4>
    <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5 overflow-x-auto">
        @if($rows->isNotEmpty())
        <table class="w-full text-sm min-w-[420px]">
            <thead>
                <tr class="text-[10px] font-bold uppercase tracking-wider text-text-muted border-b border-border">
                    <th class="text-left pb-2">Barang</th>
                    <th class="text-left pb-2 pl-4">Serial Number</th>
                    <th class="text-left pb-2 pl-4">Terpasang</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach($rows as $serial)
                <tr>
                    <td class="py-2 text-text-main font-medium">{{ $serial->item->name ?? '-' }}</td>
                    <td class="py-2 pl-4 text-text-secondary font-mono">{{ $serial->serial_number }}</td>
                    <td class="py-2 pl-4 text-text-muted">{{ $serial->installed_at?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="text-sm text-text-muted">Tidak ada perangkat aktif tercatat dari Gudang untuk pemasangan ini — device mungkin belum ke-track Inventory.</p>
        @endif
    </div>
</div>
