@extends('layouts.app')

@section('title', 'Task FOP')

@section('content')
<div x-data="fopTaskPageHandler()" x-init="initTeamConflicts()" x-effect="document.body.classList.toggle('overflow-hidden', modal.open || teamConflictModal.open || teamSelectionModal.open || switchTechModal.open)" class="px-4 py-6 max-w-12xl mx-auto space-y-5">



    {{-- ══ Page Header ══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 py-2">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight font-ui">Task FOP</h1>
            <p class="text-sm text-slate-500 mt-1 font-ui">Kelola penugasan, status, dan prioritas task FOP yang sedang berjalan.</p>
        </div>
        <div class="flex items-center gap-2">
            <button x-show="teamConflictModal.conflicts.length > 0" @click="teamConflictModal.open = true"
                    class="inline-flex items-center gap-2 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 text-sm font-medium px-4 py-2 rounded transition-colors shadow-sm font-ui"
                    style="display: none;">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <span>Konflik Team (<span x-text="teamConflictModal.conflicts.length"></span>)</span>
            </button>
            <button @click="openCreateModal()"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded transition-colors shadow-sm font-ui">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Task FOP
            </button>
        </div>
    </div>

    {{-- ══ Filters (Naked) ══ --}}
    <form method="GET" action="{{ route('fop-tasks.index') }}" class="flex flex-col gap-3 pb-3">
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
                    <option value="">Semua (Proses & Pending)</option>
                    <option value="Proses" {{ request('status') === 'Proses' ? 'selected' : '' }}>Proses</option>
                    <option value="Pending" {{ request('status') === 'Pending' ? 'selected' : '' }}>Pending</option>
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
                    <a href="{{ route('fop-tasks.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors font-ui">Reset</a>
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
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-600 uppercase tracking-wider">
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
                <tbody class="divide-y divide-slate-100 text-[11px] text-slate-700">
                    @forelse($fopTasks as $task)
                        <tr class="hover:bg-slate-50 transition-colors align-top">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border {{ $task->category instanceof \App\Enums\TaskType ? $task->category->badgeClasses() : 'border-slate-200 bg-white text-slate-600' }}">
                                    {{ $task->category instanceof \App\Enums\TaskType ? $task->category->value : $task->category }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-slate-600">
                                {{ $task->task_date ? $task->task_date->format('d/m/Y H:i') : '—' }}
                                @if($task->client_request_date)
                                    <br>
                                    @if($task->client_request_date->lte(\Illuminate\Support\Carbon::today()))
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold bg-red-50 text-red-700 border border-red-100 mt-0.5">
                                            JADWAL HARI INI
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-medium bg-slate-50 text-slate-600 border border-slate-200 mt-0.5">
                                            Terjadwal — {{ $task->client_request_date->format('d/m/Y') }}
                                        </span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-2 min-w-[200px] whitespace-normal leading-tight">
                                <span class="font-medium text-slate-800">{{ $task->tugas }}</span>
                            </td>
                            <td class="px-3 py-2 whitespace-normal leading-tight text-slate-600 min-w-[120px]">
                                {{ $task->village?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-2 min-w-[150px] whitespace-normal leading-tight text-red-600">
                                {{ $task->issue ?? '—' }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-1 items-start min-w-[150px]">
                                    @php 
                                        $visibleTechs = $task->technicians->take(2); 
                                        $hiddenTechsCount = $task->technicians->count() - 2; 
                                    @endphp
                                    @forelse($visibleTechs as $tech)
                                        @php
                                            // Ambil nama depan saja untuk menghemat ruang
                                            $firstName = explode(' ', trim($tech->name))[0];
                                        @endphp
                                        <button type="button"
                                            @click="openSwitchModal({{ $task->id }}, '{{ $task->task_number }}', @js($task->tugas), '{{ $task->task_date?->toDateString() }}', {{ $tech->id }}, @js($tech->name))"
                                            class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100 hover:bg-blue-100 hover:border-blue-300 transition-colors"
                                            title="{{ $tech->name }} — klik buat Switch Teknisi">
                                            {{ \Illuminate\Support\Str::limit($firstName, 12) }}
                                        </button>
                                    @empty
                                        <span class="text-slate-400 text-[10px] italic">Unassigned</span>
                                    @endforelse
                                    
                                    @if($hiddenTechsCount > 0)
                                        <div class="relative" x-data="{ openHidden: false }">
                                            <button type="button" @click="openHidden = !openHidden"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200 hover:bg-slate-200 transition-colors">
                                                +{{ $hiddenTechsCount }}
                                            </button>
                                            <div x-show="openHidden" @click.away="openHidden = false"
                                                class="absolute z-40 mt-1 min-w-[140px] bg-surface border border-border rounded shadow-lg py-1"
                                                style="display: none;">
                                                @foreach($task->technicians->skip(2) as $tech)
                                                    <button type="button"
                                                        @click="openSwitchModal({{ $task->id }}, '{{ $task->task_number }}', @js($task->tugas), '{{ $task->task_date?->toDateString() }}', {{ $tech->id }}, @js($tech->name)); openHidden = false"
                                                        class="w-full text-left px-3 py-1.5 text-[11px] text-text-secondary hover:bg-surface-muted transition-colors">
                                                        {{ $tech->name }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if($task->team)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                        {{ $task->team->name }}
                                    </span>
                                @elseif($task->technicians->count() === 1)
                                    <button type="button" 
                                            @click="openTeamSelectionModal({{ $task->id }}, '{{ $task->task_number }}', '{{ addslashes($task->tugas) }}', '{{ $task->task_date?->format('Y-m-d') }}')" 
                                            class="text-[10px] text-blue-600 hover:text-blue-800 font-medium underline decoration-dotted">
                                        + Masukkan ke Team...
                                    </button>
                                @else
                                    @php
                                        $taskDate = $task->task_date?->toDateString();
                                        $techIds = $task->technicians->pluck('id')->all();
                                        $candidates = \App\Models\FopTaskTeam::whereDate('work_date', $taskDate)
                                            ->whereHas('members', fn($q) => $q->whereIn('users.id', $techIds))
                                            ->get()
                                            ->map(fn($t) => ['team_id' => $t->id, 'team_name' => $t->name])
                                            ->all();
                                    @endphp
                                    @if(count($candidates) >= 2)
                                        <button type="button"
                                                @click="triggerConflictModal({{ $task->id }}, '{{ $task->task_number }}', {{ json_encode($candidates) }})"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 text-red-700 border border-red-100 hover:bg-red-100 transition-colors"
                                                title="Klik untuk memilih team">
                                            ⚠️ Konflik Roster
                                        </button>
                                    @else
                                        <span class="text-slate-300 text-[10px]">—</span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
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
                                    @if($task->status->value === 'Pending')
                                        <div class="text-[10px] text-amber-600 mt-1 leading-tight whitespace-normal min-w-[120px]">
                                            @if($task->client_request_date)
                                                <span class="font-semibold">Req: {{ $task->client_request_date->format('d/m/y') }}</span><br>
                                            @endif
                                            <span>{{ $task->pending_reason }}</span>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if($canEditFopTaskType)
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
                                @else
                                    <x-countdown-timer
                                        deadline="{{ $task->slaDeadline()->toIso8601String() }}"
                                        :total-seconds="$task->slaTotalSeconds()"
                                        label="SLA {{ $task->category->label() }}"
                                        :compact="true"
                                    />
                                @endif
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
                                <p class="text-[11px] font-medium">Tidak ada data task FOP.</p>
                                <p class="text-[10px] mt-1 text-slate-400">Silakan buat task baru atau ubah filter pencarian.</p>
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

    {{-- ══ CREATE/EDIT TASK MODAL ══ --}}
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

        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-surface border border-border w-full max-w-2xl rounded-md shadow-lg relative z-10" 
                 x-show="modal.open"
                 @click.away="modal.open = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                
                <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-surface-muted rounded-t-md">
                    <h3 class="text-sm font-semibold text-text-main" x-text="modal.isEdit ? 'Edit Task FOP' : 'Tambah Task FOP'"></h3>
                    <button type="button" @click="modal.open = false" class="text-text-muted hover:text-text-main transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form :action="modal.isEdit ? '{{ url('/fop-tasks') }}/' + modal.data.id : '{{ route('fop-tasks.store') }}'" method="POST" @submit="isSubmitting = true">
                    <div class="p-5 max-h-[75vh] overflow-y-auto space-y-4">
                        @csrf
                        <template x-if="modal.isEdit">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Tipe Task <span class="text-error">*</span></label>
                                <select name="category" x-model="modal.data.category"
                                        :disabled="modal.isEdit && !canEditCategory"
                                        required
                                        class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                                    <option value="">Pilih Tipe</option>
                                    <template x-for="[key, val] in Object.entries(availableCategories)" :key="key">
                                        <option :value="key" x-text="key + ' - ' + val"></option>
                                    </template>
                                </select>
                                <template x-if="modal.isEdit && !canEditCategory">
                                    <input type="hidden" name="category" :value="modal.data.category">
                                </template>
                                <p class="mt-1 text-[10px] text-text-muted" x-show="modal.isEdit && !canEditCategory">Anda tidak punya izin ubah tipe task.</p>
                                <p class="mt-1 text-[10px] text-text-muted" x-show="!modal.isEdit">Survey &amp; Pemasangan Baru otomatis dibuat saat Registrasi Pelanggan.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Tanggal & Waktu <span class="text-error">*</span></label>
                                <input type="datetime-local" name="task_date" x-model="modal.data.task_date" required class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">POP / Cabang <span class="text-error">*</span></label>
                                <select name="pop_id" x-model="modal.data.pop_id" required class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                                    <option value="">Pilih Cabang</option>
                                    @foreach($pops as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Area (Desa) <span class="text-error">*</span></label>
                                <select name="village_id" x-model="modal.data.village_id" required class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                                    <option value="">Pilih Desa</option>
                                    @foreach($villages as $v)
                                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="relative" @click.away="customerSearchResults = []">
                                <label class="block text-xs font-medium text-text-secondary mb-1">Penugasan / Pelanggan <span class="text-error">*</span></label>
                                <input type="text" name="tugas" x-model="modal.data.tugas" 
                                       @input.debounce.300ms="searchCustomer()"
                                       @keydown.escape="customerSearchResults = []"
                                       autocomplete="off"
                                       placeholder="Ketik tugas / nama..." required class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                                
                                <input type="hidden" name="customer_id" :value="modal.data.customer_id">
                                
                                <div x-show="customerSearchResults.length > 0" class="absolute z-50 w-full bg-surface border border-border rounded mt-1 max-h-48 overflow-y-auto shadow-lg" style="display: none;">
                                    <template x-for="c in customerSearchResults" :key="c.id">
                                        <button type="button" @click="selectCustomer(c)" class="w-full text-left px-3 py-2 text-sm bg-surface hover:bg-surface-muted text-text-main border-b border-border last:border-0 outline-none">
                                            <span class="font-medium text-text-secondary" x-text="c.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Issue / Masalah <span class="text-error">*</span></label>
                                <input type="text" name="issue" x-model="modal.data.issue" placeholder="Contoh: FO CUT..." required class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                            </div>
                        </div>

                        <div class="relative" x-data="{ openTechDropdown: false }">
                            <label class="block text-xs font-medium text-text-secondary mb-1">Pilih Teknisi <span class="text-error">*</span></label>
                            <div @click="openTechDropdown = true" @click.away="openTechDropdown = false" class="min-h-[38px] w-full border border-border rounded bg-surface px-2 py-1.5 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary cursor-text flex items-center gap-2 flex-wrap">
                                <template x-for="techId in modal.techs" :key="techId">
                                    <span class="inline-flex items-center gap-1 bg-surface-muted border border-border text-text-secondary text-xs font-medium px-2 py-0.5 rounded">
                                        <span x-text="getTechName(techId)"></span>
                                        <button type="button" @click.stop="toggleTech(techId)" class="hover:text-error transition-colors">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </span>
                                </template>
                                <input type="text" x-model="searchTech" @focus="openTechDropdown = true" placeholder="Cari..." class="flex-1 min-w-[100px] outline-none text-sm text-text-main bg-transparent border-none p-0 focus:ring-0">
                            </div>

                            <div x-show="openTechDropdown" class="absolute z-50 w-full bg-surface border border-border rounded shadow-lg mt-1 max-h-48 overflow-y-auto" style="display: none;">
                                @foreach($technicians as $tech)
                                    <label class="flex items-center gap-2 px-3 py-2 bg-surface hover:bg-surface-muted cursor-pointer border-b border-border last:border-0"
                                           x-show="searchTech === '' || '{{ strtolower($tech->name) }}'.includes(searchTech.toLowerCase())">
                                        <input type="checkbox" name="technicians[]" value="{{ $tech->id }}"
                                               :checked="modal.techs.includes({{ $tech->id }})"
                                               @change="toggleTech({{ $tech->id }})"
                                               class="w-4 h-4 rounded border-border bg-surface text-primary focus:ring-primary">
                                        <span class="text-sm text-text-secondary">{{ $tech->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <input type="hidden" :required="modal.techs.length === 0" class="absolute w-0 h-0 opacity-0" name="technicians_required">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Status <span class="text-error">*</span></label>
                                <select name="status" x-model="modal.data.status" required class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                                    <option value="Proses">Proses</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Selesai">Selesai</option>
                                    <option value="Cancel">Cancel</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Prioritas <span class="text-error">*</span></label>
                                <select name="priority" x-model="modal.data.priority" 
                                        :disabled="modal.isEdit && !canEditCategory"
                                        required class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                                    <option value="low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                                <template x-if="modal.isEdit && !canEditCategory">
                                    <input type="hidden" name="priority" :value="modal.data.priority">
                                </template>
                            </div>
                        </div>

                        <div x-show="modal.data.status === 'Pending'" class="space-y-3 bg-surface-muted border border-border rounded p-3" style="display: none;">
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Alasan Pending <span class="text-error">*</span></label>
                                <input type="text" name="pending_reason" x-model="modal.data.pending_reason" :required="modal.data.status === 'Pending'" class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Tgl Request Client <span class="text-error">*</span></label>
                                <input type="date" name="client_request_date" x-model="modal.data.client_request_date" :required="modal.data.status === 'Pending'" class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted font-mono">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-text-secondary mb-1">Catatan</label>
                            <textarea name="notes" x-model="modal.data.notes" rows="2" class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted"></textarea>
                        </div>
                    </div>

                    <div class="px-5 py-3.5 border-t border-border bg-surface-muted flex items-center justify-end gap-3 rounded-b-md">
                        <button type="button" @click="modal.open = false" class="btn-secondary">Batal</button>
                        <button type="submit" :disabled="isSubmitting" class="btn-primary disabled:opacity-50">
                            <span x-show="!isSubmitting">Simpan</span>
                            <span x-show="isSubmitting">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ══ KONFLIK TEAM MODAL (Skenario C3: task narik teknisi dari >=2 team beda) ══ --}}
    <div x-show="teamConflictModal.open"
         x-effect="document.body.classList.toggle('overflow-hidden', teamConflictModal.open)"
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="teamConflictModal.open = false"></div>

        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-surface border border-border w-full max-w-lg rounded-md shadow-lg relative z-10"
                 x-show="teamConflictModal.open"
                 @click.away="teamConflictModal.open = false">

                <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-surface-muted rounded-t-md">
                    <h3 class="text-sm font-semibold text-text-main">Konflik Team Terdeteksi</h3>
                    <button type="button" @click="teamConflictModal.open = false" class="text-text-muted hover:text-text-main transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 max-h-[75vh] overflow-y-auto space-y-4">
                    <template x-for="c in teamConflictModal.conflicts" :key="c.task_id">
                        <div class="border border-border rounded-md p-3">
                            <p class="text-xs text-text-secondary mb-2">
                                Task <span class="font-semibold" x-text="c.task_number"></span> menugaskan teknisi yang masing-masing sudah ada di team berbeda. Taruh di team mana?
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="cand in c.candidates" :key="cand.team_id">
                                    <button type="button" @click="resolveTeamConflict(c.task_id, cand.team_id)" class="btn-secondary text-xs" x-text="cand.team_name"></button>
                                </template>
                                <button type="button" @click="resolveTeamConflict(c.task_id, null)" class="btn-secondary text-xs">Buat Team Baru</button>
                            </div>
                        </div>
                    </template>
                    <p x-show="teamConflictModal.conflicts.length === 0" class="text-xs text-text-muted text-center py-3">Tidak ada konflik.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ PEMILIHAN TEAM MODAL (Skenario C2: masukkan teknisi solo/baru ke team) ══ --}}
    <div x-show="teamSelectionModal.open"
         x-effect="document.body.classList.toggle('overflow-hidden', teamSelectionModal.open || modal.open || teamConflictModal.open)"
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="teamSelectionModal.open = false"></div>

        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-surface border border-border w-full max-w-lg rounded-md shadow-lg relative z-10"
                 x-show="teamSelectionModal.open"
                 @click.away="teamSelectionModal.open = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-4">

                <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-surface-muted rounded-t-md">
                    <h3 class="text-sm font-semibold text-text-main">Pilih Team untuk Task</h3>
                    <button type="button" @click="teamSelectionModal.open = false" class="text-text-muted hover:text-text-main transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 max-h-[75vh] overflow-y-auto space-y-4">
                    <div class="border border-border rounded-md p-3 bg-surface-muted/50">
                        <p class="text-xs text-text-secondary leading-relaxed">
                            Pilih tim kerja pada tanggal <span class="font-semibold text-text-main" x-text="teamSelectionModal.taskDate"></span> untuk memasukkan task <span class="font-semibold text-text-main" x-text="teamSelectionModal.taskNumber"></span> (<span x-text="teamSelectionModal.taskTugas"></span>):
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">Tim Tersedia</label>
                        <div class="flex flex-col gap-2">
                            <template x-for="t in teamSelectionModal.teams" :key="t.id">
                                <button type="button" 
                                        @click="assignToTeam(teamSelectionModal.taskId, t.id); teamSelectionModal.open = false" 
                                        class="w-full text-left px-4 py-3 border border-border rounded-md hover:bg-surface-muted hover:border-primary/50 transition-colors flex items-center justify-between group">
                                    <div>
                                        <span class="text-xs font-semibold text-text-main group-hover:text-primary transition-colors" x-text="t.name"></span>
                                        <div class="flex items-center gap-1.5 mt-1">
                                            <template x-for="m in t.members" :key="m.id">
                                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100" x-text="m.name"></span>
                                            </template>
                                        </div>
                                    </div>
                                    <svg class="w-4 h-4 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </template>
                            <p x-show="teamSelectionModal.teams.length === 0" class="text-xs text-text-muted text-center py-4 bg-surface-muted/30 border border-dashed border-border rounded-md">
                                Tidak ada tim kerja pada tanggal ini.
                            </p>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-border flex items-center justify-between gap-3">
                        <button type="button" @click="assignToTeam(teamSelectionModal.taskId, null); teamSelectionModal.open = false" class="btn-primary text-xs w-full py-2.5 flex justify-center items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Buat Team Baru
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ SWITCH TEKNISI MODAL (Task 2: switch teknisi antar team, 1 payload atomic) ══ --}}
    <div x-show="switchTechModal.open"
         x-effect="document.body.classList.toggle('overflow-hidden', switchTechModal.open || modal.open || teamConflictModal.open || teamSelectionModal.open)"
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="switchTechModal.open = false"></div>

        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-surface border border-border w-full max-w-md rounded-md shadow-lg relative z-10"
                 x-show="switchTechModal.open"
                 @click.away="switchTechModal.open = false">

                <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-surface-muted rounded-t-md">
                    <h3 class="text-sm font-semibold text-text-main">Switch Teknisi antar Team</h3>
                    <button type="button" @click="switchTechModal.open = false" class="text-text-muted hover:text-text-main transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <p class="text-xs text-text-secondary">
                        Pindahkan <span class="font-semibold text-text-main" x-text="switchTechModal.technicianName"></span>
                        dari task <span class="font-semibold text-text-main" x-text="switchTechModal.fromTaskNumber"></span>
                        (<span x-text="switchTechModal.fromTaskTugas"></span>) ke task lain — wajib pilih pengganti supaya task asal gak kosong teknisi.
                    </p>

                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Task Tujuan <span class="text-error">*</span></label>
                        <select x-model="switchTechModal.toTaskId" class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">— Pilih Task Tujuan —</option>
                            <template x-for="t in switchTargetTasks" :key="t.id">
                                <option :value="t.id" x-text="t.task_number + ' — ' + t.tugas"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-[10px] text-text-muted" x-show="switchTargetTasks.length === 0">Gak ada task lain di tanggal yang sama (<span x-text="switchTechModal.fromTaskDate"></span>) buat dijadikan tujuan.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Pengganti di Task Asal <span class="text-error">*</span></label>
                        <select x-model="switchTechModal.replacementId" class="w-full text-sm bg-surface text-text-main border border-border rounded px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">— Pilih Pengganti —</option>
                            <template x-for="t in switchReplacementCandidates" :key="t.id">
                                <option :value="t.id" x-text="t.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="px-5 py-3.5 border-t border-border bg-surface-muted flex items-center justify-end gap-3 rounded-b-md">
                    <button type="button" @click="switchTechModal.open = false" class="btn-secondary text-xs">Batal</button>
                    <button type="button" :disabled="switchTechModal.isSubmitting || !switchTechModal.toTaskId || !switchTechModal.replacementId"
                            @click="submitSwitchTechnician()" class="btn-primary text-xs disabled:opacity-50">
                        <span x-show="!switchTechModal.isSubmitting">Switch Sekarang</span>
                        <span x-show="switchTechModal.isSubmitting">Memproses...</span>
                    </button>
                </div>
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
                isEdit: false,
                data: {
                    id: '', task_number: '', task_date: '', category: '', tugas: '',
                    customer_id: '', village_id: '', pop_id: '', issue: '', notes: '',
                    status: 'Proses', priority: 'low', pending_reason: '', client_request_date: ''
                },
                techs: []
            },
            teamConflictModal: { open: false, conflicts: @json($teamConflicts ?? []) },
            teamSelectionModal: { open: false, taskId: null, taskNumber: '', taskTugas: '', taskDate: '', teams: [] },
            switchTechModal: {
                open: false, technicianId: null, technicianName: '',
                fromTaskId: null, fromTaskNumber: '', fromTaskTugas: '', fromTaskDate: '',
                toTaskId: '', replacementId: '', isSubmitting: false,
            },
            techniciansData: {!! json_encode($technicians->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->toArray()) !!},
            teamsData: @json($teams),
            allTasksData: @json($switchTargetTasks ?? []),

            openTeamSelectionModal(taskId, taskNumber, taskTugas, taskDate) {
                this.teamSelectionModal.taskId = taskId;
                this.teamSelectionModal.taskNumber = taskNumber;
                this.teamSelectionModal.taskTugas = taskTugas;
                this.teamSelectionModal.taskDate = taskDate;
                this.teamSelectionModal.teams = this.teamsData.filter(t => t.work_date === taskDate);
                this.teamSelectionModal.open = true;
            },

            openSwitchModal(taskId, taskNumber, taskTugas, taskDate, techId, techName) {
                this.switchTechModal = {
                    open: true,
                    technicianId: techId,
                    technicianName: techName,
                    fromTaskId: taskId,
                    fromTaskNumber: taskNumber,
                    fromTaskTugas: taskTugas,
                    fromTaskDate: taskDate,
                    toTaskId: '',
                    replacementId: '',
                    isSubmitting: false,
                };
            },

            get switchTargetTasks() {
                return this.allTasksData.filter(t =>
                    t.task_date === this.switchTechModal.fromTaskDate &&
                    t.id !== this.switchTechModal.fromTaskId
                );
            },

            get switchReplacementCandidates() {
                return this.techniciansData.filter(t => t.id !== this.switchTechModal.technicianId);
            },

            submitSwitchTechnician() {
                if (!this.switchTechModal.toTaskId || !this.switchTechModal.replacementId) return;
                this.switchTechModal.isSubmitting = true;
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch('{{ route('fop-tasks.switch-technician') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({
                        technician_id: this.switchTechModal.technicianId,
                        from_task_id: this.switchTechModal.fromTaskId,
                        to_task_id: this.switchTechModal.toTaskId,
                        replacement_technician_id: this.switchTechModal.replacementId,
                    })
                })
                .then(res => res.json())
                .then(data => {
                    this.switchTechModal.isSubmitting = false;
                    if (data.success) {
                        this.showToast('success', data.message);
                        this.switchTechModal.open = false;
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        this.showToast('error', data.message || 'Gagal switch teknisi.');
                    }
                })
                .catch(() => {
                    this.switchTechModal.isSubmitting = false;
                    this.showToast('error', 'Terjadi kesalahan jaringan.');
                });
            },

            allCategoriesData: @json($categories),
            manualCategoriesData: @json($manualCategories),
            canEditCategory: @json($canEditFopTaskType),

            get availableCategories() {
                return this.modal.isEdit ? this.allCategoriesData : this.manualCategoriesData;
            },

            triggerConflictModal(taskId, taskNumber, candidates) {
                this.teamConflictModal.conflicts = [{
                    task_id: taskId,
                    task_number: taskNumber,
                    candidates: candidates
                }];
                this.teamConflictModal.open = true;
            },

            initTeamConflicts() {
                if (this.teamConflictModal.conflicts.length > 0) {
                    this.teamConflictModal.open = true;
                }
            },

            resolveTeamConflict(taskId, teamId) {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch(`{{ url('/fop-tasks') }}/${taskId}/assign-to-team`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ team_id: teamId })
                })
                .then(res => res.json())
                .then(data => {
                    this.teamConflictModal.conflicts = this.teamConflictModal.conflicts.filter(c => c.task_id !== taskId);
                    if (this.teamConflictModal.conflicts.length === 0) this.teamConflictModal.open = false;
                    this.showToast('success', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                })
                .catch(() => this.showToast('error', 'Terjadi kesalahan jaringan.'));
            },

            assignToTeam(taskId, teamId) {
                if (teamId === undefined) return;
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch(`{{ url('/fop-tasks') }}/${taskId}/assign-to-team`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ team_id: teamId })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showToast('success', data.message);
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        if (data.team_conflicts && data.team_conflicts.length > 0) {
                            this.teamConflictModal.conflicts = data.team_conflicts;
                            this.teamConflictModal.open = true;
                            this.showToast('warning', 'Konflik team terdeteksi.');
                        } else {
                            this.showToast('error', data.message || 'Gagal memasukkan ke Team.');
                        }
                    }
                })
                .catch(() => this.showToast('error', 'Terjadi kesalahan jaringan.'));
            },

            getTechName(id) {
                const tech = this.techniciansData.find(t => t.id == id);
                return tech ? tech.name : '';
            },

            async searchCustomer() {
                this.modal.data.customer_id = '';
                if (this.modal.data.tugas.length < 2) { 
                    this.customerSearchResults = []; 
                    return; 
                }
                try {
                    const res = await fetch(`/api/tasks/search-customers?q=${encodeURIComponent(this.modal.data.tugas)}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    this.customerSearchResults = await res.json();
                } catch (e) { 
                    this.customerSearchResults = []; 
                }
            },
            
            selectCustomer(c) {
                this.modal.data.tugas = c.label;
                this.modal.data.customer_id = c.id;
                this.customerSearchResults = [];
            },

            openCreateModal() {
                this.modal.isEdit = false;
                this.searchTech = '';
                this.isSubmitting = false;
                
                const now = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                const defaultDate = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;

                this.modal.data = {
                    id: '', task_number: '', task_date: defaultDate, category: '', tugas: '',
                    customer_id: '', village_id: '', pop_id: '', issue: '', notes: '',
                    status: 'Proses', priority: 'low', pending_reason: '', client_request_date: ''
                };
                this.modal.techs = [];
                this.modal.open = true;
            },

            openEditModal(task, assignedTechs) {
                this.modal.isEdit = true;
                this.searchTech = '';
                this.isSubmitting = false;
                this.modal.data = {
                    id: task.id,
                    task_number: task.task_number,
                    task_date: task.task_date ? task.task_date.substring(0, 16) : '',
                    category: task.category,
                    tugas: task.tugas,
                    customer_id: task.customer_id || '',
                    village_id: task.village_id || '',
                    pop_id: task.pop_id || '',
                    issue: task.issue || '',
                    notes: task.notes || '',
                    status: task.status,
                    priority: task.priority,
                    pending_reason: task.pending_reason || '',
                    client_request_date: task.client_request_date ? task.client_request_date.substring(0, 10) : ''
                };
                this.modal.techs = Array.isArray(assignedTechs) ? assignedTechs : [];
                this.modal.open = true;
            },

            toggleTech(id) {
                const index = this.modal.techs.indexOf(id);
                if (index > -1) {
                    this.modal.techs.splice(index, 1);
                } else {
                    this.modal.techs.push(id);
                    this.searchTech = '';
                }
            },

            showToast(type, message) {
                if (window.Toast) {
                    if (type === 'success') window.Toast.success('Berhasil', message);
                    else if (type === 'error') window.Toast.error('Gagal', message);
                    else if (type === 'warning') window.Toast.warning('Peringatan', message);
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
