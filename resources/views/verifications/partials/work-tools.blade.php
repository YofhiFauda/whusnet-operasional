{{-- Checklist alat kerja hasil input teknisi (task_work_tools).
     Dipakai dua kali di halaman Verifikasi Admin (tab Survey & tab Pemasangan),
     makanya dipisah jadi partial — bentuknya harus sama supaya admin membaca
     dua tahap itu dengan pola yang identik.

     @param string $title
     @param array<int, array{tool_name: string, note: ?string}> $rows --}}
<div class="mb-6">
    <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
        {{ $title }}
    </h4>
    <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5">
        @if(! empty($rows))
        <div class="flex flex-wrap gap-2">
            @foreach($rows as $row)
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border border-border bg-surface text-text-main shadow-xs">
                <svg class="h-3.5 w-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                {{ $row['tool_name'] }}@if(! empty($row['note']))<span class="font-normal text-text-muted"> · {{ $row['note'] }}</span>@endif
            </span>
            @endforeach
        </div>
        @else
        <p class="text-sm text-text-muted">Tidak ada alat kerja yang dicatat pada laporan ini.</p>
        @endif
    </div>
</div>
