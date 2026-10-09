@extends('layouts.app')

@section('title', 'Master Agent - Whusnet Operasional')
@section('page_title', 'Master Agent')
@section('breadcrumb_parent', 'Busdev')
@section('breadcrumb_parent_url', route('business-development.agents.index'))

@section('content')
<div x-data="{
    showGuide: false,
    copiedText: null,
    showCreateModal: {{ $errors->any() && !old('_agent_id') ? 'true' : 'false' }},
    showEditModal: {{ $errors->any() && old('_agent_id') ? 'true' : 'false' }},
    editAgent: {
        id: {{ old('_agent_id', 'null') }},
        code: '{{ old('_agent_id') ? old('code', '') : '' }}',
        name: '{{ old('_agent_id') ? old('name', '') : '' }}',
        phone: '{{ old('_agent_id') ? old('phone', '') : '' }}',
        is_active: {{ old('_agent_id') ? (old('is_active', '1') ? 'true' : 'false') : 'true' }},
        updateUrl: '{{ old('_agent_id') ? route('business-development.agents.update', old('_agent_id')) : '' }}'
    },
    openCreateModal() {
        this.showCreateModal = true;
        this.$nextTick(() => {
            this.$refs.createCodeInput?.focus();
        });
    },
    openEditModal(agent) {
        this.editAgent = {
            id: agent.id,
            code: agent.code,
            name: agent.name,
            phone: agent.phone || '',
            is_active: Boolean(agent.is_active),
            updateUrl: '{{ url('business-development/agents') }}/' + agent.id
        };
        this.showEditModal = true;
        this.$nextTick(() => {
            this.$refs.editCodeInput?.focus();
        });
    },
    closeModals() {
        this.showCreateModal = false;
        this.showEditModal = false;
    },
    confirmToggleAgent(agent) {
        const isCurrentlyActive = Boolean(agent.is_active);
        const actionText = isCurrentlyActive ? 'menonaktifkan' : 'mengaktifkan kembali';
        const titleText = isCurrentlyActive ? 'Nonaktifkan Mitra Agent' : 'Aktifkan Mitra Agent';
        const confirmBtnText = isCurrentlyActive ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan';
        const confirmBtnType = isCurrentlyActive ? 'danger' : 'primary';

        if (window.Dialog && typeof window.Dialog.show === 'function') {
            window.Dialog.show({
                title: titleText,
                message: `Apakah Anda yakin ingin ${actionText} mitra agent &quot;${agent.name}&quot; (${agent.code})?\n\n${isCurrentlyActive ? 'Agent yang dinonaktifkan tidak akan muncul pada pilihan dropdown formulir registrasi pelanggan baru.' : 'Agent yang diaktifkan akan dapat dipilih kembali pada formulir registrasi pelanggan baru.'}`,
                icon: isCurrentlyActive ? 'warning' : 'info',
                buttons: [
                    { text: 'Batal', type: 'secondary', onClick: () => window.Dialog.close() },
                    {
                        text: confirmBtnText,
                        type: confirmBtnType,
                        onClick: () => {
                            window.Dialog.close();
                            const form = document.getElementById('toggle-agent-form-' + agent.id) || document.getElementById('toggle-agent-form-mobile-' + agent.id);
                            if (form) {
                                form.submit();
                            }
                        }
                    }
                ]
            });
        } else {
            const form = document.getElementById('toggle-agent-form-' + agent.id) || document.getElementById('toggle-agent-form-mobile-' + agent.id);
            if (form) {
                form.submit();
            }
        }
    },
    copyToClipboard(text) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            this.copiedText = text;
            setTimeout(() => {
                if (this.copiedText === text) this.copiedText = null;
            }, 2000);
        });
    }
}"
@keydown.escape.window="closeModals()">

    {{-- ── LAYER 1: PAGE HEADER (NAKED — NO CARD WRAPPER, SESUAI DESIGN.MD TYPE A) ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Master Agent
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60 font-mono">
                    {{ number_format($agents->total()) }} Mitra
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                    Skema 3 Akuisisi
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-3xl">
                Master data mitra perantara/agent akuisisi pelanggan untuk pilihan rujukan (referral) saat pendaftaran pelanggan baru.
            </p>
        </div>

        <div class="flex items-center gap-2.5 self-start sm:self-center shrink-0">
            <button type="button"
                    @click="showGuide = !showGuide"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors shadow-2xs cursor-pointer">
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="showGuide ? 'Tutup Panduan' : 'Panduan Modul'"></span>
            </button>

            @can('agents.create')
            <button type="button"
                    @click="openCreateModal()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs sm:text-sm font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition-colors shadow-2xs shrink-0 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Agent
            </button>
            @endcan
        </div>
    </div>

    {{-- ── CONTEXTUAL INFO / GUIDE BANNER (COLLAPSIBLE) ── --}}
    <div x-show="showGuide"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="mb-5 p-4 rounded-lg bg-sky-50/60 dark:bg-sky-950/30 border border-sky-200/80 dark:border-sky-900/50 text-slate-700 dark:text-slate-300 text-xs">
        <div class="flex items-start gap-3">
            <div class="w-7 h-7 rounded-lg bg-sky-100 dark:bg-sky-900/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div class="space-y-1.5 flex-1">
                <div class="font-semibold text-slate-900 dark:text-slate-100">Ketentuan &amp; Peran Master Agent</div>
                <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-300">
                    <li><strong class="text-slate-800 dark:text-slate-200">Bukan Akun Login:</strong> Agent merupakan data master mitra luar/referral, bukan user yang login ke sistem operasional.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Formulir Pendaftaran:</strong> Digunakan oleh tim Sales/Busdev saat memilih skema akuisisi <span class="font-semibold text-sky-600 dark:text-sky-400">Agent</span> pada registrasi pelanggan.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Kode Unik:</strong> Kode agent bersifat unik (misal: <code class="font-mono text-sky-600 dark:text-sky-400 font-bold">AGT-001</code>) untuk memudahkan identifikasi serta rekap komisi/performa.</li>
                    <li><strong class="text-slate-800 dark:text-slate-200">Status Aktif:</strong> Hanya agent berstatus aktif yang akan muncul di dropdown formulir pendaftaran pelanggan.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── LAYER 2: SUMMARY STRIP (TYPE A — FLAT BAR WITH 1 ENCLOSING CONTAINER) ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-slate-700/60 rounded-lg shadow-2xs mb-5 overflow-hidden">
        {{-- Total Terdaftar --}}
        <div class="p-3.5 sm:p-4 border-b sm:border-b-0 border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Total Agent
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-slate-100">
                    {{ number_format($agents->total()) }}
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    terdata
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Mitra rujukan pelanggan
            </p>
        </div>

        {{-- Total Aktif --}}
        <div class="p-3.5 sm:p-4 border-b sm:border-b-0 sm:border-r border-slate-200/60 dark:border-slate-700/50">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                Agent Aktif
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                    {{ number_format($totalActive) }}
                </span>
                <span class="text-[11px] text-emerald-600/70 dark:text-emerald-400/70">
                    tersedia
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Tampil di form registrasi
            </p>
        </div>

        {{-- Total Nonaktif --}}
        <div class="p-3.5 sm:p-4">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Agent Nonaktif
            </span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-slate-600 dark:text-slate-300">
                    {{ number_format($totalInactive) }}
                </span>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    diarsipkan
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                Disembunyikan dari pilihan
            </p>
        </div>
    </div>

    {{-- ── LAYER 3: FILTER BAR (NAKED — NO CARD WRAPPER) ── --}}
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 mb-4">
        <form method="GET" action="{{ route('business-development.agents.index') }}" class="flex-1 max-w-md flex items-center gap-2">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Cari kode, nama mitra, atau telepon..."
                       class="w-full pl-9 pr-8 py-2 text-xs sm:text-sm font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                @if($search)
                <a href="{{ route('business-development.agents.index') }}"
                   class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </a>
                @endif
            </div>

            <button type="submit"
                    class="px-3 py-2 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors shadow-2xs cursor-pointer shrink-0">
                Cari
            </button>
        </form>

        @if($search)
        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>Filter pencarian: <strong class="text-slate-700 dark:text-slate-200 font-mono">"{{ $search }}"</strong></span>
            <a href="{{ route('business-development.agents.index') }}" class="text-sky-600 dark:text-sky-400 hover:underline font-semibold">
                Reset
            </a>
        </div>
        @endif
    </div>

    {{-- ── LAYER 4: DATA CONTAINER (CARD BUDGET = 1 — SESUAI DESIGN.MD) ── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-slate-700/60 rounded-lg shadow-2xs overflow-hidden">
        {{-- DESKTOP TABLE VIEW --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-900/40 border-b border-slate-200/70 dark:border-slate-700/60 text-slate-500 dark:text-slate-400 uppercase text-[10px] font-bold tracking-wider">
                        <th class="px-4 py-3 w-44">Kode Agent</th>
                        <th class="px-4 py-3">Nama Mitra Agent</th>
                        <th class="px-4 py-3 w-48">Nomor Telepon</th>
                        <th class="px-4 py-3 w-32">Status</th>
                        <th class="px-4 py-3 text-right w-44">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($agents as $agent)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-900/40 transition-colors">
                            {{-- Kode Agent (Mono & Copyable) --}}
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono font-bold text-xs text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded border border-sky-200/60 dark:border-sky-800/50">
                                        {{ $agent->code }}
                                    </span>
                                    <button type="button"
                                            @click="copyToClipboard('{{ $agent->code }}')"
                                            class="p-1 rounded text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition-colors cursor-pointer"
                                            title="Salin Kode Agent">
                                        <template x-if="copiedText === '{{ $agent->code }}'">
                                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </template>
                                        <template x-if="copiedText !== '{{ $agent->code }}'">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </template>
                                    </button>
                                </div>
                            </td>

                            {{-- Nama Mitra Agent --}}
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 font-bold text-xs flex items-center justify-center shrink-0 border border-slate-200/60 dark:border-slate-700">
                                        {{ strtoupper(substr($agent->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-slate-900 dark:text-slate-100 text-xs sm:text-sm">
                                            {{ $agent->name }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500">
                                            Mitra Referensi
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Telepon --}}
                            <td class="px-4 py-3.5">
                                @if($agent->phone)
                                    <div class="flex items-center gap-1.5 font-mono text-slate-700 dark:text-slate-300">
                                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                        </svg>
                                        <a href="tel:{{ $agent->phone }}" class="hover:text-sky-600 dark:hover:text-sky-400 hover:underline">
                                            {{ $agent->phone }}
                                        </a>
                                    </div>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500 italic">—</span>
                                @endif
                            </td>

                            {{-- Status Badge --}}
                            <td class="px-4 py-3.5">
                                @if($agent->is_active)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-800/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200/80 dark:border-slate-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Nonaktif
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3.5 text-right">
                                <div class="inline-flex items-center gap-1 justify-end">
                                    @can('agents.update')
                                    <button type="button"
                                            @click="openEditModal({ id: {{ $agent->id }}, code: '{{ addslashes($agent->code) }}', name: '{{ addslashes($agent->name) }}', phone: '{{ addslashes($agent->phone ?? '') }}', is_active: {{ $agent->is_active ? 1 : 0 }} })"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors shadow-2xs cursor-pointer">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Ubah
                                    </button>

                                    <form id="toggle-agent-form-{{ $agent->id }}" action="{{ route('business-development.agents.toggle', $agent) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="button"
                                                @click="confirmToggleAgent({ id: {{ $agent->id }}, name: '{{ addslashes($agent->name) }}', code: '{{ addslashes($agent->code) }}', is_active: {{ $agent->is_active ? 1 : 0 }} })"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded border {{ $agent->is_active ? 'border-amber-200 dark:border-amber-800/60 bg-amber-50/60 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/40' : 'border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/60 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/40' }} transition-colors shadow-2xs cursor-pointer">
                                            @if($agent->is_active)
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                </svg>
                                                Nonaktifkan
                                            @else
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Aktifkan
                                            @endif
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="max-w-sm mx-auto space-y-3">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700/60 text-slate-400 dark:text-slate-500 flex items-center justify-center mx-auto">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    <div class="space-y-1">
                                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                            @if($search)
                                                Tidak Ada Hasil Pencarian
                                            @else
                                                Belum Ada Mitra Agent
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            @if($search)
                                                Tidak ditemukan agent yang cocok dengan kata kunci <span class="font-mono font-semibold">"{{ $search }}"</span>.
                                            @else
                                                Daftarkan mitra perantara/agent baru untuk keperluan pendaftaran pelanggan skema Agent.
                                            @endif
                                        </p>
                                    </div>
                                    <div class="pt-2">
                                        @if($search)
                                            <a href="{{ route('business-development.agents.index') }}"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-2xs">
                                                Reset Pencarian
                                            </a>
                                        @else
                                            @can('agents.create')
                                                <button type="button"
                                                        @click="openCreateModal()"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition-colors shadow-2xs cursor-pointer">
                                                    + Tambah Agent Pertama
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- MOBILE / TABLET CARD STREAM VIEW --}}
        <div class="block md:hidden divide-y divide-slate-100 dark:divide-slate-700/60">
            @forelse($agents as $agent)
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 font-bold text-xs flex items-center justify-center shrink-0 border border-slate-200/60 dark:border-slate-700">
                                {{ strtoupper(substr($agent->name, 0, 2)) }}
                            </div>
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-slate-100 text-sm">
                                    {{ $agent->name }}
                                </div>
                                <div class="flex items-center gap-1 mt-0.5">
                                    <span class="font-mono font-bold text-[11px] text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/60 px-1.5 py-0.5 rounded border border-sky-200/60 dark:border-sky-800/50">
                                        {{ $agent->code }}
                                    </span>
                                    <button type="button"
                                            @click="copyToClipboard('{{ $agent->code }}')"
                                            class="p-0.5 text-slate-400 hover:text-sky-600 dark:hover:text-sky-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div>
                            @if($agent->is_active)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-800/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200/80 dark:border-slate-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    Nonaktif
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 dark:bg-slate-900/40 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800">
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Nomor Telepon</span>
                            @if($agent->phone)
                                <a href="tel:{{ $agent->phone }}" class="font-mono text-slate-800 dark:text-slate-200 font-medium hover:text-sky-600 dark:hover:text-sky-400 hover:underline">
                                    {{ $agent->phone }}
                                </a>
                            @else
                                <span class="text-slate-400 dark:text-slate-500 italic">—</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-semibold text-slate-400 block">Peran</span>
                            <span class="text-slate-700 dark:text-slate-300 font-medium">Mitra Referral</span>
                        </div>
                    </div>

                    @can('agents.update')
                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button"
                                @click="openEditModal({ id: {{ $agent->id }}, code: '{{ addslashes($agent->code) }}', name: '{{ addslashes($agent->name) }}', phone: '{{ addslashes($agent->phone ?? '') }}', is_active: {{ $agent->is_active ? 1 : 0 }} })"
                                class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-2xs cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Ubah
                        </button>

                        <form id="toggle-agent-form-mobile-{{ $agent->id }}" action="{{ route('business-development.agents.toggle', $agent) }}" method="POST" class="flex-1 sm:flex-initial">
                            @csrf
                            <button type="button"
                                    @click="confirmToggleAgent({ id: {{ $agent->id }}, name: '{{ addslashes($agent->name) }}', code: '{{ addslashes($agent->code) }}', is_active: {{ $agent->is_active ? 1 : 0 }} })"
                                    class="w-full inline-flex items-center justify-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border {{ $agent->is_active ? 'border-amber-200 dark:border-amber-800/60 bg-amber-50/60 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 hover:bg-amber-100' : 'border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/60 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100' }} transition-colors shadow-2xs cursor-pointer">
                                {{ $agent->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>
                    </div>
                    @endcan
                </div>
            @empty
                <div class="p-8 text-center text-slate-400 dark:text-slate-500 text-xs">
                    Belum ada data mitra agent.
                </div>
            @endforelse
        </div>

        {{-- PAGINATION FOOTER --}}
        @if($agents->hasPages())
        <div class="p-3.5 sm:p-4 border-t border-slate-200/70 dark:border-slate-700/60 bg-slate-50/40 dark:bg-slate-900/20">
            {{ $agents->links() }}
        </div>
        @endif
    </div>

    {{-- ========================================================================= --}}
    {{-- ── MODAL / BOTTOM DRAWER: TAMBAH AGENT ── --}}
    {{-- ========================================================================= --}}
    @can('agents.create')
    <div x-show="showCreateModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modal-create-title">

        {{-- BACKDROP OVERLAY --}}
        <div x-show="showCreateModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="closeModals()"
             class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs transition-opacity"></div>

        {{-- MODAL / DRAWER CONTAINER --}}
        <div class="fixed inset-0 z-10 flex items-end md:items-center justify-center p-0 md:p-4 sm:p-6 text-center pointer-events-none">
            <div x-show="showCreateModal"
                 @click.outside="closeModals()"
                 x-transition:enter="transform transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full md:translate-y-0 md:scale-95 md:opacity-0"
                 x-transition:enter-end="translate-y-0 md:scale-100 md:opacity-100"
                 x-transition:leave="transform transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 md:scale-100 md:opacity-100"
                 x-transition:leave-end="translate-y-full md:translate-y-0 md:scale-95 md:opacity-0"
                 class="pointer-events-auto relative w-full max-w-lg bg-white dark:bg-slate-800 rounded-t-2xl md:rounded-lg border-t md:border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden text-left max-h-[92vh] md:max-h-[85vh] flex flex-col">

                {{-- DRAWER GRAB HANDLE FOR MOBILE/TABLET --}}
                <div class="pt-3 pb-1 md:hidden flex justify-center shrink-0">
                    <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                </div>

                {{-- MODAL HEADER --}}
                <div class="p-5 sm:p-6 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-3 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-100 dark:bg-sky-900/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 id="modal-create-title" class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100">
                                Tambah Mitra Agent Baru
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Daftarkan kode rujukan dan rincian kontak mitra.
                            </p>
                        </div>
                    </div>

                    <button type="button"
                            @click="closeModals()"
                            class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 flex items-center justify-center transition-colors cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- MODAL FORM BODY (SCROLLABLE) --}}
                <form action="{{ route('business-development.agents.store') }}" method="POST" class="flex-1 flex flex-col overflow-hidden">
                    @csrf
                    <div class="p-5 sm:p-6 overflow-y-auto space-y-4 flex-1">
                        {{-- Kode Agent --}}
                        <div>
                            <label for="create_code" class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
                                Kode Agent <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   id="create_code"
                                   x-ref="createCodeInput"
                                   name="code"
                                   value="{{ old('_agent_id') ? '' : old('code') }}"
                                   maxlength="30"
                                   required
                                   placeholder="Contoh: AGT-001, AGT-SIMAN, AGT-BAMBANG"
                                   class="w-full px-3.5 py-2 text-xs sm:text-sm font-mono font-bold uppercase border @if($errors->has('code') && !old('_agent_id')) border-rose-400 dark:border-rose-600 focus:ring-rose-500/20 focus:border-rose-500 @else border-slate-200 dark:border-slate-700 focus:ring-sky-500/20 focus:border-sky-500 @endif rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder:normal-case placeholder:font-sans placeholder:font-normal placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 transition-all shadow-2xs">
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                                Kode unik rujukan agent. Maksimal 30 karakter.
                            </p>
                            @if(!old('_agent_id'))
                                @error('code')
                                    <p class="text-xs text-rose-600 dark:text-rose-400 mt-1 font-medium flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                            @endif
                        </div>

                        {{-- Nama Mitra Agent --}}
                        <div>
                            <label for="create_name" class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
                                Nama Lengkap Mitra Agent <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   id="create_name"
                                   name="name"
                                   value="{{ old('_agent_id') ? '' : old('name') }}"
                                   maxlength="150"
                                   required
                                   placeholder="Nama lengkap mitra / perorangan / instansi"
                                   class="w-full px-3.5 py-2 text-xs sm:text-sm border @if($errors->has('name') && !old('_agent_id')) border-rose-400 dark:border-rose-600 focus:ring-rose-500/20 focus:border-rose-500 @else border-slate-200 dark:border-slate-700 focus:ring-sky-500/20 focus:border-sky-500 @endif rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 transition-all shadow-2xs">
                            @if(!old('_agent_id'))
                                @error('name')
                                    <p class="text-xs text-rose-600 dark:text-rose-400 mt-1 font-medium flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                            @endif
                        </div>

                        {{-- Nomor Telepon --}}
                        <div>
                            <label for="create_phone" class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
                                Nomor Telepon / WhatsApp <span class="text-slate-400 font-normal">(Opsional)</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                </div>
                                <input type="text"
                                       id="create_phone"
                                       name="phone"
                                       value="{{ old('_agent_id') ? '' : old('phone') }}"
                                       maxlength="20"
                                       placeholder="Contoh: 081234567890"
                                       class="w-full pl-9 pr-3.5 py-2 text-xs sm:text-sm font-mono border border-slate-200 dark:border-slate-700 focus:ring-sky-500/20 focus:border-sky-500 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 transition-all shadow-2xs">
                            </div>
                        </div>

                        {{-- Switch Status Aktif --}}
                        <div class="pt-1">
                            <label class="flex items-start gap-3 p-3.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 hover:bg-slate-50 dark:hover:bg-slate-900/60 transition-colors cursor-pointer">
                                <input type="checkbox"
                                       name="is_active"
                                       value="1"
                                       {{ (old('_agent_id') ? true : old('is_active', true)) ? 'checked' : '' }}
                                       class="mt-0.5 rounded border-slate-300 dark:border-slate-600 text-sky-600 focus:ring-sky-500 focus:ring-offset-0 h-4 w-4">
                                <div class="text-xs">
                                    <span class="font-semibold text-slate-800 dark:text-slate-200 block">
                                        Mitra Agent Aktif
                                    </span>
                                    <span class="text-slate-500 dark:text-slate-400 block mt-0.5">
                                        Muncul pada dropdown pilihan form registrasi pelanggan skema Agent.
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- MODAL FOOTER --}}
                    <div class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-900/30 flex items-center justify-end gap-2.5 shrink-0">
                        <button type="button"
                                @click="closeModals()"
                                class="px-4 py-2 text-xs sm:text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-2xs cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-xs sm:text-sm font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition-colors shadow-2xs cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan Agent
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan

    {{-- ========================================================================= --}}
    {{-- ── MODAL / BOTTOM DRAWER: EDIT AGENT ── --}}
    {{-- ========================================================================= --}}
    @can('agents.update')
    <div x-show="showEditModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modal-edit-title">

        {{-- BACKDROP OVERLAY --}}
        <div x-show="showEditModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="closeModals()"
             class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs transition-opacity"></div>

        {{-- MODAL / DRAWER CONTAINER --}}
        <div class="fixed inset-0 z-10 flex items-end md:items-center justify-center p-0 md:p-4 sm:p-6 text-center pointer-events-none">
            <div x-show="showEditModal"
                 @click.outside="closeModals()"
                 x-transition:enter="transform transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full md:translate-y-0 md:scale-95 md:opacity-0"
                 x-transition:enter-end="translate-y-0 md:scale-100 md:opacity-100"
                 x-transition:leave="transform transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 md:scale-100 md:opacity-100"
                 x-transition:leave-end="translate-y-full md:translate-y-0 md:scale-95 md:opacity-0"
                 class="pointer-events-auto relative w-full max-w-lg bg-white dark:bg-slate-800 rounded-t-2xl md:rounded-lg border-t md:border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden text-left max-h-[92vh] md:max-h-[85vh] flex flex-col">

                {{-- DRAWER GRAB HANDLE FOR MOBILE/TABLET --}}
                <div class="pt-3 pb-1 md:hidden flex justify-center shrink-0">
                    <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                </div>

                {{-- MODAL HEADER --}}
                <div class="p-5 sm:p-6 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-3 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-100 dark:bg-sky-900/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 id="modal-edit-title" class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100">
                                    Ubah Data Agent
                                </h2>
                                <span class="font-mono text-xs font-bold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/60 px-1.5 py-0.5 rounded border border-sky-200/60 dark:border-sky-800/50"
                                      x-text="editAgent.code"></span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Perbarui rincian informasi dan status aktivitas mitra.
                            </p>
                        </div>
                    </div>

                    <button type="button"
                            @click="closeModals()"
                            class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 flex items-center justify-center transition-colors cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- MODAL FORM BODY (SCROLLABLE) --}}
                <form :action="editAgent.updateUrl" method="POST" class="flex-1 flex flex-col overflow-hidden">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_agent_id" :value="editAgent.id">

                    <div class="p-5 sm:p-6 overflow-y-auto space-y-4 flex-1">
                        {{-- Kode Agent --}}
                        <div>
                            <label for="edit_code" class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
                                Kode Agent <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   id="edit_code"
                                   x-ref="editCodeInput"
                                   name="code"
                                   x-model="editAgent.code"
                                   maxlength="30"
                                   required
                                   placeholder="Contoh: AGT-001"
                                   class="w-full px-3.5 py-2 text-xs sm:text-sm font-mono font-bold uppercase border @if($errors->has('code') && old('_agent_id')) border-rose-400 dark:border-rose-600 focus:ring-rose-500/20 focus:border-rose-500 @else border-slate-200 dark:border-slate-700 focus:ring-sky-500/20 focus:border-sky-500 @endif rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder:normal-case placeholder:font-sans placeholder:font-normal placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 transition-all shadow-2xs">
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                                Kode unik rujukan agent.
                            </p>
                            @if(old('_agent_id'))
                                @error('code')
                                    <p class="text-xs text-rose-600 dark:text-rose-400 mt-1 font-medium flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                            @endif
                        </div>

                        {{-- Nama Mitra Agent --}}
                        <div>
                            <label for="edit_name" class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
                                Nama Lengkap Mitra Agent <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   id="edit_name"
                                   name="name"
                                   x-model="editAgent.name"
                                   maxlength="150"
                                   required
                                   placeholder="Nama lengkap mitra agent"
                                   class="w-full px-3.5 py-2 text-xs sm:text-sm border @if($errors->has('name') && old('_agent_id')) border-rose-400 dark:border-rose-600 focus:ring-rose-500/20 focus:border-rose-500 @else border-slate-200 dark:border-slate-700 focus:ring-sky-500/20 focus:border-sky-500 @endif rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 transition-all shadow-2xs">
                            @if(old('_agent_id'))
                                @error('name')
                                    <p class="text-xs text-rose-600 dark:text-rose-400 mt-1 font-medium flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                            @endif
                        </div>

                        {{-- Nomor Telepon --}}
                        <div>
                            <label for="edit_phone" class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
                                Nomor Telepon / WhatsApp <span class="text-slate-400 font-normal">(Opsional)</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                </div>
                                <input type="text"
                                       id="edit_phone"
                                       name="phone"
                                       x-model="editAgent.phone"
                                       maxlength="20"
                                       placeholder="Contoh: 081234567890"
                                       class="w-full pl-9 pr-3.5 py-2 text-xs sm:text-sm font-mono border border-slate-200 dark:border-slate-700 focus:ring-sky-500/20 focus:border-sky-500 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 transition-all shadow-2xs">
                            </div>
                        </div>

                        {{-- Switch Status Aktif --}}
                        <div class="pt-1">
                            <label class="flex items-start gap-3 p-3.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 hover:bg-slate-50 dark:hover:bg-slate-900/60 transition-colors cursor-pointer">
                                <input type="checkbox"
                                       name="is_active"
                                       value="1"
                                       x-model="editAgent.is_active"
                                       class="mt-0.5 rounded border-slate-300 dark:border-slate-600 text-sky-600 focus:ring-sky-500 focus:ring-offset-0 h-4 w-4">
                                <div class="text-xs">
                                    <span class="font-semibold text-slate-800 dark:text-slate-200 block">
                                        Mitra Agent Aktif
                                    </span>
                                    <span class="text-slate-500 dark:text-slate-400 block mt-0.5">
                                        Muncul pada dropdown pilihan form registrasi pelanggan skema Agent.
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- MODAL FOOTER --}}
                    <div class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-900/30 flex items-center justify-end gap-2.5 shrink-0">
                        <button type="button"
                                @click="closeModals()"
                                class="px-4 py-2 text-xs sm:text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-2xs cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-xs sm:text-sm font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition-colors shadow-2xs cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan
</div>
@endsection
