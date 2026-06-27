@extends('layouts.app')

@section('title', $task->task_number . ' — Task Detail')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6 space-y-5">

    {{-- ══ Breadcrumb ═══════════════════════════════════════════════ --}}
    <nav class="flex items-center gap-1.5 text-xs text-text-muted">
        @can('viewAll', \App\Models\Task::class)
        <a href="{{ route('tasks.index') }}" class="hover:text-primary transition-colors">Task</a>
        @else
        <a href="{{ route('tasks.own') }}" class="hover:text-primary transition-colors">Task Saya</a>
        @endcan
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-mono">{{ $task->task_number }}</span>
    </nav>

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex items-start justify-between gap-4">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                {{-- Status badge --}}
                @php
                    $statusStyle = match($task->status->value) {
                        'terjadwal'  => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
                        'in_progress'=> 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
                        'selesai'    => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
                        'dibatalkan' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
                        default      => 'background:var(--color-surface-muted); color:var(--color-text-muted); border-color:var(--color-border)',
                    };
                @endphp
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full border" style="{{ $statusStyle }}">
                    {{ $task->status->label() }}
                </span>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full border {{ $task->task_type->cardClasses() }}">
                    {{ $task->task_type->label() }}
                </span>
                <span class="font-mono text-xs text-text-muted">{{ $task->task_number }}</span>
                @if($task->isOverSla())
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full border"
                      style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                    Melewati SLA
                </span>
                @endif
            </div>
            <h1 class="text-xl font-semibold text-text-main">{{ $task->title }}</h1>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @can('edit', $task)
            <a href="{{ route('tasks.edit', $task) }}"
               class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border border-border rounded-md bg-surface hover:bg-surface-muted text-text-secondary transition-colors">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
            </a>
            @endcan
            @can('cancel', $task)
            <button x-data @click="$dispatch('open-modal', 'cancel-task')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border rounded-md transition-colors"
                    style="border-color:var(--color-error-border); color:var(--color-error); background:var(--color-error-bg)">
                Batalkan
            </button>
            @endcan
        </div>
    </div>

    {{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

    {{-- ══ Metric Strip ═════════════════════════════════════════════ --}}
    <div class="bg-surface border border-border rounded-lg overflow-hidden">
        <div class="flex divide-x divide-border">
            {{-- Tipe --}}
            <div class="flex-1 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Tipe</p>
                <p class="text-sm font-semibold text-text-main">{{ $task->task_type->label() }}</p>
            </div>
            {{-- Jadwal --}}
            <div class="flex-1 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Jadwal</p>
                <p class="text-sm font-semibold font-mono text-text-main">{{ $task->scheduled_at?->format('H:i') }}</p>
                <p class="text-[11px] text-text-muted">{{ $task->scheduled_at?->translatedFormat('d M Y') }}</p>
            </div>
            {{-- Durasi vs SLA --}}
            <div class="flex-1 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">SLA</p>
                <p class="text-sm font-semibold font-mono {{ $task->isOverSla() ? '' : 'text-text-main' }}"
                   style="{{ $task->isOverSla() ? 'color:var(--color-error)' : '' }}">
                    {{ $task->sla_minutes }}mnt
                </p>
                @if($task->actualDurationMinutes() !== null)
                <p class="text-[11px] text-text-muted">Aktual: {{ $task->actualDurationMinutes() }}mnt</p>
                @endif
            </div>
            {{-- POP --}}
            <div class="flex-1 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">POP</p>
                <p class="text-sm font-semibold text-text-main">{{ $task->pop?->name ?? '—' }}</p>
            </div>
            {{-- Checklist --}}
            <div class="flex-1 px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Checklist</p>
                <p class="text-sm font-semibold font-mono text-text-main">
                    {{ $task->checklists->where('is_checked', true)->count() }}/{{ $task->checklists->count() }}
                </p>
                <p class="text-[11px]" style="color:{{ $task->evidences->count() > 0 ? 'var(--color-success)' : 'var(--color-text-muted)' }}">
                    {{ $task->evidences->count() }} foto
                </p>
            </div>
        </div>
    </div>

    {{-- ══ Waktu Pengerjaan — tampil setelah task selesai ═══════════════ --}}
    @if($task->status->value === 'selesai' && $task->started_at && $task->completed_at)
    @php
        $showStartedAt   = $task->started_at;
        $showCompletedAt = $task->completed_at;
        $showActualMin   = (int) $showStartedAt->diffInMinutes($showCompletedAt);
        $showHours       = intdiv($showActualMin, 60);
        $showRemMins     = $showActualMin % 60;
        $showDuration    = $showHours > 0
            ? "{$showHours} jam {$showRemMins} menit"
            : "{$showActualMin} menit";
        $showOverSla     = $showActualMin > $task->sla_minutes;
        $showTypeLabel   = $task->task_type->value === 'pemasangan' ? 'Pemasangan' : 'Survey';
    @endphp
    <div class="bg-surface border border-border rounded-lg overflow-hidden">
        <div class="px-5 py-3 border-b border-border flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">
                Waktu {{ $showTypeLabel }}
            </p>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                  style="{{ $showOverSla
                      ? 'background:var(--color-error-bg); color:var(--color-error); border:1px solid var(--color-error-border)'
                      : 'background:var(--color-success-bg); color:var(--color-success); border:1px solid var(--color-success-border)' }}">
                {{ $showOverSla ? 'Over SLA' : 'Dalam SLA' }}
            </span>
        </div>
        <div class="px-5 py-4">
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Mulai</p>
                    <p class="font-mono font-semibold text-text-main">{{ $showStartedAt->format('H:i') }}</p>
                    <p class="text-[11px] text-text-muted">{{ $showStartedAt->translatedFormat('d M Y') }}</p>
                </div>
                <svg class="h-4 w-4 text-text-muted mt-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Selesai</p>
                    <p class="font-mono font-semibold text-text-main">{{ $showCompletedAt->format('H:i') }}</p>
                    <p class="text-[11px] text-text-muted">{{ $showCompletedAt->translatedFormat('d M Y') }}</p>
                </div>
                <div class="ml-auto text-right">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Durasi Aktual</p>
                    <p class="font-mono font-semibold text-lg"
                       style="color:{{ $showOverSla ? 'var(--color-error)' : 'var(--color-success)' }}">
                        {{ $showDuration }}
                    </p>
                    <p class="text-[11px] text-text-muted">SLA: {{ $task->sla_minutes }} menit</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ══ Info Utama ════════════════════════════════════════════════ --}}
    <div class="bg-surface border border-border rounded-lg">
        <div class="px-5 py-3 border-b border-border">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Informasi Task</p>
        </div>
        <div class="px-5 py-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-3 gap-x-8 text-sm">
                <div class="flex gap-3 border-b border-border pb-3">
                    <span class="text-text-muted w-32 shrink-0 text-xs pt-0.5">FOP / Koordinator</span>
                    <span class="text-text-main font-medium">{{ $task->fop?->name ?? '—' }}</span>
                </div>
                <div class="flex gap-3 border-b border-border pb-3">
                    <span class="text-text-muted w-32 shrink-0 text-xs pt-0.5">Pelanggan</span>
                    <span class="text-text-main font-medium">
                        @if($task->customer)
                        <a href="{{ route('customers.show', $task->customer) }}"
                           class="hover:underline" style="color:var(--color-primary)">
                            {{ $task->customer->full_name }}
                        </a>
                        <span class="font-mono text-xs text-text-muted ml-1">{{ $task->customer->display_id }}</span>
                        @else
                        <span class="text-text-muted">—</span>
                        @endif
                    </span>
                </div>
                @if($task->description)
                <div class="flex gap-3 sm:col-span-2 border-b border-border pb-3">
                    <span class="text-text-muted w-32 shrink-0 text-xs pt-0.5">Catatan</span>
                    <span class="text-text-secondary leading-relaxed">{{ $task->description }}</span>
                </div>
                @endif
                @if($task->pending_reason)
                <div class="flex gap-3 sm:col-span-2">
                    <span class="text-text-muted w-32 shrink-0 text-xs pt-0.5">Alasan Pending</span>
                    <span class="font-medium" style="color:var(--color-warning)">{{ $task->pending_reason }}</span>
                </div>
                @endif
                @if($task->cancel_reason)
                <div class="flex gap-3 sm:col-span-2">
                    <span class="text-text-muted w-32 shrink-0 text-xs pt-0.5">Alasan Dibatalkan</span>
                    <span class="font-medium" style="color:var(--color-error)">{{ $task->cancel_reason }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══ Tim Teknisi ══════════════════════════════════════════════ --}}
    <div class="bg-surface border border-border rounded-lg">
        <div class="px-5 py-3 border-b border-border">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Tim Teknisi</p>
        </div>
        <div class="px-5 py-4">
            @if($task->teamMembers->count() > 0)
            <div class="flex flex-wrap gap-2">
                @foreach($task->teamMembers as $member)
                <div class="flex items-center gap-2.5 bg-background border border-border rounded-md px-3 py-2 w-full sm:w-auto">
                    <div class="h-7 w-7 rounded-full bg-primary-soft flex items-center justify-center text-xs font-bold shrink-0"
                         style="color:var(--color-primary)">
                        {{ strtoupper(substr($member->user?->name ?? '?', 0, 2)) }}
                    </div>
                    <div class="flex-1 min-w-0 pr-4">
                        <p class="text-xs font-semibold text-text-main truncate">{{ $member->user?->name ?? 'User dihapus' }}</p>
                        <p class="text-[10px] text-text-muted capitalize">{{ $member->role_in_task }}</p>
                    </div>
                    @can('task.assign.team')
                        @if(in_array($task->status->value, ['terjadwal', 'in_progress']))
                            <button type="button" 
                                x-data="" 
                                x-on:click="$dispatch('open-modal', 'swap-technician-{{ $member->user_id }}')" 
                                class="text-xs text-primary hover:underline whitespace-nowrap">
                                Ganti
                            </button>
                            
                            <x-ui.modal name="swap-technician-{{ $member->user_id }}" title="Ganti Teknisi" maxWidth="sm">
                                <form action="{{ route('tasks.team.update', $task) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="old_user_id" value="{{ $member->user_id }}">
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium mb-1">Pilih Teknisi Pengganti</label>
                                        <select name="new_user_id" class="w-full rounded-md border-border text-sm focus:border-primary focus:ring-primary" required>
                                            <option value="">-- Pilih Teknisi --</option>
                                            @foreach(\App\Models\User::whereHas('role', fn($q) => $q->where('code', 'teknisi'))->where('id', '!=', $member->user_id)->orderBy('name')->get() as $tek)
                                                <option value="{{ $tek->id }}">{{ $tek->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium mb-1">Jadwal Pelaksanaan (Opsional)</label>
                                        <input type="datetime-local" name="scheduled_at" 
                                               value="{{ $task->scheduled_at ? $task->scheduled_at->format('Y-m-d\TH:i') : '' }}" 
                                               class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-background focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
                                        <p class="text-[11px] text-text-muted mt-1">Biarkan default atau kosongkan jika tidak ingin mengubah jadwal.</p>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'swap-technician-{{ $member->user_id }}')">Batal</x-ui.button>
                                        <x-ui.button type="submit" variant="primary">Simpan</x-ui.button>
                                    </div>
                                </form>
                            </x-ui.modal>
                        @endif
                    @endcan
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-text-muted">Belum ada anggota tim.</p>
            @endif
        </div>

    </div>

    {{-- ══ Audit Log (History) ════════════════════════════════════════ --}}
    @if(auth()->user()->hasRole(['owner', 'admin', 'fop']))
    <div class="bg-surface border border-border rounded-lg mt-4">
        <div class="px-5 py-3 border-b border-border">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Riwayat Status (Audit Log)</p>
        </div>
        <div class="px-5 py-4">
            @if($task->auditLogs && $task->auditLogs->count() > 0)
            <div class="relative border-l border-border ml-3 space-y-6">
                @foreach($task->auditLogs as $log)
                <div class="relative pl-5">
                    {{-- Timeline node --}}
                    <div class="absolute -left-1.5 top-1.5 h-3 w-3 rounded-full bg-border border-2 border-surface"></div>
                    <div class="mb-1 flex items-center justify-between">
                        <p class="text-xs font-semibold capitalize" style="color:var(--color-text-main)">
                            {{ str_replace('_', ' ', $log->action) }}
                        </p>
                        <span class="text-[10px] text-text-muted font-mono">{{ $log->created_at->format('d M Y, H:i') }}</span>
                    </div>
                    <p class="text-[11px] text-text-secondary">Oleh: <span class="font-medium text-text-main">{{ $log->user?->name ?? 'System' }}</span></p>
                    
                    @if($log->action === 'cancelled' && isset($log->new_values['cancel_reason']))
                    <div class="mt-1 p-2 bg-error-bg/20 border border-error-border rounded-md">
                        <p class="text-[10px] text-error font-medium">Alasan: {{ $log->new_values['cancel_reason'] }}</p>
                    </div>
                    @elseif($log->action === 'rejected' && isset($log->new_values['reject_reason']))
                    <div class="mt-1 p-2 bg-error-bg/20 border border-error-border rounded-md">
                        <p class="text-[10px] text-error font-medium">Alasan: {{ $log->new_values['reject_reason'] }}</p>
                    </div>
                    @elseif($log->action === 'completed' && isset($log->new_values['status']))
                    <div class="mt-1 text-[10px] text-success font-medium">Task ditandai selesai oleh teknisi.</div>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-text-muted">Belum ada riwayat aktivitas.</p>
            @endif
        </div>
    </div>
    @endif


    {{-- ══ Checklist ════════════════════════════════════════════════ --}}
    @if($task->checklists->count() > 0)
    <div class="bg-surface border border-border rounded-lg" x-data="{ done: {{ $task->checklists->where('is_checked', true)->count() }}, total: {{ $task->checklists->count() }} }">
        <div class="px-5 py-3 border-b border-border flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Checklist</p>
            <span class="text-xs font-semibold font-mono text-text-secondary"
                  x-text="`${done}/${total} item selesai`"></span>
        </div>
        {{-- Progress bar --}}
        <div class="h-1 bg-border">
            <div class="h-full transition-all duration-500" style="background:var(--color-success)"
                 :style="`width: ${total > 0 ? (done/total*100) : 0}%`"></div>
        </div>
        <div class="divide-y divide-border">
            @foreach($task->checklists as $checklist)
            <div class="flex items-start gap-3 px-5 py-3 {{ $checklist->is_checked ? 'bg-success-bg/20' : '' }}">
                {{-- Checkbox or static indicator --}}
                @can('updateChecklist', $task)
                @if($task->status->value === 'in_progress')
                <input type="checkbox"
                       id="show-cl-{{ $checklist->id }}"
                       {{ $checklist->is_checked ? 'checked' : '' }}
                       x-data
                       @change="$dispatch('checklist-toggle', { id: {{ $checklist->id }}, checked: $event.target.checked })"
                       class="h-4 w-4 mt-0.5 rounded shrink-0"
                       style="accent-color: var(--color-success)" />
                @else
                <div class="h-4 w-4 mt-0.5 rounded border-2 flex items-center justify-center shrink-0"
                     style="{{ $checklist->is_checked ? 'background:var(--color-success); border-color:var(--color-success)' : 'border-color:var(--color-border)' }}">
                    @if($checklist->is_checked)
                    <svg class="h-2.5 w-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    @endif
                </div>
                @endif
                @else
                <div class="h-4 w-4 mt-0.5 rounded border-2 flex items-center justify-center shrink-0"
                     style="{{ $checklist->is_checked ? 'background:var(--color-success); border-color:var(--color-success)' : 'border-color:var(--color-border)' }}">
                    @if($checklist->is_checked)
                    <svg class="h-2.5 w-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    @endif
                </div>
                @endcan

                <div class="flex-1 min-w-0">
                    <p class="text-sm leading-relaxed {{ $checklist->is_checked ? 'line-through text-text-muted' : 'text-text-secondary' }}">
                        {{ $checklist->item }}
                        @if($checklist->is_required)
                        <span class="text-error text-xs ml-0.5">*</span>
                        @endif
                    </p>
                    @if($checklist->is_checked && $checklist->checkedByUser)
                    <p class="text-[11px] text-text-muted mt-0.5 font-mono">
                        ✓ {{ $checklist->checkedByUser->name }} · {{ $checklist->checked_at?->diffForHumans() }}
                    </p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ══ Bukti Foto ════════════════════════════════════════════════ --}}
    <div class="bg-surface border border-border rounded-lg"
         x-data="evidenceSection({{ $task->id }}, {{ $task->canComplete() ? 'true' : 'false' }}, {{ $task->evidences->count() }})">
        <div class="px-5 py-3 border-b border-border flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Foto Bukti</p>
            <span class="text-xs font-semibold font-mono text-text-secondary" x-text="`${evidenceCount} foto`"></span>
        </div>
        <div class="p-4">
            {{-- Grid foto --}}
            @if($task->evidences->count() > 0)
            <div class="grid grid-cols-3 gap-2 mb-4">
                @foreach($task->evidences as $evidence)
                <div class="relative group rounded-md overflow-hidden aspect-square bg-surface-muted border border-border">
                    <img src="{{ asset('storage/' . $evidence->file_path) }}"
                         alt="{{ $evidence->caption ?? 'Bukti' }}"
                         class="h-full w-full object-cover">
                    @if($evidence->caption)
                    <div class="absolute bottom-0 left-0 right-0 px-2 py-1 text-[10px] text-white truncate"
                         style="background: rgba(15,23,42,0.7)">
                        {{ $evidence->caption }}
                    </div>
                    @endif
                    @can('edit', $task)
                    <button @click="deleteEvidence({{ $evidence->id }})"
                            class="absolute top-1 right-1 h-6 w-6 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-white"
                            style="background:var(--color-error)">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    @endcan
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-text-muted mb-4">Belum ada foto bukti.</p>
            @endif

            {{-- Upload --}}
            @can('uploadEvidence', $task)
            @if($task->status->value === 'in_progress')
            <label class="block cursor-pointer">
                <input type="file" accept="image/*" capture="environment" class="hidden" @change="uploadEvidence($event.target)" />
                <div class="flex items-center justify-center gap-2 text-xs font-medium py-3 border border-dashed rounded-md transition-colors"
                     style="border-color:var(--color-primary-border); color:var(--color-primary)"
                     onmouseover="this.style.background='var(--color-primary-soft)'"
                     onmouseout="this.style.background=''">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Upload Foto Bukti
                </div>
            </label>
            <p x-show="uploadError" x-text="uploadError" class="text-xs mt-2" style="color:var(--color-error)"></p>
            @endif
            @endcan
        </div>
    </div>

    {{-- ══ Action Buttons (Teknisi) ══════════════════════════════════ --}}
    @if(in_array($task->status->value, ['terjadwal', 'in_progress']))
    <div class="flex items-center justify-end gap-3">
        @if($task->status->value === 'terjadwal')
            @if($task->task_type->value === 'survey')
                @if(auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                <form action="{{ route('customers.survey.start', $task->customer_id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Survey
                    </button>
                </form>
                @endif
            @elseif($task->task_type->value === 'pemasangan')
                @if(auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                <form action="{{ route('customers.installation.start', $task->customer_id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Pemasangan
                    </button>
                </form>
                @endif
            @else
                @can('statusStart', $task)
                <form action="{{ route('tasks.start', $task) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Task
                    </button>
                </form>
                @endcan
            @endif
        @endif

        @can('statusPending', $task)
        @if($task->status->value === 'in_progress')
        <button x-data @click="$dispatch('open-modal', 'pending-task')"
                class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-md border transition-colors"
                style="border-color:var(--color-warning-border); color:var(--color-warning)">
            Pending
        </button>
        @endif
        @endcan

        @can('statusComplete', $task)
        @if($task->status->value === 'in_progress')
            @if($task->task_type->value === 'survey')
                <button type="button"
                        @click="$dispatch('open-survey-report')"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                        style="background:var(--color-success)">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Laporan Survey
                </button>
            @elseif($task->task_type->value === 'pemasangan')
                <button type="button"
                        @click="$dispatch('open-install-report')"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                        style="background:var(--color-success)">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Laporan Pemasangan
                </button>
            @else
                <form action="{{ route('tasks.complete', $task) }}" method="POST">
                    @csrf
                    <button type="submit"
                            @if(!$task->canComplete()) disabled @endif
                            class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                            style="{{ $task->canComplete() ? 'background:var(--color-success)' : 'background:var(--color-surface-muted); color:var(--color-text-disabled); cursor:not-allowed' }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Selesai
                    </button>
                </form>
                @if(!$task->canComplete())
                <p class="text-xs text-text-muted self-center">
                    Perlu {{ $task->pendingRequiredChecklists() }} checklist + foto
                </p>
                @endif
            @endif
        @endif
        @endcan
    </div>
    @endif

    {{-- ══ Action Buttons (FOP Manage) ════════════════════════════════ --}}
    @if(in_array($task->status->value, ['pending', 'terjadwal']))
    @if(auth()->user()->can('fopReject', $task) || auth()->user()->can('fopPending', $task) || auth()->user()->can('schedule', $task))
    <div class="bg-surface border border-border rounded-lg p-5 mt-4 flex items-center justify-between">
        <div>
            <h4 class="text-sm font-semibold text-text-main mb-1">Manajemen Task (FOP)</h4>
            <p class="text-xs text-text-secondary">Kelola task sebelum mulai dikerjakan oleh teknisi.</p>
        </div>
        <div class="flex items-center gap-3">
            @if($task->status->value === 'pending')
                @can('schedule', $task)
                <button x-data @click="$dispatch('open-modal', 'schedule-task')"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-md text-white transition-colors hover:brightness-110"
                        style="background:var(--color-primary)">
                    Jadwalkan Task
                </button>
                @endcan
                @can('fopReject', $task)
                <button x-data @click="$dispatch('open-modal', 'fop-reject-task-pending')"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-md border bg-white transition-colors hover:bg-error/5"
                        style="border-color:var(--color-error-border); color:var(--color-error)">
                    Reject Task
                </button>
                @endcan
            @endif

            @if($task->status->value === 'terjadwal')
                @can('fopPending', $task)
                <button x-data @click="$dispatch('open-modal', 'fop-pending-task')"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-md border bg-white transition-colors hover:bg-warning/5"
                        style="border-color:var(--color-warning-border); color:var(--color-warning)">
                    Set Pending
                </button>
                @endcan
            @endif
        </div>
    </div>
    @endif
    @endif

    {{-- ══ Action Buttons (FOP Review) ════════════════════════════════ --}}
    @if($task->status->value === 'selesai' && $task->fop_review_status === 'pending')
    @can('review', $task)
    <div class="bg-surface border border-primary-border rounded-lg p-5 mt-4 flex items-center justify-between" style="background:var(--color-primary-soft)">
        <div>
            <h4 class="text-sm font-semibold text-text-main mb-1">Review FOP</h4>
            <p class="text-xs text-text-secondary">Task ini telah diselesaikan oleh teknisi dan menunggu persetujuan Anda.</p>
        </div>
        <div class="flex items-center gap-3">
            <button x-data @click="$dispatch('open-modal', 'reject-task')"
                    class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-md border bg-white transition-colors"
                    style="border-color:var(--color-error-border); color:var(--color-error)">
                Reject (Kembalikan ke Teknisi)
            </button>
            <form action="{{ route('tasks.review', $task) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="approve">
                <button type="submit"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                        style="background:var(--color-primary)">
                    Approve Task
                </button>
            </form>
        </div>
    </div>
    @endcan
    @endif
</div>

{{-- ══ Schedule Task Modal ═════════════════════════════════════════ --}}
@can('schedule', $task)
@if($task->status->value === 'pending')
<x-ui.modal name="schedule-task" title="Jadwalkan Task" maxWidth="md">
    <form action="{{ route('tasks.schedule', $task) }}" method="POST">
        @csrf
        <div class="space-y-4 p-4">
            <div>
                <label class="block text-sm font-medium mb-1">Tanggal & Waktu</label>
                <input type="datetime-local" name="scheduled_at" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary" required>
            </div>

            @php
                $availableTeknisi = \App\Models\User::whereHas('role', fn($q) => $q->where('code', 'teknisi'))->orderBy('name')->get();
            @endphp
            <div>
                <label class="block text-sm font-medium mb-1">Tim Teknisi (1-3)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                    @foreach($availableTeknisi as $tek)
                    <label class="flex items-center gap-2 p-2 border rounded-md cursor-pointer hover:bg-surface transition-colors border-border">
                        <input type="checkbox" name="team_member_ids[]" value="{{ $tek->id }}" class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-text-main">{{ $tek->name }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Checklist Poin (per baris / koma)</label>
                <textarea name="checklist_items" rows="4"
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                          placeholder="Verifikasi KTP&#10;Cek Sinyal&#10;Foto Lokasi"
                          required></textarea>
                <p class="text-xs text-gray-500 mt-1">Pisahkan dengan baris baru atau koma</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 px-4 py-3 bg-gray-50 border-t border-gray-200">
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'schedule-task')">
                Batal
            </x-ui.button>
            <x-ui.button type="submit" variant="primary">
                Jadwalkan
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endif
@endcan

{{-- ══ FOP Reject Pending Task Modal ═════════════════════════════════ --}}
@can('fopReject', $task)
<x-ui.modal name="fop-reject-task-pending" title="Reject Pending Task" maxWidth="sm">
    <p class="text-sm text-text-secondary mb-4">
        Task ini belum dijadwalkan dan akan tetap berstatus <span class="font-semibold text-text-main">Pending</span>, namun dengan keterangan reject.
    </p>
    <form action="{{ route('tasks.fop-reject', $task) }}" method="POST">
        @csrf
        <x-ui.textarea name="reject_reason" rows="3" placeholder="Alasan reject task..." required />
        <x-slot name="footer">
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'fop-reject-task-pending')">
                Batal
            </x-ui.button>
            <x-ui.button type="submit" variant="danger">
                Reject Task
            </x-ui.button>
        </x-slot>
    </form>
</x-ui.modal>
@endcan

{{-- ══ FOP Set Pending Scheduled Task Modal ═══════════════════════════ --}}
@can('fopPending', $task)
<x-ui.modal name="fop-pending-task" title="Set Task Menjadi Pending" maxWidth="sm">
    <p class="text-sm text-text-secondary mb-4">
        Task ini akan diubah statusnya dari <span class="font-semibold text-text-main">Terjadwal</span> menjadi <span class="font-semibold text-text-main">Pending</span>. Tim teknisi yang sudah di-assign tidak akan terhapus.
    </p>
    <form action="{{ route('tasks.fop-pending', $task) }}" method="POST">
        @csrf
        <x-ui.textarea name="pending_reason" rows="3" placeholder="Alasan mengapa di-pending..." required />
        <x-slot name="footer">
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'fop-pending-task')">
                Batal
            </x-ui.button>
            <x-ui.button type="submit" style="background:var(--color-warning); color:white; border-color:transparent">
                Set Pending
            </x-ui.button>
        </x-slot>
    </form>
</x-ui.modal>
@endcan


{{-- ══ FOP Reject Modal ══════════════════════════════════════════════ --}}
@can('review', $task)
<x-ui.modal name="reject-task" title="Reject Laporan Task" maxWidth="sm">
    <p class="text-sm text-text-secondary mb-4">
        Task ini akan dikembalikan ke status <span class="font-semibold text-text-main">In Progress</span>. 
        Teknisi harus memperbaiki laporan berdasarkan alasan reject.
    </p>
    <form action="{{ route('tasks.review', $task) }}" method="POST">
        @csrf
        <input type="hidden" name="action" value="reject">
        <x-ui.textarea name="reason" rows="3" placeholder="Alasan reject (misal: Foto bukti kurang jelas)..." required />
        <x-slot name="footer">
            <x-ui.button type="button" variant="secondary"
                         x-on:click="$dispatch('close-modal', 'reject-task')">
                Batal
            </x-ui.button>
            <x-ui.button type="submit" variant="danger">
                Konfirmasi Reject
            </x-ui.button>
        </x-slot>
    </form>
</x-ui.modal>
@endcan

{{-- ══ Cancel Modal ══════════════════════════════════════════════════ --}}
@can('cancel', $task)
<x-ui.modal name="cancel-task" title="Batalkan Task" maxWidth="sm">
    <p class="text-sm text-text-secondary mb-4">
        Task <span class="font-mono font-semibold">{{ $task->task_number }}</span> akan dibatalkan.
        Tindakan ini tidak dapat dibatalkan.
    </p>
    <form action="{{ route('tasks.cancel', $task) }}" method="POST">
        @csrf
        <x-ui.textarea name="cancel_reason" rows="3" placeholder="Alasan pembatalan..." required />
        <x-slot name="footer">
            <x-ui.button type="button" variant="secondary"
                         x-on:click="$dispatch('close-modal', 'cancel-task')">
                Batal
            </x-ui.button>
            <x-ui.button type="submit" variant="danger">
                Ya, Batalkan Task
            </x-ui.button>
        </x-slot>
    </form>
</x-ui.modal>
@endcan

{{-- ══ Pending Modal ═════════════════════════════════════════════════ --}}
@can('statusPending', $task)
<x-ui.modal name="pending-task" title="Set Pending" maxWidth="sm">
    <form action="{{ route('tasks.pending', $task) }}" method="POST">
        @csrf
        <x-ui.textarea name="pending_reason" rows="3" placeholder="Alasan task di-pending..." required />
        <x-slot name="footer">
            <x-ui.button type="button" variant="secondary"
                         x-on:click="$dispatch('close-modal', 'pending-task')">
                Batal
            </x-ui.button>
            <x-ui.button type="submit" variant="warning">
                Konfirmasi Pending
            </x-ui.button>
        </x-slot>
    </form>
</x-ui.modal>
@endcan

{{-- ══ Task Data untuk Alpine.js ═════════════════════════════════════ --}}
@php
$taskData = [
    'id' => $task->id,
    'task_number' => $task->task_number,
    'customer_name' => $task->customer?->full_name ?? '—',
    'customer_address' => $task->customer?->address ?? '—',
    'pop_name' => $task->pop?->name ?? '—',
    'task_type' => $task->task_type->value,
    'evidence_count' => $task->evidences->count(),
    'can_complete' => $task->canComplete(),
    'evidence_url' => route('tasks.evidences.store', $task),
    'submit_url_survey' => route('tasks.survey-report.store', $task),
    'submit_url_install' => route('tasks.install-report.store', $task),
    'current_package_id' => $task->customer?->customerService?->internet_package_id,
];
@endphp

{{-- ══ Slide-Over: Laporan Survey Multi-Step ════════════════════════════ --}}
<div x-data="surveyReportForm({{ json_encode($taskData) }})"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex"
     @open-survey-report="openSurveyReport()"
     style="display:none">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50" @click="closeSlideOver()"></div>

    {{-- Drawer --}}
    <div class="relative ml-auto w-full max-w-lg h-full bg-surface shadow-2xl flex flex-col overflow-hidden"
         @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-border shrink-0">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-text-muted">Laporan Survey</p>
                <p class="text-sm font-semibold text-text-main mt-0.5" x-text="taskData.customer_name"></p>
            </div>
            <button @click="closeSlideOver()" class="p-1.5 rounded-md hover:bg-surface-muted transition-colors">
                <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Step Pills --}}
        <div class="flex items-center gap-1 px-5 py-3 border-b border-border shrink-0 overflow-x-auto">
            <template x-for="(label, idx) in steps" :key="idx">
                <button @click="goToStep(idx)" class="flex items-center gap-1 shrink-0">
                    <span class="h-5 w-5 rounded-full flex items-center justify-center text-[10px] font-bold transition-colors"
                          :style="idx === currentStep ? 'background:var(--color-primary); color:white'
                                  : (idx < currentStep ? 'background:var(--color-success); color:white'
                                  : 'background:var(--color-surface-muted); color:var(--color-text-muted)')">
                        <template x-if="idx < currentStep">✓</template>
                        <template x-if="idx >= currentStep" ><span x-text="idx+1"></span></template>
                    </span>
                    <span class="text-[11px] font-medium whitespace-nowrap"
                          :style="idx === currentStep ? 'color:var(--color-primary)' : 'color:var(--color-text-muted)'"
                          x-text="label"></span>
                    <template x-if="idx < steps.length - 1">
                        <svg class="h-3 w-3 text-border mx-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </template>
                </button>
            </template>
        </div>

        {{-- Body —scrollable --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-4">

            {{-- Step 1: Data Diri --}}
            <div x-show="currentStep === 0">
                <h3 class="text-sm font-semibold text-text-main mb-3">Verifikasi Data</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex gap-3">
                        <span class="text-text-muted w-28 shrink-0 text-xs pt-0.5">Pelanggan</span>
                        <span class="font-medium text-text-main" x-text="taskData.customer_name"></span>
                    </div>
                    <div class="flex gap-3">
                        <span class="text-text-muted w-28 shrink-0 text-xs pt-0.5">Alamat</span>
                        <span class="text-text-secondary" x-text="taskData.customer_address"></span>
                    </div>
                    <div class="flex gap-3">
                        <span class="text-text-muted w-28 shrink-0 text-xs pt-0.5">POP</span>
                        <span class="text-text-secondary" x-text="taskData.pop_name"></span>
                    </div>
                    <div class="flex gap-3">
                        <span class="text-text-muted w-28 shrink-0 text-xs pt-0.5">No. Task</span>
                        <span class="font-mono text-text-secondary" x-text="taskData.task_number"></span>
                    </div>
                    <div class="flex gap-3">
                        <span class="text-text-muted w-28 shrink-0 text-xs pt-0.5">Teknisi</span>
                        <span class="font-medium text-text-main">{{ auth()->user()->name }}</span>
                    </div>
                </div>
                <p class="mt-4 text-xs text-text-muted p-3 rounded-md" style="background:var(--color-info-bg); border:1px solid var(--color-info-border); color:var(--color-info)">
                    Pastikan data di atas sesuai. Jika tidak sesuai, hubungi FOP untuk koreksi.
                </p>
            </div>

            {{-- Step 2: Foto Lokasi --}}
            <div x-show="currentStep === 1">
                <h3 class="text-sm font-semibold text-text-main mb-1">Foto Lokasi</h3>
                <p class="text-xs text-text-muted mb-3">Upload minimal 1 foto lokasi/rumah pelanggan.</p>

                {{-- Preview foto --}}
                <div class="grid grid-cols-3 gap-2 mb-3" x-show="uploadedPhotos.length > 0">
                    <template x-for="(photo, idx) in uploadedPhotos" :key="idx">
                        <div class="relative aspect-square rounded-md overflow-hidden border border-border bg-surface-muted">
                            <img :src="photo.url" class="h-full w-full object-cover" alt="Bukti">
                        </div>
                    </template>
                </div>

                {{-- Upload zone --}}
                <label class="block cursor-pointer" x-show="!uploading">
                    <input type="file" accept="image/*" capture="environment" class="hidden"
                           @change="uploadPhoto($event.target)">
                    <div class="flex items-center justify-center gap-2 py-4 border-2 border-dashed rounded-md text-xs font-medium transition-colors"
                         style="border-color:var(--color-primary-border); color:var(--color-primary)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Ambil / Pilih Foto
                    </div>
                </label>
                <div x-show="uploading" class="flex items-center gap-2 text-xs text-text-muted py-3">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Mengupload...
                </div>
                <p x-show="uploadError" x-text="uploadError" class="text-xs mt-1" style="color:var(--color-error)"></p>
                <p class="text-xs mt-2" :style="uploadedPhotos.length >= 1 ? 'color:var(--color-success)' : 'color:var(--color-text-muted)'">
                    <span x-text="uploadedPhotos.length"></span> foto diupload (minimal 1)
                </p>
            </div>

            {{-- Step 3: Cek Sinyal --}}
            <div x-show="currentStep === 2">
                <h3 class="text-sm font-semibold text-text-main mb-3">Cek Sinyal</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Kekuatan Sinyal (dBm)</label>
                        <input type="number" x-model="form.signal_strength_dbm" min="-120" max="0"
                               placeholder="Contoh: -65"
                               class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                               style="--tw-ring-color: var(--color-primary)">
                        <p class="text-[11px] text-text-muted mt-0.5">Opsional.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Catatan Kondisi Sinyal</label>
                        <textarea x-model="form.signal_note" rows="3"
                                  placeholder="Kondisi sinyal, hambatan, dll..."
                                  class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none resize-none"></textarea>
                    </div>
                </div>
            </div>

            {{-- Step 4: Teknis Jaringan --}}
            <div x-show="currentStep === 3">
                <h3 class="text-sm font-semibold text-text-main mb-3">Teknis Jaringan</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Estimasi Kabel (meter) <span style="color:var(--color-error)">*</span></label>
                        <input type="number" x-model="form.cable_estimation_meter" min="0"
                               class="w-full text-sm px-3 py-2 border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                               :style="stepErrors.cable ? 'border-color:var(--color-error)' : 'border-color:var(--color-border)'"
                               style="--tw-ring-color: var(--color-primary)">
                        <p x-show="stepErrors.cable" class="text-xs mt-1" style="color:var(--color-error)">Wajib diisi</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">ODP Terdekat <span style="color:var(--color-error)">*</span></label>
                        <input type="text" x-model="form.nearest_odp" placeholder="Nama / ID ODP"
                               class="w-full text-sm px-3 py-2 border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                               :style="stepErrors.odp ? 'border-color:var(--color-error)' : 'border-color:var(--color-border)'"
                               style="--tw-ring-color: var(--color-primary)">
                        <p x-show="stepErrors.odp" class="text-xs mt-1" style="color:var(--color-error)">Wajib diisi</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Media <span style="color:var(--color-error)">*</span></label>
                        <select x-model="form.media_type" class="w-full text-sm px-3 py-2 border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                                :style="stepErrors.media ? 'border-color:var(--color-error)' : 'border-color:var(--color-border)'"
                                style="--tw-ring-color: var(--color-primary)">
                            <option value="">Pilih Media</option>
                            <option value="Fiber">Fiber</option>
                            <option value="Wireless">Wireless</option>
                            <option value="UTP">UTP</option>
                        </select>
                        <p x-show="stepErrors.media" class="text-xs mt-1" style="color:var(--color-error)">Wajib dipilih</p>
                    </div>
                </div>
            </div>

            {{-- Step 5: Kesimpulan --}}
            <div x-show="currentStep === 4">
                <h3 class="text-sm font-semibold text-text-main mb-3">Kesimpulan</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Hasil Survey <span style="color:var(--color-error)">*</span></label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="survey_result" value="layak" x-model="form.survey_result" class="rounded accent-primary">
                                <span class="text-sm">✓ Layak Dipasang</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="survey_result" value="tidak_layak" x-model="form.survey_result" class="rounded accent-primary">
                                <span class="text-sm">✗ Tidak Layak</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="survey_result" value="kunjungan_ulang" x-model="form.survey_result" class="rounded accent-primary">
                                <span class="text-sm">⟳ Kunjungan Ulang</span>
                            </label>
                        </div>
                        <p x-show="stepErrors.result" class="text-xs mt-1" style="color:var(--color-error)">Wajib dipilih</p>
                    </div>

                    <div x-show="form.survey_result === 'tidak_layak'">
                        <label class="block text-xs font-medium text-text-secondary mb-1">Alasan Tidak Layak <span style="color:var(--color-error)">*</span></label>
                        <textarea x-model="form.reject_reason" rows="2"
                                  placeholder="Jelaskan alasan..."
                                  class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Tingkat Kesulitan <span style="color:var(--color-error)">*</span></label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="difficulty_level" value="MUDAH" x-model="form.difficulty_level" class="rounded accent-primary">
                                <span class="text-sm">Mudah</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="difficulty_level" value="SEDANG" x-model="form.difficulty_level" class="rounded accent-primary">
                                <span class="text-sm">Sedang</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="difficulty_level" value="SULIT" x-model="form.difficulty_level" class="rounded accent-primary">
                                <span class="text-sm">Sulit</span>
                            </label>
                        </div>
                        <p x-show="stepErrors.difficulty" class="text-xs mt-1" style="color:var(--color-error)">Wajib dipilih</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Tanda Tangan Teknisi <span style="color:var(--color-error)">*</span></label>
                        <canvas id="survey-signature-canvas" class="w-full h-32 border border-border rounded-md bg-white cursor-crosshair"></canvas>
                        <button type="button" @click="clearSignature()" class="text-xs mt-1" style="color:var(--color-error)">Hapus Tanda Tangan</button>
                        <p x-show="stepErrors.signature" class="text-xs mt-1" style="color:var(--color-error)">Tanda tangan wajib</p>
                    </div>
                </div>
            </div>

        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between px-5 py-4 border-t border-border bg-surface-muted shrink-0 gap-3">
            <button @click="previousStep()" x-show="currentStep > 0" class="text-sm font-semibold px-3 py-2 rounded-md border border-border text-text-main hover:bg-surface transition-colors">
                ← Sebelumnya
            </button>
            <button @click="nextStep()" x-show="currentStep < steps.length - 1" class="ml-auto text-sm font-semibold px-4 py-2 rounded-md text-white transition-colors" style="background:var(--color-primary)">
                Lanjut →
            </button>
            <button @click="submitReport()" x-show="currentStep === steps.length - 1" :disabled="submitting" class="ml-auto text-sm font-semibold px-4 py-2 rounded-md text-white transition-colors"
                    :style="submitting ? 'background:var(--color-surface-muted)' : 'background:var(--color-success)'"
                    :class="{ 'cursor-not-allowed': submitting }">
                <span x-show="!submitting">Submit Laporan</span>
                <span x-show="submitting">Mengirim...</span>
            </button>
        </div>

        <p x-show="submitError" x-text="submitError" class="text-xs px-5 py-2" style="color:var(--color-error); background:var(--color-error-bg)"></p>

    </div>
</div>

{{-- ══ Slide-Over: Laporan Pemasangan Multi-Step ════════════════════════ --}}
<div x-data="installReportForm({{ json_encode($taskData) }})"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex"
     @open-install-report="openInstallReport()"
     style="display:none">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50" @click="closeSlideOver()"></div>

    {{-- Drawer --}}
    <div class="relative ml-auto w-full max-w-lg h-full bg-surface shadow-2xl flex flex-col overflow-hidden"
         @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-border shrink-0">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-text-muted">Laporan Pemasangan</p>
                <p class="text-sm font-semibold text-text-main mt-0.5" x-text="taskData.customer_name"></p>
            </div>
            <button @click="closeSlideOver()" class="p-1.5 rounded-md hover:bg-surface-muted transition-colors">
                <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Step Pills --}}
        <div class="flex items-center gap-1 px-5 py-3 border-b border-border shrink-0 overflow-x-auto">
            <template x-for="(label, idx) in steps" :key="idx">
                <button @click="goToStep(idx)" class="flex items-center gap-1 shrink-0">
                    <span class="h-5 w-5 rounded-full flex items-center justify-center text-[10px] font-bold transition-colors"
                          :style="idx === currentStep ? 'background:var(--color-primary); color:white'
                                  : (idx < currentStep ? 'background:var(--color-success); color:white'
                                  : 'background:var(--color-surface-muted); color:var(--color-text-muted)')">
                        <template x-if="idx < currentStep">✓</template>
                        <template x-if="idx >= currentStep" ><span x-text="idx+1"></span></template>
                    </span>
                    <span class="text-[11px] font-medium whitespace-nowrap"
                          :style="idx === currentStep ? 'color:var(--color-primary)' : 'color:var(--color-text-muted)'"
                          x-text="label"></span>
                    <template x-if="idx < steps.length - 1">
                        <svg class="h-3 w-3 text-border mx-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </template>
                </button>
            </template>
        </div>

        {{-- Body —scrollable --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-4">

            {{-- Step 1: Foto Pemasangan --}}
            <div x-show="currentStep === 0">
                <h3 class="text-sm font-semibold text-text-main mb-1">Foto Pemasangan</h3>
                <p class="text-xs text-text-muted mb-3">Upload minimal 2 foto (ONT terpasang & kabel routing).</p>

                <div class="grid grid-cols-3 gap-2 mb-3" x-show="uploadedPhotos.length > 0">
                    <template x-for="(photo, idx) in uploadedPhotos" :key="idx">
                        <div class="relative aspect-square rounded-md overflow-hidden border border-border bg-surface-muted">
                            <img :src="photo.url" class="h-full w-full object-cover" alt="Bukti">
                        </div>
                    </template>
                </div>

                <label class="block cursor-pointer" x-show="!uploading">
                    <input type="file" accept="image/*" capture="environment" class="hidden" @change="uploadPhoto($event.target)">
                    <div class="flex items-center justify-center gap-2 py-4 border-2 border-dashed rounded-md text-xs font-medium transition-colors"
                         style="border-color:var(--color-primary-border); color:var(--color-primary)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Ambil / Pilih Foto
                    </div>
                </label>
                <div x-show="uploading" class="flex items-center gap-2 text-xs text-text-muted py-3">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Mengupload...
                </div>
                <p x-show="uploadError" x-text="uploadError" class="text-xs mt-1" style="color:var(--color-error)"></p>
                <p class="text-xs mt-2" :style="uploadedPhotos.length >= 2 ? 'color:var(--color-success)' : 'color:var(--color-text-muted)'">
                    <span x-text="uploadedPhotos.length"></span> foto diupload (minimal 2)
                </p>
            </div>

            {{-- Step 2: Data Teknis --}}
            <div x-show="currentStep === 1">
                <h3 class="text-sm font-semibold text-text-main mb-3">Data Teknis</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Paket Internet <span style="color:var(--color-error)">*</span></label>
                        <select x-model="form.internet_package_id" class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2" style="--tw-ring-color: var(--color-primary)">
                            <option value="">Pilih Paket</option>
                            @foreach(\App\Models\InternetPackage::active()->get() as $pkg)
                            <option value="{{ $pkg->id }}">{{ $pkg->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Serial ONT/Router <span style="color:var(--color-error)">*</span></label>
                        <input type="text" x-model="form.router_or_ont_serial" class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2" style="--tw-ring-color: var(--color-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">MAC Address <span style="color:var(--color-error)">*</span></label>
                        <input type="text" x-model="form.router_mac" class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2" style="--tw-ring-color: var(--color-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">VLAN <span style="color:var(--color-error)">*</span></label>
                        <input type="number" x-model="form.vlan" class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2" style="--tw-ring-color: var(--color-primary)">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">IP Address <span style="color:var(--color-error)">*</span></label>
                        <input type="text" x-model="form.ip_address" placeholder="192.168.1.1" class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2" style="--tw-ring-color: var(--color-primary)">
                    </div>
                </div>
            </div>

            {{-- Step 3: Kontrak & TTD --}}
            <div x-show="currentStep === 2">
                <h3 class="text-sm font-semibold text-text-main mb-3">Kontrak & Tanda Tangan</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Foto Kontrak <span style="color:var(--color-error)">*</span></label>
                        <input type="file" accept="image/*" capture="environment" @change="handleContractUpload($event.target)" class="w-full text-sm">
                        <p x-show="form.contract_file" class="text-xs mt-1" style="color:var(--color-success)">✓ File dipilih</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">TTD Pelanggan <span style="color:var(--color-error)">*</span></label>
                        <canvas id="install-customer-signature-canvas" class="w-full h-24 border border-border rounded-md bg-white cursor-crosshair"></canvas>
                        <button type="button" @click="clearSignature('customer')" class="text-xs mt-1" style="color:var(--color-error)">Hapus</button>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">TTD Teknisi <span style="color:var(--color-error)">*</span></label>
                        <canvas id="install-tech-signature-canvas" class="w-full h-24 border border-border rounded-md bg-white cursor-crosshair"></canvas>
                        <button type="button" @click="clearSignature('tech')" class="text-xs mt-1" style="color:var(--color-error)">Hapus</button>
                    </div>
                </div>
            </div>

            {{-- Step 4: Catatan --}}
            <div x-show="currentStep === 3">
                <h3 class="text-sm font-semibold text-text-main mb-3">Catatan Pemasangan</h3>
                <textarea x-model="form.installation_note" rows="5" placeholder="Catatan tambahan..." class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none resize-none"></textarea>
            </div>

        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between px-5 py-4 border-t border-border bg-surface-muted shrink-0 gap-3">
            <button @click="previousStep()" x-show="currentStep > 0" class="text-sm font-semibold px-3 py-2 rounded-md border border-border text-text-main hover:bg-surface transition-colors">
                ← Sebelumnya
            </button>
            <button @click="nextStep()" x-show="currentStep < steps.length - 1" class="ml-auto text-sm font-semibold px-4 py-2 rounded-md text-white transition-colors" style="background:var(--color-primary)">
                Lanjut →
            </button>
            <button @click="submitReport()" x-show="currentStep === steps.length - 1" :disabled="submitting" class="ml-auto text-sm font-semibold px-4 py-2 rounded-md text-white transition-colors"
                    :style="submitting ? 'background:var(--color-surface-muted)' : 'background:var(--color-success)'"
                    :class="{ 'cursor-not-allowed': submitting }">
                <span x-show="!submitting">Submit Laporan</span>
                <span x-show="submitting">Mengirim...</span>
            </button>
        </div>

        <p x-show="submitError" x-text="submitError" class="text-xs px-5 py-2" style="color:var(--color-error); background:var(--color-error-bg)"></p>

    </div>
</div>

@push('scripts')
<script>
function evidenceSection(taskId, initialCanComplete, initialCount) {
    return {
        taskId,
        canComplete:   initialCanComplete,
        evidenceCount: initialCount,
        uploadError:   null,

        async uploadEvidence(input) {
            const file = input.files[0];
            if (!file) return;
            this.uploadError = null;
            const form = new FormData();
            form.append('photo', file);
            form.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            const res  = await fetch(`/tasks/${this.taskId}/evidences`, {
                method: 'POST', body: form, headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (data.success) {
                this.evidenceCount = data.evidence_count;
                this.canComplete   = data.can_complete;
                window.location.reload();
            } else {
                this.uploadError = 'Gagal upload. Coba lagi.';
            }
            input.value = '';
        },

        deleteEvidence(id) {
            window.Confirm(
                'Hapus Foto Bukti',
                'Apakah Anda yakin ingin menghapus foto bukti ini?',
                'error',
                async () => {
                    const res  = await fetch(`/tasks/${this.taskId}/evidences/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    if (data.success) { this.evidenceCount = data.evidence_count; window.location.reload(); }
                }
            );
        },
    };
}

function surveyReportForm(taskData) {
    return {
        taskData,
        open: false,
        currentStep: 0,
        steps: ['Verifikasi', 'Foto', 'Sinyal', 'Teknis', 'Kesimpulan'],
        uploadedPhotos: [],
        uploading: false,
        uploadError: null,
        submitting: false,
        submitError: null,
        stepErrors: {},
        signatureCanvas: null,
        signatureIsEmpty: true,

        form: {
            signal_strength_dbm: '',
            signal_note: '',
            cable_estimation_meter: '',
            nearest_odp: '',
            media_type: '',
            survey_result: '',
            reject_reason: '',
            difficulty_level: '',
            signature_data: '',
        },

        openSurveyReport() {
            this.currentStep = 0;
            this.uploadedPhotos = [];
            this.uploading = false;
            this.uploadError = null;
            this.submitting = false;
            this.submitError = null;
            this.stepErrors = {};
            this.signatureIsEmpty = true;
            this.form = {
                signal_strength_dbm: '',
                signal_note: '',
                cable_estimation_meter: '',
                nearest_odp: '',
                media_type: '',
                survey_result: '',
                reject_reason: '',
                difficulty_level: '',
                signature_data: '',
            };
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.initSignatureCanvas());
        },

        closeSlideOver() {
            this.open = false;
            document.body.style.overflow = '';
        },

        goToStep(idx) { this.currentStep = idx; },
        previousStep() { if (this.currentStep > 0) this.currentStep--; },
        nextStep() { if (this.validateCurrentStep() && this.currentStep < this.steps.length - 1) this.currentStep++; },

        validateCurrentStep() {
            this.stepErrors = {};
            if (this.currentStep === 1 && this.uploadedPhotos.length < 1) {
                this.uploadError = 'Upload minimal 1 foto lokasi sebelum lanjut.';
                return false;
            }
            if (this.currentStep === 3) {
                if (!this.form.cable_estimation_meter && this.form.cable_estimation_meter !== 0) this.stepErrors.cable = true;
                if (!this.form.nearest_odp) this.stepErrors.odp = true;
                if (!this.form.media_type) this.stepErrors.media = true;
                if (Object.keys(this.stepErrors).length > 0) return false;
            }
            this.uploadError = null;
            return true;
        },

        async uploadPhoto(input) {
            const file = input.files[0];
            if (!file) return;
            this.uploading = true;
            this.uploadError = null;
            const form = new FormData();
            form.append('photo', file);
            form.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            try {
                const res = await fetch(this.taskData.evidence_url, {
                    method: 'POST', body: form, headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.success) {
                    this.uploadedPhotos.push({ url: data.evidence.url });
                } else {
                    this.uploadError = 'Gagal upload. Coba lagi.';
                }
            } catch (err) {
                this.uploadError = 'Koneksi bermasalah. Coba lagi.';
            } finally {
                this.uploading = false;
                input.value = '';
            }
        },

        initSignatureCanvas() {
            const canvas = document.getElementById('survey-signature-canvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth;
            canvas.height = 128;
            ctx.lineWidth = 2.5;
            ctx.strokeStyle = '#1e293b';
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            let drawing = false, lastX = 0, lastY = 0;
            const getPos = (e) => {
                const r = canvas.getBoundingClientRect();
                return { x: (e.clientX ?? e.pageX) - r.left, y: (e.clientY ?? e.pageY) - r.top };
            };

            canvas.addEventListener('mousedown', (e) => {
                drawing = true;
                const p = getPos(e);
                lastX = p.x; lastY = p.y;
                this.signatureIsEmpty = false;
            });

            canvas.addEventListener('mousemove', (e) => {
                if (!drawing) return;
                const p = getPos(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                lastX = p.x; lastY = p.y;
            });

            canvas.addEventListener('mouseup', () => { drawing = false; });
            canvas.addEventListener('mouseleave', () => { drawing = false; });

            this.signatureCanvas = canvas;
        },

        clearSignature() {
            if (!this.signatureCanvas) return;
            const ctx = this.signatureCanvas.getContext('2d');
            ctx.clearRect(0, 0, this.signatureCanvas.width, this.signatureCanvas.height);
            this.signatureIsEmpty = true;
        },

        async submitReport() {
            this.stepErrors = {};
            this.submitError = null;

            if (!this.form.survey_result) this.stepErrors.result = true;
            if (!this.form.difficulty_level) this.stepErrors.difficulty = true;
            if (this.signatureIsEmpty) this.stepErrors.signature = true;
            if (this.form.survey_result === 'tidak_layak' && !this.form.reject_reason) {
                this.submitError = 'Alasan tidak layak wajib diisi.';
                return;
            }
            if (Object.keys(this.stepErrors).length > 0) return;

            this.form.signature_data = this.signatureCanvas ? this.signatureCanvas.toDataURL('image/png') : '';
            if (!this.form.signature_data || this.signatureIsEmpty) {
                this.stepErrors.signature = true;
                return;
            }

            this.submitting = true;
            try {
                const body = new FormData();
                body.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                Object.entries(this.form).forEach(([k, v]) => { if (v !== '') body.append(k, v); });

                const res = await fetch(this.taskData.submit_url_survey, {
                    method: 'POST', body, headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();

                if (data.success) {
                    this.closeSlideOver();
                    window.location.reload();
                } else {
                    this.submitError = data.message || 'Terjadi kesalahan. Coba lagi.';
                }
            } catch (err) {
                this.submitError = 'Koneksi bermasalah. Pastikan internet aktif dan coba lagi.';
            } finally {
                this.submitting = false;
            }
        },
    };
}

function installReportForm(taskData) {
    return {
        taskData,
        open: false,
        currentStep: 0,
        steps: ['Foto', 'Teknis', 'Kontrak & TTD', 'Catatan'],
        uploadedPhotos: [],
        uploading: false,
        uploadError: null,
        submitting: false,
        submitError: null,
        customerIsEmpty: true,
        techIsEmpty: true,
        contractFile: null,

        form: {
            internet_package_id: taskData.current_package_id || '',
            router_or_ont_serial: '',
            router_mac: '',
            vlan: '',
            ip_address: '',
            signature_customer: '',
            signature_technician: '',
            installation_note: '',
        },

        customerCanvas: null,
        techCanvas: null,

        openInstallReport() {
            this.currentStep = 0;
            this.uploadedPhotos = [];
            this.uploading = false;
            this.uploadError = null;
            this.submitting = false;
            this.submitError = null;
            this.customerIsEmpty = true;
            this.techIsEmpty = true;
            this.contractFile = null;
            this.form = {
                internet_package_id: this.taskData.current_package_id || '',
                router_or_ont_serial: '',
                router_mac: '',
                vlan: '',
                ip_address: '',
                signature_customer: '',
                signature_technician: '',
                installation_note: '',
            };
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => {
                this.initCustomerCanvas();
                this.initTechCanvas();
            });
        },

        closeSlideOver() {
            this.open = false;
            document.body.style.overflow = '';
        },

        goToStep(idx) { this.currentStep = idx; },
        previousStep() { if (this.currentStep > 0) this.currentStep--; },
        nextStep() { if (this.validateCurrentStep() && this.currentStep < this.steps.length - 1) this.currentStep++; },

        validateCurrentStep() {
            if (this.currentStep === 0 && this.uploadedPhotos.length < 2) {
                this.uploadError = 'Upload minimal 2 foto pemasangan sebelum lanjut.';
                return false;
            }
            this.uploadError = null;
            return true;
        },

        async uploadPhoto(input) {
            const file = input.files[0];
            if (!file) return;
            this.uploading = true;
            this.uploadError = null;
            const form = new FormData();
            form.append('photo', file);
            form.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            try {
                const res = await fetch(this.taskData.evidence_url, {
                    method: 'POST', body: form, headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.success) {
                    this.uploadedPhotos.push({ url: data.evidence.url });
                } else {
                    this.uploadError = 'Gagal upload. Coba lagi.';
                }
            } catch (err) {
                this.uploadError = 'Koneksi bermasalah. Coba lagi.';
            } finally {
                this.uploading = false;
                input.value = '';
            }
        },

        handleContractUpload(input) {
            if (input.files[0]) {
                this.contractFile = input.files[0];
            }
        },

        initCustomerCanvas() {
            this.initSignatureCanvas('install-customer-signature-canvas', 'customer');
        },

        initTechCanvas() {
            this.initSignatureCanvas('install-tech-signature-canvas', 'tech');
        },

        initSignatureCanvas(id, type) {
            const canvas = document.getElementById(id);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth;
            canvas.height = 96;
            ctx.lineWidth = 2.5;
            ctx.strokeStyle = '#1e293b';
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            let drawing = false, lastX = 0, lastY = 0;
            const getPos = (e) => {
                const r = canvas.getBoundingClientRect();
                return { x: (e.clientX ?? e.pageX) - r.left, y: (e.clientY ?? e.pageY) - r.top };
            };

            canvas.addEventListener('mousedown', (e) => {
                drawing = true;
                const p = getPos(e);
                lastX = p.x; lastY = p.y;
                if (type === 'customer') this.customerIsEmpty = false;
                if (type === 'tech') this.techIsEmpty = false;
            });

            canvas.addEventListener('mousemove', (e) => {
                if (!drawing) return;
                const p = getPos(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                lastX = p.x; lastY = p.y;
            });

            canvas.addEventListener('mouseup', () => { drawing = false; });
            canvas.addEventListener('mouseleave', () => { drawing = false; });

            if (type === 'customer') this.customerCanvas = canvas;
            if (type === 'tech') this.techCanvas = canvas;
        },

        clearSignature(type) {
            const canvas = type === 'customer' ? this.customerCanvas : this.techCanvas;
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            if (type === 'customer') this.customerIsEmpty = true;
            if (type === 'tech') this.techIsEmpty = true;
        },

        async submitReport() {
            this.submitError = null;

            if (this.uploadedPhotos.length < 2) {
                this.submitError = 'Minimal 2 foto pemasangan wajib.';
                return;
            }
            if (!this.customerIsEmpty) {
                this.form.signature_customer = this.customerCanvas.toDataURL('image/png');
            }
            if (!this.techIsEmpty) {
                this.form.signature_technician = this.techCanvas.toDataURL('image/png');
            }

            this.submitting = true;
            try {
                const body = new FormData();
                body.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                Object.entries(this.form).forEach(([k, v]) => { if (v !== '') body.append(k, v); });
                if (this.contractFile) body.append('contract_file', this.contractFile);

                const res = await fetch(this.taskData.submit_url_install, {
                    method: 'POST', body, headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();

                if (data.success) {
                    this.closeSlideOver();
                    window.location.reload();
                } else {
                    this.submitError = data.message || 'Terjadi kesalahan. Coba lagi.';
                }
            } catch (err) {
                this.submitError = 'Koneksi bermasalah. Pastikan internet aktif dan coba lagi.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endpush
@endsection

