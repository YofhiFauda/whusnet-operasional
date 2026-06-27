@extends('layouts.app')

@section('title', 'Edit Task — ' . $task->task_number)

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">

    {{-- ══ Breadcrumb + Header ══════════════════════════════════════ --}}
    <div class="mb-5">
        <nav class="flex items-center gap-1.5 text-xs text-text-muted mb-3">
            <a href="{{ route('tasks.index') }}" class="hover:text-primary transition-colors">Task</a>
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
            <a href="{{ route('tasks.show', $task) }}" class="hover:text-primary transition-colors font-mono">{{ $task->task_number }}</a>
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
            <span class="text-text-secondary font-medium">Edit</span>
        </nav>
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-semibold text-text-main">Edit Task</h1>
            <span class="font-mono text-xs px-2 py-0.5 rounded border border-border bg-surface-muted text-text-muted">
                {{ $task->task_number }}
            </span>
        </div>
    </div>

    {{-- Validation errors ditangani otomatis oleh global Component Toast (<x-toast />) --}}

    <form action="{{ route('tasks.update', $task) }}" method="POST"
          x-data="taskEditForm()">
        @csrf
        @method('PUT')

        <div class="bg-surface border border-border rounded-lg divide-y divide-border">

            {{-- Tipe (read-only) --}}
            <div class="p-5">
                <label class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">Tipe Task</label>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold px-3 py-1.5 rounded-md border {{ $task->task_type->cardClasses() }}">
                        {{ $task->task_type->label() }}
                    </span>
                    <span class="text-xs text-text-muted">Tipe tidak dapat diubah setelah task dibuat.</span>
                </div>
                <input type="hidden" name="task_type" value="{{ $task->task_type->value }}">
            </div>

            {{-- Judul --}}
            <div class="p-5">
                <label for="title" class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">
                    Judul Task <span class="text-error normal-case tracking-normal font-normal">*</span>
                </label>
                <input type="text" name="title" id="title"
                       value="{{ old('title', $task->title) }}"
                       required
                       class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-background focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
            </div>

            {{-- Jadwal --}}
            @can('schedule', $task)
            <div class="p-5">
                <label for="scheduled_at" class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">
                    Tanggal & Waktu <span class="text-error normal-case tracking-normal font-normal">*</span>
                </label>
                <input type="datetime-local" name="scheduled_at" id="scheduled_at"
                       value="{{ old('scheduled_at', $task->scheduled_at?->format('Y-m-d\TH:i')) }}"
                       x-model="scheduledAt"
                       @change="checkConflicts()"
                       required
                       class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-background focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
            </div>
            @else
            <div class="p-5">
                <label class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">Jadwal</label>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-mono font-medium text-text-main">
                        {{ $task->scheduled_at?->translatedFormat('l, d M Y H:i') }} WIB
                    </span>
                    <span class="text-xs text-text-muted">(tidak punya izin ubah jadwal)</span>
                </div>
            </div>
            @endcan

            {{-- Tim Teknisi --}}
            @can('assignTeam', $task)
            <div class="p-5">
                <div class="flex items-center justify-between mb-3">
                    <label class="text-xs font-semibold text-text-secondary uppercase tracking-widest">
                        Tim Teknisi <span class="text-error normal-case tracking-normal font-normal">*</span>
                    </label>
                    <span class="text-[11px] text-text-muted"
                          x-text="`${selectedMembers.length}/3 dipilih`"></span>
                </div>

                {{-- Conflict warning --}}
                <div x-show="conflictUsers.length > 0"
                     class="mb-3 px-3 py-2.5 rounded-md border text-xs"
                     style="background:var(--color-warning-bg); border-color:var(--color-warning-border); color:var(--color-warning)">
                    <p class="font-semibold mb-1">⚠ Konflik jadwal terdeteksi</p>
                    <ul class="space-y-0.5">
                        <template x-for="u in conflictUsers" :key="u.id">
                            <li class="font-medium" x-text="'• ' + u.name"></li>
                        </template>
                    </ul>
                    @can('conflictOverride', \App\Models\Task::class)
                    <label class="flex items-center gap-2 mt-2.5 cursor-pointer">
                        <input type="checkbox" x-model="conflictOverride" class="h-3.5 w-3.5 rounded" style="accent-color:var(--color-warning)">
                        <span class="font-semibold">Override konflik jadwal</span>
                    </label>
                    @endcan
                </div>

                @php $currentMemberIds = $task->teamMembers->pluck('user_id')->toArray(); @endphp
                <div class="border border-border rounded-md divide-y divide-border overflow-hidden">
                    @forelse($teknisiList as $teknisi)
                    <label class="flex items-center gap-3 px-3 py-2.5 hover:bg-surface-muted cursor-pointer transition-colors"
                           :class="!selectedMembers.includes({{ $teknisi->id }}) && selectedMembers.length >= 3 ? 'opacity-40 cursor-not-allowed' : ''">
                        <input type="checkbox"
                               name="team_member_ids[]"
                               value="{{ $teknisi->id }}"
                               {{ in_array($teknisi->id, $currentMemberIds) ? 'checked' : '' }}
                               @change="onMemberChange($event, {{ $teknisi->id }})"
                               :disabled="!selectedMembers.includes({{ $teknisi->id }}) && selectedMembers.length >= 3"
                               class="h-4 w-4 rounded"
                               style="accent-color:var(--color-primary)">
                        <div class="h-7 w-7 rounded-full bg-primary-soft flex items-center justify-center text-[11px] font-bold shrink-0"
                             style="color:var(--color-primary)">
                            {{ strtoupper(substr($teknisi->name, 0, 2)) }}
                        </div>
                        <span class="text-sm text-text-main font-medium flex-1">{{ $teknisi->name }}</span>
                        <span x-show="conflictUsers.some(u => u.id === {{ $teknisi->id }})"
                              class="text-[10px] font-semibold px-1.5 py-0.5 rounded"
                              style="background:var(--color-warning-bg); color:var(--color-warning)">Konflik</span>
                    </label>
                    @empty
                    <div class="px-4 py-6 text-center text-sm text-text-muted">Tidak ada teknisi tersedia.</div>
                    @endforelse
                </div>
            </div>
            @else
            <div class="p-5">
                <label class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">Tim Teknisi</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($task->teamMembers as $member)
                    <div class="flex items-center gap-2 bg-background border border-border rounded-md px-2.5 py-1.5 text-xs">
                        <div class="h-5 w-5 rounded-full bg-primary-soft flex items-center justify-center text-[9px] font-bold"
                             style="color:var(--color-primary)">
                            {{ strtoupper(substr($member->user?->name ?? '?', 0, 2)) }}
                        </div>
                        <span class="text-text-main">{{ $member->user?->name ?? 'User dihapus' }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endcan

            {{-- Deskripsi --}}
            <div class="p-5">
                <label for="description" class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">Catatan</label>
                <textarea name="description" id="description" rows="3"
                          class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-background placeholder-text-muted focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition resize-none">{{ old('description', $task->description) }}</textarea>
            </div>

            {{-- Footer --}}
            <div class="px-5 py-4 bg-surface-muted flex items-center justify-between">
                <a href="{{ route('tasks.show', $task) }}" class="text-sm text-text-muted hover:text-text-secondary transition-colors">
                    ← Batal
                </a>
                <button type="submit"
                        :disabled="conflictUsers.length > 0 && !conflictOverride"
                        :style="(conflictUsers.length > 0 && !conflictOverride)
                            ? 'background:var(--color-surface-muted); color:var(--color-text-disabled); cursor:not-allowed'
                            : 'background:var(--color-primary); color:#fff'"
                        class="text-sm font-semibold px-5 py-2 rounded-md transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </div>

        <input type="hidden" name="conflict_override" :value="conflictOverride ? '1' : '0'">
    </form>
</div>

@push('scripts')
<script>
function taskEditForm() {
    const currentIds = @json($task->teamMembers->pluck('user_id')->toArray());
    return {
        scheduledAt:     '{{ old('scheduled_at', $task->scheduled_at?->format('Y-m-d\TH:i')) }}',
        selectedMembers: [...currentIds],
        conflictUsers:   [],
        conflictOverride: false,

        onMemberChange(event, userId) {
            if (event.target.checked) {
                if (this.selectedMembers.length < 3) this.selectedMembers.push(userId);
                else event.target.checked = false;
            } else {
                this.selectedMembers = this.selectedMembers.filter(id => id !== userId);
            }
            if (this.scheduledAt) this.checkConflicts();
        },

        async checkConflicts() {
            if (!this.scheduledAt || this.selectedMembers.length === 0) return;
            try {
                const res  = await fetch('/api/tasks/check-conflict', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        user_ids:        this.selectedMembers,
                        scheduled_at:    this.scheduledAt,
                        task_type:       '{{ $task->task_type->value }}',
                        exclude_task_id: {{ $task->id }},
                    }),
                });
                const data = await res.json();
                this.conflictUsers = data.conflict_users || [];
                if (!data.has_conflict) this.conflictOverride = false;
            } catch (e) { /* silent */ }
        },
    };
}
</script>
@endpush
@endsection
