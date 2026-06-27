@extends('layouts.app')

@section('title', 'Master POP/Cabang - Whusnet Operasional')
@section('page_title', 'Master POP / Cabang')

@section('content')
<!-- Notification Alerts handled by global Component Toast (<x-toast />) -->

<!-- Header Stats Bar -->
<div class="bg-white border border-slate-200 rounded-lg p-6 mb-6 shadow-sm">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-bold text-slate-800">Manajemen Wilayah Operasional POP</h3>
            <p class="text-xs text-slate-500 mt-0.5">Kelola Point of Presence (POP), Kantor Pusat, Kantor Cabang, dan Mini POP pendukung jaringan.</p>
        </div>
        <div class="flex flex-wrap items-center gap-4 md:gap-6">
            <div class="text-center">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">TOTAL POP</span>
                <span class="text-lg font-bold text-slate-800 data-text">{{ \App\Models\Pop::count() }}</span>
            </div>
            <div class="h-8 w-px bg-slate-200 hidden sm:block"></div>
            <div class="text-center">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">PUSAT</span>
                <span class="text-lg font-bold text-slate-800 data-text">{{ \App\Models\Pop::where('type', 'pusat')->count() }}</span>
            </div>
            <div class="h-8 w-px bg-slate-200 hidden sm:block"></div>
            <div class="text-center">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">CABANG</span>
                <span class="text-lg font-bold text-slate-800 data-text">{{ \App\Models\Pop::where('type', 'cabang')->count() }}</span>
            </div>
            <div class="h-8 w-px bg-slate-200 hidden sm:block"></div>
            <div class="text-center">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">MINI POP</span>
                <span class="text-lg font-bold text-slate-800 data-text">{{ \App\Models\Pop::where('type', 'mini_pop')->count() }}</span>
            </div>
        </div>
    </div>
</div>

<!-- Controls Panel -->
<div class="bg-white border border-slate-200 rounded-lg p-5 mb-6 shadow-sm">
    <form action="{{ route('master.pop.index') }}" method="GET" class="flex flex-col lg:flex-row items-end lg:items-center justify-between gap-4">
        <!-- Filters Area -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:max-w-4xl">
            <!-- Search -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Pencarian</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Kode, Nama, atau PIC..." 
                           class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Filter Tipe -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tipe POP</label>
                <select name="type" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 bg-white">
                    <option value="">Semua Tipe</option>
                    <option value="pusat" {{ $type === 'pusat' ? 'selected' : '' }}>Pusat</option>
                    <option value="cabang" {{ $type === 'cabang' ? 'selected' : '' }}>Cabang</option>
                    <option value="mini_pop" {{ $type === 'mini_pop' ? 'selected' : '' }}>Mini POP</option>
                </select>
            </div>

            <!-- Filter Status -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Status Keaktifan</label>
                <select name="status" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 bg-white">
                    <option value="">Semua Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
        </div>

        <!-- Buttons Area -->
        <div class="flex items-center gap-2 w-full lg:w-auto shrink-0 justify-end">
            <button type="submit" class="flex-1 lg:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 border border-slate-300 rounded-md shadow-sm text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer">
                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                Filter
            </button>

            @if($search || $type || $status)
            <a href="{{ route('master.pop.index') }}" class="inline-flex items-center justify-center p-2 border border-slate-300 rounded-md shadow-sm text-sm font-medium text-slate-500 bg-white hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer" title="Reset Filters">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.28 15m-2.802-5.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                </svg>
            </a>
            @endif

            @if(auth()->user()->hasPermission('manage_pop'))
            <a href="{{ route('master.pop.create') }}" class="flex-1 lg:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Tambah POP
            </a>
            @endif
        </div>
    </form>
</div>

<!-- Table / Cards List -->
<div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
    @if($pops->isEmpty())
    <div class="p-16 text-center">
        <div class="h-16 w-16 mx-auto bg-slate-100 rounded-full flex items-center justify-center mb-4 text-slate-400">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
        </div>
        <h4 class="text-sm font-bold text-slate-800">Tidak ada POP ditemukan</h4>
        <p class="text-xs text-slate-400 max-w-sm mx-auto mt-1">
            @if($search || $type || $status)
            Silakan reset filter pencarian atau ubah parameter filter Anda.
            @else
            Mulai dengan menambahkan POP/Cabang baru menggunakan tombol Tambah POP.
            @endif
        </p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Kode POP</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Nama POP</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Identifier</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Tipe</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Parent POP</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Wilayah</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">PIC / Kontak</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-100">
                @foreach($pops as $pop)
                <tr class="hover:bg-slate-50/50 transition-colors pop-row" data-id="{{ $pop->id }}" data-parent-id="{{ $pop->parent_id ?? '' }}">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-slate-900 tracking-tight">
                        {{ $pop->code }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center" style="padding-left: {{ ($pop->depth ?? 0) * 1.5 }}rem">
                            @if($pop->children->isNotEmpty())
                            <button type="button" 
                                    class="toggle-children-btn mr-2 p-0.5 rounded text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors focus:outline-none cursor-pointer"
                                    data-id="{{ $pop->id }}"
                                    data-expanded="true"
                                    onclick="togglePopChildren({{ $pop->id }}, this)"
                                    title="Toggle Anak POP">
                                <svg class="h-4 w-4 transform transition-transform rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            @else
                                @if(($pop->depth ?? 0) > 0)
                                <div class="w-6 h-4 flex items-center justify-center mr-1">
                                    <span class="text-slate-300 font-light text-xs">└─</span>
                                </div>
                                @else
                                <div class="w-6"></div>
                                @endif
                            @endif
                            <div class="text-sm font-semibold text-slate-800">{{ $pop->name }}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">
                        <span class="font-mono font-bold text-slate-700">{{ $pop->pop_code ?? '-' }}</span>
                        <span class="block text-[10px] text-slate-400 mt-0.5">ID: {{ $pop->registration_prefix ?? '-' }} / CID: {{ $pop->cid_prefix ?? '-' }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($pop->type === 'pusat')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100 uppercase tracking-wide">Pusat</span>
                        @elseif($pop->type === 'cabang')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-100 uppercase tracking-wide">Cabang</span>
                        @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-wide">Mini POP</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                        @if($pop->parent)
                        <span class="font-medium text-slate-700">{{ $pop->parent->name }}</span>
                        <span class="block text-[10px] text-slate-400 font-mono">({{ $pop->parent->code }})</span>
                        @else
                        <span class="text-slate-300">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">
                        @if($pop->city)
                        <span class="font-medium text-slate-700">{{ $pop->city }}</span>
                        <span class="block text-[10px] text-slate-400 mt-0.5">{{ $pop->district ?? '-' }}, {{ $pop->village ?? '-' }}</span>
                        @else
                        <span class="text-slate-300">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">
                        @if($pop->pic_name)
                        <span class="font-medium text-slate-700 block">{{ $pop->pic_name }}</span>
                        <span class="text-slate-400 font-mono block mt-0.5">{{ $pop->pic_phone ?? '-' }}</span>
                        @else
                        <span class="text-slate-300">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        @if($pop->status === 'active')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-100">
                            Aktif
                        </span>
                        @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-100">
                            Nonaktif
                        </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <div class="flex items-center justify-center gap-2">
                            <!-- Detail -->
                            <a href="{{ route('master.pop.show', $pop) }}" class="p-1 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-md transition-colors" title="Lihat Detail">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </a>

                            @if(auth()->user()->hasPermission('manage_pop'))
                            <!-- Edit -->
                            <a href="{{ route('master.pop.edit', $pop) }}" class="p-1 text-slate-400 hover:text-sky-600 hover:bg-sky-50 rounded-md transition-colors" title="Ubah POP">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </a>

                            <!-- Toggle Status (Form) -->
                            <form action="{{ route('master.pop.toggle', $pop) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" 
                                        class="p-1 rounded-md transition-colors cursor-pointer {{ $pop->status === 'active' ? 'text-green-500 hover:text-rose-500 hover:bg-rose-50' : 'text-slate-400 hover:text-green-500 hover:bg-green-50' }}"
                                        title="{{ $pop->status === 'active' ? 'Nonaktifkan POP' : 'Aktifkan POP' }}"
                                        onclick="event.preventDefault(); window.confirmAction('Apakah Anda yakin ingin mengubah status POP {{ $pop->name }}?', this.closest('form'))">
                                    @if($pop->status === 'active')
                                    <!-- Toggle Active (Switch-on representation) -->
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    @else
                                    <!-- Toggle Inactive (Switch-off representation) -->
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
        {{ $pops->links() }}
    </div>
    @endif
    @endif

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
            // If child row itself has children and was previously expanded, keep its children visible.
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
