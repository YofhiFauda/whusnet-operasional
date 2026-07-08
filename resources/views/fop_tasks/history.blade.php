@extends('layouts.app')

@section('title', 'Riwayat Task FOP')

@section('content')
<div x-data="fopTaskPageHandler()" class="px-4 py-6 max-w-12xl mx-auto space-y-5">

    {{-- ══ Page Header ══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 py-2">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight font-ui">Riwayat Task FOP</h1>
            <p class="text-sm text-slate-500 mt-1 font-ui">Daftar task FOP yang telah selesai atau dibatalkan.</p>
        </div>
    </div>

    {{-- ══ Filters (Naked) ══ --}}
    <form method="GET" action="{{ route('fop-tasks.history') }}" class="flex flex-col gap-3 pb-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5 font-ui">Pencarian</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari Task..." class="w-full text-sm border border-slate-300 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder:text-slate-400 font-ui bg-white">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5 font-ui">Kategori</label>
                <select name="category" class="w-full text-sm border border-slate-300 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white font-ui">
                    <option value="">Semua</option>
                    @foreach($categories as $key => $val)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $key }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5 font-ui">Status</label>
                <select name="status" class="w-full text-sm border border-slate-300 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white font-ui">
                    <option value="">Semua (Selesai & Cancel)</option>
                    <option value="Selesai" {{ request('status') === 'Selesai' ? 'selected' : '' }}>Selesai</option>
                    <option value="Cancel" {{ request('status') === 'Cancel' ? 'selected' : '' }}>Cancel</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5 font-ui">Prioritas</label>
                <select name="priority" class="w-full text-sm border border-slate-300 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white font-ui">
                    <option value="">Semua</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="Medium" {{ request('priority') === 'Medium' ? 'selected' : '' }}>Medium</option>
                    <option value="High" {{ request('priority') === 'High' ? 'selected' : '' }}>High</option>
                    <option value="Urgent" {{ request('priority') === 'Urgent' ? 'selected' : '' }}>Urgent</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5 font-ui">Area</label>
                <select name="village_id" class="w-full text-sm border border-slate-300 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white font-ui">
                    <option value="">Semua</option>
                    @foreach($villages as $v)
                        <option value="{{ $v->id }}" {{ request('village_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5 font-ui">Team</label>
                <select name="team_id" class="w-full text-sm border border-slate-300 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white font-ui">
                    <option value="">Semua</option>
                    @foreach($teams as $t)
                        <option value="{{ $t['id'] }}" {{ request('team_id') == $t['id'] ? 'selected' : '' }}>{{ $t['name'] }} ({{ $t['work_date'] }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center justify-between mt-1">
            <span class="text-sm text-slate-500 font-ui">Menampilkan <span class="font-semibold text-slate-700 font-data">{{ $fopTasks->count() }}</span> dari <span class="font-semibold text-slate-700 font-data">{{ $fopTasks->total() }}</span> data</span>
            <div class="flex items-center gap-3">
                @if(request()->anyFilled(['search', 'category', 'status', 'priority', 'village_id', 'team_id']))
                    <a href="{{ route('fop-tasks.history') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors font-ui">Reset</a>
                @endif
                <button type="submit" class="bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-sm font-medium px-4 py-1.5 rounded transition-colors font-ui">Filter</button>
            </div>
        </div>
    </form>

    {{-- ══ Table Panel (Single Card Container) ══ --}}
    <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-600 uppercase tracking-wider font-ui">
                        <th class="px-3 py-2">Kategori</th>
                        <th class="px-3 py-2">Tanggal</th>
                        <th class="px-3 py-2">Tugas</th>
                        <th class="px-3 py-2">Area</th>
                        <th class="px-3 py-2">Issue</th>
                        <th class="px-3 py-2">Teknisi</th>
                        <th class="px-3 py-2">Team</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Prioritas</th>
                        <th class="px-3 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-[11px] text-slate-700 font-ui">
                    @forelse($fopTasks as $task)
                        <tr class="hover:bg-slate-50 transition-colors align-top">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border font-ui {{ $task->category instanceof \App\Enums\TaskType ? $task->category->badgeClasses() : 'border-slate-200 bg-white text-slate-600' }}">
                                    {{ $task->category instanceof \App\Enums\TaskType ? $task->category->value : $task->category }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-slate-600 font-data">
                                {{ $task->task_date ? $task->task_date->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td class="px-3 py-2 min-w-[200px] whitespace-normal leading-tight font-ui">
                                <span class="font-medium text-slate-800">{{ $task->tugas }}</span>
                            </td>
                            <td class="px-3 py-2 whitespace-normal leading-tight text-slate-600 min-w-[120px] font-ui">
                                {{ $task->village?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-2 min-w-[150px] whitespace-normal leading-tight text-red-600 font-ui">
                                {{ $task->issue ?? '—' }}
                            </td>
                            <td class="px-3 py-2 font-ui">
                                <div class="flex flex-wrap gap-1 items-start min-w-[150px]">
                                    @php 
                                        $visibleTechs = $task->technicians->take(2); 
                                        $hiddenTechsCount = $task->technicians->count() - 2; 
                                    @endphp
                                    @forelse($visibleTechs as $tech)
                                        @php 
                                            $firstName = explode(' ', trim($tech->name))[0]; 
                                        @endphp
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100 cursor-help" title="{{ $tech->name }}">
                                            {{ \Illuminate\Support\Str::limit($firstName, 12) }}
                                        </span>
                                    @empty
                                        <span class="text-slate-400 text-[10px] italic">Unassigned</span>
                                    @endforelse
                                    
                                    @if($hiddenTechsCount > 0)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200 cursor-help font-ui" title="{{ $task->technicians->skip(2)->pluck('name')->implode(', ') }}">
                                            +{{ $hiddenTechsCount }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap font-ui">
                                @if($task->team)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                        {{ $task->team->name }}
                                    </span>
                                @else
                                    <span class="text-slate-300 text-[10px]">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap font-ui">
                                <div class="flex flex-col gap-1">
                                    <select @change="updateStatus({{ $task->id }}, $event.target.value)"
                                            class="text-[11px] font-medium rounded border px-2 py-1 outline-none focus:ring-1 focus:ring-blue-500 w-24"
                                            :class="{
                                                'border-blue-200 text-blue-700 bg-blue-50': '{{ $task->status->value }}' === 'Proses',
                                                'border-amber-200 text-amber-700 bg-amber-50': '{{ $task->status->value }}' === 'Pending',
                                                'border-green-200 text-green-700 bg-green-50': '{{ $task->status->value }}' === 'Selesai',
                                                'border-red-200 text-red-700 bg-red-50': '{{ $task->status->value }}' === 'Cancel'
                                            }">
                                        <option value="Proses" {{ $task->status->value === 'Proses' ? 'selected' : '' }}>Proses</option>
                                        <option value="Pending" {{ $task->status->value === 'Pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="Selesai" {{ $task->status->value === 'Selesai' ? 'selected' : '' }}>Selesai</option>
                                        <option value="Cancel" {{ $task->status->value === 'Cancel' ? 'selected' : '' }}>Cancel</option>
                                    </select>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap font-ui">
                                <select @change="updatePriority({{ $task->id }}, $event.target.value)" 
                                        class="text-[11px] font-medium rounded border px-2 py-1 outline-none focus:ring-1 focus:ring-blue-500 w-24"
                                        :class="{
                                            'border-slate-200 text-slate-700 bg-slate-50': '{{ $task->priority->value }}' === 'low',
                                            'border-yellow-300 text-yellow-800 bg-yellow-50': '{{ $task->priority->value }}' === 'Medium',
                                            'border-orange-300 text-orange-800 bg-orange-50': '{{ $task->priority->value }}' === 'High',
                                            'border-red-300 text-red-700 bg-red-50 font-bold': '{{ $task->priority->value }}' === 'Urgent'
                                        }">
                                    <option value="low" {{ $task->priority->value === 'low' ? 'selected' : '' }}>Low</option>
                                    <option value="Medium" {{ $task->priority->value === 'Medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="High" {{ $task->priority->value === 'High' ? 'selected' : '' }}>High</option>
                                    <option value="Urgent" {{ $task->priority->value === 'Urgent' ? 'selected' : '' }}>Urgent</option>
                                </select>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button @click="openEditModal({{ json_encode($task) }}, {{ json_encode($task->technicians->pluck('id')) }})" 
                                            class="text-slate-400 hover:text-blue-600 transition-colors bg-slate-100 hover:bg-blue-50 p-1.5 rounded"
                                            title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <form action="{{ route('fop-tasks.destroy', $task->id) }}" method="POST" onsubmit="return confirm('Hapus Task FOP ini?')" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-slate-400 hover:text-red-600 transition-colors bg-slate-100 hover:bg-red-50 p-1.5 rounded" title="Hapus">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-3 py-10 text-center text-slate-500">
                                <svg class="w-8 h-8 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p class="text-[11px] font-medium font-ui">Tidak ada data riwayat task FOP.</p>
                                <p class="text-[10px] mt-1 text-slate-400 font-ui">Silakan buat task baru atau ubah filter pencarian.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination Links --}}
    @if($fopTasks->hasPages())
        <div class="mt-4">
            {{ $fopTasks->links() }}
        </div>
    @endif

    {{-- ══ EDIT TASK MODAL ══ --}}
    <div x-show="modal.open" 
         x-effect="document.body.classList.toggle('overflow-hidden', modal.open)"
         class="fixed inset-0 z-50 overflow-y-auto" 
         x-transition:enter="transition ease-out duration-200" 
         x-transition:enter-start="opacity-0" 
         x-transition:enter-end="opacity-100" 
         x-transition:leave="transition ease-in duration-150" 
         x-transition:leave-start="opacity-100" 
         x-transition:leave-end="opacity-0"
         style="display: none;">
        
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="modal.open = false"></div>

        <div class="flex items-center justify-center min-h-screen p-0 sm:p-4">
            <div class="bg-white border-y sm:border border-slate-200 w-full sm:max-w-2xl rounded-none sm:rounded shadow-xl relative z-10 flex flex-col sm:block max-h-screen sm:max-h-none" 
                 x-show="modal.open"
                 @click.away="modal.open = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                
                <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between bg-slate-50 rounded-t">
                    <h3 class="text-sm font-semibold text-slate-800" x-text="'Edit Detail Task FOP'"></h3>
                    <button type="button" @click="modal.open = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form :action="'{{ url('/fop-tasks') }}/' + modal.data.id" method="POST" @submit="isSubmitting = true">
                    <div class="p-5 flex-1 max-h-[calc(100vh-120px)] sm:max-h-[75vh] overflow-y-auto space-y-4">
                        @csrf
                        <input type="hidden" name="_method" value="PUT">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1 font-ui">Tipe Task <span class="text-red-500">*</span></label>
                                <select name="category" x-model="modal.data.category"
                                        :disabled="!canEditCategory"
                                        required
                                        class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-500 font-ui">
                                    <option value="">Pilih Tipe</option>
                                    <template x-for="[key, val] in Object.entries(availableCategories)" :key="key">
                                        <option :value="key" x-text="key + ' - ' + val"></option>
                                    </template>
                                </select>
                                <template x-if="!canEditCategory">
                                    <input type="hidden" name="category" :value="modal.data.category">
                                </template>
                                <p class="mt-1 text-[10px] text-slate-400 font-ui" x-show="!canEditCategory">Anda tidak punya izin ubah tipe task.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1 font-ui">Tanggal & Waktu <span class="text-red-500">*</span></label>
                                <input type="datetime-local" name="task_date" x-model="modal.data.task_date" required class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-ui">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1 font-ui">POP / Cabang <span class="text-red-500">*</span></label>
                                <select name="pop_id" x-model="modal.data.pop_id" required class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-ui">
                                    <option value="">Pilih Cabang</option>
                                    @foreach($pops as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1 font-ui">Area (Desa) <span class="text-red-500">*</span></label>
                                <select name="village_id" x-model="modal.data.village_id" required class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-ui">
                                    <option value="">Pilih Desa</option>
                                    @foreach($villages as $v)
                                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1 font-ui">Team (opsional)</label>
                            <select name="team_id" x-model="modal.data.team_id" @change="onTeamChange()" class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-ui">
                                <option value="">— Tanpa Team —</option>
                                <template x-for="t in teamsData" :key="t.id">
                                    <option :value="t.id" x-text="t.name + ' (' + t.work_date + ')'"></option>
                                </template>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="relative" @click.away="customerSearchResults = []">
                                <label class="block text-xs font-medium text-slate-700 mb-1 font-ui">Penugasan / Pelanggan <span class="text-red-500">*</span></label>
                                <input type="text" name="tugas" x-model="modal.data.tugas" 
                                       @input.debounce.300ms="searchCustomer()"
                                       @keydown.escape="customerSearchResults = []"
                                       autocomplete="off"
                                       placeholder="Ketik tugas / nama..." required class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-ui">
                                
                                <input type="hidden" name="customer_id" :value="modal.data.customer_id">
                                
                                <div x-show="customerSearchResults.length > 0" class="absolute z-50 w-full bg-white border border-slate-200 rounded mt-1 max-h-48 overflow-y-auto shadow-lg" style="display: none;">
                                    <template x-for="c in customerSearchResults" :key="c.id">
                                        <button type="button" @click="selectCustomer(c)" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 border-b border-slate-100 last:border-0 outline-none font-ui">
                                            <span class="font-medium text-slate-700" x-text="c.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1 font-ui">Issue / Masalah <span class="text-red-500">*</span></label>
                                <input type="text" name="issue" x-model="modal.data.issue" placeholder="Contoh: FO CUT..." required class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-ui">
                            </div>
                        </div>

                        <div class="relative" x-data="{ openTechDropdown: false }">
                            <label class="block text-xs font-medium text-slate-700 mb-1 font-ui">Pilih Teknisi <span class="text-red-500">*</span></label>
                            <div @click="openTechDropdown = true" @click.away="openTechDropdown = false" class="min-h-[38px] w-full border border-slate-300 rounded bg-white px-2 py-1.5 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500 cursor-text flex items-center gap-2 flex-wrap">
                                <template x-for="techId in modal.techs" :key="techId">
                                    <span class="inline-flex items-center gap-1 bg-slate-100 border border-slate-200 text-slate-700 text-xs font-medium px-2 py-0.5 rounded font-ui">
                                        <span x-text="getTechName(techId)"></span>
                                        <button type="button" @click.stop="toggleTech(techId)" class="hover:text-red-500">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </span>
                                </template>
                                <input type="text" x-model="searchTech" @focus="openTechDropdown = true" placeholder="Cari..." class="flex-1 min-w-[100px] outline-none text-sm text-slate-800 bg-transparent border-none p-0 focus:ring-0 font-ui">
                            </div>

                            <div x-show="openTechDropdown" class="absolute z-50 w-full bg-white border border-slate-200 rounded shadow-lg mt-1 max-h-48 overflow-y-auto" style="display: none;">
                                @foreach($technicians as $tech)
                                    <label class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0 font-ui"
                                           x-show="(searchTech === '' || '{{ strtolower($tech->name) }}'.includes(searchTech.toLowerCase())) && (teamMemberIds.length === 0 || teamMemberIds.includes({{ $tech->id }}))">
                                        <input type="checkbox" name="technicians[]" value="{{ $tech->id }}"
                                               :checked="modal.techs.includes({{ $tech->id }})"
                                               @change="toggleTech({{ $tech->id }})"
                                               class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm text-slate-700">{{ $tech->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <input type="hidden" :required="modal.techs.length === 0" class="absolute w-0 h-0 opacity-0" name="technicians_required">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 font-ui">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Status <span class="text-red-500">*</span></label>
                                <select name="status" x-model="modal.data.status" required class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    <option value="Proses">Proses</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Selesai">Selesai</option>
                                    <option value="Cancel">Cancel</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Prioritas <span class="text-red-500">*</span></label>
                                <select name="priority" x-model="modal.data.priority" required class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    <option value="low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                            </div>
                        </div>

                        <div x-show="modal.data.status === 'Pending'" class="space-y-3 bg-slate-50 border border-slate-200 rounded p-3 font-ui" style="display: none;">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Alasan Pending <span class="text-red-500">*</span></label>
                                <input type="text" name="pending_reason" x-model="modal.data.pending_reason" :required="modal.data.status === 'Pending'" class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Tgl Request Client <span class="text-red-500">*</span></label>
                                <input type="date" name="client_request_date" x-model="modal.data.client_request_date" :required="modal.data.status === 'Pending'" class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1 font-ui">Catatan</label>
                            <textarea name="notes" x-model="modal.data.notes" rows="2" class="w-full text-sm border border-slate-300 rounded px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-ui"></textarea>
                        </div>
                    </div>

                    <div class="px-5 py-3.5 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3 rounded-b font-ui">
                        <button type="button" @click="modal.open = false" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-medium px-4 py-1.5 rounded transition-colors">Batal</button>
                        <button type="submit" :disabled="isSubmitting" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-1.5 rounded transition-colors disabled:opacity-50 font-ui font-semibold">
                            <span x-show="!isSubmitting" class="font-ui">Simpan</span>
                            <span x-show="isSubmitting" class="font-ui">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function fopTaskPageHandler() {
        return {
            isSubmitting: false,
            searchTech: '',
            customerSearchResults: [],
            isSearchingCustomer: false,
            modal: {
                open: false,
                isEdit: true,
                data: {
                    id: '', task_number: '', task_date: '', category: '', tugas: '',
                    customer_id: '', village_id: '', pop_id: '', team_id: '', issue: '', notes: '',
                    status: 'Selesai', priority: 'low',
                    pending_reason: '', client_request_date: ''
                },
                techs: []
            },

            // Data lists populated from Laravel
            availableCategories: @json($manualCategories),
            allTechnicians: @json($technicians),
            canEditCategory: @json($canEditFopTaskType),
            teamsData: @json($teams),

            // Computed / reactive state
            teamMemberIds: [],

            init() {
                // watch for team changes in modal data
                this.$watch('modal.data.team_id', (value) => {
                    this.onTeamChange();
                });
            },

            onTeamChange() {
                const teamId = this.modal.data.team_id;
                if (!teamId) {
                    this.teamMemberIds = [];
                    return;
                }
                const team = this.teamsData.find(t => t.id == teamId);
                if (team) {
                    this.teamMemberIds = team.members.map(m => m.id);
                } else {
                    this.teamMemberIds = [];
                }
            },

            getTechName(id) {
                const tech = this.allTechnicians.find(t => t.id == id);
                return tech ? tech.name : 'Unknown';
            },

            toggleTech(id) {
                const index = this.modal.techs.indexOf(id);
                if (index > -1) {
                    this.modal.techs.splice(index, 1);
                } else {
                    this.modal.techs.push(id);
                }
            },

            openEditModal(task, techIds) {
                this.modal.open = true;
                
                // Format datetime-local string
                let formattedDate = '';
                if (task.task_date) {
                    const d = new Date(task.task_date);
                    // Adjust to local timezone format YYYY-MM-DDTHH:MM
                    const pad = (n) => n.toString().padStart(2, '0');
                    formattedDate = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                }

                this.modal.data = {
                    id: task.id || '',
                    task_number: task.task_number || '',
                    task_date: formattedDate,
                    category: task.category || '',
                    tugas: task.tugas || '',
                    customer_id: task.customer_id || '',
                    village_id: task.village_id || '',
                    pop_id: task.pop_id || '',
                    team_id: task.team_id || '',
                    issue: task.issue || '',
                    notes: task.notes || '',
                    status: task.status || 'Selesai',
                    priority: task.priority || 'low',
                    pending_reason: task.pending_reason || '',
                    client_request_date: task.client_request_date ? task.client_request_date.substring(0, 10) : ''
                };
                this.modal.techs = Array.from(techIds);
                this.onTeamChange();
            },

            searchCustomer() {
                const q = this.modal.data.tugas;
                if (!q || q.length < 2) {
                    this.customerSearchResults = [];
                    return;
                }
                this.isSearchingCustomer = true;
                
                fetch(`{{ url('/api/customers/search') }}?q=${encodeURIComponent(q)}`)
                    .then(res => res.json())
                    .then(data => {
                        this.customerSearchResults = data.map(c => ({
                            id: c.id,
                            label: `${c.full_name} (${c.customer_number || 'No CID'}) - ${c.phone || ''}`,
                            fullName: c.full_name,
                            popId: c.pop_id,
                            villageId: c.village_id
                        }));
                    })
                    .finally(() => {
                        this.isSearchingCustomer = false;
                    });
            },

            selectCustomer(c) {
                this.modal.data.customer_id = c.id;
                this.modal.data.tugas = c.fullName;
                if (c.popId) this.modal.data.pop_id = c.popId;
                if (c.villageId) this.modal.data.village_id = c.villageId;
                this.customerSearchResults = [];
            },

            showToast(type, message) {
                if (window.Toast) {
                    if (type === 'success') window.Toast.success('Sukses', message);
                    else if (type === 'error') window.Toast.error('Gagal', message);
                    else window.Toast.info('Informasi', message);
                } else {
                    alert(message);
                }
            },

            updateStatus(taskId, status) {
                if (status === 'Pending') {
                    alert("Untuk status Pending, silakan isi tanggal request client dan alasan pending melalui tombol edit detail task.");
                    window.location.reload();
                    return;
                }
                this.sendUpdateRequest(taskId, { status });
            },

            updatePriority(taskId, priority) {
                this.sendUpdateRequest(taskId, { priority });
            },

            sendUpdateRequest(taskId, data) {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch(`{{ url('/fop-tasks') }}/${taskId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify(data)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showToast('success', data.message);
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        this.showToast('error', 'Gagal memperbarui data.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    this.showToast('error', 'Terjadi kesalahan jaringan.');
                });
            }
        };
    }
</script>
@endsection
