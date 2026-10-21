@extends('layouts.app')

@section('title', 'Master POP/Cabang - Whusnet Operasional')
@section('page_title', 'Master POP / Cabang')

@section('content')
{{-- Notification Alerts handled by global Component Toast (<x-toast/>) --}}

<div class="space-y-5">
    <!-- Layer 1: Naked Page Header (tanpa pembungkus card) -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-text-main tracking-tight">Manajemen Wilayah Operasional POP</h1>
            <p class="text-xs text-text-muted mt-1">
                Kelola Point of Presence (POP), Kantor Pusat, Kantor Cabang, dan Mini POP pendukung jaringan.
            </p>
        </div>
        @if(auth()->user()->hasPermission('manage_pop'))
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('master.pop.create') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600 text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah POP</span>
            </a>
        </div>
        @endif
    </div>

    <!-- Layer 2: Flat Summary Strip (Satu strip datar dengan divider vertikal, bukan 4 card terpisah) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 bg-surface border border-border rounded-lg divide-y sm:divide-y-0 sm:divide-x divide-border overflow-hidden">
        <!-- Col 1: Total POP -->
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">TOTAL POP</span>
                <span class="text-sky-600 dark:text-sky-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-text-main tabular-nums">{{ \App\Models\Pop::count() }}</span>
                <span class="text-[11px] text-text-muted">wilayah</span>
            </div>
        </div>

        <!-- Col 2: Pusat -->
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">KANTOR PUSAT</span>
                <span class="text-blue-600 dark:text-blue-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-blue-600 dark:text-blue-400 tabular-nums">{{ \App\Models\Pop::where('type', 'pusat')->count() }}</span>
                <span class="text-[11px] text-text-muted">Pusat</span>
            </div>
        </div>

        <!-- Col 3: Cabang -->
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">CABANG POP</span>
                <span class="text-purple-600 dark:text-purple-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                    </svg>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-purple-600 dark:text-purple-400 tabular-nums">{{ \App\Models\Pop::where('type', 'cabang')->count() }}</span>
                <span class="text-[11px] text-text-muted">Cabang</span>
            </div>
        </div>

        <!-- Col 4: Mini POP -->
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">MINI POP</span>
                <span class="text-amber-600 dark:text-amber-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-amber-600 dark:text-amber-400 tabular-nums">{{ \App\Models\Pop::where('type', 'mini_pop')->count() }}</span>
                <span class="text-[11px] text-text-muted">Mini POP</span>
            </div>
        </div>
    </div>

    <!-- Layer 2.5: Naked Filter Bar (tanpa pembungkus card) -->
    <form action="{{ route('master.pop.index') }}" method="GET" class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            <!-- Search -->
            <div class="sm:col-span-2 lg:col-span-5">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Pencarian POP</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari Kode, Nama, atau PIC POP..." 
                           class="w-full pl-9 pr-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Filter Tipe -->
            <div class="lg:col-span-3">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Tipe POP</label>
                <select name="type" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors cursor-pointer">
                    <option value="">— Semua Tipe POP —</option>
                    <option value="pusat" {{ $type === 'pusat' ? 'selected' : '' }}>Pusat</option>
                    <option value="cabang" {{ $type === 'cabang' ? 'selected' : '' }}>Cabang</option>
                    <option value="mini_pop" {{ $type === 'mini_pop' ? 'selected' : '' }}>Mini POP</option>
                </select>
            </div>

            <!-- Filter Status -->
            <div class="lg:col-span-2">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Status Keaktifan</label>
                <select name="status" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors cursor-pointer">
                    <option value="">— Semua Status —</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="lg:col-span-2 flex items-center gap-2 justify-end">
                <button type="submit" class="flex-1 lg:flex-none inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-primary hover:bg-primary-hover text-white text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                </button>

                @if($search || $type || $status)
                <a href="{{ route('master.pop.index') }}" class="inline-flex items-center justify-center p-2 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-lg transition-colors text-xs font-semibold" title="Reset Filter">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.28 15m-2.802-5.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                    </svg>
                </a>
                @endif
            </div>
        </div>
    </form>

    <!-- Layer 3 & 4: Table Panel & Pagination (Card Budget = 1, Satu Panel untuk Tabel & Paginasi) -->
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-xs">
        @if($pops->isEmpty())
        <div class="p-16 text-center">
            <div class="h-14 w-14 mx-auto bg-surface-muted rounded-full flex items-center justify-center mb-3 text-text-muted">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
            <h4 class="text-sm font-bold text-text-main">Tidak ada POP ditemukan</h4>
            <p class="text-xs text-text-muted max-w-sm mx-auto mt-1">
                @if($search || $type || $status)
                Silakan reset filter pencarian atau ubah parameter filter Anda.
                @else
                Mulai dengan menambahkan POP/Cabang baru menggunakan tombol Tambah POP di atas.
                @endif
            </p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs text-text-secondary">
                <thead>
                    <tr class="bg-surface-muted/60 border-b border-border text-text-muted font-bold text-[10px] uppercase tracking-wider">
                        <th scope="col" class="px-5 py-3 text-left">Kode POP</th>
                        <th scope="col" class="px-5 py-3 text-left">Nama POP</th>
                        <th scope="col" class="px-5 py-3 text-left">Identifier</th>
                        <th scope="col" class="px-5 py-3 text-left">Tipe</th>
                        <th scope="col" class="px-5 py-3 text-left">Parent POP</th>
                        <th scope="col" class="px-5 py-3 text-left">Wilayah</th>
                        <th scope="col" class="px-5 py-3 text-left">PIC / Kontak</th>
                        <th scope="col" class="px-5 py-3 text-center">Status</th>
                        <th scope="col" class="px-5 py-3 text-center w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($pops as $pop)
                    <tr class="hover:bg-surface-muted/50 transition-colors pop-row" data-id="{{ $pop->id }}" data-parent-id="{{ $pop->parent_id ?? '' }}">
                        <td class="px-5 py-3.5 whitespace-nowrap font-mono font-bold text-text-main tracking-tight">
                            {{ $pop->code }}
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <div class="flex items-center" style="padding-left: {{ ($pop->depth ?? 0) * 1.25 }}rem">
                                @if($pop->children->isNotEmpty())
                                <button type="button" 
                                        class="toggle-children-btn mr-1.5 p-0.5 rounded text-text-muted hover:text-text-main hover:bg-surface-muted transition-colors focus:outline-none cursor-pointer"
                                        data-id="{{ $pop->id }}"
                                        data-expanded="true"
                                        onclick="togglePopChildren({{ $pop->id }}, this)"
                                        title="Toggle Anak POP">
                                    <svg class="h-3.5 w-3.5 transform transition-transform rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                                @else
                                    @if(($pop->depth ?? 0) > 0)
                                    <div class="w-5 h-4 flex items-center justify-center mr-1">
                                        <span class="text-text-muted/60 font-light text-xs">└─</span>
                                    </div>
                                    @else
                                    <div class="w-5"></div>
                                    @endif
                                @endif
                                <div class="text-xs font-semibold text-text-main">{{ $pop->name }}</div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="font-mono font-bold text-text-main text-xs">{{ $pop->pop_code ?? '-' }}</span>
                            <span class="block text-[10px] text-text-muted font-mono mt-0.5">ID: {{ $pop->registration_prefix ?? '-' }} / CID: {{ $pop->cid_prefix ?? '-' }}</span>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            @if($pop->type === 'pusat')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 uppercase tracking-wide">Pusat</span>
                            @elseif($pop->type === 'cabang')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 uppercase tracking-wide">Cabang</span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 uppercase tracking-wide">Mini POP</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            @if($pop->parent)
                            <span class="font-medium text-text-main">{{ $pop->parent->name }}</span>
                            <span class="block text-[10px] text-text-muted font-mono">({{ $pop->parent->code }})</span>
                            @else
                            <span class="text-text-muted">-</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-text-secondary">
                            @if($pop->city)
                            <span class="font-medium text-text-main block">{{ $pop->city }}</span>
                            <span class="block text-[10px] text-text-muted mt-0.5">{{ $pop->district ?? '-' }}, {{ $pop->village ?? '-' }}</span>
                            @else
                            <span class="text-text-muted">-</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-text-secondary">
                            @if($pop->pic_name)
                            <span class="font-medium text-text-main block">{{ $pop->pic_name }}</span>
                            <span class="text-text-muted font-mono text-[10px] block mt-0.5">{{ $pop->pic_phone ?? '-' }}</span>
                            @else
                            <span class="text-text-muted">-</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-center">
                            @if($pop->status === 'active')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Aktif
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                Nonaktif
                            </span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <!-- Detail -->
                                <a href="{{ route('master.pop.show', $pop) }}" class="p-1.5 text-text-muted hover:text-text-main hover:bg-surface-muted rounded-lg transition-colors" title="Lihat Detail">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>

                                @if(auth()->user()->hasPermission('manage_pop'))
                                <!-- Edit -->
                                <a href="{{ route('master.pop.edit', $pop) }}" class="p-1.5 text-text-muted hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Ubah POP">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </a>

                                @if(auth()->user()->hasPermission('pops.delete'))
                                <form action="{{ route('master.pop.destroy', $pop) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="p-1.5 rounded-lg transition-colors cursor-pointer text-text-muted hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                            title="Hapus POP"
                                            onclick="event.preventDefault(); window.confirmDelete(@js('Hapus POP/Cabang '.($pop->name).'? Jika masih punya data terkait, POP hanya dinonaktifkan.'), this.closest('form'))">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                                @endif
                                <!-- Toggle Status (Form) -->
                                <form action="{{ route('master.pop.toggle', $pop) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" 
                                            class="p-1.5 rounded-lg transition-colors cursor-pointer {{ $pop->status === 'active' ? 'text-emerald-600 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40' : 'text-text-muted hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40' }}"
                                            title="{{ $pop->status === 'active' ? 'Nonaktifkan POP' : 'Aktifkan POP' }}"
                                            onclick="event.preventDefault(); window.confirmAction(@js('Apakah Anda yakin ingin mengubah status POP '.($pop->name).'?'), this.closest('form'))">
                                        @if($pop->status === 'active')
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        @else
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        @endif
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($pops->hasPages())
        <div class="px-5 py-3 border-t border-border bg-surface-muted/30">
            {{ $pops->links() }}
        </div>
        @endif
        @endif
    </div>
</div>

@section('scripts')
<script>
function togglePopChildren(parentId, button) {
    const isExpanded = button.getAttribute('data-expanded') === 'true';
    const newExpanded = !isExpanded;
    
    button.setAttribute('data-expanded', newExpanded ? 'true' : 'false');
    const svg = button.querySelector('svg');
    if (newExpanded) {
        svg.classList.add('rotate-90');
    } else {
        svg.classList.remove('rotate-90');
    }
    
    setChildRowVisibility(parentId, newExpanded);
}

function setChildRowVisibility(parentId, visible) {
    const childRows = document.querySelectorAll(`tr[data-parent-id="${parentId}"]`);
    childRows.forEach(row => {
        const childId = row.getAttribute('data-id');
        
        if (visible) {
            row.classList.remove('hidden');
            const toggleBtn = row.querySelector('.toggle-children-btn');
            if (!toggleBtn || toggleBtn.getAttribute('data-expanded') === 'true') {
                setChildRowVisibility(childId, true);
            }
        } else {
            row.classList.add('hidden');
            setChildRowVisibility(childId, false);
        }
    });
}
</script>
@endsection
@endsection
