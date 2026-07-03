@extends('layouts.app')

@section('title', 'Buat Task Baru')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">

    {{-- ══ Breadcrumb + Header ══════════════════════════════════════ --}}
    <div class="mb-5">
        <nav class="flex items-center gap-1.5 text-xs text-text-muted mb-3">
            <a href="{{ route('tasks.index') }}" class="hover:text-primary transition-colors">Task</a>
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
            <span class="text-text-secondary font-medium">Buat Task Baru</span>
        </nav>
        <h1 class="text-xl font-semibold text-text-main">Buat Task Baru</h1>
        <p class="text-sm text-text-muted mt-0.5">Jadwalkan tugas lapangan dan assign tim teknisi.</p>
    </div>

    {{-- Validation errors ditangani otomatis oleh global Component Toast (<x-toast />) --}}

    <form action="{{ route('tasks.store') }}" method="POST"
          x-data="taskCreateForm()" @submit.prevent="submitForm($el)">
        @csrf

        <div class="bg-surface border border-border rounded-lg divide-y divide-border">

            {{-- ── 1. Tipe Task ─────────────────────────────────── --}}
            <div class="p-5">
                <label class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-3">
                    Tipe Task <span class="text-error normal-case tracking-normal font-normal">*</span>
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @foreach($types as $type)
                    <label class="cursor-pointer">
                        <input type="radio" name="task_type" value="{{ $type['value'] }}"
                               x-model="taskType"
                               @change="onTypeChange()"
                               class="sr-only peer"
                               {{ old('task_type', 'survey') === $type['value'] ? 'checked' : '' }}>
                        <div class="border-2 border-border rounded-md px-3 py-2.5 text-center text-xs font-semibold transition-all
                                    peer-checked:border-primary peer-checked:bg-primary-soft peer-checked:text-primary-hover
                                    hover:border-border-strong text-text-secondary">
                            {{ $type['label'] }}
                        </div>
                    </label>
                    @endforeach
                </div>
                {{-- SLA hint --}}
                <p class="mt-2 text-[11px] text-text-muted">
                    SLA: <span x-text="slaLabel" class="font-semibold text-text-secondary"></span>
                </p>
            </div>

            {{-- ── 2. Judul ──────────────────────────────────────── --}}
            <div class="p-5">
                <label for="title" class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">
                    Judul Task <span class="text-error normal-case tracking-normal font-normal">*</span>
                </label>
                <input type="text" name="title" id="title"
                       value="{{ old('title') }}"
                       placeholder="e.g. Survey lokasi Bapak Ahmad Fauzi"
                       required
                       class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-background placeholder-text-muted focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
            </div>

            {{-- ── 3. Pelanggan (optional autocomplete) ─────────── --}}
            <div class="p-5">
                <label class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">
                    Pelanggan
                    <span class="normal-case tracking-normal font-normal text-text-muted ml-1">(opsional · hanya pelanggan aktif)</span>
                </label>
                <div class="relative" x-data="customerSearch('{{ $customer?->id }}', '{{ $customer ? addslashes($customer->full_name . ' — ' . $customer->display_id) : '' }}')">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="text"
                               x-model="query"
                               @input.debounce.300ms="search()"
                               @keydown.escape="results = []"
                               @keydown.arrowdown.prevent="focusResult(1)"
                               @keydown.arrowup.prevent="focusResult(-1)"
                               placeholder="Cari nama atau CID..."
                               autocomplete="off"
                               class="w-full border border-border rounded-md pl-9 pr-3 py-2 text-sm text-text-main bg-background placeholder-text-muted focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
                    </div>
                    <input type="hidden" name="customer_id" :value="selectedId">

                    {{-- Dropdown results --}}
                    <div x-show="results.length > 0"
                         class="absolute z-20 w-full bg-surface border border-border rounded-md shadow-md mt-1 max-h-48 overflow-y-auto">
                        <template x-for="c in results" :key="c.id">
                            <button type="button"
                                    @click="select(c)"
                                    class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-muted border-b border-border last:border-0 text-text-main">
                                <span class="font-medium" x-text="c.label.split('—')[0]"></span>
                                <span class="font-mono text-xs text-text-muted ml-1" x-text="'— ' + (c.label.split('—')[1] || '')"></span>
                            </button>
                        </template>
                    </div>

                    {{-- Selected indicator --}}
                    <div x-show="selectedId && results.length === 0" class="mt-1.5 flex items-center gap-1.5">
                        <svg class="h-3 w-3" style="color:var(--color-success)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span class="text-xs" style="color:var(--color-success)" x-text="query"></span>
                        <button type="button" @click="clear()" class="text-text-muted hover:text-error text-[11px] ml-1">× hapus</button>
                    </div>
                </div>
            </div>

            {{-- ── 4. POP ────────────────────────────────────────── --}}
            <div class="p-5">
                <label for="pop_id" class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">
                    POP / Cabang <span class="text-error normal-case tracking-normal font-normal">*</span>
                </label>
                <select name="pop_id" id="pop_id" required
                        x-model="selectedPopId"
                        class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-background focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
                    <option value="">— Pilih POP —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ old('pop_id') == $pop->id ? 'selected' : '' }}>
                        {{ $pop->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- ── 5. Jadwal ─────────────────────────────────────── --}}
            <div class="p-5">
                <label for="scheduled_at" class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">
                    Tanggal & Waktu <span class="text-error normal-case tracking-normal font-normal">*</span>
                </label>
                <input type="datetime-local" name="scheduled_at" id="scheduled_at"
                       value="{{ old('scheduled_at', request('date') ? request('date').'T08:00' : '') }}"
                       x-model="scheduledAt"
                       @change="checkConflicts()"
                       required
                       class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-background focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
            </div>

            {{-- ── 6. Tim Teknisi ────────────────────────────────── --}}
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
                    <p class="text-text-secondary">Teknisi berikut sudah punya task aktif pada waktu yang sama:</p>
                    <ul class="mt-1 space-y-0.5">
                        <template x-for="u in conflictUsers" :key="u.id">
                            <li class="font-medium" x-text="'• ' + u.name"></li>
                        </template>
                    </ul>
                    @can('conflictOverride', \App\Models\Task::class)
                    <label class="flex items-center gap-2 mt-2.5 cursor-pointer">
                        <input type="checkbox" x-model="conflictOverride" class="h-3.5 w-3.5 rounded" style="accent-color:var(--color-warning)">
                        <span class="font-semibold">Override konflik jadwal</span>
                    </label>
                    @else
                    <p class="mt-1.5 font-medium" style="color:var(--color-error)">Anda tidak punya izin override konflik.</p>
                    @endcan
                </div>



                <div class="border border-border rounded-md divide-y divide-border overflow-hidden">
                    @forelse($teknisiList as $teknisi)
                    <label class="flex items-center gap-3 px-3 py-2.5 hover:bg-surface-muted cursor-pointer transition-colors"
                           :class="!selectedMembers.includes({{ $teknisi->id }}) && selectedMembers.length >= 3 ? 'opacity-40 cursor-not-allowed' : ''">
                        <input type="checkbox"
                               name="team_member_ids[]"
                               value="{{ $teknisi->id }}"
                               @change="onMemberChange($event, {{ $teknisi->id }})"
                               :disabled="!selectedMembers.includes({{ $teknisi->id }}) && selectedMembers.length >= 3"
                               class="h-4 w-4 rounded"
                               style="accent-color: var(--color-primary)">
                        {{-- Avatar --}}
                        <div class="h-7 w-7 rounded-full bg-primary-soft flex items-center justify-center text-[11px] font-bold shrink-0"
                             style="color: var(--color-primary)">
                            {{ strtoupper(substr($teknisi->name, 0, 2)) }}
                        </div>
                        <span class="text-sm text-text-main font-medium flex-1">{{ $teknisi->name }}</span>
                        {{-- Conflict indicator --}}
                        <span x-show="conflictUsers.some(u => u.id === {{ $teknisi->id }})"
                              class="text-[10px] font-semibold px-1.5 py-0.5 rounded"
                              style="background:var(--color-warning-bg); color:var(--color-warning)">Konflik</span>
                    </label>
                    @empty
                    <div class="px-4 py-6 text-center text-sm text-text-muted">
                        <svg class="h-8 w-8 mx-auto mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Tidak ada teknisi tersedia.
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- ── 7. Deskripsi ──────────────────────────────────── --}}
            <div class="p-5">
                <label for="description" class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">
                    Catatan
                </label>
                <textarea name="description" id="description" rows="3"
                          placeholder="Catatan tambahan untuk teknisi..."
                          class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-background placeholder-text-muted focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition resize-none">{{ old('description') }}</textarea>
            </div>

            {{-- ── Footer / Submit ───────────────────────────────── --}}
            <div class="px-5 py-4 bg-surface-muted flex items-center justify-between">
                <a href="{{ route('tasks.index') }}"
                   class="text-sm text-text-muted hover:text-text-secondary transition-colors">
                    ← Batal
                </a>
                <button type="submit"
                        :disabled="submitting || (conflictUsers.length > 0 && !conflictOverride)"
                        :style="(submitting || (conflictUsers.length > 0 && !conflictOverride))
                            ? 'background:var(--color-surface-muted); color:var(--color-text-disabled); cursor:not-allowed'
                            : 'background:var(--color-primary); color:#fff'"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2 rounded-md transition-colors">
                    <svg x-show="submitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Buat Task
                </button>
            </div>
        </div>

        <input type="hidden" name="conflict_override" :value="conflictOverride ? '1' : '0'">
    </form>
</div>

@push('scripts')
<script>
function customerSearch(initialId, initialQuery) {
    return {
        query:      initialId ? initialQuery : '',
        selectedId: initialId || '',
        results:    [],

        async search() {
            if (this.query.length < 2) { this.results = []; return; }
            try {
                const res  = await fetch(`/api/tasks/search-customers?q=${encodeURIComponent(this.query)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.results = await res.json();
            } catch (e) { this.results = []; }
        },

        select(c) {
            this.query     = c.label;
            this.selectedId = c.id;
            this.results   = [];
        },
        clear() {
            this.query      = '';
            this.selectedId = '';
            this.results    = [];
        },
        focusResult() {},
    };
}

function taskCreateForm() {
    const SLA_MAP = { survey: 120, pemasangan: 240, maintenance: 180, ambil_modem: 60, relokasi: 240 };
    const SLA_LABEL = { survey: 'Survey ≤ 2 jam', pemasangan: 'Instalasi ≤ 4 jam', maintenance: 'Maintenance ≤ 3 jam', ambil_modem: '≤ 1 jam', relokasi: '≤ 4 jam' };

    return {
        taskType:           '{{ old('task_type', 'survey') }}',
        selectedPopId:      '{{ old('pop_id') }}',
        scheduledAt:        '{{ old('scheduled_at') }}',
        selectedMembers:    [],
        conflictUsers:      [],
        conflictOverride:   false,
        teamLimitWarning:   false,
        submitting:         false,

        get slaLabel() {
            return SLA_LABEL[this.taskType] || '';
        },

        onTypeChange() {
            if (this.scheduledAt && this.selectedMembers.length > 0) this.checkConflicts();
        },
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
                        user_ids:     this.selectedMembers,
                        scheduled_at: this.scheduledAt,
                        task_type:    this.taskType,
                    }),
                });
                const data = await res.json();
                this.conflictUsers = data.conflict_users || [];
                if (!data.has_conflict) this.conflictOverride = false;
            } catch (e) { /* silent */ }
        },

        submitForm(form) {
            if (this.conflictUsers.length > 0 && !this.conflictOverride) return;
            this.submitting = true;
            form.submit();
        },
    };
}
</script>
@endpush
@endsection
